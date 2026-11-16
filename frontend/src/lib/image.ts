import { MAX_SHRINKS, MAX_SIDE, MAX_UPLOAD_BYTES, QUALITIES, SKIP_BELOW_BYTES, fitWithin, renameForType } from './image-size'

export { MAX_UPLOAD_BYTES, MAX_UPLOAD_LABEL } from './image-size'

async function decode(file: File): Promise<{ source: CanvasImageSource; width: number; height: number; close: () => void }> {
  if (typeof createImageBitmap === 'function') {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
    return { source: bitmap, width: bitmap.width, height: bitmap.height, close: () => bitmap.close() }
  }
  const url = URL.createObjectURL(file)
  try {
    const img = new Image()
    img.src = url
    await img.decode()
    return { source: img, width: img.naturalWidth, height: img.naturalHeight, close: () => {} }
  } finally {
    URL.revokeObjectURL(url)
  }
}

function toBlob(canvas: HTMLCanvasElement, type: string, quality: number): Promise<Blob | null> {
  return new Promise((resolve) => canvas.toBlob(resolve, type, quality))
}

/**
 * Perkecil foto sebelum diunggah agar muat di batas 4 MB. File bukan gambar atau sudah kecil dikembalikan apa adanya;
 * bila kompresi gagal (browser tidak mendukung, file rusak), file asli dikembalikan dan server yang menilai.
 * JPEG tetap JPEG; PNG/WebP menjadi WebP supaya transparansi tidak hilang.
 */
export async function compressImage(file: File, maxBytes: number = MAX_UPLOAD_BYTES): Promise<File> {
  if (!file.type.startsWith('image/') || file.size <= SKIP_BELOW_BYTES) return file

  try {
    const decoded = await decode(file)
    const outType = file.type === 'image/jpeg' ? 'image/jpeg' : 'image/webp'
    let { width, height } = fitWithin(decoded.width, decoded.height, MAX_SIDE)
    let best: Blob | null = null

    for (let shrink = 0; shrink <= MAX_SHRINKS; shrink++) {
      const canvas = document.createElement('canvas')
      canvas.width = width
      canvas.height = height
      const ctx = canvas.getContext('2d')
      if (!ctx) break
      if (outType === 'image/jpeg') {
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, width, height)
      }
      ctx.drawImage(decoded.source, 0, 0, width, height)

      for (const quality of QUALITIES) {
        const blob = await toBlob(canvas, outType, quality)
        if (!blob || blob.type !== outType) continue
        if (!best || blob.size < best.size) best = blob
        if (blob.size <= maxBytes) {
          decoded.close()
          return new File([blob], renameForType(file.name, outType), { type: outType, lastModified: file.lastModified })
        }
      }
      width = Math.max(1, Math.round(width * 0.75))
      height = Math.max(1, Math.round(height * 0.75))
    }

    decoded.close()
    if (best && best.size < file.size) {
      return new File([best], renameForType(file.name, outType), { type: outType, lastModified: file.lastModified })
    }
    return file
  } catch {
    return file
  }
}
