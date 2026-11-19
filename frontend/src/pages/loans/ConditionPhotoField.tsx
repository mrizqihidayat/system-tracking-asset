import { Camera, X } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { FormField } from '../../components/ui/FormField'
import { MAX_UPLOAD_BYTES, MAX_UPLOAD_LABEL, compressImage } from '../../lib/image'

const ACCEPT = 'image/jpeg,image/png,image/webp'

interface Props {
  file: File | null
  onChange: (file: File | null) => void
  /** Wajib jika kondisi Rusak Ringan / Rusak Berat. */
  required: boolean
  /** Pesan dari server (422/413). */
  error?: string
  /** Label kolom (bawaan "Foto kondisi"; dipakai juga untuk "Foto aset" di form Tambah aset, T45). */
  label?: string
}

/**
 * Foto kondisi saat Terima kembali (T36): satu foto, dari galeri atau kamera HP, dengan pratinjau.
 * Disimpan per peminjaman dan menjadi foto kondisi terakhir aset.
 */
export default function ConditionPhotoField({ file, onChange, required, error, label = 'Foto kondisi' }: Props) {
  const input = useRef<HTMLInputElement>(null)
  const [localError, setLocalError] = useState('')
  // URL pratinjau diturunkan dari file (tanpa setState di effect) dan dilepas saat file berganti.
  const preview = useMemo(() => (file ? URL.createObjectURL(file) : null), [file])
  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview)
  }, [preview])

  const pick = async (picked: File | undefined) => {
    if (!picked) return
    if (!ACCEPT.split(',').includes(picked.type)) return setLocalError('Foto harus berupa JPG, PNG, atau WebP.')
    // Foto kamera HP bisa 5-10 MB: dikecilkan dulu agar muat di batas server.
    const ready = await compressImage(picked)
    if (ready.size > MAX_UPLOAD_BYTES) return setLocalError(`Ukuran foto maksimal ${MAX_UPLOAD_LABEL}.`)
    setLocalError('')
    onChange(ready)
  }

  const shownError = localError || error

  return (
    <FormField label={required ? label : `${label} (opsional)`} required={required} error={shownError}>
      <div>
        <input
          ref={input}
          type="file"
          accept={ACCEPT}
          className="sr-only"
          tabIndex={-1}
          onChange={(e) => {
            const picked = e.target.files?.[0]
            e.target.value = ''
            pick(picked)
          }}
        />
        {file && preview ? (
          <div className="flex items-center gap-3 rounded-lg border border-slate-200 p-2">
            <img src={preview} alt={`Pratinjau ${label.toLowerCase()}`} className="size-16 shrink-0 rounded-md object-cover" />
            <div className="min-w-0 flex-1 text-sm">
              <p className="truncate font-medium">{file.name}</p>
              <button type="button" className="tap text-xs font-semibold text-brand-700 hover:underline" onClick={() => input.current?.click()}>
                Ganti foto
              </button>
            </div>
            <button
              type="button"
              onClick={() => {
                setLocalError('')
                onChange(null)
              }}
              aria-label="Hapus foto"
              className="flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800"
            >
              <X size={16} />
            </button>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => input.current?.click()}
            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 bg-white px-3 text-sm font-medium text-slate-600 hover:border-slate-400 hover:bg-slate-50"
          >
            <Camera size={16} aria-hidden /> Ambil atau pilih foto
          </button>
        )}
        {!shownError && <p className="mt-1.5 text-xs text-slate-500">JPG, PNG, atau WebP, maksimal {MAX_UPLOAD_LABEL} (dikecilkan otomatis).</p>}
      </div>
    </FormField>
  )
}
