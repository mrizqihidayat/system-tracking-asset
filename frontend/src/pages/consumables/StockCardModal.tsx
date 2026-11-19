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
import type { ConsumableItem, StockTransaction } from '../../types/supply'

const REASON_LABELS: Record<string, string> = { rusak: 'Rusak', kedaluwarsa: 'Kedaluwarsa', lainnya: 'Lainnya' }

const detail = (t: StockTransaction) =>
  [
    t.reason ? REASON_LABELS[t.reason] : null,
    t.note,
    t.room ? `${t.room.name}, ${t.room.building}` : null,
    t.supply_request_id ? `Permintaan #${t.supply_request_id}` : null,
  ].filter(Boolean).join(' · ') || '-'

const columns: Column<StockTransaction>[] = [
  { header: 'Tanggal', cell: (t) => <span className="whitespace-nowrap">{formatDate(t.transaction_date)}</span> },
  { header: 'Jenis', mobile: 'title', cell: (t) => <span className="font-medium">{t.type}</span> },
  {
    header: 'Jumlah',
    cell: (t) => <span className={`tabular-nums ${t.quantity < 0 ? 'text-bad-700' : ''}`}>{t.quantity > 0 ? `+${t.quantity}` : t.quantity}</span>,
  },
  { header: 'Saldo', cell: (t) => <span className="tabular-nums">{t.balance_after}</span> },
  { header: 'Keterangan', cell: detail },
  { header: 'Dicatat oleh', cell: (t) => t.created_by ?? '-' },
]

/** Kartu stok: riwayat transaksi satu barang, terbaru lebih dulu. */
export default function StockCardModal({ item, onClose }: { item: ConsumableItem; onClose: () => void }) {
  const [page, setPage] = useState(1)
  const transactions = useQuery({
    queryKey: queryKeys.consumables.transactions(item.id, page),
    queryFn: async () => (await api.get<Paginated<StockTransaction>>(`/consumables/${item.id}/transactions`, { params: { page, per_page: 10 } })).data,
    placeholderData: keepPreviousData,
  })
  useErrorToast(transactions.error)

  return (
    <Modal
      open
      title={`Kartu stok • ${item.name}`}
      onClose={onClose}
      footer={<Button variant="secondary" onClick={onClose}>Tutup</Button>}
    >
      <p className="mb-3 text-sm text-slate-600">Stok sekarang: <b className="tabular-nums">{item.quantity}</b> {item.unit}</p>
      <DataTable
        columns={columns}
        rows={transactions.data?.data ?? []}
        rowKey={(t) => t.id}
        loading={transactions.isPending}
        error={transactions.error}
        onRetry={() => transactions.refetch()}
        retrying={transactions.isFetching}
        fetching={transactions.isPlaceholderData}
        emptyText="Belum ada transaksi."
        meta={transactions.data?.meta}
        unit="transaksi"
        onPageChange={setPage}
      />
    </Modal>
  )
}
