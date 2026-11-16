/** Batas ukuran unggahan file aset. Samakan dengan backend (StoreAssetFileRequest::MAX_BYTES); Vercel membatasi body 4,5 MB. */
export const MAX_UPLOAD_BYTES = 4 * 1024 * 1024
export const MAX_UPLOAD_LABEL = '4 MB'

export const MAX_SIDE = 2560
// Foto kecil dibiarkan apa adanya agar tidak turun kualitas tanpa perlu.
export const SKIP_BELOW_BYTES = 1024 * 1024
export const QUALITIES = [0.85, 0.75, 0.65, 0.5]
export const MAX_SHRINKS = 3

/** Ukuran baru yang muat dalam `maxSide` px pada sisi terpanjang, rasio dijaga, tidak pernah diperbesar. */
export function fitWithin(width: number, height: number, maxSide: number): { width: number; height: number } {
  const longest = Math.max(width, height)
  if (longest <= maxSide) return { width, height }
  const ratio = maxSide / longest
  return { width: Math.max(1, Math.round(width * ratio)), height: Math.max(1, Math.round(height * ratio)) }
}

/** Ganti ekstensi nama file sesuai tipe hasil kompresi. */
export function renameForType(name: string, type: string): string {
  const ext = type === 'image/webp' ? 'webp' : 'jpg'
  const dot = name.lastIndexOf('.')
  return `${dot > 0 ? name.slice(0, dot) : name}.${ext}`
}
