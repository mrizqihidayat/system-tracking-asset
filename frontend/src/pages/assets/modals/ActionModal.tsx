import { useState, type FormEvent, type ReactNode } from 'react'
import Button from '../../../components/ui/Button'
import Modal from '../../../components/ui/Modal'
import { errorMessage, fieldErrors } from '../../../lib/api'

interface Props {
  title: string
  submitLabel: string
  danger?: boolean
  submitDisabled?: boolean
  onClose: () => void
  /** Lempar error (axios) agar pesan dan error per field tampil; selesai tanpa error = sukses. */
  onSubmit: () => Promise<void>
  children: (errors: Record<string, string>) => ReactNode
}

/** Kerangka modal aksi aset: form, tombol, loading, pesan error server, dan error 422 per field. */
export default function ActionModal({ title, submitLabel, danger, submitDisabled, onClose, onSubmit, children }: Props) {
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})

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
          <Button type="submit" form="action-form" variant={danger ? 'danger' : 'primary'} loading={saving} disabled={submitDisabled}>
            {submitLabel}
          </Button>
        </>
      }
    >
      <form id="action-form" onSubmit={submit} className="space-y-3">
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
