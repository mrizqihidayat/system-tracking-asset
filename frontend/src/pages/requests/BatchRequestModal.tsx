import { X } from 'lucide-react'
import { useState } from 'react'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { useRequestMethods } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import type { SuggestionGroup } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'

const today = () => new Date().toLocaleDateString('en-CA')
const OTHER_METHOD = 'Lainnya'

interface Props {
  group: SuggestionGroup
  onClose: () => void
  onDone: () => void
}

/**
 * Buat permintaan ke satu departemen untuk beberapa barang stok sekaligus (SPEC §9).
 * Satu permintaan per barang, semua tersimpan dalam satu transaksi (`POST /supply-requests/batch`).
 */
export default function BatchRequestModal({ group, onClose, onDone }: Props) {
  const toast = useToast()
  const [items, setItems] = useState(() =>
    group.items.filter((i) => !i.has_open_request).map((i) => ({ consumable_id: i.consumable_id, item_name: i.item_name, quantity: String(i.quantity) })),
  )
  const requestMethods = useRequestMethods()
  useErrorToast(requestMethods.error)
  const methodOptions = requestMethods.data.filter((m) => m !== OTHER_METHOD)
  const [methodChoice, setMethodChoice] = useState('')
  const [otherMethod, setOtherMethod] = useState('')
  const method = methodChoice === OTHER_METHOD ? otherMethod.trim() : methodChoice
  const [date, setDate] = useState(today())
  const [purpose, setPurpose] = useState('Pengisian stok')
  const [note, setNote] = useState('')

  const setQuantity = (index: number, quantity: string) => setItems((list) => list.map((item, i) => (i === index ? { ...item, quantity } : item)))
  const remove = (index: number) => setItems((list) => list.filter((_, i) => i !== index))

  const submit = async () => {
    await api.post('/supply-requests/batch', {
      provider_department_id: group.provider_department.id,
      request_method: method,
      request_date: date || null,
      purpose: purpose.trim() || null,
      note: note.trim() || null,
      items: items.map((i) => ({ consumable_id: i.consumable_id, quantity: Number(i.quantity) })),
    })
    toast.success(`${items.length} permintaan ke ${group.provider_department.name} dicatat.`)
    onDone()
  }

  const quantitiesValid = items.every((i) => Number.isInteger(Number(i.quantity)) && Number(i.quantity) >= 1)

  return (
    <ActionModal
      title={`Buat permintaan ke ${group.provider_department.name}`}
      submitLabel={`Simpan ${items.length} permintaan`}
      submitDisabled={items.length === 0 || !quantitiesValid || !method || requestMethods.isPending}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <div>
            <p className="mb-1.5 text-sm font-medium text-slate-700">Barang</p>
            {items.length === 0 ? (
              <p className="rounded-lg bg-slate-100 px-3 py-2.5 text-sm text-slate-600">Semua barang dibuang dari daftar. Tutup popup ini untuk membatalkan.</p>
            ) : (
              <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                {items.map((item, index) => {
                  const error = errors[`items.${index}.quantity`] ?? errors[`items.${index}.consumable_id`]
                  return (
                    <li key={item.consumable_id} className="px-3 py-2">
                      <div className="flex items-center gap-2">
                        <span className="min-w-0 flex-1 truncate text-sm font-medium">{item.item_name}</span>
                        <Input
                          type="number"
                          min={1}
                          value={item.quantity}
                          onChange={(e) => setQuantity(index, e.target.value)}
                          aria-label={`Jumlah ${item.item_name}`}
                          className="w-20! text-right"
                          error={error}
                        />
                        <button
                          type="button"
                          onClick={() => remove(index)}
                          aria-label={`Buang ${item.item_name} dari daftar`}
                          className="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                        >
                          <X size={16} />
                        </button>
                      </div>
                      {error && <p className="mt-1 text-xs font-medium text-bad-700">{error}</p>}
                    </li>
                  )
                })}
              </ul>
            )}
            {errors.items && <p className="mt-1 text-xs font-medium text-bad-700">{errors.items}</p>}
          </div>

          <FormField label="Cara permintaan" required error={methodChoice === OTHER_METHOD ? undefined : errors.request_method}>
            <Select value={methodChoice} disabled={requestMethods.isPending} onChange={(e) => setMethodChoice(e.target.value)} error={methodChoice === OTHER_METHOD ? undefined : errors.request_method}>
              <option value="">{requestMethods.isPending ? 'Memuat pilihan…' : 'Pilih cara permintaan'}</option>
              {methodOptions.map((m) => <option key={m}>{m}</option>)}
              <option>{OTHER_METHOD}</option>
            </Select>
          </FormField>
          {methodChoice === OTHER_METHOD && (
            <FormField label="Cara lainnya" required error={errors.request_method}>
              <Input value={otherMethod} onChange={(e) => setOtherMethod(e.target.value)} placeholder="Misal: Telegram" error={errors.request_method} maxLength={50} autoFocus />
            </FormField>
          )}
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Tanggal permintaan" error={errors.request_date}>
              <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} error={errors.request_date} />
            </FormField>
            <FormField label="Untuk kegiatan" error={errors.purpose}>
              <Input value={purpose} onChange={(e) => setPurpose(e.target.value)} error={errors.purpose} maxLength={255} />
            </FormField>
          </div>
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
