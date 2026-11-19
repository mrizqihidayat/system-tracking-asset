/**
 * Zona waktu aplikasi (T45, temuan UAT D1): semua tanggal dan jam memakai WIB, sama dengan backend
 * (config app.timezone = Asia/Jakarta), apa pun zona waktu perangkat pengguna.
 */
export const APP_TIME_ZONE = 'Asia/Jakarta'

/** Tanggal WIB hari ini dalam format input tanggal (YYYY-MM-DD). `now` hanya untuk test. */
export const todayWib = (now: Date = new Date()): string => now.toLocaleDateString('en-CA', { timeZone: APP_TIME_ZONE })

const DAY_MS = 86_400_000

/** Selisih hari dari hari ini (WIB) ke `date` (YYYY-MM-DD): positif = nanti, negatif = sudah lewat. */
export const daysFromTodayWib = (date: string, now: Date = new Date()): number =>
  Math.round((Date.parse(`${date.slice(0, 10)}T00:00:00Z`) - Date.parse(`${todayWib(now)}T00:00:00Z`)) / DAY_MS)
