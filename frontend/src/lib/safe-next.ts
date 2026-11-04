/** Tujuan setelah login: hanya path di origin yang sama, selain itu ke Dashboard. */
export function safeNext(next: string | null, origin: string): string {
  if (!next || !next.startsWith('/') || next.startsWith('//')) return '/'

  // Browser membaca `\` seperti `/` dan membuang tab atau baris baru, jadi `/\evil.com` bisa menjadi domain lain.
  for (const char of next) {
    const code = char.charCodeAt(0)
    if (char === '\\' || code < 0x20 || code === 0x7f) return '/'
  }

  try {
    return new URL(next, origin).origin === origin ? next : '/'
  } catch {
    return '/'
  }
}
