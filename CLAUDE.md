# Asset Tracking System — Departemen SHD (Prototype)

Prototype web untuk mengelola aset Departemen SHD: aset per unit ber-QR (dengan riwayat, servis, foto & dokumen), peminjaman, kelengkapan ruangan per gedung/ruangan, permintaan ke unit lain, stok habis pakai, dan ekspor laporan. Pengguna hanya Staf/Admin Aset SHD (tanpa role).

Target: **backend matang dan siap deploy** (tim lain yang akan deploy dan membuat versi HP), sehingga saat diterapkan cukup mengganti konfigurasi `.env`. Deadline demo: awal November 2026.

Dokumen acuan:
- `docs/SPEC.md` — aturan bisnis & kontrak API (sumber kebenaran)
- `docs/erd.dbml` — skema database (sumber kebenaran skema)
- `docs/flowchart.md` — alur proses
- `docs/TASKS.md` — daftar tugas bernomor (T1, T2, ...) dan statusnya

## Stack
- Backend: Laravel (PHP 8.3), API-only, Laravel Sanctum (Bearer token)
- Database: PostgreSQL di Supabase (Session pooler, port 5432)
- Frontend: React + TypeScript (Vite) + Tailwind + React Router + axios
- QR, PDF, Excel: dibuat di backend (library ditentukan di task terkait, jelaskan sebelum `composer require`)
- File upload: Laravel Storage (disk lokal saat dev, S3/Supabase Storage saat deploy lewat env)

## Struktur
```
/backend    Laravel
/frontend   Vite + React + TS
/docs       SPEC.md, TASKS.md
```

## Cara kerja (WAJIB)
1. Kerjakan SATU task per sesi sesuai nomor di `docs/TASKS.md`.
2. Sebelum mengubah kode: tampilkan rencana (file dibuat/diubah + ringkasan), lalu TUNGGU persetujuan.
3. Setelah selesai: jalankan verifikasi di task, beri ringkasan singkat, centang task di `docs/TASKS.md`.
4. Jangan mengerjakan di luar task. Temuan di luar scope dicatat di bagian "Catatan" pada `docs/TASKS.md`.
5. Jika SPEC ambigu atau bertentangan dengan kode, berhenti dan tanyakan.

## Larangan
- Jangan membaca, menampilkan, atau mengubah `DB_*`, `APP_KEY`, atau kredensial di `.env`.
- Jangan menjalankan `migrate:fresh`, `migrate:reset`, `db:wipe`, atau `DROP`. Jika perlu, minta pengguna menjalankannya sendiri.
- Jangan mengubah migration yang sudah dijalankan di database (kecuali di task perombakan T4).
- Setiap perubahan pada aset (status, kondisi, lokasi, pinjam, servis, hapus) wajib menulis `asset_events` lewat satu service yang sama.
- Jangan menambah role/admin. Jangan hard delete data bisnis (pakai soft delete + alasan), kecuali:
  - **Aset**: hard delete hanya lewat `AssetService` setelah `delete-impact` + `confirm_code`, menghapus data turunan (pinjaman, riwayat, file, kode lama) dalam satu transaksi.
  - **Barang habis pakai**: hard delete biasa.
- Jangan hardcode URL/host; gunakan env (`APP_URL`, `FRONTEND_URL`, `QR_BASE_URL`).

## Konvensi
- Nama tabel/kolom: Inggris, snake_case. Nilai status: Bahasa Indonesia sesuai SPEC.
- Model memakai atribut `#[Fillable]` / `#[Hidden]` (gaya Laravel terbaru).
- Validasi lewat FormRequest, respons lewat API Resource, logika stok/status di Service class (`app/Services`).
- Perubahan status/lokasi/stok wajib di `DB::transaction()` + `lockForUpdate()`.
- Semua error API JSON; pesan validasi singkat Bahasa Indonesia.
- Test backend: Feature test dengan sqlite in-memory (`phpunit.xml`), tidak menyentuh Supabase.
- UI Bahasa Indonesia.

## Perintah
```
cd backend && php artisan serve                  # http://localhost:8000
cd backend && php artisan test
cd backend && php artisan route:list --path=api
cd frontend && npm run dev                       # http://localhost:5173
```
