import { TriangleAlert } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { FormField, Input, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import type { RoomConsumable } from '../../types/room'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  row: RoomConsumable
  roomName: string
  /** Tautan form permintaan untuk sisa kekurangan (jumlah terisi). */
  requestHref: (quantity: number) => string
  onClose: () => void
  onDone: () => void
}

/**
 * Isi dari stok (SPEC §8): jumlah default = kekurangan (atau sisa stok jika lebih kecil), maksimum = sisa stok.
 * Melebihi kekurangan hanya diberi peringatan. Tercatat sebagai Pemakaian dengan ruangan di kartu stok.
 */
export default function FillFromStockModal({ row, roomName, requestHref, onClose, onDone }: Props) {
  const toast = useToast()
  const shortage = row.shortage ?? 0
  const stock = row.consumable.quantity
  const unit = row.consumable.unit
  const [quantity, setQuantity] = useState(String(Math.min(shortage, stock)))
  const [note, setNote] = useState('')

  const value = Number(quantity)
  const valid = Number.isInteger(value) && value >= 1 && value <= stock
  const overStandard = valid && value > shortage
  const stockShort = stock < shortage

  const submit = async () => {
    const res = await api.post<{ message: string }>(`/room-standards/${row.id}/fill-from-stock`, {
      quantity: value,
      note: note.trim() || null,
    })
    toast.success(res.data.message)
    onDone()
  }

  const summary: [string, string][] = [
    ['Standar', `${row.standard_quantity} ${unit}`],
    ['Ada', `${row.present_quantity ?? 0} ${unit}`],
    ['Kurang', `${shortage} ${unit}`],
    ['Sisa stok', `${stock} ${unit}`],
  ]

  return (
    <ActionModal title={`Isi dari stok • ${row.item_name}`} submitLabel="Isi dari stok" submitDisabled={!valid} onClose={onClose} onSubmit={submit}>
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">Ke {roomName}. Tercatat sebagai Pemakaian di kartu stok.</p>
          <dl className="grid grid-cols-2 gap-2 rounded-lg sm:grid-cols-4 border border-slate-200 bg-slate-50 px-3 py-2.5">
            {summary.map(([label, text]) => (
              <div key={label} className="min-w-0">
                <dt className="text-xs text-slate-500">{label}</dt>
                <dd className={`mt-0.5 truncate text-sm font-medium tabular-nums ${label === 'Kurang' ? 'text-bad-700' : ''}`}>{text}</dd>
              </div>
            ))}
          </dl>

          {stockShort && (
            <div className="rounded-lg bg-warn-50 px-3 py-2.5 text-sm text-warn-700">
              Stok hanya {stock} {unit}. Setelah diisi masih kurang {shortage - stock} {unit}.{' '}
              <Link to={requestHref(shortage - stock)} className="font-semibold underline">Ajukan permintaan</Link>
            </div>
          )}

          <FormField label={`Jumlah (${unit})`} required error={errors.quantity}>
            <Input
              type="number"
              inputMode="numeric"
              min={1}
              max={stock}
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
              error={errors.quantity}
              autoFocus
            />
          </FormField>
          {quantity !== '' && !valid && !errors.quantity && (
            <p className="-mt-2 text-xs font-medium text-bad-700">Isi 1 sampai {stock} (sisa stok).</p>
          )}
          {overStandard && (
            <p className="-mt-2 flex items-center gap-1.5 text-xs font-medium text-warn-700">
              <TriangleAlert size={14} className="shrink-0" aria-hidden />
              Melebihi standar ruangan ({value - shortage} {unit} di atas kekurangan).
            </p>
          )}

          <FormField label="Keterangan (opsional)" error={errors.note}>
            <Textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Misal: untuk pelatihan K3 besok"
              error={errors.note}
              maxLength={1000}
            />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
