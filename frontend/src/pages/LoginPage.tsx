import { useState, type FormEvent } from 'react'
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import Button from '../components/ui/Button'
import { FormField, Input } from '../components/ui/FormField'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { getToken, setSession, type AuthUser } from '../lib/auth'

/** Hanya path internal; menolak URL absolut / `//host` (open redirect). */
function safeNext(next: string | null): string {
  return next && next.startsWith('/') && !next.startsWith('//') ? next : '/'
}

export default function LoginPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})

  const next = safeNext(params.get('next'))

  if (getToken()) return <Navigate to={next} replace />

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setMessage('')
    setErrors({})
    try {
      const res = await api.post<{ token: string; user: AuthUser }>('/login', { username, password })
      setSession(res.data.token, res.data.user)
      navigate(next, { replace: true })
    } catch (error) {
      setErrors(fieldErrors(error))
      setMessage(errorMessage(error))
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center p-4">
      <form onSubmit={submit} className="w-full max-w-sm space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <div>
          <h1 className="text-xl font-semibold">Aset SHD</h1>
          <p className="text-sm text-slate-500">Masuk untuk melanjutkan.</p>
        </div>
        {message && (
          <div role="alert" className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
            {message}
          </div>
        )}
        <FormField label="Username" error={errors.username}>
          <Input value={username} onChange={(e) => setUsername(e.target.value)} autoComplete="username" autoFocus required />
        </FormField>
        <FormField label="Password" error={errors.password}>
          <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required />
        </FormField>
        <Button type="submit" loading={loading} className="w-full">Masuk</Button>
      </form>
    </div>
  )
}
