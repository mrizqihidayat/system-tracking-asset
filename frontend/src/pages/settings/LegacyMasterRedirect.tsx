import { Navigate, useSearchParams } from 'react-router-dom'

/** Alamat lama Data Master (sebelum T40) diarahkan ke tab Data Master di Pengaturan. */
export default function LegacyMasterRedirect() {
  const [params] = useSearchParams()
  const section = params.get('tab')
  return <Navigate to={`/pengaturan?tab=data-master${section ? `&bagian=${encodeURIComponent(section)}` : ''}`} replace />
}
