import { useState } from 'react'
import { FormField, Input } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { formatDate } from '../../lib/format'
import type { Loan } from '../../types/loan'
import ActionModal from '../assets/modals/ActionModal'
import { todayWib as today } from '../../lib/date'


interface Props {
  loan: Loan
  onClose: () => void
  onDone: () => void
}

/** Serahkan pemesanan: dicatat saat barang benar-benar diserahkan (boleh sebelum, pada, atau setelah tanggal rencana). */
export default function HandOverModal({ loan, onClose, onDone }: Props) {
  const toast = useToast()
  const [date, setDate] = useState(today())

  const submit = async () => {
    await api.patch(`/loans/${loan.id}/hand-over`, { handover_date: date || null })
    toast.success(`${loan.asset?.code ?? 'Aset'} diserahkan ke ${loan.borrower_name}.`)
    onDone()
  }

  return (
    <ActionModal title={`Serahkan • ${loan.asset?.code}`} submitLabel="Serahkan" onClose={onClose} onSubmit={submit}>
      {(errors) => (
        <>
          <p className="text-sm text-slate-600">
            {loan.asset?.name} untuk {loan.borrower_name}, {loan.borrower_unit}. Rencana {formatDate(loan.borrow_date)} s.d.{' '}
            {formatDate(loan.due_date)}. Setelah diserahkan, status aset menjadi Dipinjam.
          </p>
          <FormField label="Tanggal penyerahan" required error={errors.handover_date}>
            <Input type="date" value={date} max={today()} onChange={(e) => setDate(e.target.value)} error={errors.handover_date} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
