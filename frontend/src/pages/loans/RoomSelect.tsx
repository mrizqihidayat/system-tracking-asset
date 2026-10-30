import { FormField, Select } from '../../components/ui/FormField'
import { useBuildings, useRooms } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'

interface Props {
  buildingLabel: string
  roomLabel: string
  buildingId: number | null
  roomId: number | null
  onChange: (buildingId: number | null, roomId: number | null) => void
  required?: boolean
  error?: string
}

/** Pilihan Gedung → Ruangan (dropdown, sesuai mockup peminjaman). */
export default function RoomSelect({ buildingLabel, roomLabel, buildingId, roomId, onChange, required, error }: Props) {
  const { data: buildings, error: buildingsError } = useBuildings()
  const { data: rooms, error: roomsError } = useRooms(buildingId, true)
  useErrorToast(buildingsError, roomsError)

  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <FormField label={buildingLabel} required={required}>
        <Select value={buildingId ?? ''} onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null, null)}>
          <option value="">Pilih gedung</option>
          {buildings.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </Select>
      </FormField>
      <FormField label={roomLabel} required={required} error={error}>
        <Select
          value={roomId ?? ''}
          disabled={!buildingId}
          error={error}
          onChange={(e) => onChange(buildingId, e.target.value ? Number(e.target.value) : null)}
        >
          <option value="">{buildingId ? 'Pilih ruangan' : 'Pilih gedung dulu'}</option>
          {(buildingId ? rooms : []).map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
        </Select>
      </FormField>
    </div>
  )
}
