import { SearchX } from 'lucide-react'
import { Link } from 'react-router-dom'
import { buttonClass } from '../components/ui/button-class'

/** Alamat yang tidak dikenal di dalam aplikasi (T45, temuan UAT U7). */
export default function NotFoundPage() {
  return (
    <div className="flex flex-col items-center gap-4 rounded-xl border border-slate-200 bg-white px-6 py-14 text-center">
      <SearchX size={36} className="text-slate-400" aria-hidden />
      <div className="space-y-1">
        <h1 className="text-xl font-semibold">Halaman tidak ditemukan</h1>
        <p className="text-sm text-slate-600">Halaman yang Anda cari tidak ditemukan.</p>
      </div>
      <Link to="/" className={buttonClass('primary')}>Kembali ke Dashboard</Link>
    </div>
  )
}
