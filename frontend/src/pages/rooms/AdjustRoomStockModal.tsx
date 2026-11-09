import { useState } from 'react'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import type { RoomConsumable } from '../../types/room'
import ActionModal from '../assets/modals/ActionModal'

const DECREASE_REASONS = ['Terpakai', 'Hilang', 'Rusak', 'Kedaluwarsa'] as const

interface Props {
  row: RoomConsumable
  roomName: string
  onClose: () => void
  onDone: () => void
}

/**
 * Catat perubahan satu barang di ruangan (SPEC §8, T30 R1). Jumlah sekarang hasil hitung staf dibandingkan catatan:
 * berkurang → alasan dipilih, bertambah → Koreksi dengan keterangan wajib, sama → hanya untuk baris Belum dicek
 * (Konfirmasi jumlah, tanpa transaksi). Jenis akhirnya ditentukan backend (mis. Saldo awal untuk catatan pertama).
 */
export default function AdjustRoomStockModal({ row, roomName, onClose, onDone }: Props) {
  const toast = useToast()
  const unit = row.consumable.unit
  const recorded = row.recorded_quantity
  const unchecked = !row.checked_at
  const [quantity, setQuantity] = useState(String(recorded))
  const [reason, setReason] = useState<string>('Terpakai')
  const [note, setNote] = useState('')

  const value = Number(quantity)
  const filled = quantity !== '' && Number.isInteger(value) && value >= 0
  const change = filled ? value - recorded : 0
  // Perkiraan catatan pertama (backend mencatat Saldo awal bila ruangan + barang belum punya transaksi).
  const opening = change > 0 && unchecked && recorded === 0 && !row.last_usage
  const needsNote = change > 0 && !opening
  const confirmOnly = filled && change === 0 && unchecked
  const canSubmit = filled && (change !== 0 || unchecked) && (!needsNote || note.trim() !== '')

  const submit = async () => {
    const res = await api.post<{ message: string }>(`/room-standards/${row.id}/adjust`, {
      quantity: value,
      reason: change < 0 ? reason : null,
      note: note.trim() || null,
    })
    toast.success(res.data.message)
    onDone()
  }

  const changeText =
    !filled || change === 0
      ? unchecked
        ? 'Sama dengan catatan. Simpan untuk mengonfirmasi jumlah.'
        : 'Sama dengan catatan.'
      : change < 0
        ? `Berkurang ${-change} ${unit}`
        : opening
          ? `Bertambah ${change} ${unit} · dicatat sebagai saldo awal`
          : `Bertambah ${change} ${unit} · dicatat sebagai Koreksi`

  return (
    <ActionModal
      title={`Catat perubahan • ${row.item_name}`}
      submitLabel={confirmOnly ? 'Konfirmasi jumlah' : 'Simpan'}
      submitDisabled={!canSubmit}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">
            {roomName} · Catatan sekarang: <b className="tabular-nums">{recorded}</b> {unit}
          </p>

          <FormField label={`Jumlah sekarang (${unit})`} required error={errors.quantity}>
            <Input
              type="number"
              inputMode="numeric"
              min={0}
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
              error={errors.quantity}
              autoFocus
            />
          </FormField>
          <p
            className={`-mt-2 text-xs font-medium ${change < 0 ? 'text-warn-700' : change > 0 ? 'text-info-700' : 'text-slate-500'}`}
            aria-live="polite"
          >
            {changeText}
          </p>

          {change < 0 && (
            <FormField label="Alasan" required error={errors.reason}>
              <Select value={reason} onChange={(e) => setReason(e.target.value)} error={errors.reason}>
                {DECREASE_REASONS.map((r) => <option key={r} value={r}>{r}</option>)}
              </Select>
            </FormField>
          )}

          <FormField label="Keterangan" required={needsNote} error={errors.note}>
            <Textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              error={errors.note}
              maxLength={1000}
            />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
