import { RotateCw } from 'lucide-react'
import Button from './Button'

interface Props {
  /** Muat ulang data (mis. `refetch` dari React Query). Tanpa ini hanya pesan yang tampil. */
  onRetry?: () => void
  /** Sedang mencoba lagi: tombol menampilkan status memproses. */
  retrying?: boolean
  className?: string
}

/**
 * Keadaan gagal memuat (T45, temuan UAT U2): pengganti skeleton yang terus memuat saat koneksi atau server gagal.
 * Dipakai panel Dashboard dan semua tabel daftar (DataTable `error`).
 */
export default function LoadError({ onRetry, retrying, className = '' }: Props) {
  return (
    <div role="alert" className={`flex flex-col items-center justify-center gap-3 px-4 py-8 text-center text-sm text-slate-600 ${className}`}>
      <p>Data gagal dimuat.</p>
      {onRetry && (
        <Button variant="secondary" onClick={onRetry} loading={retrying}>
          <RotateCw size={15} aria-hidden />
          Coba lagi
        </Button>
      )}
    </div>
  )
}
