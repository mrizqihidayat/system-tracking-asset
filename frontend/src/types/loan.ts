import type { Condition, Room, Status } from './asset'

export type LoanStatus = 'Dipinjam' | 'Dikembalikan' | 'Dibatalkan' | 'Hilang'
export type DamageConfirmation = 'Menunggu Konfirmasi' | 'Dikonfirmasi'

export interface Loan {
  id: number
  asset?: { id: number; code: string; name: string; status: Status; condition: Condition; room: Room | null }
  borrower_name: string
  borrower_unit: string
  purpose: string
  destination_room: Room | null
  destination_note: string | null
  borrow_date: string
  due_date: string
  is_overdue: boolean
  is_due_today: boolean
  condition_out: Condition
  approval_number: string | null
  approval_date: string | null
  status: LoanStatus
  return_date: string | null
  return_condition: Condition | null
  return_room: Room | null
  damage_confirmation: DamageConfirmation | null
  damage_confirmed_at: string | null
  damage_confirmation_note: string | null
  damage_confirmation_document: string | null
  cancel_reason: string | null
  note: string | null
}
