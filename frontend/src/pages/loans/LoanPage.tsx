import { EllipsisVertical, Plus, Search } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { AssetCode, Badge, ConditionDot, LoanStatusBadge } from '../../components/ui/Badge'
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
import HandOverModal from './HandOverModal'
import LoanFormModal from './LoanFormModal'
import ReturnLoanModal from './ReturnLoanModal'

type ModalState = { kind: 'return' | 'cancel' | 'handover'; loan: Loan } | { kind: 'borrow' } | null

/** Jadwal pemesanan: "Belum diserahkan" (tanggal lewat) / "Hari ini" / "N hari lagi" dari tanggal rencana. */
function Schedule({ loan }: { loan: Loan }) {
  if (loan.is_unhanded) return <Badge color="red">Belum diserahkan</Badge>
  const days = Math.round((new Date(`${loan.borrow_date}T00:00:00`).getTime() - new Date().setHours(0, 0, 0, 0)) / 86_400_000)
  if (days <= 0) return <Badge color="amber">Hari ini</Badge>
  return <span className="whitespace-nowrap text-slate-600">{days} hari lagi</span>
}

/** "Hari ini" / "N hari lagi" / "Terlambat N hari" dari rencana kembali. */
function Tempo({ loan }: { loan: Loan }) {
  if (loan.is_due_today) return <Badge color="amber">Hari ini</Badge>
  const days = Math.round((new Date(`${loan.due_date}T00:00:00`).getTime() - new Date().setHours(0, 0, 0, 0)) / 86_400_000)
  if (loan.is_overdue) return <Badge color="red">{`Terlambat ${Math.abs(days)} hari`}</Badge>
  return <span className="whitespace-nowrap text-slate-600">{days} hari lagi</span>
}

/** Chip kode aset sebagai tautan ke detail aset (garis bawah saat hover). */
const assetCodeLink = (asset: NonNullable<Loan['asset']>) => (
  <Link to={`/daftar-aset/${asset.id}`} className="tap shrink-0 hover:underline">
    <AssetCode code={asset.code} />
  </Link>
)

/** Kode + nama aset sebaris (baris tabel tetap pendek); keduanya tautan ke detail aset. */
const assetCell = (l: Loan) =>
  l.asset ? (
    <span className="flex min-w-0 items-center gap-2">
      {assetCodeLink(l.asset)}
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

/** Kondisi kembali (bukan Baik) sebagai teks bertitik, bukan lencana kedua. */
const returnedNotGood = (l: Loan) => (l.status === 'Dikembalikan' && l.return_condition && l.return_condition !== 'Baik' ? l.return_condition : null)

/**
 * Kartu HP (< sm): baris 1 chip kode + satu lencana, baris berikutnya nama aset (boleh turun baris),
 * peminjam, keterangan, lalu tombol/tautan di bawah.
 */
function LoanPhoneCard({ loan, badge, children, actions }: { loan: Loan; badge: ReactNode; children: ReactNode; actions?: ReactNode }) {
  return (
    <div className="space-y-3">
      <div className="space-y-1">
        <div className="flex items-center justify-between gap-3">
          {loan.asset ? assetCodeLink(loan.asset) : <span>-</span>}
          {badge}
        </div>
        {loan.asset && (
          <Link to={`/daftar-aset/${loan.asset.id}`} className="tap block font-medium hover:text-brand-700 hover:underline">{loan.asset.name}</Link>
        )}
        <p>
          {loan.borrower_name}
          <span className="text-slate-500"> · {loan.borrower_unit}</span>
        </p>
        {children}
      </div>
      {actions}
    </div>
  )
}

const compactButton = 'min-h-10! lg:min-h-8! px-3! text-[13px]!'

export default function LoanPage() {
  const queryClient = useQueryClient()
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const returnLoanId = params.get('return_loan_id')
  const borrowAssetId = params.get('asset_id')
  const [modal, setModal] = useState<ModalState>(null)
  const plannedList = useListParams(listConfigs.plannedLoans)
  const activeList = useListParams(listConfigs.activeLoans)
  const historyList = useListParams(listConfigs.loanHistory)
  const planned = useLoans(plannedList.params)
  const active = useLoans(activeList.params)
  const history = useLoans(historyList.params)
  useErrorToast(planned.error, active.error, history.error)

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

  // Pemesanan: Serahkan saat barang benar-benar diserahkan; status aset tetap sampai itu.
  const plannedColumns: Column<Loan>[] = [
    { header: 'Aset', mobile: 'title', cell: assetCell },
    { header: 'Peminjam', cell: borrowerLine },
    { header: 'Tgl. rencana', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.borrow_date)}</span> },
    { header: 'Rencana kembali', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.due_date)}</span> },
    { header: 'Jadwal', mobile: 'badge', cell: (l) => <Schedule loan={l} /> },
    {
      header: 'Aksi',
      className: 'text-right',
      cell: (l) => (
        <div className="flex items-center gap-1 lg:justify-end">
          <Button variant="secondary" className={`flex-1 lg:flex-none ${compactButton}`} onClick={() => setModal({ kind: 'handover', loan: l })}>
            Serahkan
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

  const activeColumns: Column<Loan>[] = [
    { header: 'Aset', mobile: 'title', cell: assetCell },
    {
      header: 'Peminjam',
      cell: borrowerLine,
    },
    // Pemesanan yang sudah diserahkan: tanggal penyerahan sebenarnya.
    { header: 'Tgl. pinjam', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.handover_date ?? l.borrow_date)}</span> },
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
        // Satu lencana status; kondisi kembali & "Belum diserahkan" sebagai teks kecil di bawahnya.
        <div className="flex flex-col items-end gap-1 lg:items-start">
          <LoanStatusBadge status={l.status} />
          {l.is_unhanded && <span className="whitespace-nowrap text-xs font-medium text-bad-700">Belum diserahkan</span>}
          {returnedNotGood(l) && <ConditionDot condition={returnedNotGood(l)!} />}
        </div>
      ),
    },
    // Gaya sama dengan kolom Detail di Daftar Aset.
    {
      header: 'Aksi',
      className: 'text-right',
      mobile: 'hidden',
      cell: (l) =>
        l.asset ? (
          <Link to={`/daftar-aset/${l.asset.id}`} className="font-semibold text-brand-700 hover:underline">
            Detail
          </Link>
        ) : null,
    },
  ]

  // Kartu HP (< sm). Tombol utama lebar penuh + menu ⋮ (40 px).
  const menuTrigger = 'px-3!'
  const cancelMenu = (l: Loan) => (
    <DropdownMenu
      label="Aksi lainnya"
      trigger={<EllipsisVertical size={16} />}
      triggerClassName={menuTrigger}
      items={[{ label: 'Batalkan', danger: true, onClick: () => setModal({ kind: 'cancel', loan: l }) }]}
    />
  )
  const plannedPhoneCard = (l: Loan) => (
    <LoanPhoneCard
      loan={l}
      badge={<Schedule loan={l} />}
      actions={
        <div className="flex gap-2">
          <Button variant="secondary" className="flex-1" onClick={() => setModal({ kind: 'handover', loan: l })}>Serahkan</Button>
          {cancelMenu(l)}
        </div>
      }
    >
      <p className="text-xs text-slate-500">Rencana {formatDate(l.borrow_date)} · Kembali {formatDate(l.due_date)}</p>
    </LoanPhoneCard>
  )
  const activePhoneCard = (l: Loan) => (
    <LoanPhoneCard
      loan={l}
      badge={<Tempo loan={l} />}
      actions={
        <div className="flex gap-2">
          <Button variant="secondary" className="flex-1" onClick={() => setModal({ kind: 'return', loan: l })}>Terima kembali</Button>
          {cancelMenu(l)}
        </div>
      }
    >
      <p className="text-xs text-slate-500">Pinjam {formatDate(l.handover_date ?? l.borrow_date)} · Kembali {formatDate(l.due_date)}</p>
    </LoanPhoneCard>
  )
  const historyPhoneCard = (l: Loan) => {
    const condition = returnedNotGood(l)
    return (
      <LoanPhoneCard loan={l} badge={<LoanStatusBadge status={l.status} />}>
        <p className="text-slate-500">{l.purpose}</p>
        <p className="tabular-nums text-slate-600">{formatDate(l.borrow_date)} → {formatDate(l.due_date)}</p>
        {l.status === 'Dikembalikan' && (
          <p className="flex flex-wrap items-center gap-x-1.5 text-slate-600">
            Kembali {formatDate(l.return_date)}
            {condition && <><span aria-hidden>·</span><ConditionDot condition={condition} /></>}
          </p>
        )}
        {l.is_unhanded && <p className="text-xs font-medium text-bad-700">Belum diserahkan</p>}
        {/* Baris sendiri di kanan bawah agar baris "Kembali · kondisi" tidak terpotong. */}
        {l.asset && (
          <div className="flex justify-end">
            <Link to={`/daftar-aset/${l.asset.id}`} className="tap text-xs font-semibold text-brand-700 hover:underline">Lihat aset ›</Link>
          </div>
        )}
      </LoanPhoneCard>
    )
  }

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

      {/* Disembunyikan bila tidak ada pemesanan. */}
      {(planned.isPending || (planned.data?.meta.total ?? 0) > 0) && (
        <section className="space-y-2">
          <div className="flex items-baseline justify-between gap-3">
            <h2 className="font-semibold">Direncanakan</h2>
            <span className="text-sm text-slate-500">{planned.data?.meta.total ?? 0} pemesanan</span>
          </div>
          <DataTable
            phoneCard={plannedPhoneCard}
            columns={plannedColumns}
            rows={planned.data?.data ?? []}
            rowKey={(l) => l.id}
            loading={planned.isPending}
            fetching={planned.isPlaceholderData}
            emptyText="Tidak ada pemesanan."
            meta={planned.data?.meta}
            unit="pemesanan"
            onPageChange={plannedList.setPage}
            dense
          />
        </section>
      )}

      <section className="space-y-2">
        <div className="flex items-baseline justify-between gap-3">
          <h2 className="font-semibold">Sedang dipinjam</h2>
          <span className="text-sm text-slate-500">{active.data?.meta.total ?? 0} aset</span>
        </div>
        <DataTable
          phoneCard={activePhoneCard}
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
            <option>Direncanakan</option>
            <option>Dipinjam</option>
            <option>Dikembalikan</option>
            <option>Dibatalkan</option>
            <option>Hilang</option>
          </Select>
        </div>
        <DataTable
          phoneCard={historyPhoneCard}
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
      {modal?.kind === 'handover' && <HandOverModal loan={modal.loan} onClose={closeModal} onDone={done} />}
    </div>
  )
}
