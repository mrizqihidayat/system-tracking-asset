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

/** Tambah jenis aset dari form aset: cukup pilih kategori (wajib); kode jenis dibuat otomatis oleh server. */
export default function NewAssetTypeModal({ name, onClose, onCreated }: Props) {
  const toast = useToast()
  const [categoryId, setCategoryId] = useState<number | null>(null)
  const { data: categories, error } = useCategories()
  useErrorToast(error)

  const addCategory = async (categoryName: string) => {
    try {
      const c = await createCategory(categoryName)
      return { id: c.id, label: c.name }
    } catch (e) {
      toast.error(fieldErrors(e).name ?? errorMessage(e))
      throw e
    }
  }

  return (
    <ActionModal
      title={`Tambah jenis '${name}'`}
      submitLabel="Simpan jenis"
      submitDisabled={!categoryId}
      onClose={onClose}
      onSubmit={async () => onCreated(await createAssetType(categoryId!, name))}
    >
      {(errors) => (
        <>
          {(errors.name || errors.code) && <p className="text-xs font-medium text-bad-700">{errors.name ?? errors.code}</p>}
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
        </>
      )}
    </ActionModal>
  )
}
