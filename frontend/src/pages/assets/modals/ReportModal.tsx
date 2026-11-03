import { useState } from 'react'
import { FormField, Input, Textarea } from '../../../components/ui/FormField'
import { useToast } from '../../../components/ui/toast-context'
import { api } from '../../../lib/api'
import type { Asset, Condition } from '../../../types/asset'
import ActionModal from './ActionModal'
import ConditionStatusFields, { type ChoiceStatus } from './ConditionStatusFields'

export type ReportMode = 'rusak' | 'hilang' | 'tidak_layak_pakai'

const titles: Record<ReportMode, string> = {
  rusak: 'Catat kerusakan',
  hilang: 'Catat kehilangan',
  tidak_layak_pakai: 'Catat tidak layak pakai',
}

interface Props {
  asset: Asset
  mode: ReportMode
  onClose: () => void
  onDone: () => void
}

export default function ReportModal({ asset, mode, onClose, onDone }: Props) {
  const toast = useToast()
  const [condition, setCondition] = useState<Condition>('Rusak Ringan')
  const [status, setStatus] = useState<ChoiceStatus>('Tersedia')
  const [party, setParty] = useState('')
  const [note, setNote] = useState('')

  const submit = async () => {
    const payload: Record<string, unknown> = { type: mode, note: note.trim(), party: party.trim() || null }
    if (mode === 'rusak') {
      payload.condition = condition
      if (condition === 'Rusak Ringan') payload.status = status
    }
    await api.post(`/assets/${asset.id}/report`, payload)
    toast.success(`${titles[mode]} untuk ${asset.code} tersimpan.`)
    onDone()
  }

  return (
    <ActionModal title={`${titles[mode]} — ${asset.code}`} submitLabel="Simpan" danger={mode !== 'rusak'} onClose={onClose} onSubmit={submit}>
      {(errors) => (
        <>
          {mode === 'rusak' && (
            <>
              <ConditionStatusFields
                label="Kondisi sekarang"
                options={['Rusak Ringan', 'Rusak Berat']}
                condition={condition}
                status={status}
                onCondition={setCondition}
                onStatus={setStatus}
                errors={errors}
              />
              {asset.status === 'Dipinjam' && (
                <p className="text-xs text-slate-500">Aset sedang dipinjam: hanya kondisi yang berubah, status tetap Dipinjam.</p>
              )}
            </>
          )}
          {mode === 'hilang' && <p className="text-sm text-slate-600">Status aset akan menjadi Hilang.</p>}
          {mode === 'tidak_layak_pakai' && (
            <p className="rounded-lg bg-warn-50 px-3 py-2.5 text-sm text-warn-700">
              Aset tidak layak pakai tidak bisa dipinjam atau diservis dan tidak dapat dikembalikan statusnya. Hanya Pindah
              lokasi dan Hapus aset yang masih tersedia.
            </p>
          )}
          {mode !== 'hilang' && (
            <FormField label="Pihak pelapor (opsional, default SHD)" error={errors.party}>
              <Input value={party} onChange={(e) => setParty(e.target.value)} error={errors.party} maxLength={150} placeholder="SHD" />
            </FormField>
          )}
          <FormField label="Catatan" required error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} required />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
