import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import logo from '../assets/logo.png'
import Button from '../components/ui/Button'
import { FormField } from '../components/ui/FormField'
import PasswordInput from '../components/ui/PasswordInput'
import { useToast } from '../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { getUser, loginId, setUser, type AuthUser } from '../lib/auth'
import { PASSWORD_MIN, passwordErrors } from '../lib/password'
import { safeNext } from '../lib/safe-next'

/** Ganti password sendiri, opsional, dibuka dari menu akun di sidebar (SPEC §15, T33 R2). */
export default function ChangePasswordPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const [params] = useSearchParams()
  const user = getUser()
  const isEmployee = user?.source === 'api'
  const next = safeNext(params.get('next'), location.origin)

  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setMessage('')
    const local = passwordErrors(form.password, form.password_confirmation, user ? loginId(user) : '', isEmployee ? 'NIK' : 'username')
    setErrors(local)
    if (Object.keys(local).length > 0) return

    setSaving(true)
    try {
      const res = await api.put<AuthUser>('/user/password', form)
      setUser(res.data)
      toast.success('Password berhasil diganti.')
      navigate(next, { replace: true })
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setMessage(errorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  return (
    <main className="flex min-h-screen items-center justify-center px-4 py-10">
      <form onSubmit={submit} className="w-full max-w-sm space-y-5">
        <div>
          <img src={logo} alt="INALUM" className="h-10 w-auto" />
          <h1 className="mt-6 text-2xl font-bold tracking-tight">Ganti password</h1>
          <p className="mt-1 text-sm text-slate-500">{`${user?.name ?? ''} · ${user ? loginId(user) : ''}`}</p>
        </div>

        {message && (
          <div role="alert" className="rounded-lg bg-bad-50 px-3 py-2.5 text-sm text-bad-700">
            {message}
          </div>
        )}

        <FormField label="Password saat ini" required error={errors.current_password}>
          <PasswordInput
            value={form.current_password}
            onChange={(e) => set('current_password', e.target.value)}
            autoComplete="current-password"
            error={errors.current_password}
            required
          />
        </FormField>
        <FormField label="Password baru" required error={errors.password}>
          <PasswordInput value={form.password} onChange={(e) => set('password', e.target.value)} autoComplete="new-password" error={errors.password} required />
          {!errors.password && (
            <span className="block text-xs text-slate-500">Minimal {PASSWORD_MIN} karakter.</span>
          )}
        </FormField>
        <FormField label="Konfirmasi password baru" required error={errors.password_confirmation}>
          <PasswordInput
            value={form.password_confirmation}
            onChange={(e) => set('password_confirmation', e.target.value)}
            autoComplete="new-password"
            error={errors.password_confirmation}
            required
          />
        </FormField>

        <div className="flex flex-col gap-2">
          <Button type="submit" loading={saving} className="w-full">Simpan password</Button>
          <Button variant="secondary" className="w-full" onClick={() => navigate(-1)}>Batal</Button>
        </div>
      </form>
    </main>
  )
}
