import { EllipsisVertical, Search } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { AssetCode, Badge, ConditionBadge } from '../../components/ui/Badge'
import { PageHeader } from '../../components/ui/Page'
import Button from '../../components/ui/Button'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu from '../../components/ui/DropdownMenu'
import { Input, Select } from '../../components/ui/FormField'
import { formatDate } from '../../lib/format'
import { invalidate } from '../../lib/queries/invalidate'
import { listConfigs } from '../../lib/queries/list-configs'
import { useLoans } from '../../lib/queries/lists'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'
import type { Loan, LoanStatus } from '../../types/loan'
import CancelLoanModal from './CancelLoanModal'
import LoanForm from './LoanForm'
import ReturnLoanModal from './ReturnLoanModal'

type ModalState = { kind: 'return' | 'cancel'; loan: Loan } | null

const statusColor = { Dipinjam: 'blue', Dikembalikan: 'green', Dibatalkan: 'slate', Hilang: 'red' } as const

/** "Hari ini" / "N hari lagi" / "Terlambat N hari" dari rencana kembali. */
function Tempo({ loan }: { loan: Loan }) {
  if (loan.is_due_today) return <Badge color="amber">Hari ini</Badge>
  const days = Math.round((new Date(`${loan.due_date}T00:00:00`).getTime() - new Date().setHours(0, 0, 0, 0)) / 86_400_000)
  if (loan.is_overdue) return <Badge color="red">{`Terlambat ${Math.abs(days)} hari`}</Badge>
  return <span className="whitespace-nowrap text-slate-600">{days} hari lagi</span>
}

const assetCell = (l: Loan) => (
  <>
    {l.asset && <AssetCode code={l.asset.code} />}
    {l.asset ? (
      <Link to={`/daftar-aset/${l.asset.id}`} className="mt-1 block font-medium hover:text-brand-700 hover:underline">{l.asset.name}</Link>
    ) : '-'}
  </>
)

export default function LoanPage() {
  const queryClient = useQueryClient()
  const [modal, setModal] = useState<ModalState>(null)
  const activeList = useListParams(listConfigs.activeLoans)
  const historyList = useListParams(listConfigs.loanHistory)
  const active = useLoans(activeList.params)
  const history = useLoans(historyList.params)
  useErrorToast(active.error, history.error)

  const status = historyList.get('status')
  const search = historyList.searchText

  const refresh = () => invalidate.loans(queryClient)
  const closeModal = () => setModal(null)
  const done = () => {
    closeModal()
    refresh()
  }

  const activeColumns: Column<Loan>[] = [
    {
      header: 'Aset & peminjam',
      mobile: 'title',
      cell: (l) => (
        <>
          {assetCell(l)}
          <span className="block text-xs text-slate-500">{l.borrower_name} · {l.borrower_unit}</span>
        </>
      ),
    },
    { header: 'Tempo', mobile: 'badge', cell: (l) => <Tempo loan={l} /> },
    {
      header: '',
      className: 'text-right',
      cell: (l) => (
        <div className="flex items-center gap-1 md:justify-end">
          <Button variant="secondary" className="flex-1 px-3! md:flex-none" onClick={() => setModal({ kind: 'return', loan: l })}>
            Terima kembali
          </Button>
          <DropdownMenu
            label="Aksi lainnya"
            trigger={<EllipsisVertical size={16} />}
            triggerClassName="px-2.5!"
            items={[{ label: 'Batalkan', danger: true, onClick: () => setModal({ kind: 'cancel', loan: l }) }]}
          />
        </div>
      ),
    },
  ]

  const historyColumns: Column<Loan>[] = [
    { header: 'Aset', mobile: 'title', cell: assetCell },
    {
      header: 'Peminjam',
      cell: (l) => (
        <>
          {l.borrower_name}
          <span className="block text-xs text-slate-500">{l.borrower_unit} · {l.purpose}</span>
        </>
      ),
    },
    { header: 'Tgl. pinjam', sortKey: 'borrow_date', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.borrow_date)}</span> },
    { header: 'Rencana kembali', sortKey: 'due_date', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.due_date)}</span> },
    {
      header: 'Status',
      mobile: 'badge',
      cell: (l) => (
        <div className="flex flex-col items-end gap-1 md:flex-row md:flex-wrap md:items-center">
          <Badge color={statusColor[l.status]}>{l.status}</Badge>
          {l.status === 'Dikembalikan' && l.return_condition && l.return_condition !== 'Baik' && (
            <ConditionBadge condition={l.return_condition} />
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="space-y-5">
      <PageHeader
        title="Peminjaman & Pengembalian"
        description="Dicatat admin atas nama peminjam. Approval atasan di luar sistem, nomornya dicatat di sini."
        actions={<ExportButton type="loans" filters={() => ({ status, search: search.trim() })} />}
      />

      <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <LoanForm onSaved={refresh} />

        <section className="space-y-3">
          <div className="flex items-baseline justify-between gap-3">
            <h2 className="font-semibold">Sedang dipinjam</h2>
            <span className="text-sm text-slate-500">{active.data?.meta.total ?? 0} aset</span>
          </div>
          <DataTable
            columns={activeColumns}
            rows={active.data?.data ?? []}
            rowKey={(l) => l.id}
            loading={active.isPending}
            fetching={active.isPlaceholderData}
            emptyText="Tidak ada aset yang sedang dipinjam."
            meta={active.data?.meta}
            unit="aset"
            onPageChange={activeList.setPage}
          />
          <p className="text-xs text-slate-500">
            Saat menerima kembali, admin memilih kondisi aset. Rusak Ringan bisa dipilih Tersedia atau Perbaikan, Rusak Berat
            bisa dipilih Perbaikan atau Tidak Layak Pakai.
          </p>
        </section>
      </div>

      <section className="space-y-3">
        <h2 className="font-semibold">Riwayat peminjaman</h2>
        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
          <div className="relative sm:w-80">
            <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
            <Input
              className="pl-9"
              value={search}
              onChange={(e) => historyList.setSearchText(e.target.value)}
              placeholder="Cari peminjam, unit, kode, atau nama aset"
              aria-label="Cari peminjaman"
            />
          </div>
          <Select clearable className="sm:w-auto" value={status} onChange={(e) => historyList.setFilter('status', e.target.value as LoanStatus | '')} aria-label="Status">
            <option value="">Semua status</option>
            <option>Dipinjam</option>
            <option>Dikembalikan</option>
            <option>Dibatalkan</option>
            <option>Hilang</option>
          </Select>
        </div>
        <DataTable
          columns={historyColumns}
          rows={history.data?.data ?? []}
          rowKey={(l) => l.id}
          loading={history.isPending}
          fetching={history.isPlaceholderData}
          emptyText="Belum ada peminjaman."
          meta={history.data?.meta}
          unit="peminjaman"
          onPageChange={historyList.setPage}
          sort={historyList.sort}
          onSortChange={historyList.setSort}
        />
      </section>

      {modal?.kind === 'return' && <ReturnLoanModal loan={modal.loan} onClose={closeModal} onDone={done} />}
      {modal?.kind === 'cancel' && <CancelLoanModal loan={modal.loan} onClose={closeModal} onDone={done} />}
    </div>
  )
}
