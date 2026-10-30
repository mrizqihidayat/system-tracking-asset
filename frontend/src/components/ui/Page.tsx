import { ArrowLeft } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

/** Kepala halaman: judul + keterangan di kiri, aksi di kanan (turun ke bawah di HP). */
export function PageHeader({ title, description, actions, back }: {
  title: ReactNode
  description?: ReactNode
  actions?: ReactNode
  back?: { to: string; label: string }
}) {
  return (
    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
      <div className="min-w-0">
        {back && (
          <Link to={back.to} className="mb-1 inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:underline">
            <ArrowLeft size={14} /> {back.label}
          </Link>
        )}
        <h1 className="text-2xl font-bold tracking-tight text-balance">{title}</h1>
        {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  )
}

/** Panel putih ber-border dengan judul dan link opsional. `flush` = isi tanpa padding (tabel/daftar). */
export function Panel({ title, link, actions, children, flush, className = '' }: {
  title?: ReactNode
  link?: { to: string; label: string }
  actions?: ReactNode
  children: ReactNode
  flush?: boolean
  className?: string
}) {
  return (
    <section className={`overflow-hidden rounded-xl border border-slate-200 bg-white ${className}`}>
      {(title || link || actions) && (
        <div className="flex items-center justify-between gap-3 px-5 pb-3 pt-4">
          {title && <h2 className="font-semibold">{title}</h2>}
          {link && (
            <Link to={link.to} className="shrink-0 text-sm font-semibold text-brand-700 hover:underline">
              {link.label}
            </Link>
          )}
          {actions}
        </div>
      )}
      <div className={flush ? '' : `px-5 pb-5 ${title || link || actions ? '' : 'pt-5'}`}>{children}</div>
    </section>
  )
}

/** Teks status kosong/memuat di dalam panel. */
export const Muted = ({ children, className = '' }: { children: ReactNode; className?: string }) => (
  <p className={`text-sm text-slate-500 ${className}`}>{children}</p>
)
