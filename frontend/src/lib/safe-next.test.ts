import assert from 'node:assert/strict'
import { test } from 'node:test'
import { safeNext } from './safe-next.ts'

const origin = 'https://aset.example.com'

test('path lokal dipertahankan beserta query-nya', () => {
  for (const next of ['/', '/daftar-aset', '/scan/abc?aksi=pinjam', '/peminjaman?return_loan_id=5', '/%5Cbukan-domain', '/@lokal', '/.lokal']) {
    assert.equal(safeNext(next, origin), next)
  }
})

test('kosong atau bukan path menuju Dashboard', () => {
  for (const next of [null, '', 'daftar-aset', 'https://evil.example', 'javascript:alert(1)']) {
    assert.equal(safeNext(next, origin), '/', String(next))
  }
})

test('bentuk yang dibaca browser sebagai domain lain ditolak', () => {
  for (const next of ['//evil.example', '///evil.example', '/\\evil.example', '/\\/evil.example', '\\\\evil.example']) {
    assert.equal(safeNext(next, origin), '/', next)
  }
})

test('karakter kontrol ditolak', () => {
  for (const next of ['/\t/evil.example', '/\n/evil.example', '/\r/evil.example', '/a\u0000b', '/a\u007fb']) {
    assert.equal(safeNext(next, origin), '/', JSON.stringify(next))
  }
})
