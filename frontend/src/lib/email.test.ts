import assert from 'node:assert/strict'
import { test } from 'node:test'
import { maskEmail } from './email.ts'

test('email disamarkan: huruf pertama dan domain tetap terlihat', () => {
  assert.equal(maskEmail('andi.demo0@inalum.id'), 'a•••@inalum.id')
  assert.equal(maskEmail('x@contoh.test'), 'x•••@contoh.test')
})

test('hanya tanda @ terakhir yang memisahkan domain', () => {
  assert.equal(maskEmail('"a@b"@contoh.test'), '"•••@contoh.test')
})

test('teks tanpa nama sebelum @ tidak membocorkan apa pun', () => {
  assert.equal(maskEmail('@contoh.test'), '•••')
  assert.equal(maskEmail('bukan-email'), '•••')
  assert.equal(maskEmail(''), '•••')
})
