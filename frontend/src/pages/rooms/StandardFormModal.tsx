import { useEffect, useState } from 'react'
import { FormField, Input, Select } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { fetchAssetTypes, fetchDepartments } from '../../lib/lookups'
import type { AssetType, Department } from '../../types/asset'
import type { CatalogItem, Consumable, CountMethod, RoomStandard } from '../../types/room'
import ActionModal from '../assets/modals/ActionModal'

type FulfillmentType = '' | 'stok_shd' | 'ambil_dari' | 'minta_ke'

interface Props {
  roomId: number
  roomName: string
  /** null = tambah baris baru. */
  standard: RoomStandard | null
  onClose: () => void
  onDone: () => void
}

/** Form satu baris standar ruangan (SPEC §8): pemenuhan hanya satu, sesuai sumber SHD / unit lain. */
export default function StandardFormModal({ roomId, roomName, standard, onClose, onDone }: Props) {
  const toast = useToast()
  const [departments, setDepartments] = useState<Department[]>([])
  const [assetTypes, setAssetTypes] = useState<AssetType[]>([])
  const [consumables, setConsumables] = useState<Consumable[]>([])
  const [catalog, setCatalog] = useState<CatalogItem[]>([])

  const [itemName, setItemName] = useState(standard?.item_name ?? '')
  const [sourceId, setSourceId] = useState<number | null>(standard?.source_department?.id ?? null)
  const [quantity, setQuantity] = useState(String(standard?.standard_quantity ?? 1))
  const [method, setMethod] = useState<CountMethod>(standard?.count_method ?? 'Manual')
  const [assetTypeId, setAssetTypeId] = useState<number | null>(standard?.asset_type?.id ?? null)
  const f = standard?.fulfillment
  const [fType, setFType] = useState<FulfillmentType>(f?.type ?? '')
  const [consumableId, setConsumableId] = useState<number | null>(f?.type === 'stok_shd' ? f.consumable.id : null)
  const [note, setNote] = useState(f?.type === 'ambil_dari' ? f.note : '')
  const [catalogId, setCatalogId] = useState<number | null>(f?.type === 'minta_ke' ? f.catalog.id : null)

  useEffect(() => {
    Promise.all([
      fetchDepartments(),
      fetchAssetTypes(),
      api.get<{ data: Consumable[] }>('/consumables', { params: { per_page: 100 } }),
      api.get<{ data: CatalogItem[] }>('/request-catalog'),
    ])
      .then(([d, t, c, k]) => {
        setDepartments(d)
        setAssetTypes(t)
        setConsumables(c.data.data)
        setCatalog(k.data.data)
        if (!standard) setSourceId((cur) => cur ?? d.find((x) => x.is_internal)?.id ?? null)
      })
      .catch((e) => toast.error(errorMessage(e)))
  }, [standard, toast])

  const source = departments.find((d) => d.id === sourceId)
  const isShd = source?.is_internal ?? false

  // Pilihan pemenuhan yang sah untuk sumber & cara hitung saat ini.
  const fOptions: { value: FulfillmentType; label: string }[] = [
    { value: '', label: 'Tidak ada' },
    ...(isShd
      ? [
          ...(method === 'Manual' ? [{ value: 'stok_shd' as const, label: 'Dari stok SHD' }] : []),
          { value: 'ambil_dari' as const, label: 'Ambil dari (lokasi)' },
        ]
      : [{ value: 'minta_ke' as const, label: 'Minta ke unit lain' }]),
  ]
  const effectiveF = fOptions.some((o) => o.value === fType) ? fType : ''
  const catalogOptions = catalog.filter((c) => !sourceId || c.provider_department?.id === sourceId)

  const submit = async () => {
    const payload = {
      item_name: itemName.trim(),
      source_department_id: sourceId,
      standard_quantity: Number(quantity),
      count_method: method,
      asset_type_id: method === 'QR' ? assetTypeId : null,
      consumable_id: effectiveF === 'stok_shd' ? consumableId : null,
      fulfillment_note: effectiveF === 'ambil_dari' ? note.trim() || null : null,
      catalog_id: effectiveF === 'minta_ke' ? catalogId : null,
    }
    if (standard) await api.put(`/room-standards/${standard.id}`, payload)
    else await api.post(`/rooms/${roomId}/standards`, payload)
    toast.success(standard ? 'Standar diperbarui.' : 'Standar ditambahkan.')
    onDone()
  }

  return (
    <ActionModal
      title={`${standard ? 'Ubah' : 'Tambah'} standar — ${roomName}`}
      submitLabel="Simpan"
      submitDisabled={!itemName.trim() || !sourceId}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <FormField label="Nama perlengkapan" required error={errors.item_name}>
            <Input value={itemName} onChange={(e) => setItemName(e.target.value)} error={errors.item_name} maxLength={150} />
          </FormField>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Sumber" required error={errors.source_department_id}>
              <Select value={sourceId ?? ''} onChange={(e) => setSourceId(Number(e.target.value) || null)} error={errors.source_department_id}>
                <option value="">Pilih sumber</option>
                {departments.map((d) => <option key={d.id} value={d.id}>{d.is_internal ? `Milik ${d.name}` : d.name}</option>)}
              </Select>
            </FormField>
            <FormField label="Jumlah standar" required error={errors.standard_quantity}>
              <Input type="number" min={1} value={quantity} onChange={(e) => setQuantity(e.target.value)} error={errors.standard_quantity} />
            </FormField>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Cara hitung" required error={errors.count_method}>
              <Select value={method} onChange={(e) => setMethod(e.target.value as CountMethod)} error={errors.count_method}>
                <option value="Manual">Manual (diisi saat cek)</option>
                <option value="QR">QR (otomatis dari aset)</option>
              </Select>
            </FormField>
            {method === 'QR' && (
              <FormField label="Jenis aset" required error={errors.asset_type_id}>
                <Select value={assetTypeId ?? ''} onChange={(e) => setAssetTypeId(Number(e.target.value) || null)} error={errors.asset_type_id}>
                  <option value="">Pilih jenis aset</option>
                  {assetTypes.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.code})</option>)}
                </Select>
              </FormField>
            )}
          </div>
          <FormField label="Keterangan pemenuhan" error={errors.fulfillment}>
            <Select value={effectiveF} onChange={(e) => setFType(e.target.value as FulfillmentType)} error={errors.fulfillment}>
              {fOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </Select>
          </FormField>
          {effectiveF === 'stok_shd' && (
            <FormField label="Item stok SHD" required error={errors.consumable_id}>
              <Select value={consumableId ?? ''} onChange={(e) => setConsumableId(Number(e.target.value) || null)} error={errors.consumable_id}>
                <option value="">Pilih item stok</option>
                {consumables.map((c) => <option key={c.id} value={c.id}>{c.name} (stok {c.quantity} {c.unit})</option>)}
              </Select>
            </FormField>
          )}
          {effectiveF === 'ambil_dari' && (
            <FormField label="Ambil dari" required error={errors.fulfillment_note}>
              <Input value={note} onChange={(e) => setNote(e.target.value)} placeholder="Misal: Gudang Gedung IHS" error={errors.fulfillment_note} maxLength={255} />
            </FormField>
          )}
          {effectiveF === 'minta_ke' && (
            <FormField label="Item katalog permintaan" required error={errors.catalog_id}>
              <Select value={catalogId ?? ''} onChange={(e) => setCatalogId(Number(e.target.value) || null)} error={errors.catalog_id}>
                <option value="">{catalogOptions.length ? 'Pilih item katalog' : 'Belum ada katalog untuk unit ini'}</option>
                {catalogOptions.map((c) => <option key={c.id} value={c.id}>{c.item_name}</option>)}
              </Select>
            </FormField>
          )}
        </>
      )}
    </ActionModal>
  )
}
