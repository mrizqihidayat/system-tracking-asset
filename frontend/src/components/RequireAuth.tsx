import axios from 'axios'
import { useEffect, useState, type ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { api } from '../lib/api'
import { getToken, getUser, isVerified, markVerified, setUser, type AuthUser } from '../lib/auth'

export default function RequireAuth({ children }: { children: ReactNode }) {
  const location = useLocation()
  const hasToken = getToken() !== null
  const [ready, setReady] = useState(isVerified())

  useEffect(() => {
    if (!hasToken || ready) return
    let cancelled = false
    api
      .get<AuthUser>('/user')
      .then((res) => {
        setUser(res.data)
        markVerified()
      })
      .catch((error) => {
        // 401: interceptor di api.ts menghapus sesi dan mengarahkan ke /login?next=…
        if (axios.isAxiosError(error) && error.response?.status === 401) return
        // Gagal jaringan/server: lanjut dengan data lokal, 401 berikutnya ditangani interceptor.
        if (!cancelled) setReady(true)
      })
      .then(() => {
        if (!cancelled && isVerified()) setReady(true)
      })
    return () => {
      cancelled = true
    }
  }, [hasToken, ready])

  if (!hasToken) {
    return <Navigate to={`/login?next=${encodeURIComponent(location.pathname + location.search)}`} replace />
  }
  if (!ready) {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-slate-500">Memuat…</div>
    )
  }

  // Wajib ganti password (setelah reset staf SHD) lalu wajib isi email, sebelum halaman lain bisa dibuka (T40).
  const user = getUser()
  const forced = user?.must_change_password ? '/ganti-password' : user?.email_required ? '/isi-email' : null
  if (forced && location.pathname !== forced) {
    const here = location.pathname + location.search
    const next = ['/ganti-password', '/isi-email'].includes(location.pathname) ? '' : `?next=${encodeURIComponent(here)}`
    return <Navigate to={`${forced}${next}`} replace />
  }

  return <>{children}</>
}
