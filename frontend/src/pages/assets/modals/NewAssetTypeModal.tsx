import { useEffect, useState } from 'react'
import EntityCombobox from '../../../components/ui/EntityCombobox'
import { FormField, Input } from '../../../components/ui/FormField'
import { useToast } from '../../../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../../../lib/api'
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

/** Tambah jenis aset dari form aset; jenis wajib punya kategori, kode terisi saran server dan bisa diubah. */
export default function NewAssetTypeModal({ name, onClose, onCreated }: Props) {
  const toast = useToast()
  const [categoryId, setCategoryId] = useState<number | null>(null)
  const [suggested, setSuggested] = useState('')
  const [typedCode, setTypedCode] = useState<string | null>(null)
  const code = typedCode ?? suggested
  const { data: categories, error: categoriesError } = useCategories()
  useErrorToast(categoriesError)

  useEffect(() => {
    let cancelled = false
    api
      .get<{ data: { code: string | null } }>('/asset-types/code-suggestion', { params: { name } })
      .then((res) => !cancelled && setSuggested(res.data.data.code ?? ''))
      .catch((e) => !cancelled && toast.error(errorMessage(e)))
    return () => {
      cancelled = true
    }
  }, [name, toast])

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
    onCreated(await createAssetType(categoryId!, name, code.trim()))
  }

  return (
    <ActionModal
      title={`Tambah jenis '${name}'`}
      submitLabel="Simpan jenis"
      submitDisabled={!code.trim() || !categoryId}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          {errors.name && <p className="text-xs font-medium text-bad-700">{errors.name}</p>}
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
          <FormField label="Kode singkat" required error={errors.code}>
            <Input
              value={code}
              onChange={(e) => setTypedCode(e.target.value.toUpperCase())}
              placeholder="Misal: PRJ"
              maxLength={5}
              error={errors.code}
            />
          </FormField>
          <p className="text-xs text-slate-500">2–5 huruf atau angka, dipakai di kode aset. Kode yang pernah dipakai jenis lain ditolak.</p>
        </>
      )}
    </ActionModal>
  )
}
