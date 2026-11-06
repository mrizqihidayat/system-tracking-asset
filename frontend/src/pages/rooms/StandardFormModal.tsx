import { useState } from 'react'
import { Link } from 'react-router-dom'
import { FormField, Input, Select } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { useConsumables } from '../../lib/queries/lists'
import { useAssetTypes } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import type { RoomAssetGroup, RoomConsumable } from '../../types/room'
import ActionModal from '../assets/modals/ActionModal'

/** Pilihan barang stok (maks. 100 barang, sama dengan sebelumnya). */
const STOCK_PARAMS = { per_page: 100 }

export type StandardTarget =
  | { kind: 'asset'; group: RoomAssetGroup | null }
  | { kind: 'consumable'; row: RoomConsumable | null }

interface Props {
  roomId: number
  roomName: string
  target: StandardTarget
  /** Jenis/barang yang sudah tampil di ruangan: tidak ditawarkan lagi saat menambah. */
  usedAssetTypeIds: number[]
  usedConsumableIds: number[]
  onClose: () => void
  onDone: () => void
}

/**
 * Atur standar satu jenis aset atau tambah/ubah barang di ruangan (SPEC §8).
 * Standar jenis aset 0 berarti tanpa baris standar: tampil jumlah Ada saja, tidak dihitung Kurang.
 */
export default function StandardFormModal({ roomId, roomName, target, usedAssetTypeIds, usedConsumableIds, onClose, onDone }: Props) {
  const toast = useToast()
  const isAsset = target.kind === 'asset'
  const group = isAsset ? target.group : null
  const row = isAsset ? null : target.row
  const { data: assetTypes, error: typesError } = useAssetTypes()
  const stock = useConsumables(STOCK_PARAMS)
  useErrorToast(typesError, stock.error)

  const [assetTypeId, setAssetTypeId] = useState<number | null>(group?.asset_type.id ?? null)
  const [consumableId, setConsumableId] = useState<number | null>(row?.consumable.id ?? null)
  const [quantity, setQuantity] = useState(String(group?.standard_quantity ?? row?.standard_quantity ?? (isAsset ? 0 : 1)))

  const typeOptions = assetTypes.filter((t) => !usedAssetTypeIds.includes(t.id))
  const consumables = (stock.data?.data ?? []).filter((c) => c.id === row?.consumable.id || !usedConsumableIds.includes(c.id))
  const consumable = consumables.find((c) => c.id === consumableId)
  const standardId = group?.standard_id ?? row?.id ?? null

  const submit = async () => {
    const value = Number(quantity)
    if (isAsset && value === 0) {
      if (standardId) await api.delete(`/room-standards/${standardId}`)
    } else {
      const payload = isAsset
        ? { asset_type_id: assetTypeId, standard_quantity: value }
        : { consumable_id: consumableId, standard_quantity: value }
      if (standardId) await api.put(`/room-standards/${standardId}`, payload)
      else await api.post(`/rooms/${roomId}/standards`, payload)
    }
    toast.success('Standar disimpan.')
    onDone()
  }

  const title = isAsset
    ? `Standar ${group?.asset_type.name ?? 'jenis aset'} • ${roomName}`
    : `${row ? 'Ubah' : 'Tambah'} barang • ${roomName}`

  return (
    <ActionModal
      title={title}
      submitLabel="Simpan"
      submitDisabled={quantity === '' || (isAsset ? !assetTypeId : !consumableId)}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          {isAsset && !group && (
            <FormField label="Jenis aset" required error={errors.asset_type_id}>
              <Select value={assetTypeId ?? ''} onChange={(e) => setAssetTypeId(Number(e.target.value) || null)} error={errors.asset_type_id}>
                <option value="">Pilih jenis aset</option>
                {typeOptions.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.code})</option>)}
              </Select>
            </FormField>
          )}

          {!isAsset && (
            <div className="space-y-1.5">
              <FormField label="Barang dari Stok" required error={errors.consumable_id}>
                <Select value={consumableId ?? ''} onChange={(e) => setConsumableId(Number(e.target.value) || null)} error={errors.consumable_id}>
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
                  Departemen penyedia: <b>{consumable.provider_department?.name ?? '-'}</b>.
                </p>
              ) : (
                <p className="text-xs text-slate-500">
                  Barang belum ada? Tambahkan dulu di{' '}
                  <Link to="/stok" className="font-semibold text-brand-700 hover:underline">Stok Habis Pakai</Link>.
                </p>
              )}
            </div>
          )}

          <FormField label="Jumlah standar" required error={errors.standard_quantity}>
            <Input type="number" min={isAsset ? 0 : 1} value={quantity} onChange={(e) => setQuantity(e.target.value)} error={errors.standard_quantity} />
          </FormField>
          {isAsset && <p className="text-xs text-slate-500">Standar 0: jumlah Ada tetap tampil, tetapi tidak dihitung kurang.</p>}
        </>
      )}
    </ActionModal>
  )
}
