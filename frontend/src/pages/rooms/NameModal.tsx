import { useState } from 'react'
import { FormField, Input } from '../../components/ui/FormField'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  title: string
  label: string
  placeholder?: string
  onClose: () => void
  /** Lempar error agar pesan server tampil di modal. */
  onSubmit: (name: string, isStorage: boolean) => Promise<void>
  /** Ruangan: tampilkan pilihan Gudang / tempat penyimpanan (sama dengan Data Master). */
  withStorage?: boolean
}

/** Modal satu isian nama (tambah gedung / ruangan). */
export default function NameModal({ title, label, placeholder, onClose, onSubmit, withStorage }: Props) {
  const [name, setName] = useState('')
  const [isStorage, setIsStorage] = useState(false)

  return (
    <ActionModal title={title} submitLabel="Tambah" submitDisabled={!name.trim()} onClose={onClose} onSubmit={() => onSubmit(name.trim(), isStorage)}>
      {(errors) => (
        <>
          <FormField label={label} required error={errors.name}>
            <Input value={name} onChange={(e) => setName(e.target.value)} error={errors.name} placeholder={placeholder} maxLength={100} autoFocus />
          </FormField>
          {withStorage && (
            <div>
              <label className="flex w-fit items-center gap-1.5 text-xs font-medium text-slate-600">
                <input type="checkbox" className="h-3.5 w-3.5" checked={isStorage} onChange={(e) => setIsStorage(e.target.checked)} />
                Gudang / tempat penyimpanan
              </label>
              {errors.is_storage && <span className="block text-xs font-medium text-bad-700">{errors.is_storage}</span>}
              {isStorage && (
                <p className="mt-1 text-xs text-slate-500">Hanya ruangan gudang yang bisa menjadi lokasi simpan stok, dan tidak dihitung dalam kesiapan ruangan.</p>
              )}
            </div>
          )}
        </>
      )}
    </ActionModal>
  )
}
