import { formatShortDate } from '../../lib/format.ts'
import type { AssetListItem, Status } from '../../types/asset.ts'

/** Status aset yang boleh dipesan untuk tanggal mendatang (dicek ulang backend; saat Serahkan aset wajib Tersedia). */
export const BOOKABLE: readonly Status[] = ['Tersedia', 'Dipinjam', 'Perbaikan']

/**
 * Alasan aset tidak bisa dipilih di form peminjaman (T45, temuan UAT A5), atau null bila bisa dipilih.
 * `planned` = tanggal pinjam di masa depan (pemesanan): Dipinjam/Perbaikan tetap boleh dipesan.
 */
export function unavailableReason(
  asset: Pick<AssetListItem, 'status' | 'active_loan_due_date'>,
  planned: boolean,
  now: Date = new Date(),
): string | null {
  if (planned) return BOOKABLE.includes(asset.status) ? null : `${asset.status}, tidak bisa dipesan`
  switch (asset.status) {
    case 'Tersedia':
      return null
    case 'Dipinjam':
      return asset.active_loan_due_date ? `Sedang dipinjam sampai ${formatShortDate(asset.active_loan_due_date, now)}` : 'Sedang dipinjam'
    default:
      return `${asset.status}, tidak bisa dipinjam`
  }
}
