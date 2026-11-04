/** Aksi yang bisa dikerjakan langsung dari hasil scan (kunci API, SPEC §6). */
export const SCAN_ACTIONS: Record<string, string> = {
  pinjam: 'Pinjam',
  terima_kembali: 'Terima kembali',
  pindah_lokasi: 'Pindah lokasi',
  catat_kerusakan: 'Catat kerusakan',
  catat_kehilangan: 'Catat kehilangan',
  catat_tidak_layak_pakai: 'Catat tidak layak pakai',
  tandai_ditemukan: 'Tandai ditemukan',
}

export type ScanActionTarget =
  | { kind: 'page'; url: string }
  | { kind: 'form'; action: string }
  | { kind: 'unavailable'; message: string }

interface ScanAsset {
  id: number
  status: string
  active_loan?: { id: number } | null
}

export const borrowUrl = (assetId: number) => `/peminjaman?asset_id=${assetId}`

/**
 * Satu titik keputusan aksi scan: Pinjam & Terima kembali dikerjakan di halaman Peminjaman, aksi lain lewat
 * form di hasil scan. Selain itu "unavailable", sehingga hasil scan tidak tertutup tanpa form pengganti.
 */
export function resolveScanAction(key: string, asset: ScanAsset, actions: string[]): ScanActionTarget {
  const loan = asset.active_loan
  if (key === 'terima_kembali' && !loan) return { kind: 'unavailable', message: 'Aset ini tidak punya peminjaman aktif.' }
  if (!Object.hasOwn(SCAN_ACTIONS, key)) return { kind: 'unavailable', message: 'Aksi ini tidak tersedia dari hasil scan.' }
  if (!actions.includes(key)) {
    return { kind: 'unavailable', message: `Aksi ${SCAN_ACTIONS[key]} tidak tersedia karena aset berstatus ${asset.status}.` }
  }
  if (key === 'pinjam') return { kind: 'page', url: borrowUrl(asset.id) }
  if (key === 'terima_kembali' && loan) return { kind: 'page', url: `/peminjaman?return_loan_id=${loan.id}` }
  return { kind: 'form', action: key }
}
