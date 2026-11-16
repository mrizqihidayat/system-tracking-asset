import { useState } from 'react'
import DepartmentCombobox from '../../components/DepartmentCombobox'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { useDepartments } from '../../lib/queries/master'
import type { RequestCatalog } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  item: RequestCatalog | null
  /** Nama awal saat ditambah dari ketikan di form permintaan. */
  initialName?: string
  onClose: () => void
  /** Item yang disimpan dikirim balik, mis. agar langsung terpilih di form permintaan. */
  onDone: (saved: RequestCatalog) => void
}

/** Tambah/ubah item katalog perlengkapan dari departemen lain (SPEC §9). */
export default function CatalogFormModal({ item, initialName, onClose, onDone }: Props) {
  const toast = useToast()
  const departments = useDepartments().data.filter((d) => !d.is_internal)
  const [name, setName] = useState(item?.item_name ?? initialName ?? '')
  const [providerId, setProviderId] = useState<number | null>(item?.provider_department?.id ?? null)
  const [mustReturn, setMustReturn] = useState(item?.must_return ?? false)
  const [leadTime, setLeadTime] = useState(String(item?.lead_time_days ?? 3))
  const [note, setNote] = useState(item?.note ?? '')

  const submit = async () => {
    const payload = {
      item_name: name.trim(),
      provider_department_id: providerId,
      must_return: mustReturn,
      lead_time_days: Number(leadTime),
      note: note.trim() || null,
    }
    const res = item
      ? await api.put<{ data: RequestCatalog }>(`/request-catalog/${item.id}`, payload)
      : await api.post<{ data: RequestCatalog }>('/request-catalog', payload)
    toast.success(item ? 'Katalog diperbarui.' : 'Item katalog ditambahkan.')
    onDone(res.data.data)
  }

  return (
    <ActionModal
      title={item ? 'Ubah item katalog' : 'Tambah item katalog'}
      submitLabel="Simpan"
      submitDisabled={!name.trim() || !providerId}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <FormField label="Item" required error={errors.item_name}>
            <Input value={name} onChange={(e) => setName(e.target.value)} error={errors.item_name} maxLength={150} />
          </FormField>
          <FormField label="Departemen penyedia" required error={errors.provider_department_id}>
            <DepartmentCombobox value={providerId} onChange={setProviderId} departments={departments} error={errors.provider_department_id} />
          </FormField>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Perlu dikembalikan" required error={errors.must_return}>
              <Select value={mustReturn ? '1' : '0'} onChange={(e) => setMustReturn(e.target.value === '1')}>
                <option value="0">Tidak</option>
                <option value="1">Ya</option>
              </Select>
            </FormField>
            <FormField label="Lead time (hari)" required error={errors.lead_time_days}>
              <Input type="number" min={0} max={365} value={leadTime} onChange={(e) => setLeadTime(e.target.value)} error={errors.lead_time_days} />
            </FormField>
          </div>
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
  )
}
