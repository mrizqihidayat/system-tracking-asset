import { useEffect, useState, type FormEvent } from 'react'
import EmployeeCombobox from '../../components/EmployeeCombobox'
import Button from '../../components/ui/Button'
import { FormField, Input } from '../../components/ui/FormField'
import Modal from '../../components/ui/Modal'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../../lib/api'
import { formatDate } from '../../lib/format'
import type { Asset } from '../../types/asset'
import AssetPicker, { type PickedAsset } from './AssetPicker'
import { unavailableReason } from './asset-availability'
import { todayWib as today } from '../../lib/date'


const emptyForm = () => ({
  borrower_name: '',
  borrower_unit: '',
  purpose: '',
  destination_note: '',
  borrow_date: today(),
  due_date: '',
  approval_number: '',
})

interface Props {
  /** Dari `?asset_id=` (detail aset / hasil scan): aset diisi otomatis jika boleh dipinjam. */
  initialAssetId?: string | null
  onClose: () => void
  onSaved: () => void
}

/** Popup "Catat peminjaman baru": isi bisa di-scroll, tombol Simpan selalu terlihat di bawah. */
export default function LoanFormModal({ initialAssetId, onClose, onSaved }: Props) {
  const toast = useToast()
  const [asset, setAsset] = useState<PickedAsset | null>(null)
  const [loadingAsset, setLoadingAsset] = useState(!!initialAssetId)
  const [form, setForm] = useState(emptyForm)
  // Tanggal pinjam setelah hari ini → pemesanan (Direncanakan). Format YYYY-MM-DD bisa dibandingkan sebagai teks.
  const planned = form.borrow_date > today()
  // Aset yang sudah dipilih lalu tanggalnya diganti (mis. pesan → pinjam sekarang) bisa jadi tidak boleh lagi.
  const assetProblem = asset ? unavailableReason({ status: asset.status, active_loan_due_date: null }, planned) : null
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  const set = <K extends keyof ReturnType<typeof emptyForm>>(key: K, value: ReturnType<typeof emptyForm>[K]) =>
    setForm((f) => ({ ...f, [key]: value }))

  useEffect(() => {
    if (!initialAssetId) return
    let cancelled = false
    api
      .get<{ data: Asset; meta: { actions: string[] } }>(`/assets/${encodeURIComponent(initialAssetId)}`)
      .then((res) => {
        if (cancelled) return
        if (res.data.meta.actions.includes('pinjam')) setAsset(res.data.data)
        else toast.error(`${res.data.data.code} berstatus ${res.data.data.status}, tidak dapat dipinjam.`)
      })
      .catch((e) => !cancelled && toast.error(errorMessage(e)))
      .finally(() => !cancelled && setLoadingAsset(false))
    return () => {
      cancelled = true
    }
  }, [initialAssetId, toast])

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setMessage('')
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
        destination_note: form.destination_note.trim() || null,
        borrow_date: form.borrow_date || null,
        due_date: form.due_date,
        approval_number: form.approval_number.trim() || null,
      })
      toast.success(planned ? `Pemesanan ${asset.code} untuk ${formatDate(form.borrow_date)} tersimpan.` : `Peminjaman ${asset.code} tersimpan.`)
      onSaved()
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setMessage(errorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open
      size="lg"
      title="Catat peminjaman baru"
      onClose={onClose}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Batal</Button>
          <Button type="submit" form="loan-form" loading={saving}>{planned ? 'Simpan pemesanan' : 'Simpan peminjaman'}</Button>
        </>
      }
    >
      <form noValidate id="loan-form" onSubmit={submit} className="space-y-4">
        {message && (
          <div role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">
            {message}
          </div>
        )}

        {/* Tanggal pinjam di atas pilihan aset: menentukan aset mana yang bisa dipilih (pinjam sekarang / pesan), T45 UAT A5. */}
        <FormField label="Tanggal pinjam" error={errors.borrow_date} className="sm:w-1/2 sm:pr-1.5">
          <Input type="date" value={form.borrow_date} onChange={(e) => set('borrow_date', e.target.value)} error={errors.borrow_date} />
          {planned && !errors.borrow_date && (
            <span className="block text-xs text-slate-500">Setelah hari ini: dicatat sebagai Direncanakan, aset tetap Tersedia sampai diserahkan.</span>
          )}
        </FormField>

        {loadingAsset ? (
          <p role="status" className="text-sm text-slate-500">Memuat aset…</p>
        ) : (
          <AssetPicker asset={asset} onPick={setAsset} error={errors.asset_id ?? assetProblem ?? undefined} planned={planned} />
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          <FormField label="Nama peminjam" required error={errors.borrower_name}>
            {/* Saran pegawai: memilih mengisi Departemen otomatis; nama di luar direktori tetap boleh diketik. */}
            <EmployeeCombobox
              value={form.borrower_name}
              onChange={(name) => set('borrower_name', name)}
              onPick={(employee) => set('borrower_unit', employee.department)}
              error={errors.borrower_name}
            />
          </FormField>
          <FormField label="Departemen" required error={errors.borrower_unit}>
            <Input value={form.borrower_unit} onChange={(e) => set('borrower_unit', e.target.value)} error={errors.borrower_unit} maxLength={100} />
          </FormField>
        </div>

        <FormField label="Keperluan / kegiatan" required error={errors.purpose}>
          <Input value={form.purpose} onChange={(e) => set('purpose', e.target.value)} error={errors.purpose} maxLength={255} />
        </FormField>

        <FormField label="Lokasi tujuan" error={errors.destination_note}>
          <Input
            value={form.destination_note}
            onChange={(e) => set('destination_note', e.target.value)}
            placeholder="Misal: Gedung IHS, Ruang Kelas 1"
            error={errors.destination_note}
            maxLength={255}
          />
        </FormField>

        <div className="grid gap-3 sm:grid-cols-2">
          <FormField label="Rencana kembali" required error={errors.due_date}>
            <Input type="date" value={form.due_date} min={form.borrow_date || undefined} onChange={(e) => set('due_date', e.target.value)} error={errors.due_date} />
          </FormField>
          <FormField label="Kondisi saat keluar">
            <Input value={asset?.condition ?? ''} placeholder="Otomatis dari aset" disabled readOnly />
          </FormField>
          <FormField label="No. / tanggal approval" error={errors.approval_number}>
            <Input value={form.approval_number} placeholder="Opsional" onChange={(e) => set('approval_number', e.target.value)} error={errors.approval_number} maxLength={100} />
          </FormField>
        </div>
      </form>
    </Modal>
  )
}
