import { useState } from 'react'
import { Input } from './ui/FormField'

interface Props {
  /** Nilai di URL (YYYY-MM-DD atau kosong). */
  from: string
  to: string
  /** Dipanggil hanya dengan rentang yang sah (Sampai tidak sebelum Dari). */
  onChange: (from: string, to: string) => void
  /** Nama tanggal yang disaring, untuk pembaca layar (mis. "tanggal pinjam"). */
  dateLabel: string
}

/**
 * Filter periode "Dari – Sampai" (T45, temuan UAT A2) untuk Riwayat peminjaman dan Riwayat permintaan; nilainya ikut
 * ke Ekspor Excel. Rentang terbalik tidak diterapkan, cukup diberi pesan, supaya daftar tidak gagal dimuat.
 */
export default function DateRangeFilter({ from, to, onChange, dateLabel }: Props) {
  const [draft, setDraft] = useState({ from, to })
  const [synced, setSynced] = useState({ from, to })
  // Ikuti URL bila berubah dari luar (mis. tombol Reset).
  if (synced.from !== from || synced.to !== to) {
    setSynced({ from, to })
    setDraft({ from, to })
  }

  const reversed = Boolean(draft.from && draft.to && draft.to < draft.from)

  const update = (next: { from: string; to: string }) => {
    setDraft(next)
    if (!(next.from && next.to && next.to < next.from)) onChange(next.from, next.to)
  }

  const field = 'flex min-w-0 flex-col gap-1 text-xs font-medium text-slate-600 sm:flex-row sm:items-center sm:gap-2 sm:text-sm sm:font-normal'

  return (
    <div className="min-w-0 space-y-1">
      <div className="grid grid-cols-2 gap-2 sm:flex sm:items-center">
        <label className={field}>
          <span className="shrink-0">Dari</span>
          <Input
            type="date"
            className="sm:w-36"
            value={draft.from}
            max={draft.to || undefined}
            onChange={(e) => update({ ...draft, from: e.target.value })}
            aria-label={`${dateLabel} dari`}
          />
        </label>
        <label className={field}>
          <span className="shrink-0">Sampai</span>
          <Input
            type="date"
            className="sm:w-36"
            value={draft.to}
            min={draft.from || undefined}
            onChange={(e) => update({ ...draft, to: e.target.value })}
            aria-label={`${dateLabel} sampai`}
            error={reversed ? 'Rentang tanggal terbalik' : undefined}
          />
        </label>
      </div>
      {reversed && <p role="alert" className="text-xs font-medium text-bad-700">Tanggal &ldquo;Sampai&rdquo; tidak boleh sebelum &ldquo;Dari&rdquo;.</p>}
    </div>
  )
}
