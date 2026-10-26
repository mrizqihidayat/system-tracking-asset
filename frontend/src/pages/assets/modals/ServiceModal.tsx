import { useState } from 'react'
import { FormField, Input, Textarea } from '../../../components/ui/FormField'
import { useToast } from '../../../components/ui/toast-context'
import { api } from '../../../lib/api'
import { CONDITIONS, type Asset, type Condition } from '../../../types/asset'
import ActionModal from './ActionModal'
import ConditionStatusFields, { type ChoiceStatus } from './ConditionStatusFields'

// Tanggal lokal (bukan UTC) agar "hari ini" tidak mundur sehari di zona WIB.
const today = () => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

interface Props {
  asset: Asset
  onClose: () => void
  onDone: () => void
}

export default function ServiceModal({ asset, onClose, onDone }: Props) {
  const toast = useToast()
  const [serviceDate, setServiceDate] = useState(today)
  const [vendor, setVendor] = useState('')
  const [condition, setCondition] = useState<Condition>('Baik')
  const [status, setStatus] = useState<ChoiceStatus>('Tersedia')
  const [nextDate, setNextDate] = useState('')
  const [note, setNote] = useState('')

  const submit = async () => {
    const payload: Record<string, unknown> = {
      service_date: serviceDate,
      vendor: vendor.trim(),
      condition,
      next_service_date: nextDate || null,
      note: note.trim() || null,
    }
    if (condition === 'Rusak Ringan') payload.status = status
    await api.post(`/assets/${asset.id}/services`, payload)
    toast.success(`Servis ${asset.code} tercatat.`)
    onDone()
  }

  return (
    <ActionModal title={`Catat servis — ${asset.code}`} submitLabel="Simpan" onClose={onClose} onSubmit={submit}>
      {(errors) => (
        <>
          <div className="grid gap-3 sm:grid-cols-2">
            <FormField label="Tanggal servis" required error={errors.service_date}>
              <Input type="date" max={today()} value={serviceDate} onChange={(e) => setServiceDate(e.target.value)} error={errors.service_date} required />
            </FormField>
            <FormField label="Vendor" required error={errors.vendor}>
              <Input value={vendor} onChange={(e) => setVendor(e.target.value)} error={errors.vendor} maxLength={150} required />
            </FormField>
          </div>
          <ConditionStatusFields
            label="Kondisi setelah servis"
            options={CONDITIONS}
            condition={condition}
            status={status}
            onCondition={setCondition}
            onStatus={setStatus}
            errors={errors}
          />
          <FormField label="Servis berikutnya (opsional, kosong = tidak diubah)" error={errors.next_service_date}>
            <Input type="date" min={serviceDate} value={nextDate} onChange={(e) => setNextDate(e.target.value)} error={errors.next_service_date} />
          </FormField>
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
