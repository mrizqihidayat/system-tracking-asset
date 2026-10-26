import { useState } from 'react'
import { FormField, Input, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import type { Loan } from '../../types/loan'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  loan: Loan
  onClose: () => void
  onDone: () => void
}

/** Konfirmasi kerusakan (peminjam mengakui di luar sistem): catatan wajib, no. dokumen opsional. */
export default function ConfirmDamageModal({ loan, onClose, onDone }: Props) {
  const toast = useToast()
  const [note, setNote] = useState('')
  const [document, setDocument] = useState('')

  const submit = async () => {
    await api.patch(`/loans/${loan.id}/confirm-damage`, {
      damage_confirmation_note: note.trim(),
      damage_confirmation_document: document.trim() || null,
    })
    toast.success('Kerusakan dikonfirmasi.')
    onDone()
  }

  return (
    <ActionModal
      title={`Konfirmasi kerusakan — ${loan.asset?.code}`}
      submitLabel="Konfirmasi"
      submitDisabled={!note.trim()}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">
            Dikembalikan {loan.borrower_name}, {loan.borrower_unit} dengan kondisi {loan.return_condition} (keluar: {loan.condition_out}).
          </p>
          <FormField label="Catatan konfirmasi" required error={errors.damage_confirmation_note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.damage_confirmation_note} maxLength={1000} />
          </FormField>
          <FormField label="No. dokumen berita acara / memo (opsional)" error={errors.damage_confirmation_document}>
            <Input
              value={document}
              onChange={(e) => setDocument(e.target.value)}
              error={errors.damage_confirmation_document}
              maxLength={100}
            />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
