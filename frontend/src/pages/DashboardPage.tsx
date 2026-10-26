import { useEffect, useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Badge } from '../components/ui/Badge'
import DropdownMenu from '../components/ui/DropdownMenu'
import { useToast } from '../components/ui/toast-context'
import { api, errorMessage } from '../lib/api'
import { downloadReport, REPORT_LABELS, type ReportType } from '../lib/download'
import { formatDate } from '../lib/format'
import type { Loan } from '../types/loan'
import type { ConsumableItem, SupplyRequest } from '../types/supply'

interface Dashboard {
  cards: {
    total_assets: { total: number; owned_by_shd: number; from_other_units: number; not_feasible: number }
    active_loans: { total: number; due_today: number; overdue: number }
    needs_repair: { assets_in_repair: number; loans_pending_confirmation: number }
    incomplete_rooms: { incomplete: number; total_rooms: number; total_buildings: number }
    needs_service: { total: number; overdue: number; upcoming: number }
    low_stock: { total: number; menipis: number; habis: number }
  }
  panels: {
    loans_due: Loan[]
    room_readiness: {
      id: number
      name: string
      slug: string
      rooms_count: number
      incomplete_rooms_count: number
      rooms_without_standards: number
      incomplete_rooms: { id: number; name: string; slug: string; shortage_items: number; unchecked_items: number }[]
    }[]
    recent_requests: SupplyRequest[]
    assets_need_service: {
      id: number
      code: string
      name: string
      next_service_date: string
      days_until_service: number
      is_overdue: boolean
      room: { name: string; building: string } | null
    }[]
    low_stock: ConsumableItem[]
  }
}

const requestColor = { Diajukan: 'blue', Diterima: 'green', 'Belum direspons': 'amber', Dikembalikan: 'slate', Dibatalkan: 'red' } as const

const linkClass = 'text-sm text-brand-700 underline'

function StatCard({ label, value, tone = 'text-slate-900', note }: { label: string; value: number; tone?: string; note: ReactNode }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-5">
      <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
      <p className={`mt-2 text-3xl font-semibold ${tone}`}>{value}</p>
      <p className="mt-1 text-xs text-slate-500">{note}</p>
    </div>
  )
}

function Panel({ title, link, children }: { title: string; link?: { to: string; label: string }; children: ReactNode }) {
  return (
    <section className="rounded-lg border border-slate-200 bg-white p-5">
      <div className="mb-3 flex items-baseline justify-between gap-2">
        <h2 className="font-semibold">{title}</h2>
        {link && <Link to={link.to} className={linkClass}>{link.label}</Link>}
      </div>
      {children}
    </section>
  )
}

function Tempo({ loan }: { loan: Loan }) {
  if (loan.is_due_today) return <span className="font-semibold text-amber-700">Hari ini</span>
  const days = Math.round((new Date(`${loan.due_date}T00:00:00`).getTime() - new Date().setHours(0, 0, 0, 0)) / 86_400_000)
  if (loan.is_overdue) return <span className="font-semibold text-red-700">Terlambat {Math.abs(days)} hari</span>
  return <span>{days} hari lagi</span>
}

const Empty = ({ children }: { children: ReactNode }) => <p className="py-2 text-sm text-slate-500">{children}</p>

export default function DashboardPage() {
  const toast = useToast()
  const [data, setData] = useState<Dashboard | null>(null)

  useEffect(() => {
    api
      .get<{ data: Dashboard }>('/dashboard')
      .then((r) => setData(r.data.data))
      .catch((e) => toast.error(errorMessage(e)))
  }, [toast])

  const exportReport = (type: ReportType) => downloadReport(type).catch((e) => toast.error(errorMessage(e)))

  if (!data) return <p className="text-slate-500">Memuat…</p>
  const { cards, panels } = data

  return (
    <div className="max-w-7xl space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Dashboard</h1>
          <p className="mt-1 text-sm text-slate-600">Ringkasan kondisi aset dan kesiapan ruangan</p>
        </div>
        <div className="flex gap-2">
          <DropdownMenu
            label="Ekspor laporan"
            trigger="Ekspor laporan"
            items={(Object.keys(REPORT_LABELS) as ReportType[]).map((t) => ({ label: REPORT_LABELS[t], onClick: () => exportReport(t) }))}
          />
          <Link
            to="/daftar-aset/baru"
            className="inline-flex items-center rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
          >
            Tambah aset
          </Link>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Total aset tercatat"
          value={cards.total_assets.total}
          note={
            <>
              {cards.total_assets.owned_by_shd} milik SHD · {cards.total_assets.from_other_units} dari unit lain
              {cards.total_assets.not_feasible > 0 && ` · ${cards.total_assets.not_feasible} tidak layak pakai (tidak dihitung)`}
            </>
          }
        />
        <StatCard
          label="Sedang dipinjam"
          value={cards.active_loans.total}
          tone="text-brand-700"
          note={
            <>
              {cards.active_loans.due_today} jatuh tempo hari ini
              {cards.active_loans.overdue > 0 && <span className="text-red-700"> · {cards.active_loans.overdue} terlambat</span>}
            </>
          }
        />
        <StatCard
          label="Perlu perbaikan"
          value={cards.needs_repair.assets_in_repair}
          tone="text-amber-700"
          note={`${cards.needs_repair.loans_pending_confirmation} menunggu konfirmasi peminjam`}
        />
        <StatCard
          label="Ruangan belum lengkap"
          value={cards.incomplete_rooms.incomplete}
          tone="text-amber-700"
          note={`dari ${cards.incomplete_rooms.total_rooms} ruangan di ${cards.incomplete_rooms.total_buildings} gedung`}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm">
          <span className="text-slate-600">Perlu servis</span>
          <span>
            <b>{cards.needs_service.total}</b> aset
            {cards.needs_service.overdue > 0 && <span className="text-red-700"> · {cards.needs_service.overdue} lewat tanggal</span>}
          </span>
        </div>
        <Link to="/stok" className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-5 py-3 text-sm hover:border-brand-500">
          <span className="text-slate-600">Stok habis pakai menipis</span>
          <span>
            <b>{cards.low_stock.total}</b> barang
            {cards.low_stock.habis > 0 && <span className="text-red-700"> · {cards.low_stock.habis} habis</span>}
          </span>
        </Link>
      </div>

      <div className="grid items-start gap-5 lg:grid-cols-2">
        <Panel title="Peminjaman jatuh tempo" link={{ to: '/peminjaman', label: 'Lihat semua' }}>
          {panels.loans_due.length === 0 ? (
            <Empty>Tidak ada peminjaman yang jatuh tempo.</Empty>
          ) : (
            <table className="w-full text-left text-sm">
              <thead className="text-slate-500">
                <tr className="border-b border-slate-200">
                  <th className="py-2 pr-3 font-normal">Kode</th>
                  <th className="py-2 pr-3 font-normal">Aset</th>
                  <th className="py-2 pr-3 font-normal">Peminjam</th>
                  <th className="py-2 font-normal">Tempo</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {panels.loans_due.map((l) => (
                  <tr key={l.id}>
                    <td className="py-3 pr-3 font-mono text-xs">{l.asset?.code}</td>
                    <td className="py-3 pr-3">{l.asset?.name}</td>
                    <td className="py-3 pr-3">{l.borrower_name}</td>
                    <td className="py-3"><Tempo loan={l} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Panel>

        <div className="space-y-5">
          <Panel title="Kesiapan ruangan" link={{ to: '/kelengkapan-ruangan', label: 'Cek kelengkapan' }}>
            {panels.room_readiness.length === 0 && <Empty>Belum ada gedung.</Empty>}
            <div className="space-y-4">
              {panels.room_readiness.map((b) => {
                const others = b.rooms_count - b.incomplete_rooms.length
                return (
                  <div key={b.id}>
                    <p className="mb-1 text-xs uppercase tracking-wide text-slate-500">{b.name} · {b.rooms_count} ruangan</p>
                    <ul className="space-y-1.5 text-sm">
                      {b.incomplete_rooms.map((r) => (
                        <li key={r.id} className="flex items-center justify-between gap-2">
                          <Link to={`/kelengkapan-ruangan/${b.slug}/${r.slug}`} className="hover:underline">{r.name}</Link>
                          <Badge color="amber">{`Kurang ${r.shortage_items} item`}</Badge>
                        </li>
                      ))}
                      {others > 0 && (
                        <li className="flex items-center justify-between gap-2">
                          <span>{b.incomplete_rooms.length ? `${others} ruangan lainnya` : `Semua ${others} ruangan`}</span>
                          <Badge color="green">Lengkap</Badge>
                        </li>
                      )}
                    </ul>
                  </div>
                )
              })}
            </div>
          </Panel>

          <Panel title="Permintaan ke unit lain" link={{ to: '/permintaan', label: 'Kelola' }}>
            {panels.recent_requests.length === 0 ? (
              <Empty>Belum ada permintaan.</Empty>
            ) : (
              <ul className="space-y-2 text-sm">
                {panels.recent_requests.map((r) => (
                  <li key={r.id} className="flex items-center justify-between gap-2">
                    <span className="min-w-0 truncate">{r.item_name} · {r.provider_department?.name ?? '-'}</span>
                    <Badge color={requestColor[r.display_status]}>{r.display_status}</Badge>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        </div>
      </div>

      {(panels.assets_need_service.length > 0 || panels.low_stock.length > 0) && (
        <div className="grid items-start gap-5 lg:grid-cols-2">
          {panels.assets_need_service.length > 0 && (
            <Panel title="Aset perlu servis" link={{ to: '/daftar-aset', label: 'Daftar aset' }}>
              <ul className="space-y-2 text-sm">
                {panels.assets_need_service.map((a) => (
                  <li key={a.id} className="flex items-center justify-between gap-2">
                    <Link to={`/daftar-aset/${a.id}`} className="min-w-0 truncate hover:underline">
                      <span className="font-mono text-xs text-slate-500">{a.code}</span> {a.name}
                    </Link>
                    <span className={`shrink-0 text-xs ${a.is_overdue ? 'font-semibold text-red-700' : 'text-slate-600'}`}>
                      {a.is_overdue ? `Lewat ${Math.abs(a.days_until_service)} hari` : formatDate(a.next_service_date)}
                    </span>
                  </li>
                ))}
              </ul>
            </Panel>
          )}
          {panels.low_stock.length > 0 && (
            <Panel title="Stok menipis" link={{ to: '/stok', label: 'Kelola stok' }}>
              <ul className="space-y-2 text-sm">
                {panels.low_stock.map((c) => (
                  <li key={c.id} className="flex items-center justify-between gap-2">
                    <span className="min-w-0 truncate">
                      {c.name} <span className="text-slate-500">· {c.quantity} {c.unit} (min. {c.min_quantity})</span>
                    </span>
                    <Badge color={c.status === 'Habis' ? 'red' : 'amber'}>{c.status}</Badge>
                  </li>
                ))}
              </ul>
            </Panel>
          )}
        </div>
      )}
    </div>
  )
}
