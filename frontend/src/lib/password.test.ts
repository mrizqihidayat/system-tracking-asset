import assert from 'node:assert/strict'
import { test } from 'node:test'
import { passwordErrors } from './password.ts'

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
