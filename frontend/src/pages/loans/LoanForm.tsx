import { useEffect, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import Button from '../../components/ui/Button'
import Combobox from '../../components/ui/Combobox'
import { FormField, Input } from '../../components/ui/FormField'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { useDepartments } from '../../lib/queries/master'
import type { Asset } from '../../types/asset'
import AssetPicker, { type PickedAsset } from './AssetPicker'
import RoomSelect from './RoomSelect'

const today = () => new Date().toLocaleDateString('en-CA')

const emptyForm = () => ({
  borrower_name: '',
  borrower_unit: '',
  purpose: '',
  buildingId: null as number | null,
  roomId: null as number | null,
  destination_note: '',
  borrow_date: today(),
  due_date: '',
  approval_number: '',
  approval_date: '',
})

/** Kartu "Catat peminjaman baru" (mockup halaman Peminjaman & Pengembalian). */
export default function LoanForm({ onSaved }: { onSaved: () => void }) {
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const presetId = params.get('asset_id')
  const [asset, setAsset] = useState<PickedAsset | null>(null)
  const [form, setForm] = useState(emptyForm)
  const { data: departments } = useDepartments()
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const set = <K extends keyof ReturnType<typeof emptyForm>>(key: K, value: ReturnType<typeof emptyForm>[K]) =>
    setForm((f) => ({ ...f, [key]: value }))

  // ?asset_id=<id> (dari detail aset / hasil scan): isi aset otomatis jika boleh dipinjam.
  useEffect(() => {
    if (!presetId) return
    api
      .get<{ data: Asset; meta: { actions: string[] } }>(`/assets/${presetId}`)
      .then((res) => {
        if (res.data.meta.actions.includes('pinjam')) setAsset(res.data.data)
        else toast.error(`${res.data.data.code} berstatus ${res.data.data.status}, tidak dapat dipinjam.`)
      })
      .catch((e) => toast.error(errorMessage(e)))
      .finally(() => setParams((p) => (p.delete('asset_id'), p), { replace: true }))
  }, [presetId, setParams, toast])

  const reset = () => {
    setAsset(null)
    setForm(emptyForm())
    setErrors({})
  }

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    if (!asset) {
      setErrors({ asset_id: 'Pilih aset yang dipinjam.' })
      return
    }
    setSaving(true)
    setErrors({})
    try {
      await api.post('/loans', {
        asset_id: asset.id,
        borrower_name: form.borrower_name.trim(),
        borrower_unit: form.borrower_unit.trim(),
        purpose: form.purpose.trim(),
        destination_room_id: form.roomId,
        destination_note: form.destination_note.trim() || null,
        borrow_date: form.borrow_date || null,
        due_date: form.due_date,
        approval_number: form.approval_number.trim() || null,
        approval_date: form.approval_date || null,
      })
      toast.success(`Peminjaman ${asset.code} tersimpan.`)
      reset()
      onSaved()
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (Object.keys(fields).length === 0) toast.error(errorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="rounded-xl border border-slate-200 bg-white p-5">
      <h2 className="mb-4 font-semibold">Catat peminjaman baru</h2>
      <form onSubmit={submit} className="space-y-4">
        <AssetPicker asset={asset} onPick={setAsset} error={errors.asset_id} />

        <div className="grid gap-3 sm:grid-cols-2">
          <FormField label="Nama peminjam" required error={errors.borrower_name}>
            <Input value={form.borrower_name} onChange={(e) => set('borrower_name', e.target.value)} error={errors.borrower_name} maxLength={150} />
          </FormField>
          <FormField label="Unit kerja" required error={errors.borrower_unit}>
            <Combobox
              value={form.borrower_unit}
              onChange={(v) => set('borrower_unit', v)}
              options={departments.map((d) => ({ id: d.id, label: d.name }))}
              error={errors.borrower_unit}
            />
          </FormField>
        </div>

        <FormField label="Keperluan / kegiatan" required error={errors.purpose}>
          <Input value={form.purpose} onChange={(e) => set('purpose', e.target.value)} error={errors.purpose} maxLength={255} />
        </FormField>

        <RoomSelect
          buildingLabel="Gedung tujuan"
          roomLabel="Ruangan tujuan"
          buildingId={form.buildingId}
          roomId={form.roomId}
          onChange={(b, r) => setForm((f) => ({ ...f, buildingId: b, roomId: r }))}
          error={errors.destination_room_id}
        />
        <FormField label="Catatan lokasi (opsional, jika di luar ruangan terdaftar)" error={errors.destination_note}>
          <Input value={form.destination_note} onChange={(e) => set('destination_note', e.target.value)} error={errors.destination_note} maxLength={255} />
        </FormField>

        <div className="grid gap-3 sm:grid-cols-2">
          <FormField label="Tanggal pinjam" error={errors.borrow_date}>
            <Input type="date" value={form.borrow_date} max={today()} onChange={(e) => set('borrow_date', e.target.value)} error={errors.borrow_date} />
          </FormField>
          <FormField label="Rencana kembali" required error={errors.due_date}>
            <Input type="date" value={form.due_date} min={form.borrow_date || undefined} onChange={(e) => set('due_date', e.target.value)} error={errors.due_date} />
          </FormField>
          <FormField label="Kondisi saat keluar">
            <Input value={asset?.condition ?? ''} placeholder="Otomatis dari aset" disabled readOnly />
          </FormField>
          <div className="grid grid-cols-2 gap-3">
            <FormField label="No. approval" error={errors.approval_number}>
              <Input value={form.approval_number} placeholder="Opsional" onChange={(e) => set('approval_number', e.target.value)} error={errors.approval_number} maxLength={100} />
            </FormField>
            <FormField label="Tgl. approval" error={errors.approval_date}>
              <Input type="date" value={form.approval_date} onChange={(e) => set('approval_date', e.target.value)} error={errors.approval_date} />
            </FormField>
          </div>
        </div>

        <div className="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row">
          <Button variant="secondary" onClick={reset}>Kosongkan</Button>
          <Button type="submit" loading={saving}>Simpan peminjaman</Button>
        </div>
      </form>
    </section>
  )
}
