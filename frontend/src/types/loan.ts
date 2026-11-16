import type { AssetFile, Condition, Room, Status } from './asset'

/** Direncanakan = pemesanan (tanggal pinjam di masa depan), aset tetap Tersedia sampai diserahkan. */
export type LoanStatus = 'Direncanakan' | 'Dipinjam' | 'Dikembalikan' | 'Dibatalkan' | 'Hilang'

export interface Loan {
  id: number
  asset?: { id: number; code: string; name: string; status: Status; condition: Condition; room: Room | null }
  borrower_name: string
  borrower_unit: string
  purpose: string
  destination_room: Room | null
  destination_note: string | null
  borrow_date: string
  /** Tanggal barang benar-benar diserahkan (pemesanan yang sudah diserahkan). */
  handover_date: string | null
  due_date: string
  /** Direncanakan dan tanggal rencana sudah lewat. */
  is_unhanded: boolean
  is_overdue: boolean
  is_due_today: boolean
  condition_out: Condition
  approval_number: string | null
  approval_date: string | null
  status: LoanStatus
  return_date: string | null
  return_condition: Condition | null
  return_room: Room | null
  /** Foto kondisi saat Terima kembali (satu per peminjaman). */
  condition_photo?: AssetFile | null
  cancel_reason: string | null
  note: string | null
}
