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
import { useSuggestions, useSupplyRequests } from '../../lib/queries/lists'
import { useRequestCatalog } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'
import type { RequestCatalog, RequestPrefill, RequestStatus, SupplyRequest } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'
import CatalogFormModal from './CatalogFormModal'
import RequestFormModal from './RequestFormModal'

const statusColor = {
  Diajukan: 'blue',
  Diterima: 'green',
  'Belum direspons': 'amber',
  Dikembalikan: 'slate',
  Dibatalkan: 'red',
} as const

type ModalState =
  | { kind: 'request'; request: SupplyRequest | null; prefill?: RequestPrefill }
  | { kind: 'catalog'; item: RequestCatalog | null }
  | { kind: 'status'; request: SupplyRequest; status: Exclude<RequestStatus, 'Diajukan'> }
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

function StatusModal({ request, status, onClose, onDone }: {
  request: SupplyRequest
  status: Exclude<RequestStatus, 'Diajukan'>
  onClose: () => void
  onDone: () => void
}) {
  const toast = useToast()
  const [note, setNote] = useState('')
  const submit = async () => {
    await api.patch(`/supply-requests/${request.id}/status`, { status, note: note.trim() || null })
    toast.success(`Permintaan ditandai ${status}.`)
    onDone()
  }
  return (
    <ActionModal title={`Tandai ${status}`} submitLabel={`Tandai ${status}`} danger={status === 'Dibatalkan'} onClose={onClose} onSubmit={submit}>
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">
            {request.item_name} × {request.quantity} dari {request.provider_department?.name ?? '-'}.
          </p>
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
  // Dari Kelengkapan Ruangan: /permintaan?room_id=&catalog_id=&quantity= → buka form terisi otomatis.
  const [modal, setModal] = useState<ModalState>(() => {
    if (!params.has('room_id') && !params.has('catalog_id')) return null
    const num = (k: string) => (params.get(k) ? Number(params.get(k)) : null)
    return {
      kind: 'request',
      request: null,
      prefill: { room_id: num('room_id'), catalog_id: num('catalog_id'), quantity: num('quantity') ?? undefined },
    }
  })
  const [deleting, setDeleting] = useState<RequestCatalog | null>(null)
  const status = list.get('status')
  const search = list.searchText

  // Query sudah dipakai untuk isian awal modal → bersihkan dari URL (filter tabel tetap).
  useEffect(() => {
    if (!params.has('room_id') && !params.has('catalog_id')) return
    setParams(
      (p) => {
        ;['room_id', 'catalog_id', 'quantity'].forEach((k) => p.delete(k))
        return p
      },
      { replace: true },
    )
  }, [params, setParams])

  const closeModal = () => setModal(null)
  const done = () => {
    closeModal()
    invalidate.supplyRequests(queryClient)
  }
  const catalogDone = () => {
    closeModal()
    invalidate.catalog(queryClient)
  }

  const removeCatalog = async () => {
    if (!deleting) return
    try {
      await api.delete(`/request-catalog/${deleting.id}`)
      toast.success(`"${deleting.item_name}" dihapus dari katalog.`)
      setDeleting(null)
      invalidate.catalog(queryClient)
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const catalogColumns: Column<RequestCatalog>[] = [
    { header: 'Item', mobile: 'title', cell: (c) => <span className="font-medium">{c.item_name}</span> },
    { header: 'Unit penyedia', cell: (c) => c.provider_department?.name ?? '-' },
    { header: 'Cara permintaan', cell: (c) => c.request_method },
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
            { label: 'Hapus', danger: true, onClick: () => setDeleting(c) },
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
    { header: 'Jumlah', sortKey: 'quantity', cell: (r) => <span className="tabular-nums">{r.quantity}</span> },
    {
      header: 'Untuk kegiatan',
      cell: (r) => (
        <>
          {r.purpose ?? '-'}
          {r.room && <span className="block text-xs text-slate-500">{r.room.name}{r.room.building ? ` (${r.room.building.name})` : ''}</span>}
        </>
      ),
    },
    { header: 'Unit penyedia', cell: (r) => r.provider_department?.name ?? '-' },
    { header: 'Status', mobile: 'badge', cell: (r) => <Badge color={statusColor[r.display_status]}>{r.display_status}</Badge> },
    {
      header: '',
      className: 'w-10',
      mobile: 'badge',
      cell: (r) => {
        const items: MenuItem[] = []
        if (r.status === 'Diajukan') {
          items.push(
            { label: 'Tandai Diterima', onClick: () => setModal({ kind: 'status', request: r, status: 'Diterima' }) },
            { label: 'Ubah', onClick: () => setModal({ kind: 'request', request: r }) },
            { label: 'Tandai Dibatalkan', danger: true, onClick: () => setModal({ kind: 'status', request: r, status: 'Dibatalkan' }) },
          )
        }
        if (r.status === 'Diterima' && r.catalog?.must_return) {
          items.push({ label: 'Tandai Dikembalikan', onClick: () => setModal({ kind: 'status', request: r, status: 'Dikembalikan' }) })
        }
        return items.length ? (
          <DropdownMenu label="Aksi permintaan" trigger={<EllipsisVertical size={16} />} triggerClassName="px-2.5!" items={items} />
        ) : null
      },
    },
  ]

  return (
    <div className="space-y-6">
      <PageHeader
        title="Permintaan ke Unit Lain"
        description="Perlengkapan yang tidak dimiliki SHD dan harus diminta ke unit penyedia."
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
                          {s.source === 'stok' ? `Stok ${s.stock_status?.toLowerCase()}` : `Kurang di ${s.room?.name} (${s.room?.building})`}
                          {s.has_open_request && ' · sudah ada permintaan Diajukan'}
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
                              catalog_id: s.catalog_id,
                              item_name: s.catalog_id ? undefined : s.item_name,
                              provider_department_id: g.provider_department.id,
                              quantity: s.quantity,
                              room_id: s.room?.id ?? null,
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
        title="Katalog perlengkapan dari unit lain"
        action={<Button variant="secondary" onClick={() => setModal({ kind: 'catalog', item: null })}><Plus size={16} /> Tambah item</Button>}
      >
        <DataTable columns={catalogColumns} rows={catalog} rowKey={(c) => c.id} loading={catalogQuery.isPending} emptyText="Katalog masih kosong." />
      </Card>

      <Card title="Riwayat permintaan">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <Input
            className="sm:max-w-xs"
            value={search}
            onChange={(e) => list.setSearchText(e.target.value)}
            placeholder="Cari item atau kegiatan"
            aria-label="Cari permintaan"
          />
          <Select
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
      {modal?.kind === 'status' && <StatusModal request={modal.request} status={modal.status} onClose={closeModal} onDone={done} />}

      <ConfirmDialog
        open={deleting !== null}
        title="Hapus item katalog"
        confirmLabel="Hapus"
        danger
        onConfirm={removeCatalog}
        onClose={() => setDeleting(null)}
      >
        Hapus "{deleting?.item_name}" dari katalog? Item yang sudah dipakai di permintaan atau standar ruangan tidak bisa dihapus.
      </ConfirmDialog>
    </div>
  )
}
