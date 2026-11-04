import { setZXingModuleOverrides } from '@yudiel/react-qr-scanner'
import wasmUrl from 'zxing-wasm/reader/zxing_reader.wasm?url'

// Default library mengambil file wasm dari CDN pihak ketiga; di sini dilayani dari server sendiri.
setZXingModuleOverrides({
  locateFile: (path: string, prefix: string) => (path.endsWith('.wasm') ? wasmUrl : prefix + path),
})

export { Scanner } from '@yudiel/react-qr-scanner'
