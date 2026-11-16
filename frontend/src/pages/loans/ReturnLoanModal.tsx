import { useState, type ReactNode } from 'react'
import { AssetCode, ConditionBadge } from '../../components/ui/Badge'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { CONDITIONS, type Condition } from '../../types/asset'
import type { Loan } from '../../types/loan'
import ActionModal from '../assets/modals/ActionModal'
import ConditionStatusFields, { type ChoiceStatus, type HeavyDamageStatus } from '../assets/modals/ConditionStatusFields'
import RoomSelect from './RoomSelect'

interface Props {
  loan: Loan
  onClose: () => void
  onDone: () => void
}

const today = () => new Date().toLocaleDateString('en-CA')

function Info({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="text-xs text-slate-500">{label}</dt>
      <dd className="mt-0.5 text-sm lg:truncate">{children}</dd>
    </div>
  )
}

/** Terima kembali: hasil Kembali (kondisi, ruangan simpan, catatan) atau Hilang saat dipinjam. */
export default function ReturnLoanModal({ loan, onClose, onDone }: Props) {
  const toast = useToast()
  const [result, setResult] = useState<'kembali' | 'hilang'>('kembali')
  const [date, setDate] = useState(today())
  const [condition, setCondition] = useState<Condition>(loan.asset?.condition ?? 'Baik')
  const [status, setStatus] = useState<ChoiceStatus>('Tersedia')
  const [heavyStatus, setHeavyStatus] = useState<HeavyDamageStatus>('Perbaikan')
  const [buildingId, setBuildingId] = useState<number | null>(loan.asset?.room?.building_id ?? null)
  const [roomId, setRoomId] = useState<number | null>(loan.asset?.room?.id ?? null)
  const [note, setNote] = useState('')

  const lost = result === 'hilang'
  const destination = loan.destination_room
    ? `${loan.destination_room.name}${loan.destination_room.building ? ` · ${loan.destination_room.building.name}` : ''}`
    : loan.destination_note || '-'
  const noteRequired = lost || condition !== 'Baik'

  const submit = async () => {
    const payload: Record<string, unknown> = {
      result,
      return_date: date === today() ? null : date,
      note: note.trim() || null,
    }
    if (!lost) {
      payload.return_condition = condition
      payload.return_room_id = roomId
      if (condition === 'Rusak Ringan') payload.status = status
      if (condition === 'Rusak Berat') payload.status = heavyStatus
    }
    await api.patch(`/loans/${loan.id}/return`, payload)
    toast.success(lost ? 'Aset dilaporkan hilang saat dipinjam.' : `${loan.asset?.code} diterima kembali.`)
    onDone()
  }

  return (
    <ActionModal
      title={`Terima kembali • ${loan.asset?.code}`}
      submitLabel="Simpan"
      danger={lost}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
            <p className="flex min-w-0 items-center gap-2">
              {loan.asset && <AssetCode code={loan.asset.code} className="bg-white!" />}
              <span className="min-w-0 font-semibold lg:truncate">{loan.asset?.name}</span>
            </p>
            <dl className="mt-2.5 grid grid-cols-2 gap-x-4 gap-y-2.5 border-t border-slate-200 pt-2.5">
              <Info label="Peminjam">
                {loan.borrower_name}
                <span className="text-slate-500"> · {loan.borrower_unit}</span>
              </Info>
              <Info label="Kondisi keluar">
                <ConditionBadge condition={loan.condition_out} />
              </Info>
              <Info label="Dipinjam dari">
                {loan.asset?.room ? (
                  <>
                    {loan.asset.room.name}
                    {loan.asset.room.building && <span className="text-slate-500"> · {loan.asset.room.building.name}</span>}
                  </>
                ) : '-'}
              </Info>
              <Info label="Lokasi tujuan">{destination}</Info>
              <Info label="Tanggal pinjam">{formatDate(loan.borrow_date)}</Info>
              <Info label="Rencana kembali">{formatDate(loan.due_date)}</Info>
            </dl>
          </div>
          <FormField label="Hasil" required>
            <Select value={result} onChange={(e) => setResult(e.target.value as 'kembali' | 'hilang')}>
              <option value="kembali">Kembali</option>
              <option value="hilang">Hilang saat dipinjam</option>
            </Select>
          </FormField>
          <FormField label={lost ? 'Tanggal hilang' : 'Tanggal kembali'} required error={errors.return_date}>
            <Input type="date" value={date} max={today()} min={loan.borrow_date} onChange={(e) => setDate(e.target.value)} error={errors.return_date} />
          </FormField>
          {!lost && (
            <>
              <ConditionStatusFields
                label="Kondisi saat kembali"
                options={CONDITIONS}
                condition={condition}
                status={status}
                onCondition={setCondition}
                onStatus={setStatus}
                heavyDamage={{ status: heavyStatus, onChange: setHeavyStatus }}
                errors={errors}
                showAutoStatus={false}
              />
              <RoomSelect
                buildingLabel="Gedung penyimpanan"
                roomLabel="Ruangan penyimpanan"
                buildingId={buildingId}
                roomId={roomId}
                onChange={(b, r) => {
                  setBuildingId(b)
                  setRoomId(r)
                }}
                error={errors.return_room_id}
              />
            </>
          )}
          <FormField label={noteRequired ? 'Catatan' : 'Catatan (opsional)'} required={noteRequired} error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
