import { TriangleAlert } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { AssetCode } from '../../components/ui/Badge'
import Button from '../../components/ui/Button'
import { buttonClass } from '../../components/ui/button-class'
import EntityCombobox from '../../components/ui/EntityCombobox'
import { FormField, Input, Select, Textarea } from '../../components/ui/FormField'
import { PageHeader } from '../../components/ui/Page'
import { LoadingLabel, Skeleton } from '../../components/ui/Skeleton'
import { useToast } from '../../components/ui/toast-context'
import AssetFilesManager from './AssetFilesManager'
import NewAssetTypeModal from './modals/NewAssetTypeModal'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { createBuilding, createDepartment, createRoom } from '../../lib/lookups'
import { invalidate } from '../../lib/queries/invalidate'
import { assetQueries } from '../../lib/queries/lists'
import { useAssetTypes, useBuildings, useDepartments, useRooms } from '../../lib/queries/master'
import { useErrorToast } from '../../lib/useErrorToast'
import { CONDITIONS, type Asset, type AssetType, type CodePreview, type Condition } from '../../types/asset'

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

const typeOption = (t: AssetType) => ({ id: t.id, label: `${t.name} (${t.code})` })

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <fieldset className="rounded-xl border border-slate-200 bg-white p-5">
      <legend className="sr-only">{title}</legend>
      <h2 className="mb-4 font-semibold" aria-hidden>{title}</h2>
      <div className="grid gap-4 sm:grid-cols-2">{children}</div>
    </fieldset>
  )
}

export default function AssetFormPage() {
  const { id } = useParams()
  const editing = id !== undefined
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()

  const [form, setForm] = useState<FormState>(empty)
  const [original, setOriginal] = useState<Asset | null>(null)
  const [loading, setLoading] = useState(editing)
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const { data: types, error: typesError } = useAssetTypes()
  const { data: departments, error: departmentsError } = useDepartments()
  const { data: buildings, error: buildingsError } = useBuildings()
  const { data: rooms, error: roomsError } = useRooms(form.building_id, true)
  useErrorToast(typesError, departmentsError, buildingsError, roomsError)
  const [preview, setPreview] = useState<{ typeId: number; data: CodePreview } | null>(null)
  const [nextCode, setNextCode] = useState<{ typeId: number; code: string } | null>(null)
  const [newType, setNewType] = useState<{
    name: string
    resolve: (option: ReturnType<typeof typeOption>) => void
    reject: () => void
  } | null>(null)

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((f) => ({ ...f, [key]: value }))

  useEffect(() => {
    if (!editing) return
    // Dari cache detail bila masih segar (mis. baru membuka halaman detail), selain itu dari API.
    queryClient
      .fetchQuery(assetQueries.detail(id))
      .then(({ data: a }) => {
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
  }, [editing, id, navigate, queryClient, toast])

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

  // Pratinjau kode aset baru; nomor baru pasti saat disimpan.
  useEffect(() => {
    if (editing || !form.asset_type_id) return
    const typeId = form.asset_type_id
    let cancelled = false
    api
      .get<{ data: { next_code: string } }>(`/asset-types/${typeId}/next-code`)
      .then((res) => !cancelled && setNextCode({ typeId, code: res.data.data.next_code }))
      .catch((e) => !cancelled && toast.error(errorMessage(e)))
    return () => {
      cancelled = true
    }
  }, [editing, form.asset_type_id, toast])
  const codeHint = editing
    ? (typeChanged ? activePreview?.new_code : original?.code)
    : nextCode?.typeId === form.asset_type_id ? nextCode?.code : undefined

  const typeOptions = useMemo(() => types.map(typeOption), [types])
  const selectedType = types.find((t) => t.id === form.asset_type_id)
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
      return { id: d.id, label: d.name }
    })
  const addBuilding = (name: string) =>
    guarded(async () => {
      const b = await createBuilding(name)
      return { id: b.id, label: b.name }
    })
  const addRoom = (name: string) =>
    guarded(async () => {
      const r = await createRoom(form.building_id!, name)
      return { id: r.id, label: r.name }
    })
  /** Menunggu popup kategori (batal = teks ketikan tetap). Kode dibuat otomatis oleh server. */
  const addType = (name: string) =>
    new Promise<ReturnType<typeof typeOption>>((resolve, reject) =>
      setNewType({ name, resolve, reject: () => reject(new Error('Dibatalkan')) }),
    )
  const cancelNewType = useCallback(() => {
    newType?.reject()
    setNewType(null)
  }, [newType])

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
      invalidate.assets(queryClient)
      navigate(`/daftar-aset/${asset.id}`)
    } catch (e) {
      setErrors(fieldErrors(e))
      toast.error(errorMessage(e))
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="space-y-5" aria-busy="true">
        <LoadingLabel />
        <Skeleton className="h-7 w-48" />
        <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
          {Array.from({ length: 6 }, (_, i) => (
            <div key={i} className="space-y-1.5">
              <Skeleton className="h-3.5 w-24" />
              <Skeleton className="h-10 w-full rounded-lg" />
            </div>
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="max-w-3xl space-y-5">
    <PageHeader
      title={editing ? 'Ubah data aset' : 'Tambah aset'}
      description={
        editing && original ? (
          <span className="flex flex-wrap items-center gap-2"><AssetCode code={original.code} /> {original.name}</span>
        ) : (
          'Kode aset dibuat otomatis (SHD-KODEJENIS-NNN) saat disimpan.'
        )
      }
      back={editing ? { to: `/daftar-aset/${id}`, label: 'Detail aset' } : { to: '/daftar-aset', label: 'Daftar Aset' }}
    />
    <form onSubmit={submit} className="space-y-5">
      <Section title="Identitas aset">
        <div className="sm:col-span-2">
          <FormField label="Jenis aset" required error={errors.asset_type_id}>
            <EntityCombobox
              value={form.asset_type_id}
              options={typeOptions}
              onChange={(v) => set('asset_type_id', v)}
              onCreate={addType}
              createLabel={(name) => `+ Tambah jenis '${name}'`}
              isExisting={(name) => types.some((t) => t.name.toLowerCase() === name.toLowerCase())}
              placeholder="Ketik untuk mencari atau menambah jenis…"
              error={errors.asset_type_id}
            />
          </FormField>
          {selectedType && (
            <p className="mt-1 text-xs text-slate-500">
              Kategori: <span className="font-medium text-slate-700">{selectedType.category?.name ?? '-'}</span>
            </p>
          )}
          {activePreview?.code_will_change && (
            <div role="alert" className="mt-2 flex gap-2 rounded-lg bg-warn-50 px-3 py-2.5 text-sm text-warn-700">
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
          {codeHint && <p className="mt-1 text-xs text-slate-500">Kode otomatis: <b>{codeHint}</b></p>}
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
      </Section>

      <Section title="Sumber & lokasi asal">
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
            placeholder={form.building_id ? 'Ketik nama ruangan, misal Lt. 8 – Ruang A' : 'Pilih gedung dulu'}
            disabled={!form.building_id}
            error={errors.room_id}
          />
        </FormField>
      </Section>

      <Section title="Administrasi & kondisi">
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
          <p className="self-end rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
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
      </Section>

      <div className="flex flex-col-reverse gap-2 sm:flex-row">
        <Link to={editing ? `/daftar-aset/${id}` : '/daftar-aset'} className={buttonClass('secondary')}>
          Batal
        </Link>
        <Button type="submit" loading={saving}>{editing ? 'Simpan perubahan' : 'Simpan aset'}</Button>
      </div>
    </form>
    {editing && original && <AssetFilesManager assetId={original.id} />}
    {newType && (
      <NewAssetTypeModal
        name={newType.name}
        onClose={cancelNewType}
        onCreated={(t) => {
          newType.resolve(typeOption(t))
          setNewType(null)
          setForm((f) => (f.name.trim() ? f : { ...f, name: t.name }))
        }}
      />
    )}
    </div>
  )
}
