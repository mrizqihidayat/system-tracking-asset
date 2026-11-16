import { EllipsisVertical, Plus } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import DepartmentCombobox from '../../components/DepartmentCombobox'
import ExportButton from '../../components/ExportButton'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu, { type MenuItem } from '../../components/ui/DropdownMenu'
import { FormField, Input, Textarea } from '../../components/ui/FormField'
import { PageHeader } from '../../components/ui/Page'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { invalidate } from '../../lib/queries/invalidate'
import { listConfigs } from '../../lib/queries/list-configs'
import { useStatusImpact, useSuggestions, useSupplyRequests } from '../../lib/queries/lists'
import { masterQueries, useDepartments, useRequestCatalog } from '../../lib/queries/master'
import { prefetchRoomOptions, prefetchStatusImpacts } from '../../lib/queries/prefetch'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'
import type { CatalogUsage, RequestCatalog, RequestPrefill, StatusImpact, SuggestionGroup, SupplyRequest } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'
import BatchRequestModal from './BatchRequestModal'
import CatalogFormModal from './CatalogFormModal'
import RequestFormModal from './RequestFormModal'
import SuggestionPanel from './SuggestionPanel'

const CATALOG_PER_PAGE = 5
const PREFILL_PARAMS = ['room_id', 'catalog_id', 'consumable_id']

const statusColor = {
  Diajukan: 'blue',
  'Diterima sebagian': 'purple',
  Diterima: 'green',
  'Belum direspons': 'amber',
  Dikembalikan: 'slate',
  Dibatalkan: 'red',
} as const

type TargetStatus = 'Diterima' | 'Dikembalikan' | 'Dibatalkan'

type ModalState =
  | { kind: 'request'; request: SupplyRequest | null; prefill?: RequestPrefill }
  | { kind: 'batch'; group: SuggestionGroup }
  | { kind: 'catalog'; item: RequestCatalog | null }
  | { kind: 'status'; request: SupplyRequest; status: TargetStatus }
  | null

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

type Tab = 'riwayat' | 'katalog'

/** Chip status Riwayat permintaan; Dikembalikan & Dibatalkan hanya tampil bila ada. */
const CHIPS: { status: SupplyRequest['display_status']; always: boolean }[] = [
  { status: 'Diajukan', always: true },
  { status: 'Belum direspons', always: true },
  { status: 'Diterima sebagian', always: true },
  { status: 'Diterima', always: true },
  { status: 'Dikembalikan', always: false },
  { status: 'Dibatalkan', always: false },
]

function Chip({ label, count, active, onClick }: { label: string; count?: number; active: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors ${
        active ? 'border-brand-800 bg-brand-800 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
      }`}
    >
      {label}
      {count !== undefined && <span className={`tabular-nums ${active ? 'text-white/80' : 'text-slate-500'}`}>{count}</span>}
    </button>
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
  const providers = useDepartments().data.filter((d) => !d.is_internal)
  useErrorToast(catalogQuery.error, historyQuery.error, suggestionsQuery.error)
  const catalog = catalogQuery.data
  const history = historyQuery.data
  const suggestions = suggestionsQuery.data ?? []
  const counts = history?.meta.status_counts
  const tab: Tab = params.get('tab') === 'katalog' ? 'katalog' : 'riwayat'
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
  const providerId = list.get('provider_department_id')
  const search = list.searchText

  // Query sudah dipakai untuk isian awal modal → bersihkan dari URL (filter tabel tetap).
  // provider_department_id dari Stok hanya dipakai bila datang bersama barang (consumable_id/item_name).
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

  const setTab = (next: Tab) =>
    setParams(
      (p) => {
        if (next === 'katalog') p.set('tab', 'katalog')
        else p.delete('tab')
        return p
      },
      { replace: true },
    )

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
      header: 'Item dan kegiatan',
      mobile: 'title',
      sortKey: 'item_name',
      cell: (r) => {
        const room = r.room ? `${r.room.name}${r.room.building ? ` (${r.room.building.name})` : ''}` : null
        const activity = r.purpose && room ? `${r.purpose} di ${room}` : (r.purpose ?? room)
        return (
          <>
            <span className="font-semibold">{r.item_name}</span>
            {activity && <span className="block text-xs text-slate-500">{activity}</span>}
          </>
        )
      },
    },
    {
      header: 'Jumlah',
      sortKey: 'quantity',
      cell: (r) =>
        r.received_quantity > 0 && r.received_quantity < r.quantity ? (
          <span className="inline-block min-w-16">
            <span className="tabular-nums">{r.received_quantity} / {r.quantity}</span>
            <span className="mt-1 block h-1 overflow-hidden rounded-full bg-slate-100" aria-hidden>
              <span className="block h-full rounded-full bg-plum-700" style={{ width: `${(r.received_quantity / r.quantity) * 100}%` }} />
            </span>
          </span>
        ) : (
          <span className="tabular-nums">{r.quantity}</span>
        ),
    },
    { header: 'Penyedia', cell: (r) => r.provider_department?.name ?? '-' },
    { header: 'Cara', cell: (r) => r.request_method ?? '-' },
    { header: 'Status', mobile: 'badge', cell: (r) => <Badge color={statusColor[r.display_status]} dot>{r.display_status}</Badge> },
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
        title="Permintaan ke Unit Lain"
        description="Catat dan pantau permintaan perlengkapan ke departemen penyedia"
        actions={
          <>
            <ExportButton
              type="requests"
              filters={() => ({
                ...(status ? { display_status: status } : {}),
                ...(providerId ? { provider_department_id: providerId } : {}),
                search: search.trim(),
              })}
            />
            <Button onClick={() => setModal({ kind: 'request', request: null })}>
              <Plus size={16} /> Catat permintaan
            </Button>
          </>
        }
      />

      {suggestions.length > 0 && (
        <SuggestionPanel
          groups={suggestions}
          onRequest={(group) => setModal({ kind: 'batch', group })}
          onShowHistory={(id) => list.setFilter('provider_department_id', String(id), { tab: null, status: null })}
        />
      )}

      <section className="rounded-xl border border-slate-200 bg-white">
        <div className="flex gap-1 overflow-x-auto border-b border-slate-200 px-5" role="tablist">
          {(['riwayat', 'katalog'] as const).map((t) => (
            <button
              key={t}
              type="button"
              role="tab"
              aria-selected={tab === t}
              onClick={() => setTab(t)}
              className={`-mb-px whitespace-nowrap border-b-2 px-3 py-3 text-sm font-semibold transition-colors ${
                tab === t ? 'border-brand-800 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-900'
              }`}
            >
              {t === 'riwayat' ? 'Riwayat permintaan' : 'Katalog perlengkapan'}
            </button>
          ))}
        </div>

        {tab === 'riwayat' ? (
          <div className="space-y-3 p-5">
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
              <div className="sm:w-56">
                <DepartmentCombobox
                  value={providerId ? Number(providerId) : null}
                  onChange={(id) => list.setFilter('provider_department_id', id ? String(id) : '')}
                  departments={providers}
                  placeholder="Semua penyedia"
                />
              </div>
            </div>
            <div className="flex flex-wrap gap-2" role="group" aria-label="Filter status">
              <Chip label="Semua" count={counts?.Semua} active={!status} onClick={() => list.setFilter('status', '')} />
              {CHIPS.filter((c) => c.always || (counts?.[c.status] ?? 0) > 0 || status === c.status).map((c) => (
                <Chip key={c.status} label={c.status} count={counts?.[c.status]} active={status === c.status} onClick={() => list.setFilter('status', c.status)} />
              ))}
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
          </div>
        ) : (
          <div className="space-y-3 p-5">
            <div className="flex justify-end">
              <Button variant="secondary" onClick={() => setModal({ kind: 'catalog', item: null })}>
                <Plus size={16} /> Tambah item
              </Button>
            </div>
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
          </div>
        )}
      </section>

      {modal?.kind === 'request' && (
        <RequestFormModal catalog={catalog} request={modal.request} prefill={modal.prefill} onClose={closeModal} onDone={done} />
      )}
      {modal?.kind === 'batch' && <BatchRequestModal group={modal.group} onClose={closeModal} onDone={done} />}
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
