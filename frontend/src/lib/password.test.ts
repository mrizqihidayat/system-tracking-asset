import assert from 'node:assert/strict'
import { test } from 'node:test'
import { confirmationState, passwordChecks, passwordErrors } from './password.ts'

test('password valid lolos', () => {
  assert.deepEqual(passwordErrors('Rahasia-2026', 'Rahasia-2026', 'bersama.hrm'), {})
})

test('kurang dari 8 karakter ditolak', () => {
  assert.equal(passwordErrors('Pendek1', 'Pendek1', 'x').password, 'Password minimal 8 karakter.')
})

test('sama dengan username/NIK ditolak tanpa beda huruf besar/kecil', () => {
  assert.equal(passwordErrors('BERSAMA.HRM', 'BERSAMA.HRM', 'bersama.hrm').password, 'Password tidak boleh sama dengan username.')
  assert.equal(passwordErrors('90100001', '90100001', '90100001', 'NIK').password, 'Password tidak boleh sama dengan NIK.')
})

test('konfirmasi harus sama', () => {
  assert.deepEqual(passwordErrors('Rahasia-2026', 'Rahasia-2025', 'x'), { password_confirmation: 'Konfirmasi password tidak sama.' })
})

test('syarat password: panjang, bukan NIK/username, beda dari password saat ini', () => {
  const opts = { loginId: '90100001', idLabel: 'NIK' as const, current: 'Lama-1234' }
  const met = (password: string) => passwordChecks(password, opts).map((c) => c.met)

  assert.deepEqual(met(''), [false, false, false])
  assert.deepEqual(met('Pendek1'), [false, true, true])
  assert.deepEqual(met('90100001'), [true, false, true])
  assert.deepEqual(met('lama-1234'), [true, true, true]) // beda huruf besar/kecil = berbeda
  assert.deepEqual(met('Lama-1234'), [true, true, false])
  assert.deepEqual(met('Baru-Rahasia-9'), [true, true, true])
})

test('syarat password tanpa kolom password saat ini hanya dua', () => {
  const checks = passwordChecks('Rahasia-2026', { loginId: 'staf.shd', idLabel: 'username', min: 10 })
  assert.deepEqual(checks.map((c) => c.key), ['min', 'login'])
  assert.equal(checks[0].label, 'Minimal 10 karakter')
})

test('keadaan konfirmasi password', () => {
  assert.equal(confirmationState('abc', ''), 'idle')
  assert.equal(confirmationState('abc', 'ab'), 'mismatch')
  assert.equal(confirmationState('abc', 'abc'), 'match')
})
