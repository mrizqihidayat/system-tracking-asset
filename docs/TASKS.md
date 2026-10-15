# Daftar Tugas

Satu task per sesi. Jangan centang sebelum semua **Kriteria** terpenuhi. Acuan: `docs/SPEC.md` dan `docs/erd.dbml`.
Label prioritas: **[Inti]** wajib untuk demo · **[Lengkap]** dikerjakan setelah inti aman.

## Sudah selesai
- [x] **T1 Setup repo**
- [x] **T2 Koneksi Supabase**
- [x] **T3 Auth** — login/logout/user, 401 JSON

---

## Minggu 1 (13–19 Okt) — Data & Aset
- [x] **T4 Skema database [Inti]** — hapus migration/model/controller/test lama yang tidak sesuai (`items`, `transactions`, atau versi T4 sebelumnya); buat migration sesuai `erd.dbml` termasuk CHECK constraint, enum sebagai CHECK, partial unique index loan aktif, dan RLS semua tabel.
  Kriteria: pengguna menjalankan `php artisan migrate:fresh` sendiri tanpa error; tidak ada tabel UNRESTRICTED.
- [x] **T5 Konfigurasi [Inti]** — Sanctum 480 menit, CORS `FRONTEND_URL`, env `QR_BASE_URL`, disk penyimpanan file (lokal dev, siap S3/Supabase Storage via env), `.env.example` tanpa kredensial, bersihkan `UserFactory`.
- [x] **T6 Model & seeder [Inti]** — semua model + relasi + SoftDeletes; generator kode jenis (PRJ) & kode aset (SHD-PRJ-001); seeder realistis: SHD + 3 unit lain, Gedung IHS & Millennials Hub dengan ruangan, kategori & jenis, ±25 aset, standar beberapa ruangan, katalog, permintaan, ±10 habis pakai.
  Kriteria: `migrate:fresh --seed` sukses dan data konsisten (status aset sesuai loan aktif).
- [x] **T7 Master data [Inti]** — endpoint departemen, gedung, ruangan, kategori, jenis aset (kode otomatis, unik), lookup unit peminjam.
- [x] **T8 Aset [Inti]** — CRUD + filter + soft delete beralasan; setiap perubahan menulis `asset_events` lewat satu service; detail menampilkan lokasi peminjaman saat ini.
- [x] **T9 Lapor & servis [Inti]** — lapor rusak/hilang, catat servis (update kondisi, status, next_service_date).
- [x] **T10 Scan & label QR [Inti]** — `GET /assets/scan/{code}` + aksi yang diizinkan (`AssetStatus::allowedActions()`), pindah lokasi; isi QR = `Asset::publicUrl()` (`{QR_BASE_URL}/aset/{public_token}`); PDF label 5×5 cm grid A4 (jelaskan pilihan library sebelum `composer require`).
  Kriteria: QR hasil cetak terbaca kamera HP dan berisi URL benar.

## Minggu 2 (20–26 Okt) — Proses bisnis & test
- [x] **T11 Peminjaman [Inti]** — pinjam, terima kembali (kondisi + ruangan + konfirmasi kerusakan), batal, konfirmasi kerusakan; daftar dengan filter overdue/due_today. Semua dalam transaksi + lock.
  Kriteria: aturan SPEC §7 ditegakkan; satu aset tidak bisa dua loan aktif.
  Catatan T9: terima kembali harus mendukung hasil **"Hilang"** (transaksi ditutup, status aset `Hilang`, riwayat), karena catat kehilangan ditolak selama aset Dipinjam. Kondisi → status pakai `AssetCondition::resolveStatus()`.
- [x] **T12 Stok habis pakai [Inti]** — CRUD (hanya barang simpanan SHD, kolom "minta ke"), ubah jumlah, status Aman/Menipis/Habis.
- [ ] **T13 Kelengkapan ruangan [Inti]** — standar per ruangan (QR/Manual + pemenuhan: stok SHD / ambil dari / minta ke), salin standar dari ruangan lain, checklist (Standar/Ada/Selisih/Hasil/Ambil dari-Minta ke), simpan hasil cek, **isi dari stok** (min(kekurangan, stok), satu transaksi + lock), ringkasan kesiapan per gedung.
  Kriteria: isi dari stok tidak pernah membuat stok negatif; sisa kekurangan tetap tampil.
  Catatan T12: pemenuhan "Dari stok SHD" = kolom ketik-dengan-saran pilih item stok (satu stok boleh ke banyak ruangan); pemenuhan hanya satu dari stok SHD / ambil dari / minta ke; stok SHD (dan ambil dari) hanya jika sumber standar = SHD (FormRequest). Isi dari stok pakai `ConsumableService::adjust()` (lock, tidak negatif).
- [ ] **T14 Permintaan [Inti]** — katalog CRUD, permintaan + ubah status, status tampilan "Belum direspons", saran permintaan dari kekurangan ruangan (minta ke unit lain) dan stok Menipis/Habis.
- [ ] **T15 Dashboard [Inti]** — semua kartu & panel SPEC §11 dalam satu endpoint (query efisien).
- [ ] **T16 Upload file aset [Lengkap]** — upload/daftar/hapus foto & dokumen, validasi tipe & 5 MB.
- [ ] **T17 Ekspor laporan [Lengkap]** — 5 laporan, PDF & Excel.
- [ ] **T18 Test backend [Inti]** — Feature test sqlite in-memory untuk aturan aset, peminjaman, kelengkapan, permintaan, stok, 401.
  Kriteria: `php artisan test` lulus.

## Minggu 3 (27 Okt–2 Nov) — Frontend
- [ ] **T19 Setup frontend [Inti]** — Vite react-ts, Tailwind, React Router, axios + interceptor, layout sidebar (menu SPEC §3, Lisensi Digital ditandai Fase 2), komponen dasar (tabel, form, modal, toast, combobox ketik-dengan-saran).
- [ ] **T20 Login & guard [Inti]**
- [ ] **T21 Daftar & detail aset [Inti]** — tabel + filter, form tambah/ubah, hapus aset (popup dari `delete-impact`; aset dengan riwayat pinjam/sedang dipinjam wajib mengetik kode aset sebagai `confirm_code`), catat kerusakan/kehilangan/tidak layak pakai, catat servis, riwayat, label QR, cetak label banyak. Foto & dokumen [Lengkap].
  Catatan T9: label aksi "Catat kerusakan", "Catat kehilangan", "Tandai ditemukan" (dicatat staf SHD). Setelah tandai ditemukan dan status Tersedia, tawarkan "Langsung pinjamkan" yang membuka form pinjam.
- [ ] **T22 Scan QR [Inti]** — halaman kamera, hasil scan + aksi dalam modal, route `/scan/{code}`; halaman publik `/aset/{public_token}` (tanpa login, `GET /api/public/assets/{token}`) berisi data aset + tombol aksi berketerangan "Khusus staf SHD": belum login → login lalu kembali otomatis ke form aksi dengan aset terisi, sudah login → langsung ke form.
  Catatan T9: aksi per status dari `actions` (`catat_kerusakan`, `catat_kehilangan`, `tandai_ditemukan`, ...); setelah tandai ditemukan tawarkan "Langsung pinjamkan".
- [ ] **T23 Peminjaman [Inti]** — form catat peminjaman (scan/ketik kode), tabel sedang dipinjam + terima kembali, riwayat, konfirmasi kerusakan.
- [ ] **T24 Kelengkapan ruangan [Inti]** — pilih gedung → ruangan, checklist, simpan hasil cek, atur standar + salin, tombol isi dari stok & buat permintaan.
- [ ] **T25 Permintaan & stok habis pakai [Inti]** — katalog, riwayat permintaan + status; tabel stok + ubah jumlah cepat.
- [ ] **T26 Dashboard & ekspor [Inti/Lengkap]** — dashboard [Inti], tombol ekspor laporan [Lengkap].

## Penyelesaian (3–4 Nov)
- [ ] **T27 QA & demo** — skenario: login → tambah aset → cetak label → scan → pinjam → terima kembali rusak → konfirmasi → servis → cek ruangan kurang → buat permintaan → stok menipis → ekspor.
- [ ] **T28 Dokumentasi** — README (setup, env, deploy, HTTPS kamera, storage), ERD & flowchart final.

## Fase 2
- [ ] **T29 Lisensi Digital** — sesuai SPEC §16.

## Catatan / temuan di luar scope
- (T4) `tests/Feature/ExampleTest.php` gagal karena view `welcome` tidak ada (proyek API-only); hapus/ganti saat T18.
- (T4) RLS diaktifkan oleh migration `enable_rls_on_all_tables` hanya untuk tabel yang ada saat itu; tabel baru di task berikutnya wajib `ENABLE ROW LEVEL SECURITY` di migration-nya sendiri.
- (T4) CHECK constraint bisnis dan RLS hanya dibuat di PostgreSQL; di test sqlite hanya enum (CHECK) dan partial unique index loan aktif yang berlaku.
- (T6→A4) QR berisi `{qr_base_url}/aset/{public_token}` lewat `Asset::publicUrl()` (bukan id/kode). `qr_base_url` = `QR_BASE_URL` (default `frontend_url`, tanpa `/` di akhir).
- (T6) Seeder menulis `asset_events` langsung lewat model (data awal, bukan perubahan bisnis). Service tunggal pencatatan event dibuat di T8; seeder tidak perlu diubah.
- (T6) Kode jenis otomatis (`CodeGenerator::assetTypeCode`): satu kata = huruf pertama + 2 konsonan berikutnya (Y = konsonan); banyak kata = inisial (maks. 3) + konsonan dari sisa kata terakhir; bentrok → angka (LPT2). Seeder memakai `PRJ` untuk Proyektor (kode diedit, contoh SPEC).
- (T6) `CodeGenerator::nextAssetCode()` wajib dipanggil di dalam `DB::transaction()` (mengunci baris `asset_types`).
- (T7) Locale default `id` (pesan validasi di `lang/id/validation.php`, fallback `en`). `.env` lokal perlu `APP_LOCALE=id`; test memakai `id` lewat `phpunit.xml`.
- (T7) Lookup unit peminjam hanya dari master `departments` (keputusan pengguna). Unit baru ditambah lewat `POST /departments`.
- (T7) Mengubah kode jenis tidak mengubah kode aset yang sudah ada (label QR tercetak tetap berlaku).
- (T7) Pesan 401 masih bawaan Laravel (`Unauthenticated.`); frontend cukup membaca status 401 (SPEC §15).
- (T7) Slug `buildings.slug` & `rooms.slug` (migration `2026_10_14_000001`) untuk URL frontend T24 `/kelengkapan-ruangan/{slug-gedung}/{slug-ruangan}`; API tetap memakai id (SPEC §8).
- (A1) Nomor urut kode aset dari `asset_types.last_number` (tidak pernah dipakai ulang); `CodeGenerator::previewAssetCode()` untuk pratinjau tanpa memakai nomor.
- (A2) T8 wajib memperluas `CodeGenerator::isTypeCodeUsedByOtherType()` agar ikut memeriksa kode lama di `asset_code_histories` (simpan jenis aset saat kode dipakai, supaya jenis pemilik kode tetap boleh kembali ke kodenya).
- (A3) `request_method` teks bebas; saran `GET /lookups/request-methods`. Validasi katalog (T14): wajib, teks, maks. 50.
- (A4) Field `PublicAssetResource` menunggu konfirmasi mentor; kurangi field di resource itu saja.
- (T8) `AssetEventService::record()` = satu-satunya jalur menulis `asset_events`; T9–T11 wajib memakainya di transaksi yang sama. Aturan kondisi → status: `AssetCondition::resolveStatus()`.
- (T8→HD) Aset tidak lagi soft delete (migration `2026_10_16_000001`): Hapus aset = hard delete lewat `AssetService::delete()` + `delete-impact` + `confirm_code`. Event `Dihapus`, enum `DeletionReason`, dan `?deleted=1` dihapus. Istilah UI: "Hapus aset".
- (T8) Pengecekan kode jenis bekas (A2) sudah mencakup `asset_code_histories`.
- (T8) Route aset memakai `whereNumber('asset')` agar `/assets/scan/{code}` dan `/assets/labels` (T10) tidak bentrok.
- (T9) Event baru `Ditemukan` (migration `2026_10_15_000001`, CHECK `asset_events.event_type`). Rollback ditolak jika sudah ada riwayat `Ditemukan`.
- (T9) Kunci aksi `lapor` diganti `catat_kerusakan` / `catat_kehilangan`; tambah `tandai_ditemukan` (khusus Hilang). Validasi pilihan status Rusak Ringan di trait `ValidatesStatusChoice`.
- (T10) Library: `barryvdh/laravel-dompdf` ^3 (PDF, dipakai lagi di T17) dan `chillerlan/php-qrcode` ^5 (QR PNG lewat GD). README/deploy (T28): server wajib ekstensi PHP `gd`, `dom`, `mbstring`. Label memakai font inti Helvetica (tanpa embed, file kecil); laporan T17 yang butuh karakter non-ASCII pakai DejaVu Sans.
- (T10) Composer di jaringan lambat: `COMPOSER_IPRESOLVE=4 COMPOSER_PROCESS_TIMEOUT=1800 composer install`.
- (T11) Status transaksi baru `Hilang` + kolom `damage_confirmation_note`/`damage_confirmation_document` (migration `2026_10_15_000002`; rollback ditolak jika ada transaksi Hilang).
- (T11) Catatan peminjaman: tabel `loans` hanya punya satu kolom `note`, dipakai untuk catatan pengembalian/kehilangan; catatan saat pinjam cukup lewat `purpose` & `destination_note`.
- (T11) T23 (frontend peminjaman): kondisi keluar ditampilkan read-only; hasil terima kembali "Kembali"/"Hilang"; detail aset Hilang memakai `lost_during_loan`.
- (T9→HD) Status & event baru `Tidak Layak Pakai` (`POST /assets/{id}/report` `type=tidak_layak_pakai`, hanya dari Tersedia/Perbaikan). Aksi aset Tidak Layak Pakai: `pindah_lokasi` saja (+ Hapus aset).
- (HD) T13: aset `Tidak Layak Pakai` tidak dihitung "Ada" di kelengkapan ruangan. T15: kartu "Total aset tercatat" tidak menghitung Tidak Layak Pakai (jumlahnya tampil terpisah bila perlu).
- (HD) SQLite test: `->change()` membangun ulang tabel dan menghapus CHECK enum kolom lain; migration `2026_10_16_000001` memasang ulang semua CHECK enum `assets`, `loans`, `asset_events`. Migration berikutnya yang memakai `->change()` di tabel ber-enum wajib memasang ulang semua enum tabel itu (khusus SQLite).
- (T12) Barang habis pakai: hard delete (migration `2026_10_16_000002`: buang `deleted_at`, unique index `lower(name)`, FK `room_standards.consumable_id` ON DELETE SET NULL). Status lewat `ConsumableStatus` + scope `Consumable::withStatus()` (pakai ulang di T14 saran permintaan & T15 dashboard).
- (T12) T25 (frontend stok): peringatan hapus dari `GET /consumables/{id}/usage`, "Dipakai di ruangan" dari `meta.used_in_rooms`, tombol +/- cepat pakai `adjustment`.
