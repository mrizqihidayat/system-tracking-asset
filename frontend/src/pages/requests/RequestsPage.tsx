import { EllipsisVertical } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu, { type MenuItem } from '../../components/ui/DropdownMenu'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import type { Paginated } from '../../types/api'
import type { RequestCatalog, RequestPrefill, RequestStatus, SuggestionGroup, SupplyRequest } from '../../types/supply'
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
    <section className="rounded-lg border border-slate-200 bg-white p-5">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
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
  const [params, setParams] = useSearchParams()
  const [catalog, setCatalog] = useState<RequestCatalog[]>([])
  const [history, setHistory] = useState<Paginated<SupplyRequest> | null>(null)
  const [suggestions, setSuggestions] = useState<SuggestionGroup[]>([])
  const [loading, setLoading] = useState(true)
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
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const loadCatalog = useCallback(
    () => api.get<{ data: RequestCatalog[] }>('/request-catalog').then((r) => setCatalog(r.data.data)),
    [],
  )

  const load = useCallback(() => {
    const query: Record<string, unknown> = { page, per_page: 10 }
    if (status === 'Belum direspons') query.unresponded = 1
    else if (status) query.status = status
    if (search.trim()) query.search = search.trim()
    return Promise.all([
      loadCatalog(),
      api.get<Paginated<SupplyRequest>>('/supply-requests', { params: query }).then((r) => setHistory(r.data)),
      api.get<{ data: SuggestionGroup[] }>('/supply-requests/suggestions').then((r) => setSuggestions(r.data.data)),
    ])
      .catch((e) => toast.error(errorMessage(e)))
      .finally(() => setLoading(false))
  }, [loadCatalog, page, status, search, toast])

  useEffect(() => {
    const t = setTimeout(load, 250) // jeda singkat agar ketikan pencarian tidak memicu request berlebih
    return () => clearTimeout(t)
  }, [load])

  // Query sudah dipakai untuk isian awal modal → bersihkan dari URL.
  useEffect(() => {
    if (params.has('room_id') || params.has('catalog_id')) setParams({}, { replace: true })
  }, [params, setParams])

  const closeModal = () => setModal(null)
  const done = () => {
    closeModal()
    load()
  }

  const removeCatalog = async () => {
    if (!deleting) return
    try {
      await api.delete(`/request-catalog/${deleting.id}`)
      toast.success(`"${deleting.item_name}" dihapus dari katalog.`)
      setDeleting(null)
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const catalogColumns: Column<RequestCatalog>[] = [
    { header: 'Item', cell: (c) => c.item_name },
    { header: 'Unit penyedia', cell: (c) => c.provider_department?.name ?? '-' },
    { header: 'Cara permintaan', cell: (c) => c.request_method },
    { header: 'Perlu dikembalikan', cell: (c) => (c.must_return ? 'Ya' : 'Tidak') },
    { header: 'Lead time', cell: (c) => `${c.lead_time_days} hari` },
    {
      header: '',
      className: 'w-10',
      cell: (c) => (
        <DropdownMenu
          label="Aksi katalog"
          trigger={<EllipsisVertical size={16} />}
          triggerClassName="px-1.5!"
          items={[
            { label: 'Ubah', onClick: () => setModal({ kind: 'catalog', item: c }) },
            { label: 'Hapus', danger: true, onClick: () => setDeleting(c) },
          ]}
        />
      ),
    },
  ]

  const historyColumns: Column<SupplyRequest>[] = [
    { header: 'Tanggal', cell: (r) => formatDate(r.request_date) },
    {
      header: 'Item',
      cell: (r) => (
        <>
          {r.item_name}
          {r.note && <span className="block text-xs text-slate-500">{r.note}</span>}
        </>
      ),
    },
    { header: 'Jumlah', cell: (r) => r.quantity },
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
    { header: 'Status', cell: (r) => <Badge color={statusColor[r.display_status]}>{r.display_status}</Badge> },
    {
      header: '',
      className: 'w-10',
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
          <DropdownMenu label="Aksi permintaan" trigger={<EllipsisVertical size={16} />} triggerClassName="px-1.5!" items={items} />
        ) : null
      },
    },
  ]

  return (
    <div className="max-w-7xl space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Permintaan ke Unit Lain</h1>
          <p className="mt-1 text-sm text-slate-600">Perlengkapan yang tidak dimiliki SHD dan harus diminta ke unit penyedia</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <ExportButton
            type="requests"
            filters={() => ({
              ...(status === 'Belum direspons' ? { unresponded: 1 } : { status }),
              search: search.trim(),
            })}
          />
          <Button onClick={() => setModal({ kind: 'request', request: null })}>Buat permintaan</Button>
        </div>
      </div>

      {suggestions.length > 0 && (
        <Card title="Saran permintaan">
          <div className="grid gap-4 md:grid-cols-2">
            {suggestions.map((g) => (
              <div key={g.provider_department.id} className="rounded-md border border-slate-200">
                <p className="border-b border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium">{g.provider_department.name}</p>
                <ul className="divide-y divide-slate-100">
                  {g.items.map((s, i) => (
                    <li key={i} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                      <div className="min-w-0">
                        <p>{s.item_name} <span className="text-slate-500">× {s.quantity}</span></p>
                        <p className="text-xs text-slate-500">
                          {s.source === 'stok' ? `Stok ${s.stock_status?.toLowerCase()}` : `Kurang di ${s.room?.name} (${s.room?.building})`}
                          {s.has_open_request && ' · sudah ada permintaan Diajukan'}
                        </p>
                      </div>
                      <Button
                        variant="secondary"
                        className="shrink-0 px-2! py-1! text-xs!"
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
        </Card>
      )}

      <Card
        title="Katalog perlengkapan dari unit lain"
        action={<Button variant="secondary" className="px-3! py-1.5!" onClick={() => setModal({ kind: 'catalog', item: null })}>Tambah item</Button>}
      >
        <DataTable columns={catalogColumns} rows={catalog} rowKey={(c) => c.id} loading={loading} emptyText="Katalog masih kosong." />
      </Card>

      <Card title="Riwayat permintaan">
        <div className="mb-3 flex flex-wrap items-center gap-2">
          <Input
            className="max-w-xs"
            value={search}
            onChange={(e) => {
              setSearch(e.target.value)
              setPage(1)
            }}
            placeholder="Cari item atau kegiatan"
            aria-label="Cari permintaan"
          />
          <Select
            className="w-auto"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value)
              setPage(1)
            }}
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
          loading={loading}
          emptyText="Belum ada permintaan."
          meta={history?.meta}
          unit="permintaan"
          onPageChange={setPage}
        />
      </Card>

      {modal?.kind === 'request' && (
        <RequestFormModal catalog={catalog} request={modal.request} prefill={modal.prefill} onClose={closeModal} onDone={done} />
      )}
      {modal?.kind === 'catalog' && <CatalogFormModal item={modal.item} onClose={closeModal} onDone={done} />}
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
