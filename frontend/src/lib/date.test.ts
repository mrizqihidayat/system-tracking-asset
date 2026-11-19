import assert from 'node:assert/strict'
import { test } from 'node:test'
import { daysFromTodayWib, todayWib } from './date.ts'
import { formatDate, formatDateTime } from './format.ts'

test('pukul 06.30 WIB tanggalnya sudah hari ini walau tanggal UTC masih kemarin', () => {
  // 18 Nov 2026 23.30 UTC = 19 Nov 2026 06.30 WIB
  assert.equal(todayWib(new Date('2026-11-18T23:30:00Z')), '2026-11-19')
  assert.equal(todayWib(new Date('2026-11-19T16:59:00Z')), '2026-11-19')
  assert.equal(todayWib(new Date('2026-11-19T17:00:00Z')), '2026-11-20')
})

test('selisih hari dihitung dari tanggal WIB', () => {
  const now = new Date('2026-11-18T23:30:00Z') // 19 Nov 06.30 WIB
  assert.equal(daysFromTodayWib('2026-11-19', now), 0)
  assert.equal(daysFromTodayWib('2026-11-17', now), -2)
  assert.equal(daysFromTodayWib('2026-11-22', now), 3)
})

test('tanggal dan jam ditampilkan dalam WIB', () => {
  assert.equal(formatDate('2026-11-19'), '19 Nov 2026')
  assert.match(formatDateTime('2026-11-18T23:30:00+00:00'), /^19 Nov 2026,? 06[.:]30$/)
  assert.match(formatDateTime('2026-11-19T06:30:00+07:00'), /^19 Nov 2026,? 06[.:]30$/)
})
