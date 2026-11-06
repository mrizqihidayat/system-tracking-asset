import { useEffect, useState } from 'react'
import Button from '../../components/ui/Button'
import { FormField, Input, Select } from '../../components/ui/FormField'
import Modal from '../../components/ui/Modal'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import ActionModal from '../assets/modals/ActionModal'

/** Satu entri data master yang sedang diubah/dihapus, beserta endpoint-nya. */
export interface MasterTarget {
  /** Label jenis, mis. "Gedung", "Jenis aset". */
  kind: string
  name: string
  /** Endpoint item, mis. `/rooms/3` (PUT/DELETE, dan `${url}/usage`). */
  url: string
  /** Kode singkat (hanya jenis aset). */
  code?: string
  /** Tanda gudang (hanya ruangan). */
  isStorage?: boolean
  /** Kategori (hanya jenis aset). */
  categoryId?: number
}

interface Usage {
  items: { label: string; count: number }[]
  total: number
  can_delete: boolean
  reason: string | null
}

/** Tambah atau ubah nama (+ kategori & kode untuk jenis aset). `createUrl` diisi untuk tambah; `target` untuk ubah. */
export function MasterFormModal({
  kind, target, createUrl, withCode, withStorage, categories, categoryId: defaultCategoryId, onClose, onDone,
}: {
  kind: string
  target?: MasterTarget
  createUrl?: string
  withCode?: boolean
  /** Ruangan: pilihan Gudang / tempat penyimpanan. */
  withStorage?: boolean
  /** Jenis aset: pilihan kategori (wajib). */
  categories?: { id: number; name: string }[]
  /** Kategori awal saat menambah jenis dari grup kategori. */
  categoryId?: number
  onClose: () => void
  onDone: () => void
}) {
  const toast = useToast()
  const [name, setName] = useState(target?.name ?? '')
  const [code, setCode] = useState(target?.code ?? '')
  const [isStorage, setIsStorage] = useState(target?.isStorage ?? false)
  const [categoryId, setCategoryId] = useState(String(target?.categoryId ?? defaultCategoryId ?? ''))

  const submit = async () => {
    const payload: Record<string, unknown> = { name: name.trim() }
    if (withCode && code.trim()) payload.code = code.trim().toUpperCase()
    if (withStorage) payload.is_storage = isStorage
    if (categories) payload.category_id = Number(categoryId)
    if (target) await api.put(target.url, payload)
    else await api.post(createUrl!, payload)
    toast.success(target ? `${kind} diperbarui.` : `${kind} ditambahkan.`)
    onDone()
  }

  return (
    <ActionModal
      title={target ? `Ubah ${kind.toLowerCase()}` : `Tambah ${kind.toLowerCase()}`}
      submitLabel="Simpan"
      submitDisabled={!name.trim() || (!!categories && !categoryId)}
      onClose={onClose}
      onSubmit={submit}
    >
      {(errors) => (
        <>
          <FormField label="Nama" required error={errors.name}>
            <Input value={name} onChange={(e) => setName(e.target.value)} error={errors.name} maxLength={100} autoFocus />
          </FormField>
          {categories && (
            <FormField label="Kategori" required error={errors.category_id}>
              <Select value={categoryId} onChange={(e) => setCategoryId(e.target.value)} error={errors.category_id}>
                <option value="">Pilih kategori</option>
                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </Select>
            </FormField>
          )}
          {withStorage && (
            <div>
              <label className="flex min-h-10 items-center gap-2 text-sm font-medium text-slate-700">
                <input type="checkbox" className="h-4 w-4" checked={isStorage} onChange={(e) => setIsStorage(e.target.checked)} />
                Gudang / tempat penyimpanan
              </label>
              {errors.is_storage && <span className="block text-xs font-medium text-bad-700">{errors.is_storage}</span>}
              <p className="text-xs text-slate-500">Hanya ruangan gudang yang bisa menjadi lokasi simpan stok, dan tidak dihitung dalam kesiapan ruangan.</p>
            </div>
          )}
          {withCode && (
            <FormField label={target ? 'Kode singkat' : 'Kode singkat (kosong = otomatis)'} required={!!target} error={errors.code}>
              <Input
                value={code}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
                placeholder="Misal: PRJ"
                maxLength={5}
                error={errors.code}
              />
            </FormField>
          )}
          {withCode && target && (
            <p className="text-xs text-slate-500">Kode baru hanya berlaku untuk aset baru; kode aset yang sudah ada tidak berubah.</p>
          )}
        </>
      )}
    </ActionModal>
  )
}

/** Popup pemakaian sebelum hapus: tampilkan rincian; tombol hapus hanya jika tidak dipakai. */
export function MasterDeleteModal({ target, onClose, onDone }: { target: MasterTarget; onClose: () => void; onDone: () => void }) {
  const toast = useToast()
  const [usage, setUsage] = useState<Usage | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api
      .get<{ data: Usage }>(`${target.url}/usage`)
      .then((r) => setUsage(r.data.data))
      .catch((e) => {
        toast.error(errorMessage(e))
        onClose()
      })
  }, [target.url, toast, onClose])

  const remove = async () => {
    setBusy(true)
    try {
      await api.delete(target.url)
      toast.success(`${target.kind} ${target.name} dihapus.`)
      onDone()
    } catch (e) {
      toast.error(errorMessage(e))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      open
      title={`Hapus ${target.kind.toLowerCase()} • ${target.name}`}
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>{usage?.can_delete ? 'Batal' : 'Tutup'}</Button>
          {usage?.can_delete && <Button variant="danger" loading={busy} onClick={remove}>Hapus permanen</Button>}
        </>
      }
    >
      {!usage ? (
        <p className="text-sm text-slate-500">Memeriksa pemakaian…</p>
      ) : usage.reason ? (
        <p className="text-sm text-slate-700">{usage.reason}</p>
      ) : usage.can_delete ? (
        <p className="text-sm text-slate-700">Tidak dipakai di data mana pun. Hapus permanen {target.kind.toLowerCase()} ini?</p>
      ) : (
        <div className="space-y-2 text-sm text-slate-700">
          <p>Tidak bisa dihapus karena masih dipakai oleh:</p>
          <ul className="list-disc pl-5">
            {usage.items.map((i) => <li key={i.label}>{i.count} {i.label}</li>)}
          </ul>
          <p className="text-xs text-slate-500">Pindahkan atau hapus data tersebut lebih dulu, atau cukup ganti namanya.</p>
        </div>
      )}
    </Modal>
  )
}
