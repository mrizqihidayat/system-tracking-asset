/**
 * API pegawai TIRUAN untuk pengembangan lokal (T34). Seluruh data di employees.json FIKTIF.
 *
 * Meniru bentuk API pegawai INALUM yang asli:
 *   - hanya SATU alamat: GET /  → semua pegawai (array langsung), nama kolom persis seperti API asli
 *   - wajib API key di header (default X-API-Key); tanpa key atau key salah → 401
 *
 * Dijalankan dengan Node biasa (tanpa Postman CLI) atau `postman mock run postman/mocks/employee-api/config.yaml`:
 *   node postman/mocks/employee-api/default.js
 *
 * Pengaturan lewat env (opsional):
 *   PORT                  bawaan 3001
 *   MOCK_API_KEY          bawaan "demo-key"  → isi EMPLOYEE_API_KEY di backend/.env dengan nilai yang sama
 *   MOCK_API_KEY_HEADER   bawaan "X-API-Key" → samakan dengan EMPLOYEE_API_KEY_HEADER
 *   MOCK_DATA_KEY         kosong = array langsung; isi (mis. "data") = dibungkus {"data": [...]}
 *                         → samakan dengan EMPLOYEE_API_DATA_KEY
 *
 * Saat deploy, folder postman/mocks/employee-api boleh dihapus; backend cukup diarahkan ke API asli lewat .env.
 */
const http = require('http')
const fs = require('fs')
const path = require('path')

const PORT = process.env.PORT || 3001
const API_KEY = process.env.MOCK_API_KEY || 'demo-key'
const KEY_HEADER = (process.env.MOCK_API_KEY_HEADER || 'X-API-Key').toLowerCase()
const DATA_KEY = process.env.MOCK_DATA_KEY || ''
const JSON_HEADER = { 'Content-Type': 'application/json; charset=utf-8' }

const employees = JSON.parse(fs.readFileSync(path.join(__dirname, 'employees.json'), 'utf8'))

const send = (res, status, body, headers = {}) => {
  res.writeHead(status, { ...JSON_HEADER, ...headers })
  res.end(JSON.stringify(body))
}

const server = http.createServer((req, res) => {
  const { pathname } = new URL(req.url, 'http://localhost')

  if (pathname !== '/') return send(res, 404, { message: 'Tidak ditemukan. API ini hanya punya satu alamat: GET /' })
  if (req.method !== 'GET') return send(res, 405, { message: 'Hanya GET.' }, { Allow: 'GET' })
  if (req.headers[KEY_HEADER] !== API_KEY) return send(res, 401, { message: 'API key tidak valid.' })

  send(res, 200, DATA_KEY ? { [DATA_KEY]: employees } : employees)
})

server.listen(PORT, () => console.log(`API pegawai tiruan (data fiktif) di http://localhost:${PORT}/  [header ${KEY_HEADER}]`))
