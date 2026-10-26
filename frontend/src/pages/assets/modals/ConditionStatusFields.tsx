import { FormField, Select } from '../../../components/ui/FormField'
import type { Condition } from '../../../types/asset'

export type ChoiceStatus = 'Tersedia' | 'Perbaikan'

interface Props {
  label?: string
  options: readonly Condition[]
  condition: Condition
  status: ChoiceStatus
  onCondition: (c: Condition) => void
  onStatus: (s: ChoiceStatus) => void
  errors: Record<string, string>
}

/** Kondisi + (hanya untuk Rusak Ringan) pilihan status Tersedia/Perbaikan; selain itu status otomatis. */
export default function ConditionStatusFields({ label = 'Kondisi', options, condition, status, onCondition, onStatus, errors }: Props) {
  return (
    <>
      <FormField label={label} required error={errors.condition}>
        <Select value={condition} onChange={(e) => onCondition(e.target.value as Condition)} error={errors.condition}>
          {options.map((c) => <option key={c}>{c}</option>)}
        </Select>
      </FormField>
      {condition === 'Rusak Ringan' ? (
        <FormField label="Status aset" error={errors.status}>
          <Select value={status} onChange={(e) => onStatus(e.target.value as ChoiceStatus)} error={errors.status}>
            <option>Tersedia</option>
            <option>Perbaikan</option>
          </Select>
        </FormField>
      ) : (
        <p className="text-xs text-slate-500">Status otomatis: {condition === 'Baik' ? 'Tersedia' : 'Perbaikan'}.</p>
      )}
    </>
  )
}
