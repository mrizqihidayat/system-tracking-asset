import type { Condition, Status } from '../../types/asset'

const tone = {
  green: 'bg-ok-50 text-ok-700',
  blue: 'bg-info-50 text-info-700',
  amber: 'bg-warn-50 text-warn-700',
  red: 'bg-bad-50 text-bad-700',
  slate: 'bg-slate-100 text-slate-600',
}

export type BadgeColor = keyof typeof tone

export function Badge({ children, color = 'slate' }: { children: string; color?: BadgeColor }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ${tone[color]}`}>
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

/** Kode aset sebagai label pelat mono; tidak pernah patah baris. */
export function AssetCode({ code, className = '' }: { code: string; className?: string }) {
  return (
    <span className={`inline-block whitespace-nowrap rounded bg-brand-50 px-1.5 py-0.5 font-mono text-xs font-semibold text-brand-800 ${className}`}>
      {code}
    </span>
  )
}
