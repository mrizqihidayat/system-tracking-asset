import { useState } from 'react'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { CONDITIONS, type Condition } from '../../types/asset'
import type { Loan } from '../../types/loan'
import ActionModal from '../assets/modals/ActionModal'
import ConditionStatusFields, { type ChoiceStatus } from '../assets/modals/ConditionStatusFields'
import RoomSelect from './RoomSelect'

interface Props {
  loan: Loan
  onClose: () => void
  onDone: () => void
}

const today = () => new Date().toLocaleDateString('en-CA')

/** Terima kembali: hasil Kembali (kondisi, ruangan simpan, catatan) atau Hilang saat dipinjam. */
export default function ReturnLoanModal({ loan, onClose, onDone }: Props) {
  const toast = useToast()
  const [result, setResult] = useState<'kembali' | 'hilang'>('kembali')
  const [date, setDate] = useState(today())
  const [condition, setCondition] = useState<Condition>(loan.asset?.condition ?? 'Baik')
  const [status, setStatus] = useState<ChoiceStatus>('Tersedia')
  const [buildingId, setBuildingId] = useState<number | null>(loan.asset?.room?.building_id ?? null)
  const [roomId, setRoomId] = useState<number | null>(loan.asset?.room?.id ?? null)
  const [note, setNote] = useState('')

  const lost = result === 'hilang'
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
    }
    await api.patch(`/loans/${loan.id}/return`, payload)
    toast.success(lost ? 'Aset dilaporkan hilang saat dipinjam.' : `${loan.asset?.code} diterima kembali.`)
    onDone()
  }

  return (
    <ActionModal
      title={`Terima kembali — ${loan.asset?.code}`}
      submitLabel="Simpan"
      danger={lost}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">
            {loan.asset?.name} · dipinjam {loan.borrower_name}, {loan.borrower_unit} · kondisi keluar {loan.condition_out}
          </p>
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
                errors={errors}
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
