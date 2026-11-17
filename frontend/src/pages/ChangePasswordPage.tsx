import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import AuthShell, { ErrorBox } from '../components/AuthShell'
import Button from '../components/ui/Button'
import { FormField } from '../components/ui/FormField'
import PasswordInput from '../components/ui/PasswordInput'
import { useToast } from '../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { getUser, loginId, setUser, type AuthUser } from '../lib/auth'
import { passwordErrors } from '../lib/password'
import { safeNext } from '../lib/safe-next'
import { usePasswordMin } from '../lib/settings'
import { useLogout } from '../lib/useLogout'

/**
 * Ganti password sendiri (SPEC §15): opsional dari menu akun di sidebar, atau wajib setelah password direset
 * staf SHD (T40) sebelum halaman lain bisa dibuka.
 */
export default function ChangePasswordPage() {
  const navigate = useNavigate()
  const toast = useToast()
  const [params] = useSearchParams()
  const user = getUser()
  const isEmployee = user?.source === 'api'
  const forced = user?.must_change_password === true
  const passwordMin = usePasswordMin()
  const logout = useLogout()
  const next = safeNext(params.get('next'), location.origin)

  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setMessage('')
    const local = passwordErrors(form.password, form.password_confirmation, user ? loginId(user) : '', isEmployee ? 'NIK' : 'username', passwordMin)
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
    <AuthShell
      title={forced ? 'Buat password baru' : 'Ganti password'}
      description={`${user?.name ?? ''} · ${user ? loginId(user) : ''}`}
    >
      <form onSubmit={submit} className="space-y-5">
        {forced && (
          <p className="rounded-lg bg-info-50 px-3 py-2.5 text-sm text-info-700">
            Password Anda baru direset staf SHD. Buat password baru dulu untuk melanjutkan.
            {isEmployee && ' Password saat ini adalah NIK Anda.'}
          </p>
        )}
        <ErrorBox message={message} />

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
            <span className="block text-xs text-slate-500">Minimal {passwordMin} karakter.</span>
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
          {forced ? (
            <Button variant="secondary" className="w-full" onClick={logout}>Keluar</Button>
          ) : (
            <Button variant="secondary" className="w-full" onClick={() => navigate(-1)}>Batal</Button>
          )}
        </div>
      </form>
    </AuthShell>
  )
}
