import { useEffect, useState } from 'react'
import Combobox from '../../components/ui/Combobox'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { useDepartments, useRequestCatalog, useStorageLocations } from '../../lib/queries/master'
import type { ConsumableItem, ConsumableUsage } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'

interface Props {
  item: ConsumableItem | null
  onClose: () => void
  onDone: () => void
}

/** Tambah/ubah barang habis pakai (SPEC §10). Ubah data tidak mengubah jumlah. */
export default function ConsumableFormModal({ item, onClose, onDone }: Props) {
  const toast = useToast()
  const { data: departments } = useDepartments()
  const { data: catalog } = useRequestCatalog()
  const { data: locations } = useStorageLocations()
  const [usedIn, setUsedIn] = useState<ConsumableUsage[]>([])
  const [name, setName] = useState(item?.name ?? '')
  const [category, setCategory] = useState(item?.category ?? '')
  const [unit, setUnit] = useState(item?.unit ?? 'pcs')
  const [quantity, setQuantity] = useState(String(item?.quantity ?? 0))
  const [minQuantity, setMinQuantity] = useState(String(item?.min_quantity ?? 0))
  const [providerId, setProviderId] = useState<number | null>(item?.provider_department?.id ?? null)
  const [catalogId, setCatalogId] = useState<number | null>(item?.catalog?.id ?? null)
  const [location, setLocation] = useState(item?.storage_location ?? '')
  const [note, setNote] = useState(item?.note ?? '')

  useEffect(() => {
    if (item) {
      api
        .get<{ meta: { used_in_rooms: ConsumableUsage[] } }>(`/consumables/${item.id}`)
        .then((r) => setUsedIn(r.data.meta.used_in_rooms))
        .catch(() => undefined)
    }
  }, [item])

  const catalogOptions = catalog.filter((c) => c.provider_department?.id === providerId)

  const submit = async () => {
    const payload: Record<string, unknown> = {
      name: name.trim(),
      category: category.trim() || null,
      unit: unit.trim(),
      min_quantity: Number(minQuantity),
      provider_department_id: providerId,
      catalog_id: catalogOptions.some((c) => c.id === catalogId) ? catalogId : null,
      storage_location: location.trim() || null,
      note: note.trim() || null,
    }
    if (item) {
      await api.put(`/consumables/${item.id}`, payload)
    } else {
      await api.post('/consumables', { ...payload, quantity: Number(quantity) })
    }
    toast.success(item ? 'Data barang diperbarui.' : 'Barang ditambahkan.')
    onDone()
  }

  return (
    <ActionModal
      title={item ? `Ubah ${item.name}` : 'Tambah barang habis pakai'}
      submitLabel="Simpan"
      submitDisabled={!name.trim() || !unit.trim() || !providerId}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Nama barang" required error={errors.name}>
              <Input value={name} onChange={(e) => setName(e.target.value)} error={errors.name} maxLength={150} />
            </FormField>
            <FormField label="Kategori" error={errors.category}>
              <Input value={category} onChange={(e) => setCategory(e.target.value)} placeholder="Misal: ATK" error={errors.category} maxLength={100} />
            </FormField>
          </div>
          <div className="grid grid-cols-3 gap-3">
            <FormField label="Satuan" required error={errors.unit}>
              <Input value={unit} onChange={(e) => setUnit(e.target.value)} error={errors.unit} maxLength={20} />
            </FormField>
            <FormField label={item ? 'Jumlah' : 'Jumlah awal'} required={!item} error={errors.quantity}>
              <Input
                type="number"
                min={0}
                value={item ? item.quantity : quantity}
                disabled={!!item}
                onChange={(e) => setQuantity(e.target.value)}
                error={errors.quantity}
              />
            </FormField>
            <FormField label="Minimum" required error={errors.min_quantity}>
              <Input type="number" min={0} value={minQuantity} onChange={(e) => setMinQuantity(e.target.value)} error={errors.min_quantity} />
            </FormField>
          </div>
          {item && <p className="-mt-1 text-xs text-slate-500">Jumlah diubah lewat tombol − / + atau klik angka di tabel.</p>}
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Minta ke (departemen penyedia)" required error={errors.provider_department_id}>
              <Select value={providerId ?? ''} onChange={(e) => setProviderId(Number(e.target.value) || null)} error={errors.provider_department_id}>
                <option value="">Pilih departemen</option>
                {departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
              </Select>
            </FormField>
            <FormField label="Katalog permintaan (opsional)" error={errors.catalog_id}>
              <Select clearable value={catalogId ?? ''} disabled={!providerId} onChange={(e) => setCatalogId(Number(e.target.value) || null)} error={errors.catalog_id}>
                <option value="">{catalogOptions.length ? 'Tidak ada' : 'Tidak ada katalog dari departemen ini'}</option>
                {catalogOptions.map((c) => <option key={c.id} value={c.id}>{c.item_name}</option>)}
              </Select>
            </FormField>
          </div>
          <FormField label="Lokasi simpan (opsional)" error={errors.storage_location}>
            <Combobox
              value={location}
              onChange={setLocation}
              options={locations.map((l) => ({ id: l, label: l }))}
              placeholder="Misal: Main Office SHD"
              error={errors.storage_location}
            />
          </FormField>
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
          {usedIn.length > 0 && (
            <p className="text-xs text-slate-500">
              Dipakai di ruangan: {usedIn.map((u) => `${u.room} (${u.building})`).join(', ')}
            </p>
          )}
        </>
      )}
    </ActionModal>
  )
}
