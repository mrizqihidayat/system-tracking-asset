import { useState } from 'react'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import type { ConsumableItem, StockTransactionType } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'
import RoomSelect from '../loans/RoomSelect'

const TYPES: { value: StockTransactionType; hint?: string }[] = [
  { value: 'Barang Masuk', hint: 'Stok bertambah.' },
  { value: 'Pemakaian',  hint: 'Stok berkurang.' },
  { value: 'Koreksi', hint: 'Isi positif untuk menambah, negatif untuk mengurangi. Keterangan wajib.' },
  { value: 'Penghapusan', hint: 'Stok berkurang karena tidak bisa dipakai lagi.' },
]

const REASONS = [
  { value: 'rusak', label: 'Rusak' },
  { value: 'kedaluwarsa', label: 'Kedaluwarsa' },
  { value: 'lainnya', label: 'Lainnya' },
]

interface Props {
  item: ConsumableItem
  onClose: () => void
  onDone: () => void
}

/** Form transaksi stok (SPEC §10): satu-satunya cara mengubah jumlah barang dari halaman Stok. */
export default function StockTransactionModal({ item, onClose, onDone }: Props) {
  const toast = useToast()
  const [type, setType] = useState<StockTransactionType>('Barang Masuk')
  const [quantity, setQuantity] = useState('')
  const [reason, setReason] = useState('')
  const [note, setNote] = useState('')
  const [buildingId, setBuildingId] = useState<number | null>(null)
  const [roomId, setRoomId] = useState<number | null>(null)

  const hint = TYPES.find((t) => t.value === type)?.hint
  const value = Number(quantity)
  const delta = type === 'Pemakaian' || type === 'Penghapusan' ? -value : value
  const noteRequired = type === 'Koreksi' || reason === 'lainnya'
  const after = item.quantity + delta
  const valid = quantity !== '' && Number.isInteger(value) && value !== 0 && (type === 'Koreksi' || value > 0)

  const submit = async () => {
    await api.post(`/consumables/${item.id}/transactions`, {
      type,
      quantity: value,
      reason: type === 'Penghapusan' ? reason : null,
      note: note.trim() || null,
      room_id: type === 'Pemakaian' ? roomId : null,
    })
    toast.success(`Transaksi ${type} ${item.name} dicatat. Stok menjadi ${after} ${item.unit}.`)
    onDone()
  }

  return (
    <ActionModal
      title={`Catat transaksi • ${item.name}`}
      submitLabel="Simpan"
      submitDisabled={!valid || (type === 'Penghapusan' && !reason) || (noteRequired && !note.trim())}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">Stok sekarang: <b className="tabular-nums">{item.quantity}</b> {item.unit}</p>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Jenis" required error={errors.type}>
              <Select value={type} onChange={(e) => setType(e.target.value as StockTransactionType)} error={errors.type}>
                {TYPES.map((t) => <option key={t.value}>{t.value}</option>)}
              </Select>
            </FormField>
            <FormField label={`Jumlah (${item.unit})`} required error={errors.quantity}>
              <Input
                type="number"
                min={type === 'Koreksi' ? undefined : 1}
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
                error={errors.quantity}
                autoFocus
              />
            </FormField>
          </div>
          {(hint || valid) && (
            <p className="-mt-1 text-xs text-slate-500">
              {hint}
              {valid && <> Stok setelah transaksi: <b className={after < 0 ? 'text-bad-700' : ''}>{after} {item.unit}</b>.</>}
            </p>
          )}
          {type === 'Penghapusan' && (
            <FormField label="Alasan" required error={errors.reason}>
              <Select value={reason} onChange={(e) => setReason(e.target.value)} error={errors.reason}>
                <option value="">Pilih alasan</option>
                {REASONS.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
              </Select>
            </FormField>
          )}
          {type === 'Pemakaian' && (
            <>
              <RoomSelect
                buildingLabel="Gedung tujuan (opsional)"
                roomLabel="Ruangan tujuan (opsional)"
                buildingId={buildingId}
                roomId={roomId}
                onChange={(b, r) => {
                  setBuildingId(b)
                  setRoomId(r)
                }}
                error={errors.room_id}
              />
              {/* Ruangan di sini hanya mencatat tujuan; jumlah Ada ruangan tidak berubah (SPEC §10). */}
              <p className="text-xs text-slate-500">Untuk mengisi ruangan, gunakan Isi dari stok di Kelengkapan Ruangan.</p>
            </>
          )}
          <FormField label="Keterangan" required={noteRequired} error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
