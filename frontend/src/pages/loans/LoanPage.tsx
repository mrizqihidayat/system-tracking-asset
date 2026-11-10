import { EllipsisVertical, Plus, Search } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { AssetCode, Badge, ConditionBadge } from '../../components/ui/Badge'
import { PageHeader } from '../../components/ui/Page'
import Button from '../../components/ui/Button'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu from '../../components/ui/DropdownMenu'
import { Input, Select } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { invalidate } from '../../lib/queries/invalidate'
import { listConfigs } from '../../lib/queries/list-configs'
import { useLoans } from '../../lib/queries/lists'
import { useErrorToast } from '../../lib/useErrorToast'
import { useListParams } from '../../lib/useListParams'
import type { Loan, LoanStatus } from '../../types/loan'
import CancelLoanModal from './CancelLoanModal'
import LoanFormModal from './LoanFormModal'
import ReturnLoanModal from './ReturnLoanModal'

type ModalState = { kind: 'return' | 'cancel'; loan: Loan } | { kind: 'borrow' } | null

const statusColor = { Dipinjam: 'blue', Dikembalikan: 'green', Dibatalkan: 'slate', Hilang: 'red' } as const

/** "Hari ini" / "N hari lagi" / "Terlambat N hari" dari rencana kembali. */
function Tempo({ loan }: { loan: Loan }) {
  if (loan.is_due_today) return <Badge color="amber">Hari ini</Badge>
  const days = Math.round((new Date(`${loan.due_date}T00:00:00`).getTime() - new Date().setHours(0, 0, 0, 0)) / 86_400_000)
  if (loan.is_overdue) return <Badge color="red">{`Terlambat ${Math.abs(days)} hari`}</Badge>
  return <span className="whitespace-nowrap text-slate-600">{days} hari lagi</span>
}

/** Kode + nama aset sebaris (baris tabel tetap pendek). */
const assetCell = (l: Loan) =>
  l.asset ? (
    <span className="flex min-w-0 items-center gap-2">
      <AssetCode code={l.asset.code} />
      <Link to={`/daftar-aset/${l.asset.id}`} className="tap min-w-0 font-medium hover:text-brand-700 hover:underline lg:truncate">{l.asset.name}</Link>
    </span>
  ) : '-'

/** "Nama · departemen" sebaris. */
const borrowerLine = (l: Loan) => (
  <span className="whitespace-nowrap">
    {l.borrower_name}
    <span className="text-slate-500"> · {l.borrower_unit}</span>
  </span>
)

const compactButton = 'min-h-10! lg:min-h-8! px-3! text-[13px]!'

export default function LoanPage() {
  const queryClient = useQueryClient()
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const returnLoanId = params.get('return_loan_id')
  const borrowAssetId = params.get('asset_id')
  const [modal, setModal] = useState<ModalState>(null)
  const activeList = useListParams(listConfigs.activeLoans)
  const historyList = useListParams(listConfigs.loanHistory)
  const active = useLoans(activeList.params)
  const history = useLoans(historyList.params)
  useErrorToast(active.error, history.error)

  const status = historyList.get('status')
  const search = historyList.searchText

  // ?return_loan_id=<id> (dari hasil scan): buka modal terima kembali. Tabel berhalaman, jadi datanya diambil sendiri.
  useEffect(() => {
    if (!returnLoanId) return
    let cancelled = false
    api
      .get<{ data: Loan }>(`/loans/${encodeURIComponent(returnLoanId)}`)
      .then((res) => {
        if (cancelled) return
        const loan = res.data.data
        if (loan.status === 'Dipinjam') setModal({ kind: 'return', loan })
        else toast.error(`Peminjaman ${loan.asset?.code ?? ''} sudah berstatus ${loan.status}, tidak ada yang perlu diterima kembali.`)
      })
      .catch((e) => {
        if (!cancelled) toast.error(errorMessage(e))
      })
      .finally(() => {
        if (!cancelled) setParams((p) => (p.delete('return_loan_id'), p), { replace: true })
      })
    return () => {
      cancelled = true
    }
  }, [returnLoanId, setParams, toast])

  // Stabil: Modal memfokuskan dirinya ulang setiap kali onClose berganti.
  const closeModal = useCallback(() => {
    setModal(null)
    setParams((p) => (p.has('asset_id') ? (p.delete('asset_id'), p) : p), { replace: true })
  }, [setParams])
  const done = useCallback(() => {
    closeModal()
    invalidate.loans(queryClient)
  }, [closeModal, queryClient])
  const openBorrow = () => setModal({ kind: 'borrow' })
  // ?asset_id=<id> (dari detail aset / hasil scan) membuka popup pinjam dengan aset terisi. Parameter tetap di URL
  // sampai popup ditutup atau disimpan, lalu dihapus tanpa menambah riwayat browser (closeModal).
  const borrowOpen = modal?.kind === 'borrow' || !!borrowAssetId

  const activeColumns: Column<Loan>[] = [
    { header: 'Aset', mobile: 'title', cell: assetCell },
    {
      header: 'Peminjam & departemen',
      cell: borrowerLine,
    },
    { header: 'Tgl. pinjam', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.borrow_date)}</span> },
    { header: 'Tempo', mobile: 'badge', cell: (l) => <Tempo loan={l} /> },
    {
      header: 'Aksi',
      className: 'text-right',
      cell: (l) => (
        <div className="flex items-center gap-1 lg:justify-end">
          <Button variant="secondary" className={`flex-1 lg:flex-none ${compactButton}`} onClick={() => setModal({ kind: 'return', loan: l })}>
            Terima kembali
          </Button>
          <DropdownMenu
            label="Aksi lainnya"
            trigger={<EllipsisVertical size={16} />}
            triggerClassName="min-h-10! px-3! lg:min-h-8! lg:px-2!"
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
          {borrowerLine(l)}
          <span className="block max-w-72 truncate text-xs text-slate-500" title={l.purpose}>{l.purpose}</span>
        </>
      ),
    },
    { header: 'Tgl. pinjam', sortKey: 'borrow_date', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.borrow_date)}</span> },
    { header: 'Rencana kembali', sortKey: 'due_date', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.due_date)}</span> },
    {
      header: 'Status',
      mobile: 'badge',
      cell: (l) => (
        <div className="flex flex-col items-end gap-1 lg:flex-row lg:flex-wrap lg:items-center">
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
        actions={
          <>
            <ExportButton type="loans" filters={() => ({ status, search: search.trim() })} />
            <Button onClick={openBorrow}>
              <Plus size={16} /> Catat peminjaman
            </Button>
          </>
        }
      />

      {returnLoanId && <p role="status" className="text-sm text-slate-500">Memuat peminjaman…</p>}

      <section className="space-y-2">
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
          dense
        />
      </section>

      <section className="space-y-2">
        <h2 className="font-semibold">Riwayat peminjaman</h2>
        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
          <div className="relative sm:w-80">
            <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
            <Input
              className="pl-9"
              value={search}
              onChange={(e) => historyList.setSearchText(e.target.value)}
              placeholder="Cari peminjam, departemen, kode, atau nama aset"
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
          dense
        />
      </section>

      {borrowOpen && <LoanFormModal key={borrowAssetId ?? 'baru'} initialAssetId={borrowAssetId} onClose={closeModal} onSaved={done} />}
      {modal?.kind === 'return' && <ReturnLoanModal loan={modal.loan} onClose={closeModal} onDone={done} />}
      {modal?.kind === 'cancel' && <CancelLoanModal loan={modal.loan} onClose={closeModal} onDone={done} />}
    </div>
  )
}
