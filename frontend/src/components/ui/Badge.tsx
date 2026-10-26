import type { Condition, Status } from '../../types/asset'

const tone = {
  green: 'bg-green-100 text-green-800',
  blue: 'bg-blue-100 text-blue-800',
  amber: 'bg-amber-100 text-amber-800',
  red: 'bg-red-100 text-red-800',
  slate: 'bg-slate-200 text-slate-700',
}

export function Badge({ children, color = 'slate' }: { children: string; color?: keyof typeof tone }) {
  return <span className={`inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ${tone[color]}`}>{children}</span>
}

const statusColor: Record<Status, keyof typeof tone> = {
  Tersedia: 'green',
  Dipinjam: 'blue',
  Perbaikan: 'amber',
  Hilang: 'red',
  'Tidak Layak Pakai': 'slate',
}
const conditionColor: Record<Condition, keyof typeof tone> = {
  Baik: 'green',
  'Rusak Ringan': 'amber',
  'Rusak Berat': 'red',
}

export const StatusBadge = ({ status }: { status: Status }) => <Badge color={statusColor[status]}>{status}</Badge>
export const ConditionBadge = ({ condition }: { condition: Condition }) => (
  <Badge color={conditionColor[condition]}>{condition}</Badge>
)
