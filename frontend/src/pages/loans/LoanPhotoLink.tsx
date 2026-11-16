import { Camera } from 'lucide-react'
import { useToast } from '../../components/ui/toast-context'
import { api, errorMessage } from '../../lib/api'
import { openInNewTab } from '../../lib/blob'
import type { AssetFile } from '../../types/asset'

/** Tautan "Foto kondisi" di riwayat peminjaman: membuka foto saat Terima kembali di tab baru (T36). */
export default function LoanPhotoLink({ photo }: { photo: AssetFile | null | undefined }) {
  const toast = useToast()
  if (!photo) return null

  return (
    <button
      type="button"
      className="tap inline-flex items-center gap-1 whitespace-nowrap text-xs font-semibold text-brand-700 hover:underline"
      onClick={() => openInNewTab(() => api.get<Blob>(photo.download_url, { responseType: 'blob' })).catch((e) => toast.error(errorMessage(e)))}
    >
      <Camera size={13} aria-hidden /> Foto kondisi
    </button>
  )
}
