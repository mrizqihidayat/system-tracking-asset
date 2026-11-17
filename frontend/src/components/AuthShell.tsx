import type { ReactNode } from 'react'
import defaultLogo from '../assets/logo.png'
import { usePublicSettings } from '../lib/settings'

/** Logo dari Pengaturan (T40); bawaan logo INALUM. */
export function SiteLogo({ className = 'h-10 w-auto' }: { className?: string }) {
  const settings = usePublicSettings()
  return <img src={settings.logo_url ?? defaultLogo} alt={settings.site_title} className={className} />
}

export function ErrorBox({ message }: { message: string }) {
  if (!message) return null
  return (
    <div role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">
      {message}
    </div>
  )
}

/**
 * Bingkai halaman satu-form di luar menu (lupa password, isi email, ganti password): logo, judul, keterangan,
 * lalu isi. Lebar penuh di HP (mulai 375 px) dengan jarak tepi 16 px.
 */
export default function AuthShell({ title, description, children }: { title: string; description?: ReactNode; children: ReactNode }) {
  return (
    <main className="flex min-h-screen items-center justify-center px-4 py-10">
      <div className="w-full max-w-sm space-y-5">
        <div>
          <SiteLogo />
          <h1 className="mt-6 text-2xl font-bold tracking-tight text-balance">{title}</h1>
          {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
        </div>
        {children}
      </div>
    </main>
  )
}
