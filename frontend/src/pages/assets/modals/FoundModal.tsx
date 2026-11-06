import { useState } from 'react'
import { FormField, Input, Textarea } from '../../../components/ui/FormField'
import { useToast } from '../../../components/ui/toast-context'
import { api } from '../../../lib/api'
import { CONDITIONS, type Asset, type Condition } from '../../../types/asset'
import ActionModal from './ActionModal'
import ConditionStatusFields, { type ChoiceStatus } from './ConditionStatusFields'

interface Props {
  asset: Asset
  onClose: () => void
  /** `available` true jika status setelah ditemukan = Tersedia (boleh langsung dipinjamkan). */
  onDone: (available: boolean) => void
}

export default function FoundModal({ asset, onClose, onDone }: Props) {
  const toast = useToast()
  const [condition, setCondition] = useState<Condition>(asset.condition)
  const [status, setStatus] = useState<ChoiceStatus>('Tersedia')
  const [party, setParty] = useState('')
  const [note, setNote] = useState('')

  const submit = async () => {
    const payload: Record<string, unknown> = { condition, note: note.trim() || null, party: party.trim() || null }
    if (condition === 'Rusak Ringan') payload.status = status
    const res = await api.post<{ data: Asset }>(`/assets/${asset.id}/found`, payload)
    toast.success(`${asset.code} ditandai ditemukan.`)
    onDone(res.data.data.status === 'Tersedia')
  }

  return (
    <ActionModal title={`Tandai ditemukan • ${asset.code}`} submitLabel="Simpan" onClose={onClose} onSubmit={submit}>
      {(errors) => (
        <>
          <ConditionStatusFields
            label="Kondisi saat ditemukan"
            options={CONDITIONS}
            condition={condition}
            status={status}
            onCondition={setCondition}
            onStatus={setStatus}
            errors={errors}
          />
          <FormField label="Pihak (opsional)" error={errors.party}>
            <Input value={party} onChange={(e) => setParty(e.target.value)} error={errors.party} maxLength={150} />
          </FormField>
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
