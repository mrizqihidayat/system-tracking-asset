import { useRef } from 'react'
import Button from '../../components/ui/Button'
import { ASSET_FILE_ACCEPT } from '../../lib/asset-files'
import { formatSize } from '../../lib/format'
import { MAX_UPLOAD_LABEL } from '../../lib/image'

/** Dokumen yang dipilih di form Tambah aset; diunggah setelah aset tersimpan. */
export interface PendingDocs {
  BAST: File | null
  Garansi: File | null
  Lainnya: File[]
}

const SLOTS: { type: 'BAST' | 'Garansi'; label: string }[] = [
  { type: 'BAST', label: 'BAST penerimaan' },
  { type: 'Garansi', label: 'Kartu garansi' },
]

/** Pilihan dokumen (BAST, garansi, dokumen lain) saat Tambah aset. Belum dikirim ke server sampai "Simpan aset". */
export default function PendingAssetDocs({ value, onChange }: { value: PendingDocs; onChange: (docs: PendingDocs) => void }) {
  const inputs = useRef<Record<string, HTMLInputElement | null>>({})

  const picker = (key: string, onPick: (file: File) => void) => (
    <input
      ref={(el) => {
        inputs.current[key] = el
      }}
      type="file"
      accept={ASSET_FILE_ACCEPT}
      className="hidden"
      onChange={(e) => {
        const file = e.target.files?.[0]
        e.target.value = ''
        if (file) onPick(file)
      }}
    />
  )

  return (
    <div className="space-y-3 text-sm sm:col-span-2">
      <p className="text-xs text-slate-500">
        Opsional, diunggah setelah aset tersimpan. JPG, PNG, WebP, atau PDF, maksimal {MAX_UPLOAD_LABEL} per file.
      </p>
      <ul className="divide-y divide-slate-100">
        {SLOTS.map(({ type, label }) => {
          const file = value[type]
          return (
            <li key={type} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
              <span className="min-w-0">
                <span className="block font-medium">{label}</span>
                <span className="block break-all text-xs text-slate-500">{file ? `${file.name} · ${formatSize(file.size)}` : 'Belum dipilih'}</span>
              </span>
              <span className="flex shrink-0 gap-2">
                {picker(type, (f) => onChange({ ...value, [type]: f }))}
                <Button type="button" variant="secondary" className="px-3!" onClick={() => inputs.current[type]?.click()}>
                  {file ? 'Ganti' : 'Pilih file'}
                </Button>
                {file && (
                  <Button type="button" variant="secondary" className="px-3! text-bad-700!" onClick={() => onChange({ ...value, [type]: null })}>
                    Hapus
                  </Button>
                )}
              </span>
            </li>
          )
        })}
      </ul>

      <div className="space-y-2 border-t border-slate-100 pt-3">
        <div className="flex items-center justify-between gap-2">
          <span className="font-medium">Dokumen lain ({value.Lainnya.length})</span>
          {picker('Lainnya', (f) => onChange({ ...value, Lainnya: [...value.Lainnya, f] }))}
          <Button type="button" variant="secondary" className="px-3!" onClick={() => inputs.current.Lainnya?.click()}>
            Tambah dokumen
          </Button>
        </div>
        {value.Lainnya.length > 0 && (
          <ul className="divide-y divide-slate-100">
            {value.Lainnya.map((file, i) => (
              <li key={`${file.name}-${i}`} className="flex items-center justify-between gap-2 py-2">
                <span className="min-w-0 break-all">
                  {file.name} <span className="text-slate-500">· {formatSize(file.size)}</span>
                </span>
                <Button
                  type="button"
                  variant="secondary"
                  className="px-3! text-bad-700!"
                  onClick={() => onChange({ ...value, Lainnya: value.Lainnya.filter((_, j) => j !== i) })}
                >
                  Hapus
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}
