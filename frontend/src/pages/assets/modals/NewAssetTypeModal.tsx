import { useState } from 'react'
import EntityCombobox from '../../../components/ui/EntityCombobox'
import { FormField } from '../../../components/ui/FormField'
import { useToast } from '../../../components/ui/toast-context'
import { errorMessage, fieldErrors } from '../../../lib/api'
import { createAssetType, createCategory } from '../../../lib/lookups'
import { useCategories } from '../../../lib/queries/master'
import { useErrorToast } from '../../../lib/useErrorToast'
import type { AssetType } from '../../../types/asset'
import ActionModal from './ActionModal'

interface Props {
  name: string
  onClose: () => void
  onCreated: (type: AssetType) => void
}

/** id sementara untuk kategori baru yang belum disimpan. */
const NEW_ID = -1

/** Tambah jenis aset dari form aset: cukup pilih kategori (wajib); kode jenis dibuat otomatis oleh server. */
export default function NewAssetTypeModal({ name, onClose, onCreated }: Props) {
  const toast = useToast()
  const [categoryId, setCategoryId] = useState<number | null>(null)
  // Kategori baru belum disimpan ke server: baru dibuat bersamaan dengan jenis saat "Simpan jenis".
  const [newCategory, setNewCategory] = useState('')
  const [typed, setTyped] = useState('')
  const { data: categories, error } = useCategories()
  useErrorToast(error)

  const options = categories.map((c) => ({ id: c.id, label: c.name }))
  if (newCategory) options.push({ id: NEW_ID, label: newCategory })

  // Nama kategori yang akan dipakai: pilihan baru, atau ketikan yang belum dipilih (otomatis dibuat bila belum ada).
  const pendingName = categoryId === NEW_ID ? newCategory : categoryId === null ? typed.trim() : ''
  const canSubmit = (categoryId !== null && categoryId !== NEW_ID) || !!pendingName

  const submit = async () => {
    let id = categoryId
    if (id === null || id === NEW_ID) {
      const existing = categories.find((c) => c.name.toLowerCase() === pendingName.toLowerCase())
      try {
        id = existing ? existing.id : (await createCategory(pendingName)).id
      } catch (e) {
        toast.error(fieldErrors(e).name ?? errorMessage(e))
        throw e
      }
    }
    onCreated(await createAssetType(id, name))
  }

  return (
    <ActionModal
      title={`Tambah jenis '${name}'`}
      submitLabel="Simpan jenis"
      submitDisabled={!canSubmit}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          {(errors.name || errors.code) && <p className="text-xs font-medium text-bad-700">{errors.name ?? errors.code}</p>}
          <FormField label="Kategori" required error={errors.category_id}>
            <EntityCombobox
              value={categoryId}
              options={options}
              onChange={setCategoryId}
              onTextChange={(t) => {
                setTyped(t)
                if (t !== newCategory) setNewCategory('')
              }}
              onCreate={async (n) => {
                setNewCategory(n)
                return { id: NEW_ID, label: n }
              }}
              placeholder="Ketik untuk mencari atau menambah kategori…"
              error={errors.category_id}
            />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
