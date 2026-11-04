import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'

const packageJson = (path: string) => JSON.parse(readFileSync(new URL(path, import.meta.url), 'utf8'))

test('versi file wasm sama dengan yang dibutuhkan barcode-detector', () => {
  const project = packageJson('../../package.json')
  const detector = packageJson('../../node_modules/barcode-detector/package.json')

  assert.equal(project.dependencies['zxing-wasm'], detector.dependencies['zxing-wasm'])
})
