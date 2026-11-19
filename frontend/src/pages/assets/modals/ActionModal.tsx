import { useId, useState, type FormEvent, type ReactNode } from 'react'
import Button from '../../../components/ui/Button'
import Modal from '../../../components/ui/Modal'
import { errorMessage, fieldErrors } from '../../../lib/api'

interface Props {
  title: string
  submitLabel: string
  danger?: boolean
  submitDisabled?: boolean
  /** Alasan tombol simpan nonaktif, tampil di dekat tombol selama nonaktif (T45, temuan UAT A3). */
  submitHint?: string
  onClose: () => void
  /** Lempar error (axios) agar pesan dan error per field tampil; selesai tanpa error = sukses. */
  onSubmit: () => Promise<void>
  children: (errors: Record<string, string>) => ReactNode
}

/** Kerangka modal aksi aset: form, tombol, loading, pesan error server, dan error 422 per field. */
export default function ActionModal({ title, submitLabel, danger, submitDisabled, submitHint, onClose, onSubmit, children }: Props) {
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  // Id unik per modal: popup bertumpuk (mis. tambah item katalog dari form permintaan) tidak saling mengirim form.
  const formId = useId()

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setSaving(true)
    setMessage('')
    setErrors({})
    try {
      await onSubmit()
    } catch (error) {
      setErrors(fieldErrors(error))
      setMessage(errorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open
      title={title}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Batal</Button>
          <Button type="submit" form={formId} variant={danger ? 'danger' : 'primary'} loading={saving} disabled={submitDisabled}>
            {submitLabel}
          </Button>
          {/* HP: tampil di atas tombol (footer tersusun terbalik); laptop: di kiri tombol. */}
          {submitDisabled && submitHint && (
            <p role="status" className="self-center text-sm font-medium text-bad-700 sm:order-first sm:mr-auto">{submitHint}</p>
          )}
        </>
      }
    >
      <form noValidate id={formId} onSubmit={submit} className="space-y-3">
        {message && (
          <div role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">
            {message}
          </div>
        )}
        {children(errors)}
      </form>
    </Modal>
  )
}
