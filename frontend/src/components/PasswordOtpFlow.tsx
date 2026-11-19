import axios from 'axios'
import { useState, type FormEvent } from 'react'
import { ErrorBox } from './AuthShell'
import PasswordRequirements from './PasswordRequirements'
import Button from './ui/Button'
import { FormField } from './ui/FormField'
import OtpInput from './ui/OtpInput'
import PasswordInput from './ui/PasswordInput'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { loginId, type AuthUser } from '../lib/auth'
import { maskEmail } from '../lib/email'
import { OTP_LENGTH } from '../lib/otp'
import { confirmationState, passwordChecks } from '../lib/password'
import { usePasswordMin } from '../lib/settings'
import { useCountdown } from '../lib/useCountdown'
import type { ApiErrorBody } from '../types/api'

export type OtpStep = 'send' | 'code' | 'new'

interface Props {
  step: OtpStep
  onStep: (step: OtpStep) => void
  user: AuthUser
  /** Password berhasil diganti; `user` adalah data akun terbaru dari server. */
  onDone: (user: AuthUser) => void
  /** Kembali ke form ganti password biasa. */
  onCancel: () => void
}

interface SendResponse {
  resend_after_seconds: number
  expires_in_minutes: number
}

/**
 * "Lupa password?" (T42, SPEC §15), tiga langkah di kartu yang sama: kirim kode ke email akun →
 * masukkan 6 angka → password baru tanpa password lama. Email hanya tampil disamarkan.
 */
export default function PasswordOtpFlow({ step, onStep, user, onDone, onCancel }: Props) {
  const passwordMin = usePasswordMin()
  const [otp, setOtp] = useState('')
  const [resetToken, setResetToken] = useState('')
  const [form, setForm] = useState({ password: '', password_confirmation: '' })
  const [minutes, setMinutes] = useState(10)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(false)
  const [wait, setWait] = useCountdown()

  const masked = user.email ? maskEmail(user.email) : ''

  const run = async (request: () => Promise<void>) => {
    setMessage('')
    setErrors({})
    setLoading(true)
    try {
      await request()
    } catch (error) {
      const fields = fieldErrors(error)
      setErrors(fields)
      if (Object.keys(fields).length === 0) setMessage(errorMessage(error))
    } finally {
      setLoading(false)
    }
  }

  const sendCode = () =>
    run(async () => {
      try {
        const res = await api.post<SendResponse>('/user/password/otp')
        setMinutes(res.data.expires_in_minutes)
        setWait(res.data.resend_after_seconds)
        setOtp('')
        onStep('code')
      } catch (error) {
        // Kode sudah dikirim kurang dari 60 detik lalu: lanjut ke langkah kode dengan sisa hitung mundur.
        const body = axios.isAxiosError<ApiErrorBody>(error) ? error.response?.data : undefined
        if (body?.code !== 'otp_resend_wait') throw error
        setWait(body.resend_after_seconds ?? 60)
        onStep('code')
      }
    })

  const submitCode = (e: FormEvent) => {
    e.preventDefault()
    run(async () => {
      try {
        const res = await api.post<{ reset_token: string }>('/user/password/otp/verify', { otp })
        setResetToken(res.data.reset_token)
        onStep('new')
      } catch (error) {
        setOtp('')
        throw error
      }
    })
  }

  const checks = passwordChecks(form.password, {
    loginId: loginId(user),
    idLabel: user.source === 'api' ? 'NIK' : 'username',
    min: passwordMin,
  })
  const confirmation = confirmationState(form.password, form.password_confirmation)
  const canSave = checks.every((c) => c.met) && confirmation === 'match'

  const submitPassword = (e: FormEvent) => {
    e.preventDefault()
    if (!canSave) return
    run(async () => {
      try {
        const res = await api.post<AuthUser>('/user/password/otp/reset', { reset_token: resetToken, ...form })
        onDone(res.data)
      } catch (error) {
        // Sesi verifikasi habis: ulang dari kirim kode dengan pesan yang jelas.
        const expired = fieldErrors(error).reset_token
        if (!expired) throw error
        setForm({ password: '', password_confirmation: '' })
        onStep('send')
        setMessage(expired)
      }
    })
  }

  const cancel = (
    <Button variant="ghost" className="w-full" onClick={onCancel} disabled={loading}>
      Kembali
    </Button>
  )

  if (step === 'send') {
    if (!user.email) {
      return (
        <div className="space-y-5">
          <p role="alert" className="rounded-lg bg-info-50 px-3 py-2.5 text-sm text-info-700">
            Akun ini belum memiliki email. Minta pegawai SHD mereset password melalui Pengaturan &gt; Akun.
          </p>
          {cancel}
        </div>
      )
    }

    return (
      <div className="space-y-5">
        <p className="text-sm text-slate-600">
          Kode verifikasi akan dikirim ke <span className="font-semibold text-slate-800">{masked}</span>
        </p>
        <ErrorBox message={message} />
        <div className="flex flex-col gap-2">
          <Button loading={loading} className="w-full" onClick={sendCode}>Kirim kode</Button>
          {cancel}
        </div>
      </div>
    )
  }

  if (step === 'code') {
    return (
      <form noValidate onSubmit={submitCode} className="space-y-5">
        <p className="text-sm text-slate-600">
          Masukkan {OTP_LENGTH} angka yang dikirim ke <span className="font-semibold text-slate-800">{masked}</span>.
          Kode berlaku {minutes} menit.
        </p>
        <ErrorBox message={message} />
        <div className="space-y-1.5">
          <OtpInput value={otp} onChange={setOtp} invalid={Boolean(errors.otp)} disabled={loading} autoFocus />
          {errors.otp && (
            <p role="alert" className="text-xs font-medium text-bad-700">
              {errors.otp}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Button type="submit" loading={loading} disabled={otp.length !== OTP_LENGTH} className="w-full">Lanjut</Button>
          <Button variant="secondary" className="w-full" disabled={wait > 0 || loading} onClick={sendCode}>
            {wait > 0 ? `Kirim ulang kode (${wait} detik)` : 'Kirim ulang kode'}
          </Button>
          <Button variant="ghost" className="w-full" onClick={onCancel} disabled={loading}>Batal</Button>
        </div>
      </form>
    )
  }

  return (
    <form noValidate onSubmit={submitPassword} className="space-y-5">
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
        <PasswordRequirements checks={checks} />
      </FormField>
      <FormField label="Konfirmasi password baru" required error={errors.password_confirmation}>
        <PasswordInput
          value={form.password_confirmation}
          onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))}
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
        <Button type="submit" loading={loading} disabled={!canSave} className="w-full">Simpan password</Button>
        {cancel}
      </div>
    </form>
  )
}
