import { EllipsisVertical, Plus } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { Badge } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { PageHeader } from '../../components/ui/Page'
import ConfirmDialog from '../../components/ui/ConfirmDialog'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu from '../../components/ui/DropdownMenu'
import { Input, Select } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { invalidate } from '../../lib/queries/invalidate'
import { listConfigs } from '../../lib/queries/list-configs'
import { useConsumables } from '../../lib/queries/lists'
import { useBuildings, useRooms } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'
import type { ConsumableItem, ConsumableUsage } from '../../types/supply'
import ConsumableFormModal from './ConsumableFormModal'
import StockCardModal from './StockCardModal'
import StockTransactionModal from './StockTransactionModal'

const statusColor = { Aman: 'green', Menipis: 'amber', Habis: 'red' } as const

type ModalState =
  | { kind: 'form'; item: ConsumableItem | null }
  | { kind: 'transaction'; item: ConsumableItem }
  | { kind: 'card'; item: ConsumableItem }
  | null

/** Form Buat permintaan terisi: item, departemen, jumlah = minimum − jumlah (minimal 1), tanpa katalog. */
const requestUrl = (c: ConsumableItem) => {
  const p = new URLSearchParams({
    consumable_id: String(c.id),
    item_name: c.name,
    quantity: String(Math.max(c.min_quantity - c.quantity, 1)),
  })
  if (c.provider_department) p.set('provider_department_id', String(c.provider_department.id))
  return `/permintaan?${p}`
}

const openRequestUrl = (id: number) => `/permintaan?id=${id}`

export default function ConsumablesPage() {
  const toast = useToast()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [modal, setModal] = useState<ModalState>(null)
  const [deleting, setDeleting] = useState<{ item: ConsumableItem; usage: ConsumableUsage[] } | null>(null)
  const list = useListParams(listConfigs.consumables)
  const consumables = useConsumables(list.params)
  useErrorToast(consumables.error)
  const result = consumables.data
  const status = list.get('status')
  const search = list.searchText
  const buildingId = list.get('building_id')
  const roomId = list.get('room_id')
  const { data: buildings } = useBuildings()
  const { data: rooms } = useRooms(buildingId ? Number(buildingId) : null, true)

  const load = () => invalidate.consumables(queryClient)

  const askDelete = async (item: ConsumableItem) => {
    try {
      const r = await api.get<{ data: { rooms: ConsumableUsage[]; delete_block_reason: string | null } }>(`/consumables/${item.id}/usage`)
      if (r.data.data.delete_block_reason) toast.error(r.data.data.delete_block_reason)
      else setDeleting({ item, usage: r.data.data.rooms })
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
      sortKey: 'name',
      cell: (c) => (
        <>
          <span className="font-medium">{c.name}</span>
          {c.note && <span className="block text-xs text-slate-500">{c.note}</span>}
        </>
      ),
    },
    { header: 'Kategori', className: 'text-center', sortKey: 'category', cell: (c) => c.category ?? '-' },
    {
      header: 'Jumlah',
      className: 'text-center',
      sortKey: 'quantity',
      cell: (c) => (
        <>
          <span className="whitespace-nowrap font-semibold tabular-nums">
            {c.quantity} <span className="text-xs font-normal text-slate-500">{c.unit}</span>
          </span>
          <button type="button" className="tap block whitespace-nowrap text-xs font-semibold text-brand-700 hover:underline lg:mx-auto" onClick={() => setModal({ kind: 'transaction', item: c })}>
            Catat transaksi
          </button>
        </>
      ),
    },
    { header: 'Minimum', className: 'text-center', cell: (c) => <span className="tabular-nums">{c.min_quantity}</span> },
    { header: 'Status', className: 'text-center', mobile: 'badge', cell: (c) => <Badge color={statusColor[c.status]}>{c.status}</Badge> },
    { header: 'Lokasi simpan', cell: (c) => (c.room ? `${c.room.name}, ${c.room.building?.name ?? '-'}` : '-') },
    {
      header: 'Departemen penyedia',
      className: 'text-center',
      cell: (c) => (
        <>
          {c.provider_department?.name ?? '-'}
          {c.provider_department && !c.provider_department.is_internal && (c.open_request ? (
            <Link to={openRequestUrl(c.open_request.id)} className="tap block whitespace-nowrap text-xs text-slate-500 hover:text-brand-700 hover:underline">
              Diajukan · {formatDate(c.open_request.created_at)}
            </Link>
          ) : c.status !== 'Aman' && (
            <Link to={requestUrl(c)} className="tap block whitespace-nowrap text-xs font-semibold text-brand-700 hover:underline">
              Ajukan permintaan
            </Link>
          ))}
        </>
      ),
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
            { label: 'Catat transaksi', onClick: () => setModal({ kind: 'transaction', item: c }) },
            { label: 'Kartu stok', onClick: () => setModal({ kind: 'card', item: c }) },
            { label: 'Ubah data', onClick: () => setModal({ kind: 'form', item: c }) },
            ...(c.provider_department && !c.provider_department.is_internal
              ? [c.open_request
                ? { label: 'Lihat permintaan', onClick: () => navigate(openRequestUrl(c.open_request!.id)) }
                : { label: 'Ajukan permintaan', onClick: () => navigate(requestUrl(c)) }]
              : []),
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
        description={`${result ? `${result.meta.total} barang. ` : ''}Setiap perubahan jumlah dicatat sebagai transaksi. Stok menipis/habis masuk saran permintaan.`}
        actions={
          <>
            <ExportButton type="consumables" filters={() => ({ status, building_id: buildingId, room_id: roomId, search: search.trim() })} />
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
          onChange={(e) => list.setSearchText(e.target.value)}
          placeholder="Cari nama barang"
          aria-label="Cari barang"
        />
        <Select
          clearable
          className="sm:w-auto"
          value={status}
          onChange={(e) => list.setFilter('status', e.target.value)}
          aria-label="Status"
        >
          <option value="">Semua status</option>
          <option>Aman</option>
          <option>Menipis</option>
          <option>Habis</option>
        </Select>
        <Select
          clearable
          className="sm:w-auto"
          value={buildingId}
          onChange={(e) => list.setFilter('building_id', e.target.value, { room_id: null })}
          aria-label="Gedung lokasi simpan"
        >
          <option value="">Semua gedung</option>
          {buildings.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </Select>
        <Select
          clearable
          className="sm:w-auto"
          value={roomId}
          disabled={!buildingId}
          onChange={(e) => list.setFilter('room_id', e.target.value)}
          aria-label="Ruangan lokasi simpan"
        >
          <option value="">Semua ruangan</option>
          {rooms.filter((r) => r.is_storage).map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
        </Select>
      </div>

      <DataTable
        columns={columns}
        rows={result?.data ?? []}
        rowKey={(c) => c.id}
        loading={consumables.isPending}
        error={consumables.error}
        onRetry={() => consumables.refetch()}
        retrying={consumables.isFetching}
        fetching={consumables.isPlaceholderData}
        emptyText="Belum ada barang habis pakai."
        meta={result?.meta}
        unit="barang"
        onPageChange={list.setPage}
        sort={list.sort}
        onSortChange={list.setSort}
      />

      {modal?.kind === 'form' && <ConsumableFormModal item={modal.item} onClose={() => setModal(null)} onDone={done} />}
      {modal?.kind === 'transaction' && <StockTransactionModal item={modal.item} onClose={() => setModal(null)} onDone={done} />}
      {modal?.kind === 'card' && <StockCardModal item={modal.item} onClose={() => setModal(null)} />}

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
            <b>{deleting.usage.map((u) => `${u.room} (${u.building})`).join(', ')}</b>. Barang itu ikut dilepas dari ruangan-ruangan tersebut.
          </>
        ) : (
          'Hapus permanen barang ini? Tindakan ini tidak bisa dibatalkan.'
        )}
      </ConfirmDialog>
    </div>
  )
}
