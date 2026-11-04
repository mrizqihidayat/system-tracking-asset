import { api } from './api'
import { unwrapBlobError } from './blob'

export type ReportType = 'assets' | 'loans' | 'rooms' | 'requests' | 'consumables'

export const REPORT_LABELS: Record<ReportType, string> = {
  assets: 'Laporan Aset',
  loans: 'Laporan Peminjaman',
  rooms: 'Laporan Kelengkapan Ruangan',
  requests: 'Laporan Permintaan',
  consumables: 'Laporan Stok Habis Pakai',
}

// Sama dengan ReportType::slug() di backend (nama cadangan bila header tidak terbaca).
const SLUGS: Record<ReportType, string> = {
  assets: 'aset',
  loans: 'peminjaman',
  rooms: 'kelengkapan-ruangan',
  requests: 'permintaan',
  consumables: 'stok-habis-pakai',
}

/** Nama file dari `Content-Disposition` (filename* atau filename). */
function filenameFrom(header: string | undefined): string | null {
  if (!header) return null
  const star = /filename\*=(?:UTF-8'')?([^;]+)/i.exec(header)
  if (star) return decodeURIComponent(star[1].trim().replace(/"/g, ''))
  const plain = /filename="?([^";]+)"?/i.exec(header)
  return plain ? plain[1].trim() : null
}

/** Simpan Blob sebagai file lewat tautan sementara. */
function save(blob: Blob, name: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 10_000)
}

/**
 * Ekspor Excel: kirim filter yang sedang aktif ke GET /reports/{type} (tanpa parameter format),
 * ambil sebagai blob ber-token, lalu unduh dengan nama dari Content-Disposition.
 */
export async function downloadReport(type: ReportType, filters: Record<string, unknown> | URLSearchParams = {}) {
  const params =
    filters instanceof URLSearchParams
      ? filters
      : Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== false))
  try {
    const res = await api.get<Blob>(`/reports/${type}`, { params, responseType: 'blob' })
    const fallback = `laporan-${SLUGS[type]}-${new Date().toLocaleDateString('en-CA').replaceAll('-', '')}.xlsx`
    save(res.data, filenameFrom(res.headers['content-disposition']) ?? fallback)
  } catch (error) {
    return unwrapBlobError(error)
  }
}
