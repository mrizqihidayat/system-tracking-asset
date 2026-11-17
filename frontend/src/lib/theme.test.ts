import assert from 'node:assert/strict'
import { test } from 'node:test'
import { brandScale, contrastRatio, contrastWarnings, DEFAULT_THEME, isHexColor, mix, THEME_PRESETS, themeVars } from './theme.ts'

test('kode warna harus #rrggbb', () => {
  assert.equal(isHexColor('#017cc2'), true)
  assert.equal(isHexColor('#017CC2'), true)
  assert.equal(isHexColor('#fff'), false)
  assert.equal(isHexColor('biru'), false)
})

test('mix mencampur dua warna', () => {
  assert.equal(mix('#000000', '#ffffff', 0.5), '#808080')
  assert.equal(mix('#017cc2', '#ffffff', 0), '#017cc2')
  assert.equal(mix('#017cc2', '#000000', 1), '#000000')
})

test('rasio kontras hitam-putih 21 dan warna sama 1', () => {
  assert.equal(Math.round(contrastRatio('#000000', '#ffffff')), 21)
  assert.equal(contrastRatio('#017cc2', '#017cc2'), 1)
})

test('skala aksen: 600 = warna utama, makin besar makin gelap', () => {
  const scale = brandScale('#0b7a53')
  assert.equal(scale['--color-brand-600'], '#0b7a53')
  assert.ok(contrastRatio(scale['--color-brand-50'], '#ffffff') < contrastRatio(scale['--color-brand-200'], '#ffffff'))
  assert.ok(contrastRatio(scale['--color-brand-900'], '#ffffff') > contrastRatio(scale['--color-brand-700'], '#ffffff'))
})

test('tema bawaan tidak menimpa token apa pun', () => {
  assert.deepEqual(themeVars(DEFAULT_THEME), {})
  assert.deepEqual(themeVars({ primary: '#017CC2', sidebar: '#0E4F7E' }), {})
})

test('tema lain menimpa skala aksen dan warna sidebar', () => {
  const vars = themeVars({ primary: '#0b7a53', sidebar: '#0d4a36' })
  assert.equal(vars['--color-brand-600'], '#0b7a53')
  assert.equal(vars['--color-rail'], '#0d4a36')
})

test('kode warna tidak valid diabaikan', () => {
  assert.deepEqual(themeVars({ primary: 'biru', sidebar: '#12' }), {})
  assert.deepEqual(contrastWarnings({ primary: 'biru', sidebar: '#0e4f7e' }), [])
})

test('semua preset aman dibaca', () => {
  for (const preset of THEME_PRESETS) {
    assert.deepEqual(contrastWarnings(preset.theme), [], preset.name)
  }
})

test('warna terlalu terang diberi peringatan', () => {
  assert.equal(contrastWarnings({ primary: '#ffd54a', sidebar: '#0e4f7e' }).length, 2)
  const sidebar = contrastWarnings({ primary: '#017cc2', sidebar: '#cfe8f7' })
  assert.equal(sidebar.length, 1)
  assert.match(sidebar[0], /sidebar/)
})
