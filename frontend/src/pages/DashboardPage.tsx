import { QrCode, Search } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AssetCode, Badge } from '../components/ui/Badge'
import { buttonClass } from '../components/ui/button-class'
import DropdownMenu from '../components/ui/DropdownMenu'
import { Muted, PageHeader, Panel } from '../components/ui/Page'
import { CardSkeleton, ListSkeleton } from '../components/ui/Skeleton'
import { useToast } from '../components/ui/toast-context'
import { errorMessage } from '../lib/api'
import { downloadReport, REPORT_LABELS, type ReportType } from '../lib/download'
import { formatDate } from '../lib/format'
import { useDashboardCards, useDashboardPanels, useDashboardReadiness } from '../lib/queries/dashboard'
import { useErrorToast } from '../lib/useErrorToast'
import type { Loan } from '../types/loan'

const requestColor = { Diajukan: 'blue', 'Diterima sebagian': 'amber', Diterima: 'green', 'Belum direspons': 'amber', Dikembalikan: 'slate', Dibatalkan: 'red' } as const

/** Satu baris keterangan kartu; `bad` = merah tebal (perlu tindakan). */
type NoteLine = { text: string; bad?: boolean }

/** Kartu ringkasan: label, angka besar, lalu keterangan kecil; label & keterangan turun ke baris berikut jika tidak cukup. */
function StatCard({ label, value, to, alert, notes }: { label: string; value: number; to: string; alert?: boolean; notes: NoteLine[] }) {
  return (
    <Link
      to={to}
      className="group rounded-xl border border-slate-200 bg-white px-4 py-3.5 transition-colors hover:border-brand-200"
    >
      <p className="text-xs font-semibold uppercase leading-snug tracking-wide text-slate-500 group-hover:text-brand-700">
        {label}
      </p>
      <p className={`mt-1 text-4xl font-bold leading-none tabular-nums ${alert ? 'text-bad-700' : ''}`}>{value}</p>
      <p className="mt-2 text-[11px] leading-snug text-slate-500">
        {notes.map((n, i) => (
          <span key={n.text}>
            {i > 0 && ' · '}
            <span className={n.bad ? 'font-semibold text-bad-700' : ''}>{n.text}</span>
          </span>
        ))}
      </p>
    </Link>
  )
}

function Tempo({ loan }: { loan: Loan }) {
  if (loan.is_due_today) return <Badge color="amber">Hari ini</Badge>
  const days = Math.round((new Date(`${loan.due_date}T00:00:00`).getTime() - new Date().setHours(0, 0, 0, 0)) / 86_400_000)
  if (loan.is_overdue) return <Badge color="red">{`Terlambat ${Math.abs(days)} hari`}</Badge>
  return <Badge color="blue">{`${days} hari lagi`}</Badge>
}

/** Bar kesiapan ruangan per gedung: lengkap (hijau) vs kurang (merah). */
function ReadinessBar({ complete, incomplete }: { complete: number; incomplete: number }) {
  const total = complete + incomplete
  if (total === 0) return null
  return (
    <div className="flex h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden>
      <span className="bg-bar-ok" style={{ width: `${(complete / total) * 100}%` }} />
      <span className="bg-bar-bad" style={{ width: `${(incomplete / total) * 100}%` }} />
    </div>
  )
}

export default function DashboardPage() {
  const toast = useToast()
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const { data: cards, error: cardsError } = useDashboardCards()
  const { data: readiness, error: readinessError } = useDashboardReadiness()
  const { data: panels, error: panelsError } = useDashboardPanels()
  useErrorToast(cardsError, readinessError, panelsError)

  const exportReport = (type: ReportType) => downloadReport(type).catch((e) => toast.error(errorMessage(e)))

  const submitSearch = (e: FormEvent) => {
    e.preventDefault()
    const q = search.trim()
    navigate(q ? `/daftar-aset?search=${encodeURIComponent(q)}` : '/daftar-aset')
  }

  const header = (
    <PageHeader
      title="Dashboard"
      actions={
        <>
          <form onSubmit={submitSearch} className="relative w-full sm:w-64" role="search">
            <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
            <input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Cari kode atau nama aset"
              aria-label="Cari aset"
              className="min-h-10 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm placeholder:text-slate-500 hover:border-slate-300 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-100"
            />
          </form>
          <DropdownMenu
            label="Ekspor laporan"
            trigger="Ekspor laporan"
            items={(Object.keys(REPORT_LABELS) as ReportType[]).map((t) => ({ label: REPORT_LABELS[t], onClick: () => exportReport(t) }))}
          />
          <Link to="/scan" className={buttonClass('primary')}>
            <QrCode size={16} /> Scan QR
          </Link>
        </>
      }
    />
  )

  return (
    <div className="space-y-5">
      {header}

      <div className="grid grid-cols-2 gap-3 md:grid-cols-3 min-[85rem]:grid-cols-6" aria-busy={!cards || undefined}>
        {!cards ? Array.from({ length: 6 }, (_, i) => <CardSkeleton key={i} />) : <>
        <StatCard
          label="Total aset"
          value={cards.total_assets.total}
          to="/daftar-aset"
          notes={[
            { text: `${cards.total_assets.owned_by_shd} milik SHD` },
            { text: `${cards.total_assets.from_other_units} dari departemen lain` },
          ]}
        />
        <StatCard
          label="Sedang dipinjam"
          value={cards.active_loans.total}
          to="/peminjaman"
          notes={[
            { text: `${cards.active_loans.due_today} jatuh tempo hari ini` },
            { text: `${cards.active_loans.overdue} terlambat`, bad: cards.active_loans.overdue > 0 },
          ]}
        />
        <StatCard
          label="Perlu perbaikan"
          value={cards.needs_repair.assets_in_repair}
          to="/daftar-aset?status=Perbaikan"
          notes={[
            { text: `${cards.needs_repair.light_damage} rusak ringan` },
            { text: `${cards.needs_repair.heavy_damage} rusak berat` },
          ]}
        />
        <StatCard
          label="Ruangan belum lengkap"
          value={cards.incomplete_rooms.incomplete}
          to="/kelengkapan-ruangan"
          alert={cards.incomplete_rooms.incomplete > 0}
          notes={[
            { text: `dari ${cards.incomplete_rooms.total_rooms} ruangan` },
            { text: `di ${cards.incomplete_rooms.total_buildings} gedung` },
          ]}
        />
        <StatCard
          label="Perlu servis"
          value={cards.needs_service.total}
          to="/daftar-aset"
          notes={[
            { text: `${cards.needs_service.overdue} lewat tanggal`, bad: cards.needs_service.overdue > 0 },
            { text: `${cards.needs_service.upcoming} dalam 14 hari` },
          ]}
        />
        <StatCard
          label="Stok menipis"
          value={cards.low_stock.total}
          to="/stok"
          alert={cards.low_stock.habis > 0}
          notes={[
            { text: `${cards.low_stock.menipis} menipis` },
            { text: `${cards.low_stock.habis} habis`, bad: cards.low_stock.habis > 0 },
          ]}
        />
        </>}
      </div>

      <div className="grid grid-cols-[minmax(0,1fr)] items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div className="min-w-0 space-y-5">
        <Panel title="Peminjaman jatuh tempo & terlambat" link={{ to: '/peminjaman', label: 'Lihat semua' }} flush>
          {!panels ? (
            <ListSkeleton />
          ) : panels.loans_due.length === 0 ? (
            <Muted className="px-5 pb-5">Tidak ada peminjaman yang jatuh tempo.</Muted>
          ) : (
            <>
              <table className="hidden w-full text-left text-sm md:table">
                <thead className="border-y border-slate-100 bg-slate-50 text-xs text-slate-500">
                  <tr>
                    <th className="px-5 py-2.5 font-semibold">Kode</th>
                    <th className="px-3 py-2.5 font-semibold">Aset</th>
                    <th className="px-3 py-2.5 font-semibold">Peminjam</th>
                    <th className="whitespace-nowrap px-3 py-2.5 font-semibold">Rencana kembali</th>
                    <th className="px-5 py-2.5 font-semibold">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {panels.loans_due.map((l) => (
                    <tr key={l.id}>
                      <td className="px-5 py-3">{l.asset && <AssetCode code={l.asset.code} />}</td>
                      <td className="px-3 py-3">{l.asset?.name}</td>
                      <td className="px-3 py-3">
                        {l.borrower_name}
                        <span className="block text-xs text-slate-500">{l.borrower_unit}</span>
                      </td>
                      <td className="whitespace-nowrap px-3 py-3">{formatDate(l.due_date)}</td>
                      <td className="px-5 py-3"><Tempo loan={l} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <ul className="divide-y divide-slate-100 border-t border-slate-100 md:hidden">
                {panels.loans_due.map((l) => (
                  <li key={l.id} className="space-y-1 px-5 py-3 text-sm">
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        {l.asset && <AssetCode code={l.asset.code} />}
                        <p className="mt-1 font-medium">{l.asset?.name}</p>
                      </div>
                      <Tempo loan={l} />
                    </div>
                    <p className="text-slate-500">
                      {l.borrower_name} · {l.borrower_unit} · kembali {formatDate(l.due_date)}
                    </p>
                  </li>
                ))}
              </ul>
            </>
          )}
        </Panel>

        <Panel title="Permintaan ke departemen lain" link={{ to: '/permintaan', label: 'Kelola' }} flush>
          {!panels ? (
            <ListSkeleton />
          ) : panels.recent_requests.length === 0 ? (
            <Muted className="px-5 pb-5">Belum ada permintaan.</Muted>
          ) : (
            <ul className="divide-y divide-slate-100 border-t border-slate-100">
              {panels.recent_requests.map((r) => (
                <li key={r.id} className="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                  <span className="min-w-0">
                    <span className="block truncate font-medium">{r.item_name}</span>
                    <span className="block truncate text-xs text-slate-500">{r.provider_department?.name ?? '-'}</span>
                  </span>
                  <Badge color={requestColor[r.display_status]}>{r.display_status}</Badge>
                </li>
              ))}
            </ul>
          )}
        </Panel>
        </div>

        <div className="min-w-0 space-y-5">
          <Panel title="Kelengkapan ruangan" link={{ to: '/kelengkapan-ruangan', label: 'Lihat ruangan' }} flush>
            {!readiness && <ListSkeleton />}
            {readiness?.length === 0 && <Muted className="px-5 pb-5">Belum ada gedung.</Muted>}
            <ul className="divide-y divide-slate-100 border-t border-slate-100">
              {readiness?.map((b) => {
                const incomplete = b.incomplete_rooms_count
                const complete = b.rooms_count - incomplete - b.rooms_without_standards
                return (
                  <li key={b.id} className="space-y-2 px-5 py-3.5">
                    <div className="flex items-center justify-between gap-3">
                      <span className="font-semibold">{b.name}</span>
                      {incomplete > 0 ? (
                        <Badge color="red">{`Kurang ${incomplete} ruangan`}</Badge>
                      ) : complete > 0 ? (
                        <Badge color="green">Lengkap</Badge>
                      ) : (
                        <Badge color="amber">Belum ada standar</Badge>
                      )}
                    </div>
                    <ReadinessBar complete={complete} incomplete={incomplete} />
                    <p className="text-sm text-slate-500">
                      {complete} lengkap · {incomplete} kurang · {b.rooms_count} ruangan
                    </p>
                    {incomplete > 0 && (
                      <ul className="space-y-1 pt-1 text-sm">
                        {b.incomplete_rooms.map((r) => (
                          <li key={r.id} className="flex items-center justify-between gap-3">
                            <Link to={`/kelengkapan-ruangan/${b.slug}/${r.slug}`} className="min-w-0 truncate text-brand-700 hover:underline">
                              {r.name}
                            </Link>
                            <span className="shrink-0 whitespace-nowrap text-xs font-semibold text-bad-700">
                              Kurang {r.shortage_items} item
                              {r.unchecked_items > 0 && <span className="font-normal text-slate-500"> · {r.unchecked_items} belum dicek</span>}
                            </span>
                          </li>
                        ))}
                      </ul>
                    )}
                  </li>
                )
              })}
            </ul>
          </Panel>

          <Panel title="Stok menipis" link={{ to: '/stok', label: 'Lihat stok' }} flush>
            {!panels ? (
              <ListSkeleton />
            ) : panels.low_stock.length === 0 ? (
              <Muted className="px-5 pb-5">Semua stok aman.</Muted>
            ) : (
              <ul className="divide-y divide-slate-100 border-t border-slate-100">
                {panels.low_stock.map((c) => (
                  <li key={c.id} className="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                    <span className="min-w-0 truncate">{c.name}</span>
                    <span className="flex shrink-0 items-center gap-3">
                      <span className="whitespace-nowrap tabular-nums text-slate-500">
                        {c.quantity} {c.unit}
                      </span>
                      <Badge color={c.status === 'Habis' ? 'red' : 'amber'}>{c.status}</Badge>
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          <Panel title="Aset perlu servis" link={{ to: '/daftar-aset', label: 'Daftar aset' }} flush>
            {!panels ? (
              <ListSkeleton />
            ) : panels.assets_need_service.length === 0 ? (
              <Muted className="px-5 pb-5">Tidak ada aset yang perlu servis dalam 14 hari.</Muted>
            ) : (
              <ul className="divide-y divide-slate-100 border-t border-slate-100">
                {panels.assets_need_service.map((a) => (
                  <li key={a.id} className="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                    <Link to={`/daftar-aset/${a.id}`} className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 hover:text-brand-700">
                      <AssetCode code={a.code} />
                      <span>{a.name}</span>
                    </Link>
                    {a.is_overdue ? (
                      <Badge color="red">{`Lewat ${Math.abs(a.days_until_service)} hari`}</Badge>
                    ) : (
                      <span className="shrink-0 whitespace-nowrap text-slate-500">{formatDate(a.next_service_date)}</span>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </div>
      </div>
    </div>
  )
}
