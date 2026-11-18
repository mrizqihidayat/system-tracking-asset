import assert from 'node:assert/strict'
import { test } from 'node:test'
import { otpBackspace, otpDigits, otpInsert } from './otp.ts'

test('hanya angka yang diterima dan panjangnya dibatasi 6', () => {
  assert.equal(otpDigits('12 34-56 78'), '123456')
  assert.equal(otpDigits('abc'), '')
})

test('mengetik satu angka mengisi kotak itu lalu pindah ke kanan', () => {
  assert.deepEqual(otpInsert('', 0, '4'), { value: '4', focus: 1 })
  assert.deepEqual(otpInsert('12', 2, '3'), { value: '123', focus: 3 })
})

test('mengetik di kotak yang sudah terisi menimpa angkanya', () => {
  assert.deepEqual(otpInsert('123456', 2, '9'), { value: '129456', focus: 3 })
})

test('mengetik di kotak yang jauh dari isi mengisi kotak kosong pertama', () => {
  assert.deepEqual(otpInsert('12', 5, '7'), { value: '127', focus: 3 })
})

test('menempel kode penuh mengisi semua kotak dan fokus ke kotak terakhir', () => {
  assert.deepEqual(otpInsert('', 0, '123 456'), { value: '123456', focus: 5 })
  assert.deepEqual(otpInsert('', 3, '123456789'), { value: '123456', focus: 5 })
})

test('menempel sebagian mengisi dari kotak yang dipilih', () => {
  assert.deepEqual(otpInsert('12', 2, '345'), { value: '12345', focus: 5 })
})

test('teks tanpa angka tidak mengubah apa pun', () => {
  assert.deepEqual(otpInsert('12', 2, 'abc'), { value: '12', focus: 2 })
})

test('backspace di kotak terisi menghapus angkanya', () => {
  assert.deepEqual(otpBackspace('1234', 3), { value: '123', focus: 3 })
  assert.deepEqual(otpBackspace('1234', 1), { value: '134', focus: 1 })
})

test('backspace di kotak kosong menghapus angka sebelumnya dan mundur', () => {
  assert.deepEqual(otpBackspace('123', 3), { value: '12', focus: 2 })
  assert.deepEqual(otpBackspace('', 0), { value: '', focus: 0 })
})
