import { useState } from 'react'
import EntityCombobox from '../../../components/ui/EntityCombobox'
import { FormField, Input } from '../../../components/ui/FormField'
import { useToast } from '../../../components/ui/toast-context'
import { errorMessage, fieldErrors } from '../../../lib/api'
import { createAssetType, createCategory } from '../../../lib/lookups'
import { useCategories } from '../../../lib/queries/master'
import { useErrorToast } from '../../../lib/useErrorToast'
import type { AssetType } from '../../../types/asset'
import ActionModal from './ActionModal'

interface Props {
  initialName: string
  onClose: () => void
  onCreated: (type: AssetType) => void
}

/** Tambah jenis aset dari form Tambah aset; jenis wajib punya kategori, kode dibuat otomatis oleh server. */
export default function NewAssetTypeModal({ initialName, onClose, onCreated }: Props) {
  const toast = useToast()
  const [name, setName] = useState(initialName)
  const [categoryId, setCategoryId] = useState<number | null>(null)
  const { data: categories, error: categoriesError } = useCategories()
  useErrorToast(categoriesError)

  const addCategory = async (categoryName: string) => {
    try {
      const c = await createCategory(categoryName)
      return { id: c.id, label: c.name }
    } catch (e) {
      toast.error(fieldErrors(e).name ?? errorMessage(e))
      throw e
    }
  }

  const submit = async () => {
    onCreated(await createAssetType(categoryId!, name.trim()))
  }

  return (
    <ActionModal
      title="Tambah jenis aset"
      submitLabel="Simpan jenis"
      submitDisabled={!name.trim() || !categoryId}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <FormField label="Nama jenis" required error={errors.name}>
            <Input value={name} onChange={(e) => setName(e.target.value)} error={errors.name} maxLength={100} />
          </FormField>
          <FormField label="Kategori" required error={errors.category_id}>
            <EntityCombobox
              value={categoryId}
              options={categories.map((c) => ({ id: c.id, label: c.name }))}
              onChange={setCategoryId}
              onCreate={addCategory}
              placeholder="Ketik untuk mencari atau menambah kategori…"
              error={errors.category_id}
            />
          </FormField>
          <p className="text-xs text-slate-500">Kode jenis dibuat otomatis dari nama dan bisa diubah di Data Master.</p>
        </>
      )}
    </ActionModal>
  )
}
