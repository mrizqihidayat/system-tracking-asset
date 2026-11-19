import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import Button from '../../components/ui/Button'
import DataTable, { type Column } from '../../components/ui/DataTable'
import Modal from '../../components/ui/Modal'
import { api } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { queryKeys } from '../../lib/queries/keys'
import { useErrorToast } from '../../lib/useErrorToast'
import type { Paginated } from '../../types/api'
import type { RoomConsumable, RoomStockTransaction } from '../../types/room'

const columns: Column<RoomStockTransaction>[] = [
  { header: 'Tanggal', cell: (t) => <span className="whitespace-nowrap">{formatDate(t.transaction_date)}</span> },
  { header: 'Jenis', mobile: 'title', cell: (t) => <span className="font-medium">{t.type}</span> },
  {
    header: 'Jumlah',
    cell: (t) => <span className={`tabular-nums ${t.quantity < 0 ? 'text-bad-700' : ''}`}>{t.quantity > 0 ? `+${t.quantity}` : t.quantity}</span>,
  },
  { header: 'Saldo', cell: (t) => <span className="tabular-nums">{t.balance_after}</span> },
  { header: 'Keterangan', cell: (t) => t.note ?? '-' },
  { header: 'Dicatat oleh', cell: (t) => t.created_by ?? '-' },
]

/** Kartu ruangan: riwayat satu barang di satu ruangan (termasuk sebelum barang pernah dilepas), terbaru lebih dulu. */
export default function RoomCardModal({ row, roomName, onClose }: { row: RoomConsumable; roomName: string; onClose: () => void }) {
  const [page, setPage] = useState(1)
  const transactions = useQuery({
    queryKey: queryKeys.rooms.transactions(row.id, page),
    queryFn: async () =>
      (await api.get<Paginated<RoomStockTransaction>>(`/room-standards/${row.id}/transactions`, { params: { page, per_page: 10 } })).data,
    placeholderData: keepPreviousData,
  })
  useErrorToast(transactions.error)

  return (
    <Modal
      open
      title={`Kartu ruangan • ${row.item_name}`}
      onClose={onClose}
      footer={<Button variant="secondary" onClick={onClose}>Tutup</Button>}
    >
      <p className="mb-3 text-sm text-slate-600">
        {roomName} · Catatan sekarang: <b className="tabular-nums">{row.recorded_quantity}</b> {row.consumable.unit}
      </p>
      <DataTable
        columns={columns}
        rows={transactions.data?.data ?? []}
        rowKey={(t) => t.id}
        loading={transactions.isPending}
        error={transactions.error}
        onRetry={() => transactions.refetch()}
        retrying={transactions.isFetching}
        fetching={transactions.isPlaceholderData}
        emptyText="Belum ada transaksi. Riwayat dimulai saat perubahan pertama dicatat."
        meta={transactions.data?.meta}
        unit="transaksi"
        onPageChange={setPage}
      />
    </Modal>
  )
}
