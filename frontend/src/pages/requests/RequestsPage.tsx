import { EllipsisVertical, Plus } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import DepartmentCombobox from '../../components/DepartmentCombobox'
import DateRangeFilter from '../../components/DateRangeFilter'
import ExportButton from '../../components/ExportButton'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu, { type MenuItem } from '../../components/ui/DropdownMenu'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { PageHeader } from '../../components/ui/Page'
import { LoadingLabel, Skeleton } from '../../components/ui/Skeleton'
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

const CATALOG_PER_PAGE = 10
const PREFILL_PARAMS = ['room_id', 'catalog_id', 'consumable_id']

const statusColor = {
  Diajukan: 'blue',
  'Diterima sebagian': 'teal',
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
  // Terbuka: Diajukan. Selesai: Diterima sebagian hanya bisa Dikembalikan (bila perlu dikembalikan), selain itu final.
  if (request.status === 'Diajukan') return ['Diterima', 'Dibatalkan']
  if (request.status === 'Diterima') return request.must_return ? ['Dikembalikan', 'Dibatalkan'] : ['Dibatalkan']
  if (request.status === 'Diterima sebagian') return request.must_return ? ['Dikembalikan'] : []
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
  // Barang stok: staf mengisi jumlah yang benar-benar datang; kurang dari yang diminta = Diterima sebagian (selesai).
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
    toast.success(receivesStock && receivedValue < remaining ? `Diterima sebagian: ${receivedValue} dari ${request.quantity}.` : `Permintaan ditandai ${status}.`)
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
            {request.received_quantity > 0 && ` Diterima ${request.received_quantity} dari ${request.quantity}.`}
          </p>
          {receivesStock ? (
            <>
              <FormField label="Jumlah yang diterima" required error={errors.received_quantity}>
                <Input type="number" min={1} max={remaining} value={received} onChange={(e) => setReceived(e.target.value)} error={errors.received_quantity} autoFocus />
              </FormField>
              {receivedValid && (
                <p className="rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-700">
                  Stok {request.item_name} akan bertambah {receivedValue}.
                  {receivedValue < remaining && ` Status menjadi Diterima sebagian (selesai): sisa ${remaining - receivedValue} tidak bisa diterima lagi, ajukan ulang lewat Saran permintaan.`}
                </p>
              )}
            </>
          ) : impact ? (
            <p className={`rounded-lg px-3 py-2.5 text-sm ${impact.clamped ? 'bg-warn-50 text-warn-700' : 'bg-slate-100 text-slate-700'}`}>{impact.message}</p>
          ) : impactError ? (
            <p role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">{errorMessage(impactError)}</p>
          ) : (
            // Placeholder seukuran kotak dampak (tanpa teks yang berkedip); pembaca layar tetap mendengar "Memuat…".
            <div className="space-y-2 rounded-lg border border-slate-100 px-3 py-3" aria-busy="true">
              <LoadingLabel />
              <Skeleton className="h-3.5 w-11/12" />
              <Skeleton className="h-3.5 w-2/3" />
            </div>
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

/** Pilihan filter status Riwayat permintaan (status tampilan; Diajukan tanpa yang Belum direspons). */
const STATUS_OPTIONS: SupplyRequest['display_status'][] = ['Diajukan', 'Belum direspons', 'Diterima sebagian', 'Diterima', 'Dikembalikan', 'Dibatalkan']

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
  // Katalog sudah dimuat utuh: cari, filter departemen, dan halaman dikerjakan di browser, tanpa request baru.
  const [catalogPage, setCatalogPage] = useState(1)
  const [catalogSearch, setCatalogSearch] = useState('')
  const [catalogProvider, setCatalogProvider] = useState<number | null>(null)
  const catalogQueryText = catalogSearch.trim().toLowerCase()
  const catalogRows = catalog.filter(
    (c) => (!catalogQueryText || c.item_name.toLowerCase().includes(catalogQueryText)) && (!catalogProvider || c.provider_department?.id === catalogProvider),
  )
  const catalogLastPage = Math.max(1, Math.ceil(catalogRows.length / CATALOG_PER_PAGE))
  const catalogPageNow = Math.min(catalogPage, catalogLastPage)
  const status = list.get('status')
  const providerId = list.get('provider_department_id')
  const search = list.searchText
  const from = list.get('from')
  const to = list.get('to')

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
        // Diterima sebagian = selesai (bukan proses berjalan): tampil "4 dari 8" + keterangan, tanpa bilah progres.
        r.status === 'Diterima sebagian' ? (
          <span className="whitespace-nowrap">
            <span className="tabular-nums">{r.received_quantity} dari {r.quantity}</span>
            <span className="block text-xs text-slate-500">diterima</span>
          </span>
        ) : (
          <span className="tabular-nums">{r.quantity}</span>
        ),
    },
    { header: 'Penyedia', className: 'text-center', cell: (r) => r.provider_department?.name ?? '-' },
    { header: 'Cara', className: 'text-center', cell: (r) => r.request_method ?? '-' },
    { header: 'Perlu dikembalikan', className: 'text-center', cell: (r) => (r.must_return ? 'Ya' : 'Tidak') },
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
        if ((r.status === 'Diterima' || r.status === 'Diterima sebagian') && r.must_return) {
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
                from,
                to,
              })}
            />
            <Button onClick={() => setModal({ kind: 'request', request: null })}>
              <Plus size={16} /> Catat permintaan
            </Button>
          </>
        }
      />

      <SuggestionPanel groups={suggestions} onRequest={(group) => setModal({ kind: 'batch', group })} />

      <section className="rounded-xl border border-slate-200 bg-white">
        <div className="flex gap-1 border-b border-slate-200 px-5" role="tablist">
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
            <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
              <Input
                className="col-span-2 sm:w-48"
                value={search}
                onChange={(e) => list.setSearchText(e.target.value)}
                placeholder="Cari item atau kegiatan"
                aria-label="Cari permintaan"
              />
              <div className="min-w-0 sm:w-40">
                <DepartmentCombobox
                  value={providerId ? Number(providerId) : null}
                  onChange={(id) => list.setFilter('provider_department_id', id ? String(id) : '')}
                  departments={providers}
                  placeholder="Semua penyedia"
                />
              </div>
              <Select clearable className="min-w-0 sm:w-44" value={status} onChange={(e) => list.setFilter('status', e.target.value)} aria-label="Status">
                <option value="">Semua status</option>
                {STATUS_OPTIONS.map((s) => <option key={s}>{s}</option>)}
              </Select>
              <div className="col-span-2 sm:col-span-1">
                <DateRangeFilter
                  from={from}
                  to={to}
                  dateLabel="Tanggal permintaan"
                  onChange={(nextFrom, nextTo) => list.setFilter('from', nextFrom, { to: nextTo || null })}
                />
              </div>
            </div>
            <DataTable
              columns={historyColumns}
              rows={history?.data ?? []}
              rowKey={(r) => r.id}
              loading={historyQuery.isPending}
              error={historyQuery.error}
              onRetry={() => historyQuery.refetch()}
              retrying={historyQuery.isFetching}
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
            <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
              <Input
                className="col-span-2 sm:w-60"
                value={catalogSearch}
                onChange={(e) => {
                  setCatalogSearch(e.target.value)
                  setCatalogPage(1)
                }}
                placeholder="Cari item"
                aria-label="Cari item katalog"
              />
              <div className="min-w-0 sm:w-48">
                <DepartmentCombobox
                  value={catalogProvider}
                  onChange={(id) => {
                    setCatalogProvider(id)
                    setCatalogPage(1)
                  }}
                  departments={providers}
                  placeholder="Semua penyedia"
                />
              </div>
              <Button variant="secondary" className="sm:ml-auto" onClick={() => setModal({ kind: 'catalog', item: null })}>
                <Plus size={16} /> Tambah item
              </Button>
            </div>
            <DataTable
              columns={catalogColumns}
              rows={catalogRows.slice((catalogPageNow - 1) * CATALOG_PER_PAGE, catalogPageNow * CATALOG_PER_PAGE)}
              rowKey={(c) => c.id}
              loading={catalogQuery.isPending}
              error={catalogQuery.error}
              onRetry={() => catalogQuery.refetch()}
              retrying={catalogQuery.isFetching}
              emptyText={catalog.length === 0 ? 'Katalog masih kosong.' : 'Tidak ada item yang cocok.'}
              meta={catalogRows.length > CATALOG_PER_PAGE ? { current_page: catalogPageNow, last_page: catalogLastPage, per_page: CATALOG_PER_PAGE, total: catalogRows.length } : undefined}
              unit="item"
              onPageChange={setCatalogPage}
              dense
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
