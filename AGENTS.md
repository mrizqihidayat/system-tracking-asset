# AGENTS.md — Panduan untuk AI agent (Antigravity, Codex, dll.)

File ini berisi aturan yang **wajib** dipatuhi agent mana pun yang bekerja di repo ini. Isinya sama dengan `CLAUDE.md` ditambah kebiasaan kerja yang selama ini dipakai. Jika `AGENTS.md` dan `CLAUDE.md` berbeda, **`CLAUDE.md` yang berlaku**. Jika salah satunya diubah, ubah keduanya.

## 0. Baca dulu sebelum mengerjakan apa pun
1. `CLAUDE.md` — aturan proyek (sumber utama).
2. `docs/TASKS.md` — task bernomor (T1, T2, ...), status, keputusan pengguna per task, dan bagian **"Catatan / temuan di luar scope"** (paling bawah).
3. `docs/SPEC.md` — aturan bisnis & kontrak API (**sumber kebenaran**).
4. `docs/erd.dbml` — skema database (**sumber kebenaran skema**).
5. Sesuai kebutuhan task: `docs/flowchart.md` (alur), `docs/endpoints.md`, `docs/TEST-CASES.md`, `DESIGN.md` & `PRODUCT.md` (desain UI & pengguna), `backend/CLAUDE.md` (pedoman Laravel Boost).

Jangan menebak isi file: buka dan baca dulu. Sebelum menulis kode baru, lihat file sejenis di folder yang sama dan ikuti polanya.

## 1. Tentang proyek
Prototype web untuk mengelola aset Departemen SHD PT INALUM: aset per unit ber-QR (riwayat, servis, foto & dokumen), peminjaman, kelengkapan ruangan per gedung/ruangan, permintaan ke departemen lain, stok habis pakai, dan ekspor laporan. Pengguna hanya Staf/Admin Aset SHD (**tanpa role**). Halaman publik read-only `/aset/{token}` untuk siapa pun yang memindai QR.

Target: **backend matang dan siap deploy** (tim lain yang akan deploy dan membuat versi HP), sehingga saat diterapkan cukup mengganti konfigurasi `.env`.

### Stack
- Backend: Laravel (PHP 8.3), API-only, Laravel Sanctum (Bearer token)
- Database: PostgreSQL di Supabase (Session pooler, port 5432)
- Frontend: React + TypeScript (Vite) + Tailwind + React Router + axios + TanStack Query
- QR, PDF, Excel: dibuat di backend
- File upload: Laravel Storage (disk lokal saat dev, S3/Supabase Storage saat deploy lewat env)

### Struktur
```
/backend    Laravel
/frontend   Vite + React + TS
/docs       SPEC.md, TASKS.md, erd.dbml, flowchart.md, ...
```

## 2. Cara kerja (WAJIB)
1. Kerjakan **SATU task per sesi** sesuai nomor di `docs/TASKS.md`. Jika pengguna meminta perubahan kecil yang tidak bernomor, kerjakan hanya yang diminta itu.
2. **Sebelum mengubah kode: tampilkan rencana, lalu TUNGGU persetujuan.** Rencana berisi:
   - file yang dibuat/diubah/dihapus beserta ringkasan perubahannya (backend, frontend, test, SPEC/ERD/TASKS),
   - migration baru (jika ada),
   - test yang akan ditambah/diubah,
   - pertanyaan untuk hal yang ambigu atau bentrok, masing-masing dengan usulan.
   Jangan mulai menulis kode sebelum pengguna setuju.
3. Setelah selesai: jalankan verifikasi (bagian 6), beri ringkasan singkat, lalu **centang task di `docs/TASKS.md`** hanya jika semua **Kriteria** task terpenuhi.
4. **Jangan mengerjakan di luar task.** Temuan di luar scope (bug lain, ide, test flaky) dicatat di bagian **"Catatan / temuan di luar scope"** di `docs/TASKS.md`, dengan format `- (Txx) ...` atau `- (Txx, di luar scope) ...`.
5. **Jika SPEC ambigu atau bertentangan dengan kode, berhenti dan tanyakan.** Jangan memilih sendiri.
6. Keputusan pengguna yang mengubah perilaku dicatat di task terkait (`**Keputusan pengguna**`) atau di Catatan dengan label `(Keputusan pengguna)`, lalu SPEC/ERD diperbarui agar tetap menjadi sumber kebenaran.
7. Jangan menambah dependency (`composer require`, `npm install <paket>`) tanpa menjelaskan pilihan library dan alasannya, lalu menunggu persetujuan.

## 3. Larangan
- **Jangan membaca, menampilkan, atau mengubah `DB_*`, `APP_KEY`, atau kredensial di `.env`.** (Boleh melihat `.env.example`.)
- **Jangan menjalankan `migrate:fresh`, `migrate:reset`, `db:wipe`, atau `DROP`.** Jika perlu, minta pengguna menjalankannya sendiri.
- **Jangan mengubah migration yang sudah dijalankan di database.** Buat migration baru untuk perubahan skema. Tanyakan dulu sebelum menjalankan `php artisan migrate` (database dev adalah Supabase), dan catat di Catatan `docs/TASKS.md`: "Migration `nama_file` wajib dijalankan (`php artisan migrate`) bersamaan dengan kode Txx."
- Setiap perubahan pada aset (status, kondisi, lokasi, pinjam, servis, hapus) **wajib menulis `asset_events` lewat satu service yang sama** (`AssetService`).
- **Jangan menambah role/admin.**
- **Jangan hard delete data bisnis** (pakai soft delete + alasan), kecuali:
  - **Aset**: hard delete hanya lewat `AssetService` setelah `delete-impact` + `confirm_code`, menghapus data turunan (pinjaman, riwayat, file, kode lama) dalam satu transaksi.
  - **Barang habis pakai**: hard delete hanya jika transaksinya saldo awal saja (kasus salah input); selain itu ditolak 422 dan staf mencatat Penghapusan agar stok menjadi 0.
- Setiap perubahan jumlah stok **wajib lewat `ConsumableService::record()`** (tabel `stock_transactions`), sehingga jumlah stok selalu sama dengan hasil transaksi.
- Setiap perubahan jumlah Ada barang habis pakai ruangan **wajib lewat `RoomStockService::record()`** (tabel `room_stock_transactions`), sehingga Ada selalu sama dengan hasil transaksi ruangan + barang.
- **Jangan hardcode URL/host**; gunakan env (`APP_URL`, `FRONTEND_URL`, `QR_BASE_URL`, `EMPLOYEE_API_URL`).
- **Data pribadi pegawai**: tabel `employees` hanya menyimpan kolom yang dibutuhkan (tanpa tanggal lahir, agama, jenis kelamin, telepon, tanggal MPP/pensiun). Jangan mencatat API key atau data pegawai di log.
- Jangan commit/push kecuali pengguna meminta.

## 4. Konvensi
- Nama tabel/kolom: Inggris, snake_case. **Nilai status: Bahasa Indonesia sesuai SPEC** (misal `Tersedia`, `Dipinjam`, `Hilang`).
- Model memakai atribut `#[Fillable]` / `#[Hidden]` (gaya Laravel terbaru).
- Validasi lewat **FormRequest**, respons lewat **API Resource**, logika stok/status di **Service class** (`app/Services`).
- Perubahan status/lokasi/stok wajib di `DB::transaction()` + `lockForUpdate()`.
- Semua error API berupa JSON; pesan validasi singkat dalam Bahasa Indonesia.
- Test backend: Feature test dengan sqlite in-memory (`phpunit.xml`), **tidak menyentuh Supabase**.
- UI dalam Bahasa Indonesia. Ikuti `DESIGN.md` (warna, tipografi, komponen) dan pakai ulang komponen yang sudah ada.
- Perubahan sekecil mungkin yang menyelesaikan task. Jangan merapikan/refactor kode yang tidak diminta.

## 5. Apa yang boleh diubah, kapan
| File / area | Aturan |
|---|---|
| Kode `backend/`, `frontend/` | Hanya yang masuk rencana task yang disetujui. |
| `docs/SPEC.md`, `docs/erd.dbml` | Diperbarui jika task/keputusan pengguna mengubah aturan bisnis, API, atau skema. Jangan diubah diam-diam. |
| `docs/TASKS.md` | Centang task selesai, tambah keputusan pengguna di task terkait, tambah temuan di "Catatan". Jangan menghapus catatan lama. |
| `docs/flowchart.md` | Diperbarui jika alur proses berubah. |
| Migration lama di `backend/database/migrations` | **Jangan diubah.** Buat migration baru. |
| `.env` | **Jangan disentuh.** Variabel baru ditambahkan ke `.env.example` tanpa nilai rahasia. |
| `CLAUDE.md` / `AGENTS.md` | Hanya jika pengguna meminta; ubah keduanya. |

## 6. Verifikasi setelah mengubah kode
```
cd backend && php artisan test                # wajib jika backend berubah
cd backend && vendor/bin/pint --dirty         # format PHP yang diubah
cd backend && php artisan route:list --path=api
cd frontend && npm run build                  # tsc + vite build, wajib jika frontend berubah
cd frontend && npm run lint
cd frontend && npm test                       # node --test untuk logika murni
```
Laporkan hasil apa adanya. Jika ada test gagal, tampilkan outputnya dan jangan menyebut task selesai.
Diketahui flaky (bukan regresi): `AssetScanLabelTest::test_label_html_contains_code_and_qr_with_public_url` sesekali gagal di decoder QR dan lolos saat diulang.

## 7. Git
- Commit hanya jika diminta. Branch kerja saat ini `main`.
- Pesan commit Bahasa Indonesia, singkat, kalimat perintah. Untuk task bernomor diawali nomornya, contoh:
  - `T30: barang habis pakai ruangan berbasis transaksi, catat perubahan per barang, dan tandai sudah dicek`
  - `UI: perbaikan responsif HP dan tablet`
- Jangan memakai `--no-verify` atau `git push --force`.

## 8. Gaya respons ke pengguna
- **Bahasa Indonesia**, singkat dan langsung ke inti. Jangan mengulang hal yang sudah disepakati.
- Sebut file dengan path relatif dari root (dan nomor baris jika relevan), misal `backend/app/Services/AssetService.php:42`.
- Jika ada pilihan, berikan **satu rekomendasi** beserta alasannya, bukan daftar panjang.
- Pertanyaan diberi nomor dan masing-masing disertai usulan, supaya pengguna cukup menjawab "setuju 1, 2; untuk 3 ...".
- Jujur soal hasil: sebut yang belum dikerjakan, dilewati, atau gagal.

## 9. Kendala lingkungan Windows (mesin pengembang)
- OS Windows 11; shell PowerShell 5.1 dan Git Bash.
- File di `frontend/src` memakai line ending **CRLF**, file backend **LF**. Pertahankan line ending asli setiap file saat mengedit.
- Python `open(p, 'w')` di Windows menulis CRLF. Selalu pakai `newline=''`.
- Heredoc di bash bisa mengubah `\\` menjadi `\` dan `'\r\n'` menjadi baris baru. Tulis skrip edit ke file terpisah, jangan inline di heredoc.
- PowerShell 5.1 memecah pesan `git commit -m` yang mengandung tanda kutip ganda (`"`). Tulis pesan ke file lalu pakai `git commit -F <file>`.
- PowerShell 5.1 tidak mendukung `&&`; pakai `;` atau `if ($?) { ... }`.

## 10. Perintah
```
cd backend && composer run dev                   # backend + queue + frontend + API pegawai tiruan (satu perintah)
cd backend && php artisan serve                  # http://localhost:8000
cd backend && php artisan test
cd backend && php artisan route:list --path=api
cd backend && php artisan employees:sync         # salin pegawai & departemen dari API pegawai (terjadwal 02.00 WIB)
node postman/mocks/employee-api/default.js       # API pegawai tiruan saja: http://localhost:3001 (API key demo-key)
cd frontend && npm run dev                       # http://localhost:5173
```

**Setup awal (database baru):** `cd backend && php artisan migrate --seed`, tambahkan di `backend/.env` `EMPLOYEE_API_URL=http://localhost:3001` dan `EMPLOYEE_API_KEY=demo-key`, jalankan API tiruan (`composer run dev` sudah menjalankannya), lalu `php artisan employees:sync`. Tanpa langkah terakhir, pegawai SHD belum bisa login pertama kali (seeder tidak memanggil API).
**Deploy:** isi `EMPLOYEE_API_URL`, `EMPLOYEE_API_KEY` (+ `EMPLOYEE_API_KEY_HEADER` / `EMPLOYEE_API_DATA_KEY` bila berbeda), hapus folder `postman/mocks/employee-api`, jalankan `php artisan employees:sync` **sekali setelah migration dan sebelum pegawai login**, dan aktifkan cron `* * * * * php artisan schedule:run` (sinkronisasi harian 02.00 WIB; tidak ada tombol sinkron di aplikasi).
