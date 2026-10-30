import { EllipsisVertical, Minus, Plus } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import ExportButton from '../../components/ExportButton'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { PageHeader } from '../../components/ui/Page'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu from '../../components/ui/DropdownMenu'
import { FormField, Input, Select } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import type { Paginated } from '../../types/api'
import type { ConsumableItem, ConsumableUsage } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'
import ConsumableFormModal from './ConsumableFormModal'

const statusColor = { Aman: 'green', Menipis: 'amber', Habis: 'red' } as const

type ModalState = { kind: 'form'; item: ConsumableItem | null } | { kind: 'quantity'; item: ConsumableItem } | null

function QuantityModal({ item, onClose, onDone }: { item: ConsumableItem; onClose: () => void; onDone: () => void }) {
  const toast = useToast()
  const [value, setValue] = useState(String(item.quantity))
  const submit = async () => {
    await api.patch(`/consumables/${item.id}/quantity`, { quantity: Number(value) })
    toast.success(`Jumlah ${item.name} menjadi ${value} ${item.unit}.`)
    onDone()
  }
  return (
    <ActionModal title={`Ubah jumlah — ${item.name}`} submitLabel="Simpan" submitDisabled={value === ''} onClose={onClose} onSubmit={submit}>
      {(errors) => (
        <FormField label={`Jumlah sekarang (${item.unit})`} required error={errors.quantity}>
          <Input type="number" min={0} value={value} onChange={(e) => setValue(e.target.value)} error={errors.quantity} autoFocus />
        </FormField>
      )}
    </ActionModal>
  )
}

export default function ConsumablesPage() {
  const toast = useToast()
  const [result, setResult] = useState<Paginated<ConsumableItem> | null>(null)
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState<ModalState>(null)
  const [busyId, setBusyId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState<{ item: ConsumableItem; usage: ConsumableUsage[] } | null>(null)
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const load = useCallback(() => {
    const params: Record<string, unknown> = { page, per_page: 20 }
    if (status) params.status = status
    if (search.trim()) params.search = search.trim()
    return api
      .get<Paginated<ConsumableItem>>('/consumables', { params })
      .then((r) => setResult(r.data))
      .catch((e) => toast.error(errorMessage(e)))
      .finally(() => setLoading(false))
  }, [page, status, search, toast])

  useEffect(() => {
    const t = setTimeout(load, 250) // jeda singkat agar ketikan pencarian tidak memicu request berlebih
    return () => clearTimeout(t)
  }, [load])

  const adjust = async (item: ConsumableItem, adjustment: number) => {
    setBusyId(item.id)
    try {
      const r = await api.patch<{ data: ConsumableItem }>(`/consumables/${item.id}/quantity`, { adjustment })
      setResult((cur) => cur && { ...cur, data: cur.data.map((c) => (c.id === item.id ? r.data.data : c)) })
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusyId(null)
    }
  }

  const askDelete = async (item: ConsumableItem) => {
    try {
      const r = await api.get<{ data: { rooms: ConsumableUsage[] } }>(`/consumables/${item.id}/usage`)
      setDeleting({ item, usage: r.data.data.rooms })
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const remove = async () => {
    if (!deleting) return
    try {
      const r = await api.delete<{ message: string }>(`/consumables/${deleting.item.id}`)
      toast.success(r.data.message)
      setDeleting(null)
      load()
    } catch (e) {
      toast.error(errorMessage(e))
    }
  }

  const done = () => {
    setModal(null)
    load()
  }

  const columns: Column<ConsumableItem>[] = [
    {
      header: 'Nama',
      headerClassName: 'text-center',
      mobile: 'title',
      cell: (c) => (
        <>
          <span className="font-medium">{c.name}</span>
          {c.note && <span className="block text-xs text-slate-500">{c.note}</span>}
        </>
      ),
    },
    { header: 'Kategori', className: 'text-center', cell: (c) => c.category ?? '-' },
    {
      header: 'Jumlah',
      className: 'text-center',
      cell: (c) => (
        <div className="flex items-center gap-1 md:justify-center">
          <button
            type="button"
            aria-label={`Kurangi ${c.name}`}
            disabled={busyId === c.id || c.quantity === 0}
            onClick={() => adjust(c, -1)}
            className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 disabled:opacity-40"
          >
            <Minus size={14} />
          </button>
          <button
            type="button"
            title="Klik untuk mengisi jumlah langsung"
            onClick={() => setModal({ kind: 'quantity', item: c })}
            className="min-h-9 min-w-16 whitespace-nowrap rounded-lg px-2 text-center font-semibold tabular-nums hover:bg-slate-100"
          >
            {c.quantity} <span className="text-xs font-normal text-slate-500">{c.unit}</span>
          </button>
          <button
            type="button"
            aria-label={`Tambah ${c.name}`}
            disabled={busyId === c.id}
            onClick={() => adjust(c, 1)}
            className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 disabled:opacity-40"
          >
            <Plus size={14} />
          </button>
        </div>
      ),
    },
    { header: 'Minimum', className: 'text-center', cell: (c) => <span className="tabular-nums">{c.min_quantity}</span> },
    { header: 'Status', className: 'text-center', mobile: 'badge', cell: (c) => <Badge color={statusColor[c.status]}>{c.status}</Badge> },
    { header: 'Departemen penyedia', className: 'text-center', cell: (c) => c.provider_department?.name ?? '-' },
    {
      header: 'Lokasi simpan',
      cell: (c) => (c.room ? `${c.room.name}${c.room.building ? `, ${c.room.building.name}` : ''}` : '-'),
    },
    {
      header: '',
      className: 'w-10',
      mobile: 'badge',
      cell: (c) => (
        <DropdownMenu
          label="Aksi barang"
          trigger={<EllipsisVertical size={16} />}
          triggerClassName="px-2.5!"
          items={[
            { label: 'Ubah data', onClick: () => setModal({ kind: 'form', item: c }) },
            { label: 'Hapus', danger: true, onClick: () => askDelete(c) },
          ]}
        />
      ),
    },
  ]

  return (
    <div className="space-y-5">
      <PageHeader
        title="Stok Habis Pakai"
        description={`Barang yang disimpan SHD${result ? ` · ${result.meta.total} barang` : ''}. Stok menipis/habis masuk saran permintaan.`}
        actions={
          <>
            <ExportButton type="consumables" filters={() => ({ status, search: search.trim() })} />
            <Button onClick={() => setModal({ kind: 'form', item: null })}>
              <Plus size={16} /> Tambah barang
            </Button>
          </>
        }
      />

      <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
        <Input
          className="sm:max-w-xs"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
          placeholder="Cari nama barang"
          aria-label="Cari barang"
        />
        <Select
          className="sm:w-auto"
          value={status}
          onChange={(e) => {
            setStatus(e.target.value)
            setPage(1)
          }}
          aria-label="Status"
        >
          <option value="">Semua status</option>
          <option>Aman</option>
          <option>Menipis</option>
          <option>Habis</option>
        </Select>
      </div>

      <DataTable
        columns={columns}
        rows={result?.data ?? []}
        rowKey={(c) => c.id}
        loading={loading}
        emptyText="Belum ada barang habis pakai."
        meta={result?.meta}
        unit="barang"
        onPageChange={setPage}
      />

      {modal?.kind === 'form' && <ConsumableFormModal item={modal.item} onClose={() => setModal(null)} onDone={done} />}
      {modal?.kind === 'quantity' && <QuantityModal item={modal.item} onClose={() => setModal(null)} onDone={done} />}

      <ConfirmDialog
        open={deleting !== null}
        title={`Hapus ${deleting?.item.name ?? ''}`}
        confirmLabel="Hapus permanen"
        danger
        onConfirm={remove}
        onClose={() => setDeleting(null)}
      >
        {deleting && deleting.usage.length > 0 ? (
          <>
            Hapus permanen. Barang ini dipakai di standar:{' '}
            <b>{deleting.usage.map((u) => `${u.room} (${u.building})`).join(', ')}</b>. Tautannya akan dilepas.
          </>
        ) : (
          'Hapus permanen barang ini? Tindakan ini tidak bisa dibatalkan.'
        )}
      </ConfirmDialog>
    </div>
  )
}
