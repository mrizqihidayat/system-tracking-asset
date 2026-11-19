import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import AuthShell, { ErrorBox } from '../components/AuthShell'
import PasswordOtpFlow, { type OtpStep } from '../components/PasswordOtpFlow'
import PasswordRequirements from '../components/PasswordRequirements'
import Button from '../components/ui/Button'
import { FormField } from '../components/ui/FormField'
import PasswordInput from '../components/ui/PasswordInput'
import { useToast } from '../components/ui/toast-context'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { getUser, loginId, setUser, type AuthUser } from '../lib/auth'
import { confirmationState, passwordChecks } from '../lib/password'
import { safeNext } from '../lib/safe-next'
import { usePasswordMin } from '../lib/settings'
import { useLogout } from '../lib/useLogout'

type Step = 'form' | OtpStep

const OTP_TITLES: Record<OtpStep, { title: string; description: string }> = {
  send: { title: 'Lupa password?', description: 'Verifikasi lewat email, lalu buat password baru tanpa password saat ini.' },
  code: { title: 'Masukkan kode', description: 'Kode verifikasi dikirim ke email akun Anda.' },
  new: { title: 'Buat password baru', description: 'Kode benar. Tulis password baru untuk akun Anda.' },
}

/**
 * Ganti password sendiri (SPEC §15) dalam satu kartu: form dengan password saat ini, atau (T42) "Lupa password
 * saat ini?" lewat kode OTP ke email akun. Opsional dari menu akun di sidebar, atau wajib setelah password
 * direset staf SHD (T40) sebelum halaman lain bisa dibuka.
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
  const nextParam = params.get('next')
  const next = safeNext(nextParam, location.origin)

  const [step, setStep] = useState<Step>('form')
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  const set = (key: keyof typeof form, value: string) => {
    setForm((f) => ({ ...f, [key]: value }))
    setErrors((e) => ({ ...e, [key]: '' }))
  }

  /** Setelah selesai atau batal: ke tujuan `next`, kalau tidak ke halaman sebelumnya (Dashboard bila tidak ada). */
  const leave = () => {
    if (nextParam || forced || (window.history.state?.idx ?? 0) === 0) navigate(next, { replace: true })
    else navigate(-1)
  }

  const done = (updated: AuthUser) => {
    setUser(updated)
    toast.success('Password berhasil diganti.')
    leave()
  }

  const checks = passwordChecks(form.password, {
    loginId: user ? loginId(user) : '',
    idLabel: isEmployee ? 'NIK' : 'username',
    min: passwordMin,
    current: form.current_password,
  })
  const confirmation = confirmationState(form.password, form.password_confirmation)
  const canSave = form.current_password !== '' && checks.every((c) => c.met) && confirmation === 'match'

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    if (!canSave) return
    setMessage('')
    setSaving(true)
    try {
      const res = await api.put<AuthUser>('/user/password', form)
      done(res.data)
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setMessage(errorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  const subtitle = `${user?.name ?? ''} · ${user ? loginId(user) : ''}`
  const heading = step === 'form' ? { title: forced ? 'Buat password baru' : 'Ganti password', description: subtitle } : OTP_TITLES[step]

  return (
    <AuthShell card title={heading.title} description={heading.description}>
      {step !== 'form' && user ? (
        <PasswordOtpFlow step={step} onStep={setStep} user={user} onDone={done} onCancel={() => setStep('form')} />
      ) : (
        <form noValidate onSubmit={submit} className="space-y-5">
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
          <div className="-mt-3 text-right">
            <button
              type="button"
              onClick={() => setStep('send')}
              className="tap rounded text-sm font-medium text-brand-700 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
            >
              Lupa password?
            </button>
          </div>

          <FormField label="Password baru" required error={errors.password}>
            <PasswordInput value={form.password} onChange={(e) => set('password', e.target.value)} autoComplete="new-password" error={errors.password} required />
            <PasswordRequirements checks={checks} />
          </FormField>
          <FormField label="Konfirmasi password baru" required error={errors.password_confirmation}>
            <PasswordInput
              value={form.password_confirmation}
              onChange={(e) => set('password_confirmation', e.target.value)}
              autoComplete="new-password"
              error={errors.password_confirmation}
              required
            />
            {confirmation !== 'idle' && (
              <span className={`block text-xs font-medium ${confirmation === 'match' ? 'text-ok-700' : 'text-bad-700'}`} aria-live="polite">
                {confirmation === 'match' ? 'Password cocok' : 'Password tidak sama'}
              </span>
            )}
          </FormField>

          <div className="flex flex-col gap-2">
            <Button type="submit" loading={saving} disabled={!canSave} className="w-full">Simpan password</Button>
            {forced ? (
              <Button variant="ghost" className="w-full" onClick={logout}>Keluar</Button>
            ) : (
              <Button variant="ghost" className="w-full" onClick={leave}>Batal</Button>
            )}
          </div>
        </form>
      )}
    </AuthShell>
  )
}
