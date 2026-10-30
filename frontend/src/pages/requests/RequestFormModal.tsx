import { useState } from 'react'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { useDepartments, useRooms } from '../../lib/queries/master'
import type { RequestCatalog, RequestPrefill, SupplyRequest } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'
import RoomSelect from '../loans/RoomSelect'

interface Props {
  catalog: RequestCatalog[]
  /** null = permintaan baru. */
  request: SupplyRequest | null
  prefill?: RequestPrefill
  onClose: () => void
  onDone: () => void
}

const today = () => new Date().toLocaleDateString('en-CA')
const MANUAL = 'manual'

/** Catat permintaan ke unit lain (proses permintaan tetap di luar sistem). */
export default function RequestFormModal({ catalog, request, prefill, onClose, onDone }: Props) {
  const toast = useToast()
  const departments = useDepartments().data.filter((d) => !d.is_internal)
  const { data: allRooms } = useRooms()
  const initialCatalog = request ? request.catalog?.id : prefill?.catalog_id
  const [catalogId, setCatalogId] = useState<string>(
    initialCatalog ? String(initialCatalog) : request || prefill?.item_name ? MANUAL : '',
  )
  const [itemName, setItemName] = useState(request?.item_name ?? prefill?.item_name ?? '')
  const [providerId, setProviderId] = useState<number | null>(
    request?.provider_department?.id ?? prefill?.provider_department_id ?? null,
  )
  const [quantity, setQuantity] = useState(String(request?.quantity ?? prefill?.quantity ?? 1))
  const [purpose, setPurpose] = useState(request?.purpose ?? '')
  const [date, setDate] = useState(request?.request_date ?? today())
  const [buildingId, setBuildingId] = useState<number | null>(request?.room?.building_id ?? null)
  const [roomId, setRoomId] = useState<number | null>(request?.room?.id ?? prefill?.room_id ?? null)
  const [note, setNote] = useState(request?.note ?? '')

  // Ruangan dari query/saran hanya membawa id → cari gedungnya agar dropdown terisi (sampai gedung dipilih).
  const prefillRoom = prefill?.room_id
  const prefillBuilding = !request && prefillRoom ? allRooms.find((r) => r.id === prefillRoom)?.building_id ?? null : null
  const effectiveBuildingId = buildingId ?? prefillBuilding

  const manual = catalogId === MANUAL
  const selected = catalog.find((c) => String(c.id) === catalogId)

  const submit = async () => {
    const payload = {
      catalog_id: selected?.id ?? null,
      item_name: manual ? itemName.trim() : null,
      provider_department_id: manual ? providerId : null,
      quantity: Number(quantity),
      purpose: purpose.trim() || null,
      request_date: date || null,
      room_id: roomId,
      note: note.trim() || null,
    }
    if (request) await api.put(`/supply-requests/${request.id}`, payload)
    else await api.post('/supply-requests', payload)
    toast.success(request ? 'Permintaan diperbarui.' : 'Permintaan dicatat.')
    onDone()
  }

  return (
    <ActionModal
      title={request ? 'Ubah permintaan' : 'Buat permintaan'}
      submitLabel="Simpan"
      submitDisabled={!catalogId || (manual && (!itemName.trim() || !providerId))}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <FormField label="Item" required error={errors.catalog_id}>
            <Select value={catalogId} onChange={(e) => setCatalogId(e.target.value)} error={errors.catalog_id}>
              <option value="">Pilih item katalog</option>
              {catalog.map((c) => (
                <option key={c.id} value={c.id}>{c.item_name} — {c.provider_department?.name}</option>
              ))}
              <option value={MANUAL}>Item lain (tidak ada di katalog)</option>
            </Select>
          </FormField>
          {selected && (
            <p className="-mt-1 text-xs text-slate-500">
              {selected.provider_department?.name} · via {selected.request_method} · lead time {selected.lead_time_days} hari
              {selected.must_return && ' · perlu dikembalikan'}
            </p>
          )}
          {manual && (
            <div className="grid grid-cols-2 gap-3">
              <FormField label="Nama item" required error={errors.item_name}>
                <Input value={itemName} onChange={(e) => setItemName(e.target.value)} error={errors.item_name} maxLength={150} />
              </FormField>
              <FormField label="Unit penyedia" required error={errors.provider_department_id}>
                <Select value={providerId ?? ''} onChange={(e) => setProviderId(Number(e.target.value) || null)} error={errors.provider_department_id}>
                  <option value="">Pilih unit</option>
                  {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                </Select>
              </FormField>
            </div>
          )}
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Jumlah" required error={errors.quantity}>
              <Input type="number" min={1} value={quantity} onChange={(e) => setQuantity(e.target.value)} error={errors.quantity} />
            </FormField>
            <FormField label="Tanggal permintaan" error={errors.request_date}>
              <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} error={errors.request_date} />
            </FormField>
          </div>
          <FormField label="Untuk kegiatan" error={errors.purpose}>
            <Input value={purpose} onChange={(e) => setPurpose(e.target.value)} error={errors.purpose} maxLength={255} />
          </FormField>
          <RoomSelect
            buildingLabel="Gedung terkait (opsional)"
            roomLabel="Ruangan terkait (opsional)"
            buildingId={effectiveBuildingId}
            roomId={roomId}
            onChange={(b, r) => {
              setBuildingId(b)
              setRoomId(r)
            }}
            error={errors.room_id}
          />
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
