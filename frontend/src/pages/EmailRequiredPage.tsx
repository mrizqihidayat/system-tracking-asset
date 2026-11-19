import { useState, type FormEvent } from 'react'
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import AuthShell, { ErrorBox } from '../components/AuthShell'
import Button from '../components/ui/Button'
import { FormField, Input } from '../components/ui/FormField'
import { useToast } from '../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { getUser, loginId, setUser, type AuthUser } from '../lib/auth'
import { safeNext } from '../lib/safe-next'
import { useLogout } from '../lib/useLogout'

/**
 * Form wajib isi email (T40, SPEC §15): akun tanpa email mengisinya sekali setelah login sebelum bisa memakai
 * aplikasi. Email menjadi tujuan kode OTP saat lupa password.
 */
export default function EmailRequiredPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const logout = useLogout()
  const [params] = useSearchParams()
  const user = getUser()
  const next = safeNext(params.get('next'), location.origin)

  const [email, setEmail] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  if (user && !user.email_required) return <Navigate to={next} replace />

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setError('')
    setMessage('')
    setSaving(true)
    try {
      const res = await api.put<AuthUser>('/user/email', { email: email.trim() })
      setUser(res.data)
      toast.success('Email tersimpan.')
      navigate(next, { replace: true })
    } catch (err) {
      const field = fieldErrors(err).email
      if (field) setError(field)
      else setMessage(errorMessage(err))
      setSaving(false)
    }
  }

  return (
    <AuthShell
      title="Isi email Anda"
      description="Email dipakai untuk mengirim kode saat Anda lupa password. Cukup diisi sekali."
    >
      <form noValidate onSubmit={submit} className="space-y-5">
        <p className="text-sm text-slate-500">{`${user?.name ?? ''} · ${user ? loginId(user) : ''}`}</p>
        <ErrorBox message={message} />
        <FormField label="Email" required error={error}>
          <Input
            type="email"
            inputMode="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            autoComplete="email"
            maxLength={150}
            placeholder="nama@contoh.com"
            error={error}
            autoFocus
            required
          />
          {!error && <span className="block text-xs text-slate-500">Pakai email yang bisa Anda buka. Periksa lagi ejaannya sebelum menyimpan.</span>}
        </FormField>
        <div className="flex flex-col gap-2">
          <Button type="submit" loading={saving} className="w-full">Simpan dan lanjut</Button>
          <Button variant="secondary" className="w-full" onClick={logout}>Keluar</Button>
        </div>
      </form>
    </AuthShell>
  )
}
