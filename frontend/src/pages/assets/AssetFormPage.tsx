import { TriangleAlert } from 'lucide-react'
import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import Button from '../../components/ui/Button'
import EntityCombobox from '../../components/ui/EntityCombobox'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import AssetFilesManager from './AssetFilesManager'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import {
  createBuilding, createDepartment, createRoom, fetchAssetTypes, fetchBuildings, fetchDepartments, fetchRooms,
} from '../../lib/lookups'
import {
  CONDITIONS, type Asset, type AssetType, type Building, type CodePreview, type Condition, type Department, type Room,
} from '../../types/asset'

interface FormState {
  asset_type_id: number | null
  name: string
  brand: string
  serial_number: string
  specification: string
  source_department_id: number | null
  building_id: number | null
  room_id: number | null
  pic_name: string
  finance_code: string
  acquisition_date: string
  next_service_date: string
  condition: Condition
  status: 'Tersedia' | 'Perbaikan'
  note: string
}

const empty: FormState = {
  asset_type_id: null, name: '', brand: '', serial_number: '', specification: '', source_department_id: null,
  building_id: null, room_id: null, pic_name: '', finance_code: '', acquisition_date: '', next_service_date: '',
  condition: 'Baik', status: 'Tersedia', note: '',
}

const orNull = (v: string) => (v.trim() === '' ? null : v.trim())

export default function AssetFormPage() {
  const { id } = useParams()
  const editing = id !== undefined
  const navigate = useNavigate()
  const toast = useToast()

  const [form, setForm] = useState<FormState>(empty)
  const [original, setOriginal] = useState<Asset | null>(null)
  const [loading, setLoading] = useState(editing)
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const [types, setTypes] = useState<AssetType[]>([])
  const [departments, setDepartments] = useState<Department[]>([])
  const [buildings, setBuildings] = useState<Building[]>([])
  const [rooms, setRooms] = useState<Room[]>([])
  const [preview, setPreview] = useState<{ typeId: number; data: CodePreview } | null>(null)

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((f) => ({ ...f, [key]: value }))

  useEffect(() => {
    Promise.all([fetchAssetTypes(), fetchDepartments(), fetchBuildings()])
      .then(([t, d, b]) => {
        setTypes(t)
        setDepartments(d)
        setBuildings(b)
      })
      .catch((e) => toast.error(errorMessage(e)))
  }, [toast])

  useEffect(() => {
    if (!editing) return
    api
      .get<{ data: Asset }>(`/assets/${id}`)
      .then(({ data: { data: a } }) => {
        setOriginal(a)
        setForm({
          asset_type_id: a.asset_type?.id ?? null,
          name: a.name,
          brand: a.brand ?? '',
          serial_number: a.serial_number ?? '',
          specification: a.specification ?? '',
          source_department_id: a.source_department?.id ?? null,
          building_id: a.room?.building_id ?? null,
          room_id: a.room?.id ?? null,
          pic_name: a.pic_name ?? '',
          finance_code: a.finance_code ?? '',
          acquisition_date: a.acquisition_date ?? '',
          next_service_date: a.next_service_date ?? '',
          condition: a.condition,
          status: 'Tersedia',
          note: a.note ?? '',
        })
      })
      .catch((e) => {
        toast.error(errorMessage(e))
        navigate('/daftar-aset', { replace: true })
      })
      .finally(() => setLoading(false))
  }, [editing, id, navigate, toast])

  useEffect(() => {
    if (!form.building_id) return
    fetchRooms(form.building_id)
      .then(setRooms)
      .catch((e) => toast.error(errorMessage(e)))
  }, [form.building_id, toast])

  // Peringatan kode baru (hanya saat jenis diganti pada form Ubah).
  const originalTypeId = original?.asset_type?.id ?? null
  const typeChanged = editing && form.asset_type_id !== null && form.asset_type_id !== originalTypeId
  useEffect(() => {
    if (!typeChanged || !form.asset_type_id) return
    const typeId = form.asset_type_id
    let cancelled = false
    api
      .get<{ data: CodePreview }>(`/assets/${id}/code-preview`, { params: { asset_type_id: typeId } })
      .then((res) => !cancelled && setPreview({ typeId, data: res.data.data }))
      .catch((e) => !cancelled && toast.error(errorMessage(e)))
    return () => {
      cancelled = true
    }
  }, [typeChanged, form.asset_type_id, id, toast])
  const activePreview = typeChanged && preview?.typeId === form.asset_type_id ? preview.data : null

  const typeOptions = useMemo(
    () => types.map((t) => ({ id: t.id, label: `${t.name} (${t.code}) — ${t.category?.name ?? ''}` })),
    [types],
  )
  const departmentOptions = departments.map((d) => ({ id: d.id, label: d.name }))
  const buildingOptions = buildings.map((b) => ({ id: b.id, label: b.name }))
  const roomOptions = form.building_id ? rooms.map((r) => ({ id: r.id, label: r.name })) : []

  const guarded = async <T,>(fn: () => Promise<T>): Promise<T> => {
    try {
      return await fn()
    } catch (e) {
      toast.error(fieldErrors(e).name ?? errorMessage(e))
      throw e
    }
  }

  const addDepartment = (name: string) =>
    guarded(async () => {
      const d = await createDepartment(name)
      setDepartments((list) => [...list, d])
      return { id: d.id, label: d.name }
    })
  const addBuilding = (name: string) =>
    guarded(async () => {
      const b = await createBuilding(name)
      setBuildings((list) => [...list, b])
      return { id: b.id, label: b.name }
    })
  const addRoom = (name: string) =>
    guarded(async () => {
      const r = await createRoom(form.building_id!, name)
      setRooms((list) => [...list, r])
      return { id: r.id, label: r.name }
    })

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    const local: Record<string, string> = {}
    if (!form.asset_type_id) local.asset_type_id = 'Jenis aset wajib dipilih.'
    if (!form.source_department_id) local.source_department_id = 'Sumber wajib dipilih.'
    if (!form.room_id) local.room_id = 'Ruangan wajib dipilih.'
    setErrors(local)
    if (Object.keys(local).length > 0) return

    const payload: Record<string, unknown> = {
      asset_type_id: form.asset_type_id,
      name: form.name.trim(),
      brand: orNull(form.brand),
      serial_number: orNull(form.serial_number),
      specification: orNull(form.specification),
      source_department_id: form.source_department_id,
      room_id: form.room_id,
      pic_name: orNull(form.pic_name),
      finance_code: orNull(form.finance_code),
      acquisition_date: orNull(form.acquisition_date),
      next_service_date: orNull(form.next_service_date),
      note: orNull(form.note),
    }
    if (!editing) {
      payload.condition = form.condition
      if (form.condition === 'Rusak Ringan') payload.status = form.status
    }

    setSaving(true)
    try {
      const res = editing
        ? await api.put<{ data: Asset; meta?: { code_changed?: boolean } }>(`/assets/${id}`, payload)
        : await api.post<{ data: Asset }>('/assets', payload)
      const asset = res.data.data
      toast.success(editing ? `Data ${asset.code} disimpan.` : `Aset ${asset.code} ditambahkan.`)
      navigate(`/daftar-aset/${asset.id}`)
    } catch (e) {
      setErrors(fieldErrors(e))
      toast.error(errorMessage(e))
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <p className="text-slate-500">Memuat…</p>

  return (
    <div className="space-y-6">
    <form onSubmit={submit} className="max-w-3xl space-y-4">
      <h1 className="text-2xl font-semibold">{editing ? `Ubah aset ${original?.code ?? ''}` : 'Tambah aset'}</h1>

      <div className="grid gap-4 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <FormField label="Jenis aset" required error={errors.asset_type_id}>
            <EntityCombobox
              value={form.asset_type_id}
              options={typeOptions}
              onChange={(v) => set('asset_type_id', v)}
              placeholder="Ketik untuk mencari jenis…"
              error={errors.asset_type_id}
            />
          </FormField>
          {!editing && (
            <p className="mt-1 text-xs text-slate-500">Kode aset dibuat otomatis (SHD-KODEJENIS-NNN) saat disimpan.</p>
          )}
          {activePreview?.code_will_change && (
            <div role="alert" className="mt-2 flex gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
              <TriangleAlert size={18} className="mt-0.5 shrink-0" />
              <span>
                Kode aset akan berganti dari <b>{activePreview.current_code}</b> menjadi <b>{activePreview.new_code}</b>.
                Kode lama dicatat di riwayat dan tetap bisa dicari. Label QR yang sudah tercetak tetap berlaku.
              </span>
            </div>
          )}
        </div>

        <div className="sm:col-span-2">
          <FormField label="Nama aset" required error={errors.name}>
            <Input value={form.name} onChange={(e) => set('name', e.target.value)} error={errors.name} required maxLength={255} />
          </FormField>
        </div>
        <FormField label="Merek" error={errors.brand}>
          <Input value={form.brand} onChange={(e) => set('brand', e.target.value)} error={errors.brand} maxLength={100} />
        </FormField>
        <FormField label="Nomor seri" error={errors.serial_number}>
          <Input value={form.serial_number} onChange={(e) => set('serial_number', e.target.value)} error={errors.serial_number} maxLength={100} />
        </FormField>
        <div className="sm:col-span-2">
          <FormField label="Spesifikasi" error={errors.specification}>
            <Textarea value={form.specification} onChange={(e) => set('specification', e.target.value)} error={errors.specification} />
          </FormField>
        </div>

        <FormField label="Sumber (departemen)" required error={errors.source_department_id}>
          <EntityCombobox
            value={form.source_department_id}
            options={departmentOptions}
            onChange={(v) => set('source_department_id', v)}
            onCreate={addDepartment}
            placeholder="Ketik nama departemen…"
            error={errors.source_department_id}
          />
        </FormField>
        <FormField label="Penanggung jawab (PIC)" error={errors.pic_name}>
          <Input value={form.pic_name} onChange={(e) => set('pic_name', e.target.value)} error={errors.pic_name} maxLength={150} />
        </FormField>

        <FormField label="Gedung (lokasi asal)" required>
          <EntityCombobox
            value={form.building_id}
            options={buildingOptions}
            onChange={(v) => setForm((f) => ({ ...f, building_id: v, room_id: null }))}
            onCreate={addBuilding}
            placeholder="Ketik nama gedung…"
          />
        </FormField>
        <FormField label="Ruangan (lokasi asal)" required error={errors.room_id}>
          <EntityCombobox
            key={form.building_id ?? 'none'}
            value={form.room_id}
            options={roomOptions}
            onChange={(v) => set('room_id', v)}
            onCreate={form.building_id ? addRoom : undefined}
            placeholder={form.building_id ? 'Ketik nama ruangan…' : 'Pilih gedung dulu'}
            disabled={!form.building_id}
            error={errors.room_id}
          />
        </FormField>

        <FormField label="Kode aset keuangan" error={errors.finance_code}>
          <Input value={form.finance_code} onChange={(e) => set('finance_code', e.target.value)} error={errors.finance_code} maxLength={100} />
        </FormField>
        <FormField label="Tanggal perolehan" error={errors.acquisition_date}>
          <Input type="date" value={form.acquisition_date} onChange={(e) => set('acquisition_date', e.target.value)} error={errors.acquisition_date} />
        </FormField>
        <FormField label="Servis berikutnya" error={errors.next_service_date}>
          <Input type="date" value={form.next_service_date} onChange={(e) => set('next_service_date', e.target.value)} error={errors.next_service_date} />
        </FormField>

        {editing ? (
          <p className="self-end text-xs text-slate-500">
            Kondisi dan status tidak bisa diubah di sini. Gunakan Catat kerusakan, Servis, atau Peminjaman.
          </p>
        ) : (
          <>
            <FormField label="Kondisi" error={errors.condition}>
              <Select value={form.condition} onChange={(e) => set('condition', e.target.value as Condition)} error={errors.condition}>
                {CONDITIONS.map((c) => <option key={c}>{c}</option>)}
              </Select>
            </FormField>
            {form.condition === 'Rusak Ringan' ? (
              <FormField label="Status aset" error={errors.status}>
                <Select value={form.status} onChange={(e) => set('status', e.target.value as FormState['status'])} error={errors.status}>
                  <option>Tersedia</option>
                  <option>Perbaikan</option>
                </Select>
              </FormField>
            ) : (
              <p className="self-end text-xs text-slate-500">
                Status otomatis: {form.condition === 'Baik' ? 'Tersedia' : 'Perbaikan'}.
              </p>
            )}
          </>
        )}

        <div className="sm:col-span-2">
          <FormField label="Catatan" error={errors.note}>
            <Textarea value={form.note} onChange={(e) => set('note', e.target.value)} error={errors.note} />
          </FormField>
        </div>
      </div>

      <div className="flex gap-2">
        <Button type="submit" loading={saving}>Simpan</Button>
        <Link
          to={editing ? `/daftar-aset/${id}` : '/daftar-aset'}
          className="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
          Batal
        </Link>
      </div>
    </form>
    {editing && original && <AssetFilesManager assetId={original.id} />}
    </div>
  )
}
