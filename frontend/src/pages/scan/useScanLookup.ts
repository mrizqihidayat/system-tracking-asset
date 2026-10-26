import { useCallback, useState } from 'react'
import { api } from '../../lib/api'
import type { Asset } from '../../types/asset'

export interface ScanResult {
  asset: Asset
  actions: string[]
  matchedBy: string | null
}

/** Isi QR berupa URL `{QR_BASE_URL}/aset/{token}` → ambil segmen terakhir; kode biasa dipakai apa adanya. */
export function extractScanValue(raw: string): string {
  const text = raw.trim()
  if (!text.includes('/')) return text
  const segments = text.split(/[?#]/)[0].split('/').filter(Boolean)
  return segments[segments.length - 1] ?? ''
}

/** Mencari aset lewat GET /assets/scan/{value}. */
export function useScanLookup() {
  const [loading, setLoading] = useState(false)

  const lookup = useCallback(async (raw: string): Promise<ScanResult> => {
    setLoading(true)
    try {
      const res = await api.get<{ data: Asset; meta: { actions: string[]; matched_by: string | null } }>(
        `/assets/scan/${encodeURIComponent(extractScanValue(raw))}`,
      )
      return { asset: res.data.data, actions: res.data.meta.actions, matchedBy: res.data.meta.matched_by }
    } finally {
      setLoading(false)
    }
  }, [])

  return { lookup, loading }
}
