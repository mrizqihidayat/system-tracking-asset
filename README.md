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
Tanpa langkah ini pegawai SHD belum bisa login dengan NIK untuk pertama kali (seeder tidak memanggil API). Akun contoh `staf1` / `inalum2026` (akun bersama) tetap bisa dipakai.

## Deploy
- Isi `EMPLOYEE_API_URL` (URL lengkap endpoint API pegawai asli, tanpa path tambahan), `EMPLOYEE_API_KEY`, dan bila perlu `EMPLOYEE_API_KEY_HEADER` (bawaan `X-API-Key`) dan `EMPLOYEE_API_DATA_KEY` (kosong = respons array langsung) di `.env`.
- Hapus folder `postman/mocks/employee-api` (hanya untuk development).
- Setelah `php artisan migrate`, jalankan **`php artisan employees:sync` sekali, sebelum pegawai login**.
- Aktifkan cron untuk scheduler (sinkronisasi pegawai harian pukul 02.00 WIB, tanpa tombol di aplikasi):
  `* * * * * cd /path/ke/backend && php artisan schedule:run >> /dev/null 2>&1`
- Checklist keamanan sebelum go-live: `docs/security-review.md`.

## Perintah
Lihat bagian "Perintah" di `CLAUDE.md`.
