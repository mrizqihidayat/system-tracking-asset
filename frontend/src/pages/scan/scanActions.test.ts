import assert from 'node:assert/strict'
import { test } from 'node:test'
import { resolveScanAction } from './scanActions.ts'

const available = { id: 7, status: 'Tersedia', active_loan: null }
const borrowed = { id: 7, status: 'Dipinjam', active_loan: { id: 42 } }
const availableActions = ['pinjam', 'pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai']
const borrowedActions = ['terima_kembali', 'pindah_lokasi', 'catat_kerusakan', 'batalkan']

test('Pinjam membuka form pinjam dengan aset terisi', () => {
  assert.deepEqual(resolveScanAction('pinjam', available, availableActions), { kind: 'page', url: '/peminjaman?asset_id=7' })
})

test('Terima kembali membuka modal untuk peminjaman aktif aset itu', () => {
  assert.deepEqual(resolveScanAction('terima_kembali', borrowed, borrowedActions), { kind: 'page', url: '/peminjaman?return_loan_id=42' })
})

test('aksi lain membuka form di hasil scan', () => {
  for (const key of ['pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai']) {
    assert.deepEqual(resolveScanAction(key, available, availableActions), { kind: 'form', action: key })
  }
  assert.deepEqual(
    resolveScanAction('tandai_ditemukan', { id: 7, status: 'Hilang', active_loan: null }, ['tandai_ditemukan', 'pindah_lokasi']),
    { kind: 'form', action: 'tandai_ditemukan' },
  )
})

test('Terima kembali tanpa peminjaman aktif memberi pesan, baik status sudah berubah maupun data tidak konsisten', () => {
  const unavailable = { kind: 'unavailable', message: 'Aset ini tidak punya peminjaman aktif.' }
  assert.deepEqual(resolveScanAction('terima_kembali', available, availableActions), unavailable)
  assert.deepEqual(resolveScanAction('terima_kembali', { id: 7, status: 'Dipinjam', active_loan: null }, borrowedActions), unavailable)
})

test('aksi yang tidak berlaku untuk status terkini memberi pesan berisi status itu', () => {
  assert.deepEqual(resolveScanAction('pinjam', borrowed, borrowedActions), {
    kind: 'unavailable',
    message: 'Aksi Pinjam tidak tersedia karena aset berstatus Dipinjam.',
  })
})

test('kunci di luar SCAN_ACTIONS tidak pernah menjadi form, termasuk batalkan yang ada di meta.actions', () => {
  for (const key of ['batalkan', 'tidak_dikenal', 'constructor']) {
    assert.equal(resolveScanAction(key, borrowed, borrowedActions).kind, 'unavailable', key)
  }
})
