import { EllipsisVertical, Plus } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu, { type MenuItem } from '../../components/ui/DropdownMenu'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { PageHeader, Panel } from '../../components/ui/Page'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { invalidate } from '../../lib/queries/invalidate'
import { listConfigs } from '../../lib/queries/list-configs'
import { useStatusImpact, useSuggestions, useSupplyRequests } from '../../lib/queries/lists'
import { masterQueries, useRequestCatalog } from '../../lib/queries/master'
import { prefetchRoomOptions, prefetchStatusImpacts } from '../../lib/queries/prefetch'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'
import type { CatalogUsage, RequestCatalog, RequestPrefill, StatusImpact, SupplyRequest } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'
import CatalogFormModal from './CatalogFormModal'
import RequestFormModal from './RequestFormModal'

const CATALOG_PER_PAGE = 5
const PREFILL_PARAMS = ['room_id', 'catalog_id', 'consumable_id']

const statusColor = {
  Diajukan: 'blue',
  'Diterima sebagian': 'amber',
  Diterima: 'green',
  'Belum direspons': 'amber',
  Dikembalikan: 'slate',
  Dibatalkan: 'red',
} as const

type TargetStatus = 'Diterima' | 'Dikembalikan' | 'Dibatalkan'

type ModalState =
  | { kind: 'request'; request: SupplyRequest | null; prefill?: RequestPrefill }
  | { kind: 'catalog'; item: RequestCatalog | null }
  | { kind: 'status'; request: SupplyRequest; status: TargetStatus }
  | null

function Card({ title, action, children }: { title: string; action?: React.ReactNode; children: React.ReactNode }) {
  return (
    <section className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="font-semibold">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  )
}

/** Status tujuan yang tersedia di menu aksi baris permintaan. */
function nextStatuses(request: SupplyRequest): TargetStatus[] {
  if (request.status === 'Diajukan' || request.status === 'Diterima sebagian') return ['Diterima', 'Dibatalkan']
  if (request.status === 'Diterima') return request.must_return ? ['Dikembalikan', 'Dibatalkan'] : ['Dibatalkan']
  return []
}

function StatusModal({ request, status, onClose, onDone }: {
  request: SupplyRequest
  status: TargetStatus
  onClose: () => void
  onDone: () => void
}) {
  const toast = useToast()
  const [note, setNote] = useState('')
  // Barang stok: staf mengisi jumlah yang benar-benar datang; kurang dari sisa = Diterima sebagian.
  const receivesStock = status === 'Diterima' && request.consumable_id !== null
  const remaining = request.quantity - request.received_quantity
  const [received, setReceived] = useState(String(remaining))
  const receivedValue = Number(received)
  const receivedValid = Number.isInteger(receivedValue) && receivedValue >= 1 && receivedValue <= remaining
  // Dampak biasanya sudah termuat saat menu aksi dibuka; simpan aktif setelah dampaknya tersedia.
  const { data: impact, error: impactError } = useStatusImpact(request.id, status)

  const submit = async () => {
    const res = await api.patch<{ meta: { impact: StatusImpact } }>(`/supply-requests/${request.id}/status`, {
      status,
      note: note.trim() || null,
      ...(receivesStock ? { received_quantity: receivedValue } : {}),
    })
    toast.success(receivesStock && receivedValue < remaining ? `Diterima sebagian, sisa ${remaining - receivedValue}.` : `Permintaan ditandai ${status}.`)
    if (res.data.meta.impact.clamped) toast.error(res.data.meta.impact.message)
    onDone()
  }
  return (
    <ActionModal
      title={receivesStock ? 'Catat barang diterima' : `Tandai ${status}`}
      submitLabel={receivesStock ? 'Simpan' : `Tandai ${status}`}
      danger={status === 'Dibatalkan'}
      submitDisabled={receivesStock ? !receivedValid : !impact}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">
            {request.item_name} × {request.quantity} dari {request.provider_department?.name ?? '-'}.
            {request.received_quantity > 0 && ` Sudah diterima ${request.received_quantity}, sisa ${remaining}.`}
          </p>
          {receivesStock ? (
            <>
              <FormField label="Jumlah yang diterima" required error={errors.received_quantity}>
                <Input type="number" min={1} max={remaining} value={received} onChange={(e) => setReceived(e.target.value)} error={errors.received_quantity} autoFocus />
              </FormField>
              {receivedValid && (
                <p className="rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-700">
                  Stok {request.item_name} akan bertambah {receivedValue}.
                  {receivedValue < remaining && ` Status menjadi Diterima sebagian, sisa ${remaining - receivedValue} bisa diterima kemudian.`}
                </p>
              )}
            </>
          ) : impact ? (
            <p className={`rounded-lg px-3 py-2.5 text-sm ${impact.clamped ? 'bg-warn-50 text-warn-700' : 'bg-slate-100 text-slate-700'}`}>{impact.message}</p>
          ) : impactError ? (
            <p role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">{errorMessage(impactError)}</p>
          ) : (
            <p className="text-sm text-slate-500">Memeriksa dampak…</p>
          )}
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}

export default function RequestsPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  const list = useListParams(listConfigs.supplyRequests)
  const catalogQuery = useRequestCatalog()
  const historyQuery = useSupplyRequests(list.params)
  const suggestionsQuery = useSuggestions()
  useErrorToast(catalogQuery.error, historyQuery.error, suggestionsQuery.error)
  const catalog = catalogQuery.data
  const history = historyQuery.data
  const suggestions = suggestionsQuery.data ?? []
  // Dari Kelengkapan Ruangan/Stok: /permintaan?room_id=&consumable_id=&item_name=&provider_department_id=&quantity= → buka form terisi otomatis.
  const [modal, setModal] = useState<ModalState>(() => {
    if (!PREFILL_PARAMS.some((k) => params.has(k))) return null
    const num = (k: string) => (params.get(k) ? Number(params.get(k)) : null)
    return {
      kind: 'request',
      request: null,
      prefill: {
        room_id: num('room_id'),
        catalog_id: num('catalog_id'),
        consumable_id: num('consumable_id'),
        item_name: params.get('item_name') ?? undefined,
        provider_department_id: num('provider_department_id'),
        quantity: num('quantity') ?? undefined,
      },
    }
  })
  const [deleting, setDeleting] = useState<{ item: RequestCatalog; usage: CatalogUsage } | null>(null)
  // Katalog sudah dimuat utuh: halaman dibagi di browser, tanpa request baru.
  const [catalogPage, setCatalogPage] = useState(1)
  const catalogLastPage = Math.max(1, Math.ceil(catalog.length / CATALOG_PER_PAGE))
  const catalogPageNow = Math.min(catalogPage, catalogLastPage)
  const status = list.get('status')
  const search = list.searchText

  // Query sudah dipakai untuk isian awal modal → bersihkan dari URL (filter tabel tetap).
  useEffect(() => {
    if (!PREFILL_PARAMS.some((k) => params.has(k))) return
    setParams(
      (p) => {
        ;[...PREFILL_PARAMS, 'item_name', 'provider_department_id', 'quantity'].forEach((k) => p.delete(k))
        return p
      },
      { replace: true },
    )
  }, [params, setParams])

  // Form permintaan butuh gedung, ruangan, dan cara permintaan: disiapkan saat halaman dibuka.
  useEffect(() => {
    prefetchRoomOptions(queryClient)
    queryClient.prefetchQuery(masterQueries.requestMethods())
  }, [queryClient])

  const closeModal = () => setModal(null)
  const done = () => {
    closeModal()
    invalidate.supplyRequests(queryClient)
  }
  // Status bisa mengubah stok.
  const statusDone = () => {
    closeModal()
    invalidate.rooms(queryClient)
  }
  const catalogDone = () => {
    closeModal()
    invalidate.catalog(queryClient)
  }

  // Pemakaian dimuat dulu agar popup menyebut jumlah tautan yang akan dilepas.
  const askDeleteCatalog = async (item: RequestCatalog) => {
    try {
      const r = await api.get<{ data: CatalogUsage }>(`/request-catalog/${item.id}/usage`)
      setDeleting({ item, usage: r.data.data })
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const removeCatalog = async () => {
    if (!deleting) return
    try {
      await api.delete(`/request-catalog/${deleting.item.id}`)
      toast.success(`"${deleting.item.item_name}" dihapus dari katalog.`)
      setDeleting(null)
      invalidate.catalog(queryClient)
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const catalogColumns: Column<RequestCatalog>[] = [
    { header: 'Item', mobile: 'title', cell: (c) => <span className="font-medium">{c.item_name}</span> },
    { header: 'Departemen penyedia', cell: (c) => c.provider_department?.name ?? '-' },
    { header: 'Perlu dikembalikan', cell: (c) => (c.must_return ? 'Ya' : 'Tidak') },
    { header: 'Lead time', cell: (c) => <span className="whitespace-nowrap">{c.lead_time_days} hari</span> },
    {
      header: '',
      className: 'w-10',
      mobile: 'badge',
      cell: (c) => (
        <DropdownMenu
          label="Aksi katalog"
          trigger={<EllipsisVertical size={16} />}
          triggerClassName="px-2.5!"
          items={[
            { label: 'Ubah', onClick: () => setModal({ kind: 'catalog', item: c }) },
            { label: 'Hapus', danger: true, onClick: () => askDeleteCatalog(c) },
          ]}
        />
      ),
    },
  ]

  const historyColumns: Column<SupplyRequest>[] = [
    { header: 'Tanggal', sortKey: 'request_date', cell: (r) => <span className="whitespace-nowrap">{formatDate(r.request_date)}</span> },
    {
      header: 'Item',
      mobile: 'title',
      sortKey: 'item_name',
      cell: (r) => (
        <>
          <span className="font-medium">{r.item_name}</span>
          {r.note && <span className="block text-xs text-slate-500">{r.note}</span>}
        </>
      ),
    },
    {
      header: 'Jumlah',
      sortKey: 'quantity',
      cell: (r) => (
        <>
          <span className="tabular-nums">{r.quantity}</span>
          {r.received_quantity > 0 && r.received_quantity < r.quantity && (
            <span className="block whitespace-nowrap text-xs text-slate-500">diterima {r.received_quantity} dari {r.quantity}</span>
          )}
        </>
      ),
    },
    {
      header: 'Untuk kegiatan',
      cell: (r) => {
        const room = r.room ? `${r.room.name}${r.room.building ? ` (${r.room.building.name})` : ''}` : null
        return r.purpose && room ? `${r.purpose} di ${room}` : (r.purpose ?? room ?? '-')
      },
    },
    { header: 'Departemen penyedia', cell: (r) => r.provider_department?.name ?? '-' },
    { header: 'Cara permintaan', cell: (r) => r.request_method ?? '-' },
    { header: 'Status', mobile: 'badge', cell: (r) => <Badge color={statusColor[r.display_status]}>{r.display_status}</Badge> },
    {
      header: '',
      className: 'w-10',
      mobile: 'badge',
      cell: (r) => {
        const items: MenuItem[] = []
        if (r.status === 'Diajukan') {
          items.push(
            { label: r.consumable_id ? 'Catat barang diterima' : 'Tandai Diterima', onClick: () => setModal({ kind: 'status', request: r, status: 'Diterima' }) },
            { label: 'Ubah', onClick: () => setModal({ kind: 'request', request: r }) },
            { label: 'Tandai Dibatalkan', danger: true, onClick: () => setModal({ kind: 'status', request: r, status: 'Dibatalkan' }) },
          )
        }
        if (r.status === 'Diterima sebagian') {
          items.push(
            { label: 'Catat barang diterima', onClick: () => setModal({ kind: 'status', request: r, status: 'Diterima' }) },
            { label: 'Batalkan sisa', danger: true, onClick: () => setModal({ kind: 'status', request: r, status: 'Dibatalkan' }) },
          )
        }
        if (r.status === 'Diterima' && r.must_return) {
          items.push({ label: 'Tandai Dikembalikan', onClick: () => setModal({ kind: 'status', request: r, status: 'Dikembalikan' }) })
        }
        if (r.status === 'Diterima') {
          items.push({ label: 'Tandai Dibatalkan', danger: true, onClick: () => setModal({ kind: 'status', request: r, status: 'Dibatalkan' }) })
        }
        return items.length ? (
          <DropdownMenu
            label="Aksi permintaan"
            trigger={<EllipsisVertical size={16} />}
            triggerClassName="px-2.5!"
            items={items}
            onOpen={() => prefetchStatusImpacts(queryClient, r.id, nextStatuses(r))}
          />
        ) : null
      },
    },
  ]

  return (
    <div className="space-y-6">
      <PageHeader
        title="Permintaan ke Departemen Lain"
        description="Perlengkapan yang tidak dimiliki SHD dan harus diminta ke departemen penyedia."
        actions={
          <>
            <ExportButton
              type="requests"
              filters={() => ({
                ...(status === 'Belum direspons' ? { unresponded: 1 } : { status }),
                search: search.trim(),
              })}
            />
            <Button onClick={() => setModal({ kind: 'request', request: null })}>
              <Plus size={16} /> Buat permintaan
            </Button>
          </>
        }
      />

      {suggestions.length > 0 && (
        <Panel title="Saran permintaan" flush>
          <div className="grid border-t border-slate-100 md:grid-cols-2 md:divide-x md:divide-slate-100">
            {suggestions.map((g) => (
              <div key={g.provider_department.id} className="border-b border-slate-100 last:border-b-0">
                <p className="bg-slate-50 px-5 py-2 text-sm font-semibold text-slate-700">{g.provider_department.name}</p>
                <ul className="divide-y divide-slate-100">
                  {g.items.map((s, i) => (
                    <li key={i} className="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                      <div className="min-w-0">
                        <p className="font-medium">{s.item_name} <span className="font-normal tabular-nums text-slate-500">× {s.quantity}</span></p>
                        <p className="text-xs text-slate-500">
                          Stok {s.stock_status.toLowerCase()}
                          {s.has_open_request && ' · sudah ada permintaan terbuka'}
                        </p>
                      </div>
                      <Button
                        variant="secondary"
                        className="min-h-8! shrink-0 px-3! text-xs!"
                        onClick={() =>
                          setModal({
                            kind: 'request',
                            request: null,
                            prefill: {
                              consumable_id: s.consumable_id,
                              item_name: s.item_name,
                              provider_department_id: g.provider_department.id,
                              quantity: s.quantity,
                            },
                          })
                        }
                      >
                        Buat permintaan
                      </Button>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </Panel>
      )}

      <Card
        title="Katalog perlengkapan dari departemen lain"
        action={<Button variant="secondary" onClick={() => setModal({ kind: 'catalog', item: null })}><Plus size={16} /> Tambah item</Button>}
      >
        <DataTable
          columns={catalogColumns}
          rows={catalog.slice((catalogPageNow - 1) * CATALOG_PER_PAGE, catalogPageNow * CATALOG_PER_PAGE)}
          rowKey={(c) => c.id}
          loading={catalogQuery.isPending}
          emptyText="Katalog masih kosong."
          meta={catalog.length > CATALOG_PER_PAGE ? { current_page: catalogPageNow, last_page: catalogLastPage, per_page: CATALOG_PER_PAGE, total: catalog.length } : undefined}
          unit="item"
          onPageChange={setCatalogPage}
        />
      </Card>

      <Card title="Riwayat permintaan">
        {list.get('id') && (
          <p className="text-sm text-slate-600">
            Menampilkan satu permintaan.{' '}
            <button type="button" className="font-semibold text-brand-700 hover:underline" onClick={() => list.setFilter('id', '')}>
              Tampilkan semua
            </button>
          </p>
        )}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <Input
            className="sm:max-w-xs"
            value={search}
            onChange={(e) => list.setSearchText(e.target.value)}
            placeholder="Cari item atau kegiatan"
            aria-label="Cari permintaan"
          />
          <Select
            clearable
            className="sm:w-auto"
            value={status}
            onChange={(e) => list.setFilter('status', e.target.value)}
            aria-label="Status"
          >
            <option value="">Semua status</option>
            {Object.keys(statusColor).map((s) => <option key={s}>{s}</option>)}
          </Select>
        </div>
        <DataTable
          columns={historyColumns}
          rows={history?.data ?? []}
          rowKey={(r) => r.id}
          loading={historyQuery.isPending}
          fetching={historyQuery.isPlaceholderData}
          emptyText="Belum ada permintaan."
          meta={history?.meta}
          unit="permintaan"
          onPageChange={list.setPage}
          sort={list.sort}
          onSortChange={list.setSort}
        />
      </Card>

      {modal?.kind === 'request' && (
        <RequestFormModal catalog={catalog} request={modal.request} prefill={modal.prefill} onClose={closeModal} onDone={done} />
      )}
      {modal?.kind === 'catalog' && <CatalogFormModal item={modal.item} onClose={closeModal} onDone={catalogDone} />}
      {modal?.kind === 'status' && <StatusModal request={modal.request} status={modal.status} onClose={closeModal} onDone={statusDone} />}

      <ConfirmDialog
        open={deleting !== null}
        title="Hapus item katalog"
        confirmLabel="Hapus"
        danger
        onConfirm={removeCatalog}
        onClose={() => setDeleting(null)}
      >
        {deleting && deleting.usage.total > 0 ? (
          <>
            Item ini dipakai di {deleting.usage.supply_requests} permintaan.
            Tautannya akan dilepas, riwayat tetap tersimpan.
          </>
        ) : (
          <>Hapus "{deleting?.item.item_name}" dari katalog?</>
        )}
      </ConfirmDialog>
    </div>
  )
}
