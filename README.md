# Sistem Informasi Aset SHD (prototype)

Aset per unit ber-QR, peminjaman, kelengkapan ruangan, permintaan ke departemen lain, stok habis pakai, dan ekspor laporan untuk Departemen SHD PT INALUM. Aturan bisnis dan kontrak API ada di `docs/SPEC.md`; daftar tugas di `docs/TASKS.md`. README lengkap (T28) menyusul; bagian di bawah memuat langkah yang sudah pasti.

## Setup awal (development)
```
cd backend && composer install && cp .env.example .env && php artisan key:generate
# isi DB_* di backend/.env, lalu tambahkan:
#   EMPLOYEE_API_URL=http://localhost:3001
#   EMPLOYEE_API_KEY=demo-key
php artisan migrate --seed
cd ../frontend && npm install
```
Jalankan semuanya dengan satu perintah: `cd backend && composer run dev` (backend, queue, frontend, dan API pegawai tiruan di `postman/mocks/employee-api`).

Lalu isi salinan pegawai dan departemen dari API pegawai (tiruan) **satu kali**:
```
cd backend && php artisan employees:sync
```
Tanpa langkah ini pegawai SHD belum bisa login dengan NIK untuk pertama kali (seeder tidak memanggil API). Akun contoh `staf1` / `inalum2026` (akun eksternal) tetap bisa dipakai.

## Deploy
- Hapus folder `postman/mocks/employee-api` (hanya untuk development).
- Checklist keamanan sebelum go-live: `docs/security-review.md`.

### Data pegawai lewat agen sinkron (T44)
API pegawai asli hanya bisa diakses dari jaringan INALUM, sedangkan backend berjalan di internet. Karena itu data pegawai dikirim oleh **agen sinkron** (`employee-sync-agent/`), yang dipasang tim IT di server perusahaan dan berjalan setiap pukul 23.59. Agen mengambil data dari API pegawai, membuang kolom yang tidak dipakai, lalu mengirimnya ke `POST /api/internal/employees/push`.
1. Buat token acak, mis. `php -r "echo bin2hex(random_bytes(32));"`, isi sebagai `EMPLOYEE_PUSH_TOKEN` di env backend, lalu deploy ulang.
2. Buat paket: `cd backend && composer agent:package`. Hasilnya `employee-sync-agent-vX.Y.zip` di root repo (tidak masuk git).
3. Kirim zip ke tim IT, lalu berikan `BACKEND_URL` dan token lewat jalur aman. Tim IT mengikuti `README.md` dan `CHECKLIST.md` di dalam zip.
4. **Sinkron pertama agen wajib berhasil sebelum pegawai login.** Hasilnya terlihat di Pengaturan > Data Master > Departemen ("Sinkron terakhir").

Dengan agen, backend tidak butuh `EMPLOYEE_API_*`, `CRON_SECRET`, maupun scheduler untuk data pegawai.

**Mode tarik** (hanya bila backend dipasang di dalam jaringan INALUM): isi `EMPLOYEE_API_URL` (URL lengkap, tanpa path tambahan), `EMPLOYEE_API_KEY`, dan bila perlu `EMPLOYEE_API_KEY_HEADER` (bawaan `X-API-Key`) dan `EMPLOYEE_API_DATA_KEY` (kosong = respons array langsung). Setelah `php artisan migrate`, jalankan `php artisan employees:sync` sekali sebelum pegawai login, lalu aktifkan cron scheduler (sinkronisasi harian 23.59 WIB, hanya jalan bila `EMPLOYEE_API_URL` diisi):
`* * * * * cd /path/ke/backend && php artisan schedule:run >> /dev/null 2>&1`

### Deploy uji coba di Vercel (container, T38)
- Di Vercel, atur **Root Directory = `backend`**. File: `Dockerfile.vercel`, `Caddyfile`, `vercel.json` (region `sin1`). Folder `employee-sync-agent/` berada di luar Root Directory, jadi tidak ikut di-deploy.
- Build lokal: `cd backend && docker build -f Dockerfile.vercel -t asset-backend .`, lalu `docker run -p 8080:80 -e APP_KEY=... asset-backend` (uji `http://localhost:8080/up`).
- Isi env di Vercel (bukan di image): `APP_KEY`, `DB_*`, `AWS_*` (upload ke Supabase Storage, `ASSET_DISK=s3`), `APP_URL`, `FRONTEND_URL`, `TRUSTED_PROXIES`, `EMPLOYEE_PUSH_TOKEN`. Daftar lengkap ada di blok T38 pada `backend/.env.example`.
- Jalankan `php artisan migrate --force` sebagai langkah terpisah (jangan saat container start).
- Uji coba tanpa jaringan INALUM: jalankan agen dari laptop dengan API pegawai tiruan (`node postman/mocks/employee-api/default.js`; di `.env` agen isi `EMPLOYEE_API_URL=http://localhost:3001`, `EMPLOYEE_API_KEY=demo-key`) dan `BACKEND_URL=https://<domain-vercel>`. Hapus API tiruan saat produksi.
- Batas file aset 4 MB (batas body Vercel 4,5 MB); foto dikecilkan otomatis di browser.

## Perintah
Lihat bagian "Perintah" di `CLAUDE.md`.
