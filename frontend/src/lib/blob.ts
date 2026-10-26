import type { AxiosResponse } from 'axios'

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
    throw error
  }
}
