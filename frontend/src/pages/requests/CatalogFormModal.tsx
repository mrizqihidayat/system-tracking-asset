import { useEffect, useState } from 'react'
import Combobox from '../../components/ui/Combobox'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { fetchDepartments } from '../../lib/lookups'
import type { Department } from '../../types/asset'
import type { RequestCatalog } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  item: RequestCatalog | null
  onClose: () => void
  onDone: () => void
}

/** Tambah/ubah item katalog perlengkapan dari unit lain (SPEC §9). */
export default function CatalogFormModal({ item, onClose, onDone }: Props) {
  const toast = useToast()
  const [departments, setDepartments] = useState<Department[]>([])
  const [methods, setMethods] = useState<string[]>([])
  const [name, setName] = useState(item?.item_name ?? '')
  const [providerId, setProviderId] = useState<number | null>(item?.provider_department?.id ?? null)
  const [method, setMethod] = useState(item?.request_method ?? '')
  const [mustReturn, setMustReturn] = useState(item?.must_return ?? false)
  const [leadTime, setLeadTime] = useState(String(item?.lead_time_days ?? 3))
  const [note, setNote] = useState(item?.note ?? '')

  useEffect(() => {
    fetchDepartments().then((d) => setDepartments(d.filter((x) => !x.is_internal))).catch(() => undefined)
    api.get<{ data: string[] }>('/lookups/request-methods').then((r) => setMethods(r.data.data)).catch(() => undefined)
  }, [])

  const submit = async () => {
    const payload = {
      item_name: name.trim(),
      provider_department_id: providerId,
      request_method: method.trim(),
      must_return: mustReturn,
      lead_time_days: Number(leadTime),
      note: note.trim() || null,
    }
    if (item) await api.put(`/request-catalog/${item.id}`, payload)
    else await api.post('/request-catalog', payload)
    toast.success(item ? 'Katalog diperbarui.' : 'Item katalog ditambahkan.')
    onDone()
  }

  return (
    <ActionModal
      title={item ? 'Ubah item katalog' : 'Tambah item katalog'}
      submitLabel="Simpan"
      submitDisabled={!name.trim() || !providerId || !method.trim()}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <FormField label="Item" required error={errors.item_name}>
            <Input value={name} onChange={(e) => setName(e.target.value)} error={errors.item_name} maxLength={150} />
          </FormField>
          <FormField label="Unit penyedia" required error={errors.provider_department_id}>
            <Select value={providerId ?? ''} onChange={(e) => setProviderId(Number(e.target.value) || null)} error={errors.provider_department_id}>
              <option value="">Pilih unit</option>
              {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
            </Select>
          </FormField>
          <FormField label="Cara permintaan" required error={errors.request_method}>
            <Combobox
              value={method}
              onChange={setMethod}
              options={methods.map((m) => ({ id: m, label: m }))}
              placeholder="Memo, Form, Email, WhatsApp…"
              error={errors.request_method}
            />
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
