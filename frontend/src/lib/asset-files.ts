import type { FileType } from '../types/asset'
import { api, errorMessage } from './api'
import { MAX_UPLOAD_BYTES, MAX_UPLOAD_LABEL, compressImage } from './image'

export const ASSET_FILE_ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf'
export const ASSET_PHOTO_ACCEPT = 'image/jpeg,image/png,image/webp'

/** File ditolak sebelum dikirim (tipe atau ukuran); pesannya langsung untuk pengguna. */
class UploadRejected extends Error {}

/**
 * Unggah satu file aset (SPEC §5): cek tipe, kecilkan foto agar muat batas server, lalu kirim. Dipakai Ubah data,
 * kotak Foto aset di detail, dan form Tambah aset (T45, temuan UAT U1). Slot terisi diganti (`replaced`).
 */
export async function uploadAssetFile(assetId: number | string, type: FileType, file: File, accept = ASSET_FILE_ACCEPT): Promise<{ replaced: boolean }> {
  if (!accept.split(',').includes(file.type)) {
    throw new UploadRejected(accept === ASSET_PHOTO_ACCEPT ? 'Foto harus berupa JPG, PNG, atau WebP.' : 'Tipe file harus JPG, PNG, WebP, atau PDF.')
  }
  // Foto dikecilkan dulu agar muat di batas server; PDF tidak diubah.
  const ready = await compressImage(file)
  if (ready.size > MAX_UPLOAD_BYTES) throw new UploadRejected(`Ukuran file maksimal ${MAX_UPLOAD_LABEL}.`)

  const body = new FormData()
  body.append('file_type', type)
  body.append('file', ready)
  const res = await api.post<{ meta?: { replaced?: boolean } }>(`/assets/${assetId}/files`, body)
  return { replaced: Boolean(res.data.meta?.replaced) }
}

/** Pesan gagal unggah untuk pengguna (penolakan lokal atau respons server). */
export const uploadErrorMessage = (error: unknown) => (error instanceof UploadRejected ? error.message : errorMessage(error))
