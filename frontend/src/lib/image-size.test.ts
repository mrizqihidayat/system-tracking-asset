import assert from 'node:assert/strict'
import { test } from 'node:test'
import { fitWithin, renameForType } from './image-size.ts'

test('fitWithin tidak memperbesar gambar yang sudah muat', () => {
  assert.deepEqual(fitWithin(1200, 800, 2560), { width: 1200, height: 800 })
})

test('fitWithin menjaga rasio pada sisi terpanjang', () => {
  assert.deepEqual(fitWithin(5120, 2560, 2560), { width: 2560, height: 1280 })
  assert.deepEqual(fitWithin(3000, 6000, 2560), { width: 1280, height: 2560 })
})

test('renameForType mengganti ekstensi sesuai tipe', () => {
  assert.equal(renameForType('IMG_0001.PNG', 'image/webp'), 'IMG_0001.webp')
  assert.equal(renameForType('foto.heic', 'image/jpeg'), 'foto.jpg')
  assert.equal(renameForType('tanpa-ekstensi', 'image/jpeg'), 'tanpa-ekstensi.jpg')
})
