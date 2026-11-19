import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import bgInalum from '../assets/bg_inalum.webp'
import { SiteLogo } from '../components/AuthShell'
import Button from '../components/ui/Button'
import { FormField, Input } from '../components/ui/FormField'
import PasswordInput from '../components/ui/PasswordInput'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { getToken, setSession, type AuthUser } from '../lib/auth'
import { safeNext } from '../lib/safe-next'
import { usePublicSettings } from '../lib/settings'

export default function LoginPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const settings = usePublicSettings()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})

  const next = safeNext(params.get('next'), location.origin)

  if (getToken()) return <Navigate to={next} replace />

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setMessage('')
    // Pesan sendiri di bawah kolom, bukan pesan bawaan browser (T45, temuan UAT U4).
    const local: Record<string, string> = {}
    if (!username.trim()) local.username = 'Username wajib diisi.'
    if (!password) local.password = 'Password wajib diisi.'
    setErrors(local)
    if (Object.keys(local).length > 0) return
    setLoading(true)
    try {
      const res = await api.post<{ token: string; user: AuthUser }>('/login', { username, password })
      setSession(res.data.token, res.data.user)
      navigate(next, { replace: true })
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setMessage(errorMessage(error))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
      <aside className="relative hidden flex-col justify-center overflow-hidden bg-rail p-10 text-white lg:flex">
        <img src={bgInalum} alt="" className="absolute inset-0 h-full w-full object-cover object-[46%_center]" />
        <div className="absolute inset-0 bg-rail/70" />
        <div className="relative max-w-sm">
          <p className="text-3xl font-bold leading-tight tracking-tight text-balance">
            Aset, peminjaman, dan kelengkapan ruangan pelatihan dalam satu catatan.
          </p>
          {settings.footer_text && <p className="mt-3 text-sm text-white/80">{settings.footer_text}</p>}
        </div>
      </aside>

      <main className="flex items-center justify-center px-4 py-10">
        <form noValidate onSubmit={submit} className="w-full max-w-sm space-y-5">
          <div>
            <SiteLogo />
            <h1 className="mt-6 text-2xl font-bold tracking-tight">Selamat Datang Kembali</h1>
            {settings.site_description && <p className="mt-1 text-sm text-slate-500">{settings.site_description}</p>}
          </div>
          {message && (
            <div role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">
              {message}
            </div>
          )}
          <FormField label="Username" error={errors.username}>
            <Input value={username} onChange={(e) => setUsername(e.target.value)} autoComplete="username" autoFocus required />
          </FormField>
          <FormField label="Password" error={errors.password}>
            <PasswordInput value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required />
          </FormField>
          <Button type="submit" loading={loading} className="w-full">Masuk</Button>
          <p className="text-center text-sm">
            <Link to="/lupa-password" className="tap font-semibold text-brand-700 hover:underline">Lupa password?</Link>
          </p>
          {settings.footer_text && <p className="text-center text-xs text-slate-500 lg:hidden">{settings.footer_text}</p>}
        </form>
      </main>
    </div>
  )
}
