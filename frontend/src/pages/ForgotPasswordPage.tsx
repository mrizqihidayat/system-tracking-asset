import { useEffect, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import AuthShell, { ErrorBox } from '../components/AuthShell'
import Button from '../components/ui/Button'
import { buttonClass } from '../components/ui/button-class'
import { FormField, Input } from '../components/ui/FormField'
import PasswordInput from '../components/ui/PasswordInput'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { passwordErrors } from '../lib/password'
import { usePasswordMin } from '../lib/settings'

type Step = 'email' | 'otp' | 'password' | 'done'

interface ForgotResponse {
  message: string
  resend_after_seconds: number
  expires_in_minutes: number
}

/** Hitung mundur detik sampai kode boleh dikirim ulang. */
function useCountdown() {
  const [seconds, setSeconds] = useState(0)

  useEffect(() => {
    if (seconds <= 0) return
    const timer = setTimeout(() => setSeconds((s) => s - 1), 1000)
    return () => clearTimeout(timer)
  }, [seconds])

  return [seconds, setSeconds] as const
}

/**
 * Lupa password via OTP (T40, SPEC §15): isi email → masukkan kode 6 angka dari email → buat password baru.
 * Server tidak pernah memberi tahu apakah email terdaftar, jadi halaman ini juga tidak.
 */
export default function ForgotPasswordPage() {
  const passwordMin = usePasswordMin()
  const [step, setStep] = useState<Step>('email')
  const [email, setEmail] = useState('')
  const [otp, setOtp] = useState('')
  const [resetToken, setResetToken] = useState('')
  const [form, setForm] = useState({ password: '', password_confirmation: '' })
  const [minutes, setMinutes] = useState(10)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(false)
  const [wait, setWait] = useCountdown()

  const fail = (error: unknown) => {
    const fields = fieldErrors(error)
    setErrors(fields)
    if (Object.keys(fields).length === 0) setMessage(errorMessage(error))
  }

  const run = async (request: () => Promise<void>) => {
    setMessage('')
    setErrors({})
    setLoading(true)
    try {
      await request()
    } catch (error) {
      fail(error)
    } finally {
      setLoading(false)
    }
  }

  const requestOtp = () =>
    run(async () => {
      const res = await api.post<ForgotResponse>('/password/forgot', { email: email.trim() })
      setMinutes(res.data.expires_in_minutes)
      setWait(res.data.resend_after_seconds)
      setOtp('')
      setStep('otp')
    })

  const submitEmail = (e: FormEvent) => {
    e.preventDefault()
    requestOtp()
  }

  const submitOtp = (e: FormEvent) => {
    e.preventDefault()
    run(async () => {
      const res = await api.post<{ reset_token: string }>('/password/verify-otp', { email: email.trim(), otp })
      setResetToken(res.data.reset_token)
      setStep('password')
    })
  }

  const submitPassword = (e: FormEvent) => {
    e.preventDefault()
    const local = passwordErrors(form.password, form.password_confirmation, '', 'username', passwordMin)
    if (Object.keys(local).length > 0) {
      setErrors(local)
      return
    }
    run(async () => {
      try {
        await api.post('/password/reset', { email: email.trim(), reset_token: resetToken, ...form })
        setStep('done')
      } catch (error) {
        // Sesi reset habis: ulang dari awal dengan pesan yang jelas.
        const expired = fieldErrors(error).reset_token
        if (!expired) throw error
        setStep('email')
        setForm({ password: '', password_confirmation: '' })
        setMessage(expired)
      }
    })
  }

  const backToLogin = (
    <Link to="/login" className={buttonClass('secondary', 'w-full')}>
      Kembali ke halaman masuk
    </Link>
  )

  if (step === 'done') {
    return (
      <AuthShell title="Password berhasil diganti" description="Silakan masuk dengan password baru Anda.">
        <Link to="/login" className={buttonClass('primary', 'w-full')}>
          Masuk
        </Link>
      </AuthShell>
    )
  }

  if (step === 'email') {
    return (
      <AuthShell title="Lupa password" description="Tulis email akun Anda. Kami akan mengirim kode 6 angka ke email itu.">
        <form onSubmit={submitEmail} className="space-y-5">
          <ErrorBox message={message} />
          <FormField label="Email" required error={errors.email}>
            <Input
              type="email"
              inputMode="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
              maxLength={150}
              error={errors.email}
              autoFocus
              required
            />
          </FormField>
          <div className="flex flex-col gap-2">
            <Button type="submit" loading={loading} className="w-full">Kirim kode</Button>
            {backToLogin}
          </div>
        </form>
      </AuthShell>
    )
  }

  if (step === 'otp') {
    return (
      <AuthShell
        title="Masukkan kode"
        description={
          <>
            Jika <span className="break-all font-medium text-slate-700">{email.trim()}</span> terdaftar, kode 6 angka sudah dikirim ke email itu.
            Kode berlaku {minutes} menit.
          </>
        }
      >
        <form onSubmit={submitOtp} className="space-y-5">
          <ErrorBox message={message} />
          <FormField label="Kode dari email" required error={errors.otp}>
            <Input
              value={otp}
              onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))}
              inputMode="numeric"
              autoComplete="one-time-code"
              pattern="\d{6}"
              maxLength={6}
              placeholder="6 angka"
              error={errors.otp}
              className="text-center font-mono text-lg tracking-[0.4em]"
              autoFocus
              required
            />
          </FormField>
          <div className="flex flex-col gap-2">
            <Button type="submit" loading={loading} disabled={otp.length !== 6} className="w-full">Lanjut</Button>
            <Button variant="secondary" className="w-full" disabled={wait > 0 || loading} onClick={requestOtp}>
              {wait > 0 ? `Kirim ulang kode (${wait} detik)` : 'Kirim ulang kode'}
            </Button>
            <Button variant="ghost" className="w-full" onClick={() => { setStep('email'); setMessage(''); setErrors({}) }}>
              Ganti email
            </Button>
          </div>
          <p className="text-sm text-slate-500">Tidak menerima email? Hubungi staf SHD untuk reset password.</p>
        </form>
      </AuthShell>
    )
  }

  return (
    <AuthShell title="Buat password baru" description="Kode benar. Tulis password baru untuk akun Anda.">
      <form onSubmit={submitPassword} className="space-y-5">
        <ErrorBox message={message} />
        <FormField label="Password baru" required error={errors.password}>
          <PasswordInput
            value={form.password}
            onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
            autoComplete="new-password"
            error={errors.password}
            autoFocus
            required
          />
          {!errors.password && <span className="block text-xs text-slate-500">Minimal {passwordMin} karakter.</span>}
        </FormField>
        <FormField label="Tulis ulang password baru" required error={errors.password_confirmation}>
          <PasswordInput
            value={form.password_confirmation}
            onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))}
            autoComplete="new-password"
            error={errors.password_confirmation}
            required
          />
        </FormField>
        <div className="flex flex-col gap-2">
          <Button type="submit" loading={loading} className="w-full">Simpan password</Button>
          {backToLogin}
        </div>
      </form>
    </AuthShell>
  )
}
