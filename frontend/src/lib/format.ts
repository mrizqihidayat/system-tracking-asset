import { APP_TIME_ZONE } from './date.ts'

// Selalu WIB (T45, temuan UAT D1), tidak bergantung zona waktu perangkat.
const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: APP_TIME_ZONE })
const dateTimeFmt = new Intl.DateTimeFormat('id-ID', {
  day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: APP_TIME_ZONE,
})

export const formatDate = (value: string | null | undefined) => (value ? dateFmt.format(new Date(value)) : '-')

const dayMonthFmt = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', timeZone: APP_TIME_ZONE })
const yearFmt = new Intl.DateTimeFormat('id-ID', { year: 'numeric', timeZone: APP_TIME_ZONE })

/** Tanggal ringkas: "20 Nov" bila tahun ini, "5 Jan 2027" bila tahun lain. `now` hanya untuk test. */
export const formatShortDate = (value: string, now: Date = new Date()) => {
  const date = new Date(value)
  return yearFmt.format(date) === yearFmt.format(now) ? dayMonthFmt.format(date) : dateFmt.format(date).replace(/^0/, '')
}
export const formatDateTime = (value: string | null | undefined) => (value ? dateTimeFmt.format(new Date(value)) : '-')

export const formatSize = (bytes: number) =>
  bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`
