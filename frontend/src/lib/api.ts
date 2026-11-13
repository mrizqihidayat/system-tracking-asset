import axios, { AxiosError } from 'axios'
import type { ApiErrorBody } from '../types/api'
import { clearSession, getToken } from './auth'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (error: AxiosError<ApiErrorBody>) => {
    const isLogin = error.config?.url?.endsWith('/login')
    if (error.response?.status === 401 && !isLogin) {
      clearSession()
      if (location.pathname !== '/login') {
        location.assign(`/login?next=${encodeURIComponent(location.pathname + location.search)}`)
      }
    }
    // Akun wajib ganti password (SPEC §15): semua endpoint lain ditolak sampai password diganti.
    if (error.response?.status === 403 && error.response.data?.code === 'password_change_required' && location.pathname !== '/ganti-password') {
      location.assign(`/ganti-password?next=${encodeURIComponent(location.pathname + location.search)}`)
    }
    return Promise.reject(error)
  },
)

/** Pesan error ramah pengguna dari respons API (validasi, 429, dst.). */
export function errorMessage(error: unknown): string {
  if (axios.isAxiosError<ApiErrorBody>(error)) {
    if (!error.response) return 'Tidak dapat terhubung ke server.'
    return error.response.data?.message ?? 'Terjadi kesalahan.'
  }
  return 'Terjadi kesalahan.'
}

/** Error validasi per field (422), kosong jika bukan 422. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (axios.isAxiosError<ApiErrorBody>(error) && error.response?.status === 422) {
    const errors = error.response.data?.errors ?? {}
    return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]))
  }
  return {}
}
