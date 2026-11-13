import { useState, type FormEvent } from 'react'
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import bgInalum from '../assets/bg_inalum.webp'
import logo from '../assets/logo.png'
import Button from '../components/ui/Button'
import { FormField, Input } from '../components/ui/FormField'
import PasswordInput from '../components/ui/PasswordInput'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { getToken, setSession, type AuthUser } from '../lib/auth'
import { safeNext } from '../lib/safe-next'

export default function LoginPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})

  const next = safeNext(params.get('next'), location.origin)

  if (getToken()) return <Navigate to={next} replace />

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setMessage('')
    setErrors({})
    try {
      const res = await api.post<{ token: string; user: AuthUser }>('/login', { username, password })
      setSession(res.data.token, res.data.user)
      // Akun pegawai SHD saat login pertama / setelah reset: wajib ganti password dulu (SPEC §15).
      navigate(res.data.user.must_change_password ? `/ganti-password?next=${encodeURIComponent(next)}` : next, { replace: true })
    } catch (error) {
      setErrors(fieldErrors(error))
      setMessage(errorMessage(error))
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
          <p className="mt-3 text-sm text-[#c4d6e4]">Departemen SHD · PT INALUM</p>
        </div>
      </aside>

      <main className="flex items-center justify-center px-4 py-10">
        <form onSubmit={submit} className="w-full max-w-sm space-y-5">
          <div>
            <img src={logo} alt="INALUM" className="h-10 w-auto" />
            <h1 className="mt-6 text-2xl font-bold tracking-tight">Selamat Datang Kembali</h1>
            <p className="mt-1 text-sm text-slate-500">Masuk untuk melanjutkan ke Sistem Informasi Aset</p>
          </div>
          {message && (
            <div role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">
              {message}
            </div>
          )}
          <FormField label="NIK / Username" error={errors.username}>
            <Input value={username} onChange={(e) => setUsername(e.target.value)} autoComplete="username" autoFocus required />
            <span className="block text-xs text-slate-500">Pegawai SHD memakai NIK. Login pertama: password = tanggal lahir (DDMMYYYY).</span>
          </FormField>
          <FormField label="Password" error={errors.password}>
            <PasswordInput value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required />
          </FormField>
          <Button type="submit" loading={loading} className="w-full">Masuk</Button>
        </form>
      </main>
    </div>
  )
}
