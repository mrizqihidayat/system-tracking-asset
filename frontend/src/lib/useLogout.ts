import { useNavigate } from 'react-router-dom'
import { api } from './api'
import { clearSession } from './auth'

/** Keluar: cabut token di server (bila terjangkau), hapus sesi lokal, kembali ke halaman masuk. */
export function useLogout() {
  const navigate = useNavigate()

  return async () => {
    try {
      await api.post('/logout')
    } catch {
      // token tetap dihapus lokal walau server tidak terjangkau
    }
    clearSession()
    navigate('/login', { replace: true })
  }
}
