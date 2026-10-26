import { useEffect, useState } from 'react'
import { api } from './api'

/** Ambil gambar ber-auth sebagai blob dan kembalikan object URL (null selagi memuat/gagal/tanpa path). */
export function useBlobUrl(path: string | null): string | null {
  const [loaded, setLoaded] = useState<{ path: string; url: string } | null>(null)

  useEffect(() => {
    if (!path) return
    let cancelled = false
    let created: string | undefined
    api
      .get<Blob>(path, { responseType: 'blob' })
      .then((res) => {
        if (cancelled) return
        created = URL.createObjectURL(res.data)
        setLoaded({ path, url: created })
      })
      .catch(() => {})
    return () => {
      cancelled = true
      if (created) URL.revokeObjectURL(created)
    }
  }, [path])

  return loaded && loaded.path === path ? loaded.url : null
}
