import { useState } from 'react'
import { FormField, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import type { Loan } from '../../types/loan'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  loan: Loan
  onClose: () => void
  onDone: () => void
}

export default function CancelLoanModal({ loan, onClose, onDone }: Props) {
  const toast = useToast()
  const [reason, setReason] = useState('')

  const submit = async () => {
    await api.patch(`/loans/${loan.id}/cancel`, { cancel_reason: reason.trim() })
    toast.success('Peminjaman dibatalkan.')
    onDone()
  }

  return (
    <ActionModal
      title={`Batalkan peminjaman — ${loan.asset?.code}`}
      submitLabel="Batalkan peminjaman"
      danger
      submitDisabled={!reason.trim()}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">
            Peminjaman oleh {loan.borrower_name}, {loan.borrower_unit}. Status aset menyesuaikan kondisinya saat ini.
          </p>
          <FormField label="Alasan pembatalan" required error={errors.cancel_reason}>
            <Textarea value={reason} onChange={(e) => setReason(e.target.value)} error={errors.cancel_reason} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
