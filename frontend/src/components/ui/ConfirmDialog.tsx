import type { ReactNode } from 'react'
import Button from './Button'
import Modal from './Modal'

interface Props {
  open: boolean
  title: string
  children: ReactNode
  confirmLabel?: string
  danger?: boolean
  loading?: boolean
  onConfirm: () => void
  onClose: () => void
}

export default function ConfirmDialog({
  open, title, children, confirmLabel = 'Ya, lanjutkan', danger, loading, onConfirm, onClose,
}: Props) {
  return (
    <Modal
      open={open}
      title={title}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Batal</Button>
          <Button variant={danger ? 'danger' : 'primary'} loading={loading} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      <div className="text-sm text-slate-700">{children}</div>
    </Modal>
  )
}
