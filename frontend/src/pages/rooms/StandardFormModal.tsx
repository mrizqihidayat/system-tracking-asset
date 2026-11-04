import { TriangleAlert } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { FormField, Input, Select } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { useConsumables } from '../../lib/queries/lists'
import { useAssetTypes, useDepartments, useRequestCatalog } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import type { RoomStandard } from '../../types/room'
import ActionModal from '../assets/modals/ActionModal'

/** Pilihan barang stok (maks. 100 barang, sama dengan sebelumnya). */
const STOCK_PARAMS = { per_page: 100 }

type Kind = 'asset' | 'stock' | 'other'

const KINDS: { value: Kind; label: string; hint: string }[] = [
  { value: 'asset', label: 'Aset', hint: 'Punya kode & QR, jumlah Ada dihitung otomatis' },
  { value: 'stock', label: 'Stok Habis Pakai', hint: 'Barang di Stok SHD, jumlah Ada diisi saat cek' },
  { value: 'other', label: 'Barang unit lain', hint: 'Tidak disimpan SHD, kekurangan diminta ke unitnya' },
]

const kindOf = (standard: RoomStandard | null): Kind | null => {
  if (!standard || standard.needs_completion) return null
  if (standard.count_method === 'QR') return 'asset'
  return standard.fulfillment?.type === 'unit_lain' ? 'other' : 'stock'
}

interface Props {
  roomId: number
  roomName: string
  /** null = tambah baris baru. */
  standard: RoomStandard | null
  onClose: () => void
  onDone: () => void
}

/** Form satu baris standar ruangan (SPEC §8): pilih tipe dulu; pemenuhan baris stok mengikuti data stok. */
export default function StandardFormModal({ roomId, roomName, standard, onClose, onDone }: Props) {
  const toast = useToast()
  const { data: departments, error: departmentsError } = useDepartments()
  const { data: assetTypes, error: typesError } = useAssetTypes()
  const { data: catalog, error: catalogError } = useRequestCatalog()
  const stock = useConsumables(STOCK_PARAMS)
  useErrorToast(departmentsError, typesError, catalogError, stock.error)
  const consumables = stock.data?.data ?? []

  const [kind, setKind] = useState<Kind | null>(kindOf(standard))
  const [itemName, setItemName] = useState(standard?.item_name ?? '')
  const [quantity, setQuantity] = useState(String(standard?.standard_quantity ?? 1))
  const [assetTypeId, setAssetTypeId] = useState<number | null>(standard?.asset_type?.id ?? null)
  const f = standard?.fulfillment
  const [consumableId, setConsumableId] = useState<number | null>(f?.type === 'stok' ? f.consumable.id : null)
  const [otherSourceId, setOtherSourceId] = useState<number | null>(f?.type === 'unit_lain' ? (f.provider_department?.id ?? null) : null)
  const [catalogId, setCatalogId] = useState<number | null>(f?.type === 'unit_lain' ? (f.catalog?.id ?? null) : null)
  // Baris baru: sumber aset bawaan SHD sampai dipilih lain (undefined = belum dipilih).
  const [pickedSourceId, setSourceId] = useState<number | null | undefined>(standard?.count_method === 'QR' ? (standard.source_department?.id ?? null) : undefined)
  const sourceId = pickedSourceId === undefined ? (departments.find((d) => d.is_internal)?.id ?? null) : pickedSourceId

  const consumable = consumables.find((c) => c.id === consumableId)
  const otherUnits = departments.filter((d) => !d.is_internal)
  const catalogOptions = catalog.filter((c) => c.provider_department?.id === otherSourceId)

  // Nama mengikuti pilihan selama belum diubah sendiri.
  const followName = (previous: string | undefined, next: string | undefined) =>
    setItemName((name) => (!name.trim() || name === previous ? (next ?? '') : name))

  const pickAssetType = (id: number | null) => {
    followName(assetTypes.find((t) => t.id === assetTypeId)?.name, assetTypes.find((t) => t.id === id)?.name)
    setAssetTypeId(id)
  }
  const pickConsumable = (id: number | null) => {
    followName(consumable?.name, consumables.find((c) => c.id === id)?.name)
    setConsumableId(id)
  }
  const pickOtherSource = (id: number | null) => {
    setOtherSourceId(id)
    setCatalogId(null)
  }
  const pickCatalog = (id: number | null) => {
    followName(catalog.find((c) => c.id === catalogId)?.item_name, catalog.find((c) => c.id === id)?.item_name)
    setCatalogId(id)
  }

  const submit = async () => {
    const payload = {
      item_name: itemName.trim(),
      standard_quantity: Number(quantity),
      count_method: kind === 'asset' ? 'QR' : 'Manual',
      source_department_id: kind === 'asset' ? sourceId : kind === 'other' ? otherSourceId : null,
      asset_type_id: kind === 'asset' ? assetTypeId : null,
      consumable_id: kind === 'stock' ? consumableId : null,
      catalog_id: kind === 'other' ? catalogId : null,
    }
    if (standard) await api.put(`/room-standards/${standard.id}`, payload)
    else await api.post(`/rooms/${roomId}/standards`, payload)
    toast.success(standard ? 'Standar diperbarui.' : 'Standar ditambahkan.')
    onDone()
  }

  const selectionMissing = kind === 'asset' ? !assetTypeId || !sourceId : kind === 'stock' ? !consumableId : !otherSourceId

  return (
    <ActionModal
      title={`${standard ? 'Ubah' : 'Tambah'} standar — ${roomName}`}
      submitLabel="Simpan"
      submitDisabled={!kind || selectionMissing || !itemName.trim()}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          {standard?.needs_completion && (
            <div role="alert" className="flex gap-2 rounded-lg bg-warn-50 px-3 py-2.5 text-sm text-warn-700">
              <TriangleAlert size={18} className="mt-0.5 shrink-0" />
              <span>Baris ini perlu dilengkapi. Pilih tipe dan barangnya agar aksi kekurangan muncul.</span>
            </div>
          )}
          <div role="radiogroup" aria-label="Tipe perlengkapan" className="space-y-1.5">
            <p className="text-sm font-medium text-slate-700">Tipe perlengkapan <span className="text-bad-700">*</span></p>
            <div className="grid gap-2 sm:grid-cols-3">
              {KINDS.map((k) => (
                <button
                  key={k.value}
                  type="button"
                  role="radio"
                  aria-checked={kind === k.value}
                  onClick={() => setKind(k.value)}
                  className={`rounded-lg border px-3 py-2.5 text-left transition-colors ${
                    kind === k.value ? 'border-brand-600 bg-brand-50' : 'border-slate-300 bg-white hover:border-slate-400'
                  }`}
                >
                  <span className="block text-sm font-semibold">{k.label}</span>
                  <span className="block text-xs text-slate-500">{k.hint}</span>
                </button>
              ))}
            </div>
          </div>

          {kind === 'asset' && (
            <div className="grid gap-3 sm:grid-cols-2">
              <FormField label="Jenis aset" required error={errors.asset_type_id}>
                <Select value={assetTypeId ?? ''} onChange={(e) => pickAssetType(Number(e.target.value) || null)} error={errors.asset_type_id}>
                  <option value="">Pilih jenis aset</option>
                  {assetTypes.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.code})</option>)}
                </Select>
              </FormField>
              <FormField label="Sumber" required error={errors.source_department_id}>
                <Select value={sourceId ?? ''} onChange={(e) => setSourceId(Number(e.target.value) || null)} error={errors.source_department_id}>
                  <option value="">Pilih sumber</option>
                  {departments.map((d) => <option key={d.id} value={d.id}>{d.is_internal ? `Milik ${d.name}` : d.name}</option>)}
                </Select>
              </FormField>
            </div>
          )}

          {kind === 'other' && (
            <div className="grid gap-3 sm:grid-cols-2">
              <FormField label="Departemen penyedia" required error={errors.source_department_id}>
                <Select value={otherSourceId ?? ''} onChange={(e) => pickOtherSource(Number(e.target.value) || null)} error={errors.source_department_id}>
                  <option value="">Pilih departemen</option>
                  {otherUnits.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
                </Select>
              </FormField>
              <FormField label="Katalog permintaan (opsional)" error={errors.catalog_id}>
                <Select clearable value={catalogId ?? ''} disabled={!otherSourceId} onChange={(e) => pickCatalog(Number(e.target.value) || null)} error={errors.catalog_id}>
                  <option value="">{catalogOptions.length ? 'Tidak ada' : 'Tidak ada katalog dari unit ini'}</option>
                  {catalogOptions.map((c) => <option key={c.id} value={c.id}>{c.item_name}</option>)}
                </Select>
              </FormField>
            </div>
          )}

          {kind === 'stock' && (
            <div className="space-y-1.5">
              <FormField label="Barang stok" required error={errors.consumable_id}>
                <Select value={consumableId ?? ''} onChange={(e) => pickConsumable(Number(e.target.value) || null)} error={errors.consumable_id}>
                  <option value="">{stock.isPending ? 'Memuat barang…' : 'Pilih barang'}</option>
                  {consumables.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name} (stok {c.quantity} {c.unit})
                    </option>
                  ))}
                </Select>
              </FormField>
              {consumable ? (
                <p className="text-xs text-slate-500">
                  Sumber & tujuan permintaan otomatis: <b>{consumable.provider_department?.name ?? '-'}</b>.
                </p>
              ) : (
                <p className="text-xs text-slate-500">
                  Barang belum ada? Tambahkan dulu di{' '}
                  <Link to="/stok" className="font-semibold text-brand-700 hover:underline">Stok Habis Pakai</Link>.
                </p>
              )}
            </div>
          )}

          {kind && (
            <div className="grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-3">
              <FormField label="Nama perlengkapan" required error={errors.item_name}>
                <Input value={itemName} onChange={(e) => setItemName(e.target.value)} error={errors.item_name} maxLength={150} />
              </FormField>
              <FormField label="Jumlah standar" required error={errors.standard_quantity}>
                <Input type="number" min={1} value={quantity} onChange={(e) => setQuantity(e.target.value)} error={errors.standard_quantity} />
              </FormField>
            </div>
          )}
        </>
      )}
    </ActionModal>
  )
}
