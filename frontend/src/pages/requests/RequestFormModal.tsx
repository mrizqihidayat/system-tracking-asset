import { useQueryClient } from '@tanstack/react-query'
import DepartmentCombobox from '../../components/DepartmentCombobox'
import { useCallback, useState } from 'react'
import EntityCombobox from '../../components/ui/EntityCombobox'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api } from '../../lib/api'
import { invalidate } from '../../lib/queries/invalidate'
import { useDepartments, useRequestMethods, useRooms } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import type { RequestCatalog, RequestPrefill, SupplyRequest } from '../../types/supply'
import ActionModal from '../assets/modals/ActionModal'
import RoomSelect from '../loans/RoomSelect'
import CatalogFormModal from './CatalogFormModal'
import { todayWib as today } from '../../lib/date'

interface Props {
  catalog: RequestCatalog[]
  /** null = permintaan baru. */
  request: SupplyRequest | null
  prefill?: RequestPrefill
  onClose: () => void
  onDone: () => void
}

const MANUAL = 'manual'
const OTHER_METHOD = 'Lainnya'

const catalogOption = (c: RequestCatalog) => ({
  id: c.id,
  label: c.provider_department?.name ? `${c.item_name} • ${c.provider_department.name}` : c.item_name,
})

/** Catat permintaan ke departemen lain (proses permintaan tetap di luar sistem). */
export default function RequestFormModal({ catalog: catalogProp, request, prefill, onClose, onDone }: Props) {
  const toast = useToast()
  const queryClient = useQueryClient()
  // Item yang baru ditambah dari form ini ikut tampil sebelum daftar katalog dimuat ulang.
  const [added, setAdded] = useState<RequestCatalog[]>([])
  const catalog = [...catalogProp, ...added.filter((a) => !catalogProp.some((c) => c.id === a.id))]
  const [newItem, setNewItem] = useState<{ name: string; resolve: (c: RequestCatalog) => void; reject: () => void } | null>(null)
  const departments = useDepartments().data.filter((d) => !d.is_internal)
  const { data: allRooms } = useRooms()
  // Barang stok tidak memakai katalog (permintaan lama yang masih punya katalog ikut menjadi Item lain).
  const linkedConsumable = request ? request.consumable_id : prefill?.consumable_id
  const initialCatalog = linkedConsumable ? null : request ? request.catalog?.id : prefill?.catalog_id
  const [catalogId, setCatalogId] = useState<string>(
    initialCatalog ? String(initialCatalog) : request || prefill?.item_name ? MANUAL : '',
  )
  const [itemName, setItemName] = useState(request?.item_name ?? prefill?.item_name ?? '')
  const [providerId, setProviderId] = useState<number | null>(
    request?.provider_department?.id ?? prefill?.provider_department_id ?? null,
  )
  // null = mengikuti katalog; pilihan staf disimpan sampai item diganti.
  const [returnChoice, setReturnChoice] = useState<boolean | null>(request?.must_return ?? null)
  // Pilihan dari server (satu sumber untuk web dan versi HP). "Lainnya" selalu ada agar form tetap bisa dipakai.
  const requestMethods = useRequestMethods()
  useErrorToast(requestMethods.error)
  const methodOptions = requestMethods.data.filter((m) => m !== OTHER_METHOD)
  // Nilai di luar daftar (mis. "Telegram") tampil sebagai Lainnya + isian teks. Daftar bisa tiba setelah form
  // terbuka, jadi nilai awal dihitung saat render sampai staf memilih atau mengetik sendiri.
  const initialMethod = request?.request_method ?? ''
  const knownMethod = methodOptions.includes(initialMethod)
  const [pickedMethod, setPickedMethod] = useState<string | null>(null)
  const [typedMethod, setTypedMethod] = useState<string | null>(null)
  const methodChoice = pickedMethod ?? (knownMethod ? initialMethod : initialMethod && OTHER_METHOD)
  const otherMethod = typedMethod ?? (knownMethod || initialMethod === OTHER_METHOD ? '' : initialMethod)
  const method = methodChoice === OTHER_METHOD ? otherMethod.trim() : methodChoice
  const [quantity, setQuantity] = useState(String(request?.quantity ?? prefill?.quantity ?? 1))
  const [purpose, setPurpose] = useState(request?.purpose ?? '')
  const [date, setDate] = useState(request?.request_date ?? today())
  const [buildingId, setBuildingId] = useState<number | null>(request?.room?.building_id ?? null)
  const [roomId, setRoomId] = useState<number | null>(request?.room?.id ?? prefill?.room_id ?? null)
  const [note, setNote] = useState(request?.note ?? '')

  // Ruangan dari query/saran hanya membawa id → cari gedungnya agar dropdown terisi (sampai gedung dipilih).
  const prefillRoom = prefill?.room_id
  const prefillBuilding = !request && prefillRoom ? allRooms.find((r) => r.id === prefillRoom)?.building_id ?? null : null
  const effectiveBuildingId = buildingId ?? prefillBuilding

  // Item di luar katalog hanya untuk data lama dan permintaan dari Stok; item baru ditambah ke katalog lewat ketikan.
  const manual = catalogId === MANUAL
  const selected = catalog.find((c) => String(c.id) === catalogId)

  const addCatalogItem = (name: string) =>
    new Promise<RequestCatalog>((resolve, reject) => setNewItem({ name, resolve, reject: () => reject(new Error('Dibatalkan')) }))
  const cancelNewItem = useCallback(() => {
    newItem?.reject()
    setNewItem(null)
  }, [newItem])
  const mustReturn = returnChoice ?? selected?.must_return ?? false

  // Tautan barang stok hanya berlaku selama itemnya tidak diganti di form.
  const initialName = request?.item_name ?? prefill?.item_name ?? ''
  const itemUnchanged = (selected?.id ?? null) === (initialCatalog ?? null) && (!manual || itemName.trim() === initialName.trim())
  const consumableId = itemUnchanged ? (linkedConsumable ?? null) : null

  const submit = async () => {
    const payload = {
      catalog_id: selected?.id ?? null,
      consumable_id: consumableId,
      item_name: manual ? itemName.trim() : null,
      provider_department_id: manual ? providerId : null,
      must_return: mustReturn,
      request_method: method,
      quantity: Number(quantity),
      purpose: purpose.trim() || null,
      request_date: date || null,
      room_id: roomId,
      note: note.trim() || null,
    }
    if (request) await api.put(`/supply-requests/${request.id}`, payload)
    else await api.post('/supply-requests', payload)
    toast.success(request ? 'Permintaan diperbarui.' : 'Permintaan dicatat.')
    onDone()
  }

  return (
    <>
    <ActionModal
      title={request ? 'Ubah permintaan' : 'Buat permintaan'}
      submitLabel="Simpan"
      submitDisabled={!catalogId || !method || requestMethods.isPending || (manual && (!itemName.trim() || !providerId)) || !!newItem}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          {!manual && (
            <FormField label="Item" required error={errors.catalog_id}>
              <EntityCombobox
                value={selected?.id ?? null}
                options={catalog.map(catalogOption)}
                onChange={(id) => {
                  setCatalogId(id ? String(id) : '')
                  setReturnChoice(null)
                }}
                onCreate={async (name) => catalogOption(await addCatalogItem(name))}
                createLabel={(name) => `+ Tambah '${name}' ke katalog`}
                isExisting={(name) => catalog.some((c) => c.item_name.toLowerCase() === name.toLowerCase())}
                placeholder="Ketik nama item…"
                error={errors.catalog_id}
              />
            </FormField>
          )}
          {selected && (
            <p className="-mt-1 text-xs text-slate-500">
              {selected.provider_department?.name} · lead time {selected.lead_time_days} hari
              {selected.must_return && ' · perlu dikembalikan'}
            </p>
          )}
          {consumableId && initialName && (
            <p className="-mt-1 text-xs text-slate-500">Barang stok: <b>{initialName}</b></p>
          )}
          {manual && (
            <div className="grid grid-cols-2 gap-3">
              <FormField label="Nama item" required error={errors.item_name}>
                <Input value={itemName} onChange={(e) => setItemName(e.target.value)} error={errors.item_name} maxLength={150} />
              </FormField>
              <FormField label="Departemen penyedia" required error={errors.provider_department_id}>
                <DepartmentCombobox value={providerId} onChange={setProviderId} departments={departments} error={errors.provider_department_id} />
              </FormField>
            </div>
          )}
          <FormField label="Pengembalian barang" required error={errors.must_return}>
            <Select value={mustReturn ? '1' : '0'} onChange={(e) => setReturnChoice(e.target.value === '1')} error={errors.must_return}>
              <option value="0">Tidak dikembalikan</option>
              <option value="1">Dikembalikan</option>
            </Select>
          </FormField>
          <FormField label="Cara permintaan" required error={methodChoice === OTHER_METHOD ? undefined : errors.request_method}>
            <Select value={methodChoice} disabled={requestMethods.isPending} onChange={(e) => setPickedMethod(e.target.value)} error={methodChoice === OTHER_METHOD ? undefined : errors.request_method}>
              <option value="">{requestMethods.isPending ? 'Memuat pilihan…' : 'Pilih cara permintaan'}</option>
              {methodOptions.map((m) => <option key={m}>{m}</option>)}
              <option>{OTHER_METHOD}</option>
            </Select>
          </FormField>
          {methodChoice === OTHER_METHOD && (
            <FormField label="Cara lainnya" required error={errors.request_method}>
              <Input value={otherMethod} onChange={(e) => setTypedMethod(e.target.value)} placeholder="Misal: Telegram" error={errors.request_method} maxLength={50} autoFocus />
            </FormField>
          )}
          <div className="grid grid-cols-2 gap-3">
            <FormField label="Jumlah" required error={errors.quantity}>
              <Input type="number" min={1} value={quantity} onChange={(e) => setQuantity(e.target.value)} error={errors.quantity} />
            </FormField>
            <FormField label="Tanggal permintaan" error={errors.request_date}>
              <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} error={errors.request_date} />
            </FormField>
          </div>
          <FormField label="Untuk kegiatan" error={errors.purpose}>
            <Input value={purpose} onChange={(e) => setPurpose(e.target.value)} error={errors.purpose} maxLength={255} />
          </FormField>
          <RoomSelect
            buildingLabel="Gedung terkait (opsional)"
            roomLabel="Ruangan terkait (opsional)"
            buildingId={effectiveBuildingId}
            roomId={roomId}
            onChange={(b, r) => {
              setBuildingId(b)
              setRoomId(r)
            }}
            error={errors.room_id}
          />
          <FormField label="Catatan (opsional)" error={errors.note}>
            <Textarea value={note} onChange={(e) => setNote(e.target.value)} error={errors.note} maxLength={1000} />
          </FormField>
        </>
      )}
    </ActionModal>
      {newItem && (
        <CatalogFormModal
          item={null}
          initialName={newItem.name}
          onClose={cancelNewItem}
          onDone={(saved) => {
            setAdded((list) => [...list, saved])
            newItem.resolve(saved)
            setNewItem(null)
            invalidate.catalog(queryClient)
          }}
        />
      )}
    </>
  )
}
