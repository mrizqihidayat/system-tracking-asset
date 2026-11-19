import assert from 'node:assert/strict'
import { test } from 'node:test'
import { unavailableReason } from './asset-availability.ts'

const now = new Date('2026-11-19T03:00:00Z')

test('pinjam sekarang: hanya aset Tersedia yang bisa dipilih, lainnya diberi alasan', () => {
  assert.equal(unavailableReason({ status: 'Tersedia', active_loan_due_date: null }, false, now), null)
  assert.equal(unavailableReason({ status: 'Perbaikan', active_loan_due_date: null }, false, now), 'Perbaikan, tidak bisa dipinjam')
  assert.equal(unavailableReason({ status: 'Dipinjam', active_loan_due_date: '2026-11-20' }, false, now), 'Sedang dipinjam sampai 20 Nov')
  assert.equal(unavailableReason({ status: 'Dipinjam', active_loan_due_date: '2027-01-05' }, false, now), 'Sedang dipinjam sampai 5 Jan 2027')
  assert.equal(unavailableReason({ status: 'Hilang', active_loan_due_date: null }, false, now), 'Hilang, tidak bisa dipinjam')
  assert.equal(unavailableReason({ status: 'Tidak Layak Pakai', active_loan_due_date: null }, false, now), 'Tidak Layak Pakai, tidak bisa dipinjam')
})

test('pemesanan: aset Dipinjam dan Perbaikan boleh dipesan, Hilang dan Tidak Layak Pakai tidak', () => {
  assert.equal(unavailableReason({ status: 'Dipinjam', active_loan_due_date: '2026-11-20' }, true, now), null)
  assert.equal(unavailableReason({ status: 'Perbaikan', active_loan_due_date: null }, true, now), null)
  assert.equal(unavailableReason({ status: 'Hilang', active_loan_due_date: null }, true, now), 'Hilang, tidak bisa dipesan')
})
