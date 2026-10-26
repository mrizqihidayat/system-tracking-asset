import { useState } from 'react'
import { FormField, Input } from '../../components/ui/FormField'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  title: string
  label: string
  onClose: () => void
  /** Lempar error agar pesan server tampil di modal. */
  onSubmit: (name: string) => Promise<void>
}

/** Modal satu isian nama (tambah gedung / ruangan). */
export default function NameModal({ title, label, onClose, onSubmit }: Props) {
  const [name, setName] = useState('')

  return (
    <ActionModal title={title} submitLabel="Tambah" submitDisabled={!name.trim()} onClose={onClose} onSubmit={() => onSubmit(name.trim())}>
      {(errors) => (
        <FormField label={label} required error={errors.name}>
          <Input value={name} onChange={(e) => setName(e.target.value)} error={errors.name} maxLength={100} autoFocus />
        </FormField>
      )}
    </ActionModal>
  )
}
