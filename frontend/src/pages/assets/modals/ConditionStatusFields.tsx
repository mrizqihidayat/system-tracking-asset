import { FormField, Select } from '../../../components/ui/FormField'
import type { Condition } from '../../../types/asset'

export type ChoiceStatus = 'Tersedia' | 'Perbaikan'
export type HeavyDamageStatus = 'Perbaikan' | 'Tidak Layak Pakai'

interface Props {
  label?: string
  options: readonly Condition[]
  condition: Condition
  status: ChoiceStatus
  onCondition: (c: Condition) => void
  onStatus: (s: ChoiceStatus) => void
  /** Jika diisi (terima kembali), Rusak Berat bisa memilih Perbaikan / Tidak Layak Pakai. */
  heavyDamage?: { status: HeavyDamageStatus; onChange: (s: HeavyDamageStatus) => void }
  errors: Record<string, string>
  /** false: sembunyikan keterangan "Status otomatis: …" (mis. di form terima kembali). */
  showAutoStatus?: boolean
}

/** Kondisi + pilihan status Rusak Ringan (dan Rusak Berat jika `heavyDamage` diisi); selain itu status otomatis. */
export default function ConditionStatusFields({ label = 'Kondisi', options, condition, status, onCondition, onStatus, heavyDamage, errors, showAutoStatus = true }: Props) {
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
      ) : condition === 'Rusak Berat' && heavyDamage ? (
        <>
          <FormField label="Status aset" error={errors.status}>
            <Select value={heavyDamage.status} onChange={(e) => heavyDamage.onChange(e.target.value as HeavyDamageStatus)} error={errors.status}>
              <option>Perbaikan</option>
              <option>Tidak Layak Pakai</option>
            </Select>
          </FormField>
          {heavyDamage.status === 'Tidak Layak Pakai' && (
            <p className="rounded-lg bg-warn-50 px-3 py-2.5 text-sm text-warn-700">
              Aset tidak layak pakai tidak bisa dipinjam atau diservis dan tidak dapat dikembalikan statusnya.
            </p>
          )}
        </>
      ) : (
        showAutoStatus && <p className="text-xs text-slate-500">Status otomatis: {condition === 'Baik' ? 'Tersedia' : 'Perbaikan'}.</p>
      )}
    </>
  )
}
