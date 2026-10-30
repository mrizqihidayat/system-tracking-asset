import { EllipsisVertical, Search } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import ExportButton from '../../components/ExportButton'
import { AssetCode, Badge } from '../../components/ui/Badge'
import { PageHeader } from '../../components/ui/Page'
import Button from '../../components/ui/Button'
import DataTable, { type Column } from '../../components/ui/DataTable'
import DropdownMenu from '../../components/ui/DropdownMenu'
import { Input, Select } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { formatDate } from '../../lib/format'
import type { Paginated } from '../../types/api'
import type { Loan, LoanStatus } from '../../types/loan'
import CancelLoanModal from './CancelLoanModal'
import ConfirmDamageModal from './ConfirmDamageModal'
import LoanForm from './LoanForm'
import ReturnLoanModal from './ReturnLoanModal'

type ModalState = { kind: 'return' | 'cancel' | 'confirm'; loan: Loan } | null

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
  const toast = useToast()
  const [active, setActive] = useState<Paginated<Loan> | null>(null)
  const [history, setHistory] = useState<Paginated<Loan> | null>(null)
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState<ModalState>(null)
  const [status, setStatus] = useState('')
  const [pending, setPending] = useState(false)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const load = useCallback(() => {
    const params: Record<string, unknown> = { page, per_page: 10 }
    if (status) params.status = status
    if (pending) params.pending_confirmation = 1
    if (search.trim()) params.search = search.trim()
    return Promise.all([
      api.get<Paginated<Loan>>('/loans', { params: { status: 'Dipinjam', per_page: 100 } }),
      api.get<Paginated<Loan>>('/loans', { params }),
    ])
      .then(([a, h]) => {
        setActive(a.data)
        setHistory(h.data)
      })
      .catch((e) => toast.error(errorMessage(e)))
      .finally(() => setLoading(false))
  }, [page, status, pending, search, toast])

  useEffect(() => {
    const t = setTimeout(load, 250) // jeda singkat agar ketikan pencarian tidak memicu request berlebih
    return () => clearTimeout(t)
  }, [load])

  const closeModal = () => setModal(null)
  const done = () => {
    closeModal()
    load()
  }
  const filter = <T,>(setter: (v: T) => void) => (v: T) => {
    setter(v)
    setPage(1)
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
    { header: 'Tgl. pinjam', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.borrow_date)}</span> },
    { header: 'Rencana kembali', cell: (l) => <span className="whitespace-nowrap">{formatDate(l.due_date)}</span> },
    {
      header: 'Status',
      mobile: 'badge',
      cell: (l) => (
        <div className="flex flex-col items-end gap-1 md:flex-row md:flex-wrap md:items-center">
          <Badge color={statusColor[l.status]}>{l.status}</Badge>
          {l.damage_confirmation && (
            <Badge color={l.damage_confirmation === 'Dikonfirmasi' ? 'green' : 'amber'}>
              {l.damage_confirmation === 'Dikonfirmasi' ? 'Kerusakan dikonfirmasi' : 'Menunggu konfirmasi'}
            </Badge>
          )}
        </div>
      ),
    },
    {
      header: '',
      className: 'text-right',
      cell: (l) =>
        l.damage_confirmation === 'Menunggu Konfirmasi' ? (
          <Button variant="secondary" className="w-full md:w-auto" onClick={() => setModal({ kind: 'confirm', loan: l })}>
            Konfirmasi kerusakan
          </Button>
        ) : null,
    },
  ]

  return (
    <div className="space-y-5">
      <PageHeader
        title="Peminjaman & Pengembalian"
        description="Dicatat admin atas nama peminjam. Approval atasan di luar sistem, nomornya dicatat di sini."
        actions={<ExportButton type="loans" filters={() => ({ status, pending_confirmation: pending ? 1 : '', search: search.trim() })} />}
      />

      <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <LoanForm onSaved={load} />

        <section className="space-y-3">
          <div className="flex items-baseline justify-between gap-3">
            <h2 className="font-semibold">Sedang dipinjam</h2>
            <span className="text-sm text-slate-500">{active?.meta.total ?? 0} aset</span>
          </div>
          <DataTable columns={activeColumns} rows={active?.data ?? []} rowKey={(l) => l.id} loading={loading} emptyText="Tidak ada aset yang sedang dipinjam." />
          <p className="text-xs text-slate-500">
            Saat menerima kembali, admin memilih kondisi aset. Kondisi selain Baik membuat status aset berubah menjadi Perbaikan
            (Rusak Ringan bisa dipilih Tersedia atau Perbaikan).
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
              onChange={(e) => filter(setSearch)(e.target.value)}
              placeholder="Cari peminjam, unit, kode, atau nama aset"
              aria-label="Cari peminjaman"
            />
          </div>
          <Select className="sm:w-auto" value={status} onChange={(e) => filter(setStatus)(e.target.value as LoanStatus | '')} aria-label="Status">
            <option value="">Semua status</option>
            <option>Dipinjam</option>
            <option>Dikembalikan</option>
            <option>Dibatalkan</option>
            <option>Hilang</option>
          </Select>
          <label className="flex min-h-10 items-center gap-2 text-sm">
            <input type="checkbox" checked={pending} onChange={(e) => filter(setPending)(e.target.checked)} />
            Menunggu konfirmasi
          </label>
        </div>
        <DataTable
          columns={historyColumns}
          rows={history?.data ?? []}
          rowKey={(l) => l.id}
          loading={loading}
          emptyText="Belum ada peminjaman."
          meta={history?.meta}
          unit="peminjaman"
          onPageChange={setPage}
        />
      </section>

      {modal?.kind === 'return' && <ReturnLoanModal loan={modal.loan} onClose={closeModal} onDone={done} />}
      {modal?.kind === 'cancel' && <CancelLoanModal loan={modal.loan} onClose={closeModal} onDone={done} />}
      {modal?.kind === 'confirm' && <ConfirmDamageModal loan={modal.loan} onClose={closeModal} onDone={done} />}
    </div>
  )
}
