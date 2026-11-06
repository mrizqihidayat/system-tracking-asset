import { useEffect, useState } from 'react'
import EntityCombobox from '../../components/ui/EntityCombobox'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { createBuilding, createConsumableCategory, createRoom } from '../../lib/lookups'
import { useBuildings, useConsumableCategories, useDepartments, useRooms } from '../../lib/queries/master'
import type { ConsumableItem, ConsumableUsage } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  item: ConsumableItem | null
  onClose: () => void
  onDone: () => void
}

/** Tambah/ubah barang habis pakai (SPEC §10). Ubah data tidak mengubah jumlah. */
export default function ConsumableFormModal({ item, onClose, onDone }: Props) {
  const toast = useToast()
  const { data: departments } = useDepartments()
  const { data: categories } = useConsumableCategories()
  const { data: buildings } = useBuildings()
  const [usedIn, setUsedIn] = useState<ConsumableUsage[]>([])
  const [name, setName] = useState(item?.name ?? '')
  const [categoryId, setCategoryId] = useState<number | null>(item?.consumable_category_id ?? null)
  // Teks yang belum dipilih/ditambahkan tidak ikut tersimpan, jadi Simpan ditahan dulu.
  const [categoryPending, setCategoryPending] = useState(false)
  const [unit, setUnit] = useState(item?.unit ?? 'pcs')
  const [quantity, setQuantity] = useState(String(item?.quantity ?? 0))
  const [minQuantity, setMinQuantity] = useState(String(item?.min_quantity ?? 0))
  const [providerId, setProviderId] = useState<number | null>(item?.provider_department?.id ?? null)
  const [buildingId, setBuildingId] = useState<number | null>(item?.room?.building_id ?? null)
  const [roomId, setRoomId] = useState<number | null>(item?.room?.id ?? null)
  const [buildingPending, setBuildingPending] = useState(false)
  const [roomPending, setRoomPending] = useState(false)
  const [roomText, setRoomText] = useState('')
  const { data: rooms } = useRooms(buildingId, true)
  const [note, setNote] = useState(item?.note ?? '')

  useEffect(() => {
    if (item) {
      api
        .get<{ meta: { used_in_rooms: ConsumableUsage[] } }>(`/consumables/${item.id}`)
        .then((r) => setUsedIn(r.data.meta.used_in_rooms))
        .catch(() => undefined)
    }
  }, [item])

  const addCategory = async (categoryName: string) => {
    try {
      const c = await createConsumableCategory(categoryName)
      return { id: c.id, label: c.name }
    } catch (e) {
      toast.error(fieldErrors(e).name ?? errorMessage(e))
      throw e
    }
  }

  const guarded = async <T,>(fn: () => Promise<T>): Promise<T> => {
    try {
      return await fn()
    } catch (e) {
      toast.error(fieldErrors(e).name ?? errorMessage(e))
      throw e
    }
  }
  const addBuilding = (buildingName: string) =>
    guarded(async () => {
      const b = await createBuilding(buildingName)
      return { id: b.id, label: b.name }
    })
  const addRoom = (roomName: string) =>
    guarded(async () => {
      const r = await createRoom(buildingId!, roomName, true)
      return { id: r.id, label: r.name }
    })

  /** Lokasi simpan hanya gudang; nama ruangan biasa tidak bisa ditambah ulang, harus ditandai di Data Master. */
  const isNonStorageRoom = (text: string) => rooms.some((r) => !r.is_storage && r.name.toLowerCase() === text.trim().toLowerCase())
  const roomBlocked = !!buildingId && isNonStorageRoom(roomText)

  const submit = async () => {
    const payload: Record<string, unknown> = {
      name: name.trim(),
      consumable_category_id: categoryId,
      unit: unit.trim(),
      min_quantity: Number(minQuantity),
      provider_department_id: providerId,
      room_id: roomId,
      note: note.trim() || null,
    }
    if (item) {
      await api.put(`/consumables/${item.id}`, payload)
    } else {
      await api.post('/consumables', { ...payload, quantity: Number(quantity) })
    }
    toast.success(item ? 'Data barang diperbarui.' : 'Barang ditambahkan.')
    onDone()
  }

  return (
    <ActionModal
      title={item ? `Ubah ${item.name}` : 'Tambah barang habis pakai'}
      submitLabel="Simpan"
      submitDisabled={!name.trim() || !unit.trim() || !providerId || !roomId || categoryPending || buildingPending || roomPending}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Nama barang" required error={errors.name}>
              <Input value={name} onChange={(e) => setName(e.target.value)} error={errors.name} maxLength={150} />
            </FormField>
            <FormField label="Kategori" error={errors.consumable_category_id}>
              <EntityCombobox
                value={categoryId}
                options={categories.map((c) => ({ id: c.id, label: c.name }))}
                onChange={setCategoryId}
                onCreate={addCategory}
                onPendingChange={setCategoryPending}
                placeholder="Misal: ATK"
                error={errors.consumable_category_id}
              />
              {categoryPending && <p className="mt-1 text-xs text-slate-500">Pilih dari daftar atau klik Tambah, atau kosongkan.</p>}
            </FormField>
          </div>
          <div className="grid grid-cols-3 gap-3">
            <FormField label="Satuan" required error={errors.unit}>
              <Input value={unit} onChange={(e) => setUnit(e.target.value)} error={errors.unit} maxLength={20} />
            </FormField>
            <FormField label={item ? 'Jumlah' : 'Jumlah awal'} required={!item} error={errors.quantity}>
              <Input
                type="number"
                min={0}
                value={item ? item.quantity : quantity}
                disabled={!!item}
                onChange={(e) => setQuantity(e.target.value)}
                error={errors.quantity}
              />
            </FormField>
            <FormField label="Minimum" required error={errors.min_quantity}>
              <Input type="number" min={0} value={minQuantity} onChange={(e) => setMinQuantity(e.target.value)} error={errors.min_quantity} />
            </FormField>
          </div>
          {item && <p className="-mt-1 text-xs text-slate-500">Jumlah diubah lewat Catat transaksi di tabel.</p>}
          <FormField label="Minta ke (departemen penyedia)" required error={errors.provider_department_id}>
            <Select value={providerId ?? ''} onChange={(e) => setProviderId(Number(e.target.value) || null)} error={errors.provider_department_id}>
              <option value="">Pilih departemen</option>
              {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
            </Select>
          </FormField>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Gedung lokasi simpan" required>
              <EntityCombobox
                value={buildingId}
                options={buildings.map((b) => ({ id: b.id, label: b.name }))}
                onChange={(id) => {
                  setBuildingId(id)
                  setRoomId(null)
                  setRoomPending(false)
                  setRoomText('')
                }}
                onCreate={addBuilding}
                onPendingChange={setBuildingPending}
                placeholder="Ketik nama gedung…"
              />
            </FormField>
            <FormField label="Ruangan lokasi simpan" required error={errors.room_id}>
              <EntityCombobox
                key={buildingId ?? 'none'}
                value={roomId}
                options={buildingId ? rooms.filter((r) => r.is_storage).map((r) => ({ id: r.id, label: r.name })) : []}
                onChange={setRoomId}
                onCreate={buildingId ? addRoom : undefined}
                onPendingChange={setRoomPending}
                onTextChange={setRoomText}
                isExisting={isNonStorageRoom}
                placeholder={buildingId ? 'Pilih gudang, misal Gudang IHS' : 'Pilih gedung dulu'}
                disabled={!buildingId}
                error={errors.room_id}
              />
            </FormField>
          </div>
          {roomBlocked ? (
            <p role="alert" className="-mt-1 text-xs font-medium text-warn-700">Ruangan ini sudah ada tetapi bukan gudang. Tandai sebagai gudang di Data Master.</p>
          ) : (buildingPending || roomPending) && (
            <p className="-mt-1 text-xs text-slate-500">Pilih gedung/ruangan dari daftar atau klik Tambah.</p>
          )}
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
          {usedIn.length > 0 && (
            <p className="text-xs text-slate-500">
              Dipakai di ruangan: {usedIn.map((u) => `${u.room} (${u.building})`).join(', ')}
            </p>
          )}
        </>
      )}
    </ActionModal>
  )
}
