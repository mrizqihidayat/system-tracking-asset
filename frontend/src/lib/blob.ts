import type { AxiosResponse } from 'axios'

/** Blob error dari axios (responseType blob) → JSON agar errorMessage() bisa membaca `message`. */
export async function unwrapBlobError(error: unknown): Promise<never> {
  const res = (error as { response?: { data?: unknown } }).response
  if (res?.data instanceof Blob) {
    try {
      res.data = JSON.parse(await res.data.text())
    } catch {
      // bukan JSON; biarkan pesan umum
    }
  }
  throw error
}

/**
 * Ambil berkas (PDF/gambar) lewat axios ber-token lalu tampilkan di tab baru.
 * Tab dibuka lebih dulu (sinkron dengan klik) supaya tidak diblokir popup blocker.
 */
export async function openInNewTab(request: () => Promise<AxiosResponse<Blob>>) {
  const tab = window.open('', '_blank')
  try {
    const res = await request()
    const url = URL.createObjectURL(res.data)
    if (tab) tab.location.href = url
    else window.location.href = url
    setTimeout(() => URL.revokeObjectURL(url), 60_000)
  } catch (error) {
    tab?.close()
    return unwrapBlobError(error)
  }
}
