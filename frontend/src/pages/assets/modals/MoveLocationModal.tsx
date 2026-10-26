import { useEffect, useState } from 'react'
import EntityCombobox from '../../../components/ui/EntityCombobox'
import { FormField, Textarea } from '../../../components/ui/FormField'
import { useToast } from '../../../components/ui/toast-context'
import { api, errorMessage } from '../../../lib/api'
import { createBuilding, createRoom, fetchBuildings, fetchRooms } from '../../../lib/lookups'
import type { Asset, Building, Room } from '../../../types/asset'
import ActionModal from './ActionModal'

interface Props {
  asset: Asset
  onClose: () => void
  onDone: () => void
}

export default function MoveLocationModal({ asset, onClose, onDone }: Props) {
  const toast = useToast()
  const [buildings, setBuildings] = useState<Building[]>([])
  const [rooms, setRooms] = useState<Room[]>([])
  const [buildingId, setBuildingId] = useState<number | null>(asset.room?.building_id ?? null)
  const [roomId, setRoomId] = useState<number | null>(null)
  const [note, setNote] = useState('')

  useEffect(() => {
    fetchBuildings().then(setBuildings).catch((e) => toast.error(errorMessage(e)))
  }, [toast])

  useEffect(() => {
    if (!buildingId) return
    fetchRooms(buildingId).then(setRooms).catch((e) => toast.error(errorMessage(e)))
  }, [buildingId, toast])

  const guarded = async <T,>(fn: () => Promise<T>) => {
    try {
      return await fn()
    } catch (e) {
      toast.error(errorMessage(e))
      throw e
    }
  }

  const addBuilding = (name: string) =>
    guarded(async () => {
      const b = await createBuilding(name)
      setBuildings((l) => [...l, b])
      return { id: b.id, label: b.name }
    })
  const addRoom = (name: string) =>
    guarded(async () => {
      const r = await createRoom(buildingId!, name)
      setRooms((l) => [...l, r])
      return { id: r.id, label: r.name }
    })

  const submit = async () => {
    await api.patch(`/assets/${asset.id}/location`, { room_id: roomId, note: note.trim() || null })
    toast.success(`Lokasi ${asset.code} dipindahkan.`)
    onDone()
  }

  return (
    <ActionModal title={`Pindah lokasi — ${asset.code}`} submitLabel="Pindahkan" submitDisabled={!roomId} onClose={onClose} onSubmit={submit}>
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">
            Lokasi asal sekarang: {asset.room ? `${asset.room.name}, ${asset.room.building?.name ?? ''}` : '-'}
          </p>
          <FormField label="Gedung tujuan" required>
            <EntityCombobox
              value={buildingId}
              options={buildings.map((b) => ({ id: b.id, label: b.name }))}
              onChange={(v) => {
                setBuildingId(v)
                setRoomId(null)
              }}
              onCreate={addBuilding}
              placeholder="Ketik nama gedung…"
            />
          </FormField>
          <FormField label="Ruangan tujuan" required error={errors.room_id}>
            <EntityCombobox
              key={buildingId ?? 'none'}
              value={roomId}
              options={buildingId ? rooms.map((r) => ({ id: r.id, label: r.name })) : []}
              onChange={setRoomId}
              onCreate={buildingId ? addRoom : undefined}
              placeholder={buildingId ? 'Ketik nama ruangan…' : 'Pilih gedung dulu'}
              disabled={!buildingId}
              error={errors.room_id}
            />
          </FormField>
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
