import type { Condition, Status } from '../../types/asset'
import type { LoanStatus } from '../../types/loan'

const tone = {
  green: 'bg-ok-50 text-ok-700',
  blue: 'bg-info-50 text-info-700',
  amber: 'bg-warn-50 text-warn-700',
  red: 'bg-bad-50 text-bad-700',
  teal: 'bg-teal-50 text-teal-700',
  slate: 'bg-slate-100 text-slate-600',
}

export type BadgeColor = keyof typeof tone

/** `dot`: titik warna di depan teks (lencana status di tabel permintaan). */
export function Badge({ children, color = 'slate', dot }: { children: string; color?: BadgeColor; dot?: boolean }) {
  return (
    <span className={`inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ${tone[color]}`}>
      {dot && <span className="size-1.5 shrink-0 rounded-full bg-current" aria-hidden />}
      {children}
    </span>
  )
}

const statusColor: Record<Status, BadgeColor> = {
  Tersedia: 'green',
  Dipinjam: 'blue',
  Perbaikan: 'amber',
  Hilang: 'red',
  'Tidak Layak Pakai': 'slate',
}
const conditionColor: Record<Condition, BadgeColor> = {
  Baik: 'green',
  'Rusak Ringan': 'amber',
  'Rusak Berat': 'red',
}

export const StatusBadge = ({ status }: { status: Status }) => <Badge color={statusColor[status]}>{status}</Badge>
/** `label` opsional mengganti teks (mis. "3 Baik"), warna tetap mengikuti kondisi. */
export const ConditionBadge = ({ condition, label }: { condition: Condition; label?: string }) => (
  <Badge color={conditionColor[condition]}>{label ?? condition}</Badge>
)

const loanStatusColor: Record<LoanStatus, BadgeColor> = {
  Direncanakan: 'amber',
  Dipinjam: 'blue',
  Dikembalikan: 'green',
  Dibatalkan: 'slate',
  Hilang: 'red',
}

export const LoanStatusBadge = ({ status }: { status: LoanStatus }) => <Badge color={loanStatusColor[status]}>{status}</Badge>

const conditionDot: Record<Condition, string> = {
  Baik: 'bg-ok-700',
  'Rusak Ringan': 'bg-warn-700',
  'Rusak Berat': 'bg-bad-700',
}

/** Kondisi sebagai teks kecil bertitik warna (bukan lencana kedua; satu lencana per baris). */
export const ConditionDot = ({ condition }: { condition: Condition }) => (
  <span className="inline-flex items-center gap-1.5 whitespace-nowrap text-xs text-slate-600">
    <span className={`size-2 shrink-0 rounded-full ${conditionDot[condition]}`} aria-hidden />
    {condition}
  </span>
)

/** Kode aset sebagai label pelat mono; tidak pernah patah baris. */
export function AssetCode({ code, className = '' }: { code: string; className?: string }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded bg-brand-50 px-1.5 py-0.5 font-mono text-xs font-semibold text-brand-800 ${className}`}>
      {code}
    </span>
  )
}
