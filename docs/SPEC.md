# Spesifikasi — Sistem Informasi Aset SHD (Prototype)

Acuan isi: mockup "Aset SHD" (UI tidak mengikat, isi tabel/field minimal sesuai mockup).

## 1. Tujuan
1. Mendata aset per unit secara terpusat dengan QR, lokasi, kondisi, dan dokumen.
2. Memantau ketersediaan, peminjaman, perbaikan, dan jadwal servis secara real-time.
3. Mencatat riwayat lengkap tiap aset sebagai bukti akuntabilitas dan data pendukung pengadaan.
4. Memantau kesiapan ruangan terhadap standar perlengkapan dan mengelola permintaan ke unit lain.
5. Memantau stok barang habis pakai.

## 2. Pengguna
Satu jenis pengguna: **Staf/Admin Aset SHD**, tanpa role. Akun dibuat lewat seeder. Peminjam dan unit lain tidak memakai sistem.

## 3. Modul & fase
**Fase 1 (wajib, deadline awal November):** Dashboard · Daftar Aset · Peminjaman · Kelengkapan Ruangan · Permintaan ke Unit Lain · Stok Habis Pakai · Scan QR · Label QR · Ekspor laporan
**Fase 2 (jika ada waktu):** Lisensi Digital

## 4. Data master
- **Departemen/unit**: nama, `is_internal` (true = SHD). Bisa ditambah dengan mengetik nama baru.
- **Gedung → Ruangan**: dua tingkat. Keduanya bisa ditambah sendiri (ketik dengan saran). Gudang = ruangan biasa.
- **Kategori → Jenis aset**: kategori (Elektronik, Furnitur, Alat pelatihan) berisi jenis aset (Proyektor, Laptop, Meja peserta). Tiap jenis punya **kode singkat otomatis** 3 huruf dari namanya (Proyektor → PRJ, Laptop → LPT), unik, dan bisa diedit.

## 5. Aset per unit (ber-QR)
- **Kode otomatis**: `SHD-{KODE_JENIS}-{NNN}`, nomor urut per jenis (SHD-PRJ-001, SHD-PRJ-002; padding 3 digit tanpa batas: SHD-PRJ-1000). Prefiks `SHD` untuk semua aset. Nomor diambil dari penghitung per jenis (`asset_types.last_number`) dan **tidak pernah dipakai ulang** (termasuk setelah aset dihapus atau pindah jenis); aman terhadap permintaan bersamaan.
- **Kode jenis** (`asset_types.code`) otomatis dari nama jenis, unik, bisa diedit; perubahan hanya berlaku untuk aset baru. Kode yang pernah dipakai di kode aset milik jenis lain (aset yang masih ada atau kode lama di riwayat) tidak boleh dipakai lagi; setelah semua aset berkode itu dihapus, kodenya bebas lagi ("Kode pernah dipakai jenis lain.").
- **Token publik**: tiap aset punya `public_token` acak (12 karakter, tidak berurutan), dibuat otomatis saat aset dibuat dan tidak pernah berubah. Dipakai untuk link QR (§6).
- **Field**: kode, nama, jenis (→ kategori), merek, nomor seri, spesifikasi, sumber (departemen), gedung & ruangan **asal/seharusnya**, penanggung jawab (PIC, teks), kode aset keuangan, tanggal perolehan, tanggal servis berikutnya, kondisi, status, catatan.
- **Sumber**: aset milik SHD maupun unit lain. **Semua aset ber-QR bisa dipinjam** (jika status Tersedia), apa pun sumbernya. Label sumber di UI: **"Milik SHD"** (`is_internal = true`) dan **"Unit lain"**.
- **Kondisi**: `Baik`, `Rusak Ringan`, `Rusak Berat`.
- **Status**: `Tersedia`, `Dipinjam`, `Perbaikan`, `Hilang`, `Tidak Layak Pakai`.
- **Kondisi → status** (berlaku di tambah aset, catat kerusakan, tandai ditemukan, servis (T9), terima kembali (T11)): `Baik` → `Tersedia`; `Rusak Berat` → `Perbaikan` (otomatis); `Rusak Ringan` → staf memilih `Tersedia` (default) atau `Perbaikan`.
- **Lokasi** = lokasi asal (tempat seharusnya). Saat `Dipinjam`, detail aset juga menampilkan **lokasi peminjaman saat ini** (dari transaksi aktif).
- **Pindah lokasi asal**: lewat scan QR atau edit, selalu tercatat di riwayat.
- **Ubah data**: status dan kondisi **tidak** bisa diubah lewat ubah data (hanya lewat peminjaman, lapor, servis). **Jenis boleh diganti**: kode otomatis diganti ke nomor berikutnya pada jenis baru, nomor lama tidak dipakai ulang, kode lama dicatat di riwayat "Ubah Data" (`Kode: SHD-PRJ-005 → SHD-LPT-012`), disimpan di `asset_code_histories`, dan tetap bisa dicari. Frontend menampilkan peringatan kode baru dari `code-preview` sebelum menyimpan. Perubahan field lain dicatat sebagai "Ubah Data" berisi daftar perubahan; perubahan ruangan sebagai "Pindah Lokasi".
- **Catat kerusakan** (dicatat staf SHD): kondisi Rusak Ringan/Berat + catatan wajib + pihak pelapor (opsional, default SHD); status mengikuti aturan kondisi → status. Jika aset sedang `Dipinjam`, hanya kondisi yang berubah (status tetap `Dipinjam`, ditentukan saat terima kembali). Aset `Hilang` ditolak. Riwayat "Lapor Rusak".
- **Catat kehilangan** (dicatat staf SHD): status → `Hilang`, kondisi tetap, catatan wajib. Aset `Dipinjam` ditolak ("Aset sedang dipinjam. Selesaikan lewat peminjaman."); kehilangan saat dipinjam dicatat lewat terima kembali dengan hasil "Hilang" (T11). Riwayat "Lapor Hilang".
- **Tandai ditemukan** (khusus aset `Hilang`): staf memilih kondisi; status mengikuti aturan kondisi → status (`Tersedia`/`Perbaikan`, **tidak pernah langsung `Dipinjam`**; untuk dipinjam lewat alur Pinjam biasa). Riwayat "Ditemukan". Setelahnya frontend menawarkan "Langsung pinjamkan" yang membuka form pinjam.
- **Servis**: catat tanggal (≤ hari ini), vendor, kondisi setelah servis, catatan, dan tanggal servis berikutnya (opsional, > tanggal servis; kosong = tidak diubah). Status mengikuti aturan kondisi → status (Baik → `Tersedia`, Rusak Berat → `Perbaikan`, Rusak Ringan → pilihan staf, default `Tersedia`). Aset `Dipinjam` atau `Hilang` ditolak. Riwayat "Servis" bertanggal servis, pihak = vendor.
- **Perlu servis**: `next_service_date` sudah lewat atau ≤ 14 hari lagi (tampil di dashboard).
- **Hapus aset** (istilah UI; tanpa daftar "aset dihapuskan"): **hard delete permanen** untuk semua status, termasuk aset yang punya riwayat pinjam atau sedang Dipinjam. Dalam satu transaksi (lock aset) dihapus: pinjaman (termasuk yang aktif), riwayat, file (termasuk file di storage, setelah commit), kode lama, lalu asetnya; FK `loans`/`asset_events` tetap RESTRICT sehingga penghapusan hanya lewat `AssetService`. Sebelum menghapus, frontend memanggil `GET /assets/{id}/delete-impact` (jumlah pinjaman, pinjaman aktif + peminjam & unit, jumlah riwayat & file, standar ruangan terpengaruh, `requires_confirm_code`) untuk popup. Jika aset punya riwayat pinjam/sedang dipinjam, `DELETE` wajib `confirm_code` = kode aset (tidak membedakan huruf besar/kecil), jika tidak cocok 422 "Kode konfirmasi tidak cocok.". Riwayat aset yang dihapus hilang permanen dan tidak muncul di laporan (keputusan pengguna). Nomor urut kodenya tidak dipakai ulang.
- **Catat tidak layak pakai** (`POST /assets/{id}/report` `type=tidak_layak_pakai`, catatan wajib): hanya dari `Tersedia`/`Perbaikan`; `Dipinjam` ditolak ("Selesaikan lewat peminjaman."). Status → `Tidak Layak Pakai`, kondisi tetap, riwayat "Tidak Layak Pakai". Aset ini tidak bisa dipinjam, diservis, dicatat kerusakan/kehilangan, atau ditandai ditemukan; tidak dihitung "Ada" di kelengkapan ruangan; tidak ada aksi untuk mengembalikannya (hanya Pindah lokasi dan Hapus aset). Tetap tampil di Daftar Aset (filter status).
- **Foto & dokumen**: upload `Foto Aset`, `Foto Kondisi`, `BAST`, `Garansi`, `Lainnya` (gambar/PDF, maks 5 MB). Penyimpanan via Laravel Storage (lokal saat dev, Supabase Storage/S3 saat deploy, cukup ganti env).
- **Riwayat aset** (satu tabel gabungan): Registrasi, Dipinjam, Dikembalikan, Dibatalkan, Pindah Lokasi, Lapor Rusak, Lapor Hilang, Ditemukan, Tidak Layak Pakai, Servis, Ubah Data. Kolom: tanggal, kegiatan, pihak, kondisi, dicatat oleh, catatan.
- **Daftar Aset**: filter kategori, gedung, ruangan, sumber, kondisi, status, pencarian nama/kode. Kolom minimal: Kode, Nama, Kategori, Lokasi (ruangan + gedung), Sumber, Kondisi, Status.

## 6. Scan QR & Label
- QR berisi link halaman publik `{QR_BASE_URL}/aset/{public_token}` (env; bukan id/kode). QR PNG, error correction M.
- **Scan staf** `GET /assets/scan/{value}`: `value` = kode aset, kode lama, atau `public_token` (frontend mengambil segmen terakhir URL QR); urutan pencocokan kode → kode lama → token (kode tidak membedakan huruf besar/kecil, token persis). Respons data aset + `meta.matched_by` (`code`/`previous_code`/`token`, untuk peringatan "kode lama") + `meta.actions` (aksi status + `batalkan` jika Dipinjam). Tidak ditemukan (termasuk aset yang sudah dihapus) → 404 "Aset tidak ditemukan."
- **Pindah lokasi** `PATCH /assets/{id}/location` (`room_id`, `note` opsional): semua status, ruangan sama ditolak, riwayat "Pindah Lokasi".
- **Halaman publik** `/aset/{public_token}` (frontend, tanpa login) memanggil `GET /api/public/assets/{token}` (read-only, rate limit 30/menit per IP; aset tidak ada/sudah dihapus → 404 "Aset tidak ditemukan."). Data: kode, nama, kategori, jenis, sumber, gedung & ruangan asal, lokasi saat ini, PIC, kondisi, status, tanggal perolehan, servis berikutnya, kode aset keuangan, apakah jenis aset termasuk standar ruangannya, dan aksi yang valid. Field publik diatur di satu tempat (`PublicAssetResource`) dan **masih menunggu konfirmasi mentor** soal data yang boleh tampil publik.
- Tombol aksi di halaman publik diberi keterangan **"Khusus staf SHD"**. Belum login → diarahkan ke login lalu kembali otomatis ke form aksi dengan aset terisi; sudah login → langsung ke form. Semua aksi tetap hanya lewat endpoint ber-auth.
- Hasil scan: nama, kode, jenis, spesifikasi, sumber, lokasi asal, lokasi peminjaman (jika dipinjam), kondisi, status.
- Aksi sesuai status (`AssetStatus::allowedActions()`, dicatat staf SHD, bukan peminjam): `Tersedia` → **Pinjam**, **Pindah lokasi**, **Catat kerusakan**, **Catat kehilangan**, **Catat tidak layak pakai**; `Dipinjam` → **Terima kembali**, **Pindah lokasi**, **Catat kerusakan** (+ **Batalkan** di halaman staf); `Perbaikan` → **Pindah lokasi**, **Catat kerusakan**, **Catat kehilangan**, **Catat tidak layak pakai**; `Hilang` → **Tandai ditemukan**, **Pindah lokasi**; `Tidak Layak Pakai` → **Pindah lokasi** saja. **Hapus aset** berlaku untuk semua status (bukan bagian daftar aksi). Kunci aksi API: `pinjam`, `terima_kembali`, `pindah_lokasi`, `catat_kerusakan`, `catat_kehilangan`, `catat_tidak_layak_pakai`, `tandai_ditemukan`.
- Label PDF: QR 5×5 cm + kode kecil di bawah (garis potong putus-putus), grid **3 kolom × 4 baris = 12 label per A4** (margin 10 mm), urut kode. Satu aset → sel pertama halaman A4. Banyak aset maks. 120 per cetak; id aset yang tidak ada ditolak. Default tampil di browser (inline), `?download=1` untuk unduh.
- Demo dengan webcam laptop (`localhost`). Versi HP butuh HTTPS (tim deploy).

## 7. Peminjaman
- Dicatat staf atas nama peminjam. Approval atasan di luar sistem, nomornya dicatat.
- Satu transaksi = satu unit aset. Satu aset maksimal satu transaksi `Dipinjam` (lock + partial unique index).
- **Field pinjam**: aset (scan/ketik kode), nama peminjam, unit kerja (teks bebas, saran dari master departemen), keperluan/kegiatan, gedung & ruangan tujuan (opsional, bisa catatan lokasi bebas), tanggal pinjam, rencana kembali (wajib, ≥ tanggal pinjam), no. & tanggal approval (opsional).
- **Kondisi saat keluar** diisi otomatis dari kondisi aset saat dipinjam (tidak diinput staf). Jika kondisi fisik berbeda, staf memakai "Catat kerusakan" dulu.
- **Tanggal pinjam** default hari ini; boleh tanggal lampau (pencatatan terlambat), tidak boleh di masa depan, dan tidak boleh sebelum aset terdaftar. Jika rencana kembali sudah lewat, transaksi otomatis tampil terlambat. `created_at` transaksi tetap mencatat waktu pencatatan sebenarnya.
- Syarat: aset ada dan status `Tersedia` (bukan Perbaikan/Hilang/Tidak Layak Pakai). Efek: status → `Dipinjam`, riwayat "Dipinjam" (pihak = peminjam, bertanggal pinjam).
- **Terima kembali** (`result=kembali`): tanggal kembali (default sekarang, ≥ tanggal pinjam, tidak di masa depan), kondisi saat kembali, ruangan penyimpanan (default lokasi asal, boleh diubah → lokasi asal ikut pindah, tercatat "Pindah Lokasi"), catatan (wajib jika tidak Baik).
  - Kondisi & status aset mengikuti aturan kondisi → status (§5): Baik → `Tersedia`, Rusak Berat → `Perbaikan`, Rusak Ringan → pilihan staf (default `Tersedia`).
  - Transaksi ditandai **Menunggu Konfirmasi** hanya jika kondisi saat kembali **lebih buruk** dari kondisi saat keluar (Baik < Rusak Ringan < Rusak Berat), sampai staf menandai **Dikonfirmasi** (konfirmasi di luar sistem).
- **Hilang saat dipinjam** (`result=hilang`): tanggal + catatan wajib. Transaksi berstatus `Hilang` (peminjam tetap tersimpan, **tanpa** konfirmasi peminjam), status aset `Hilang`, riwayat "Lapor Hilang" tertaut ke pinjaman dengan pihak = nama peminjam. Detail aset (staf) menampilkan "Hilang saat dipinjam oleh [nama], [unit kerja], [tanggal]"; halaman publik tidak menampilkan data peminjam. Aset tetap bisa "Tandai ditemukan".
- **Konfirmasi kerusakan**: hanya transaksi Menunggu Konfirmasi; catatan konfirmasi wajib, no. dokumen (berita acara/memo) opsional; waktu & staf otomatis.
- **Batalkan**: hanya transaksi `Dipinjam`, alasan wajib → status aset mengikuti kondisi aset saat itu (aturan kondisi → status), riwayat "Dibatalkan".
- Jika aset dihapus (Hapus aset), semua transaksinya ikut terhapus permanen, termasuk transaksi `Dipinjam` yang masih aktif.
- Status transaksi: `Dipinjam`, `Dikembalikan`, `Dibatalkan`, `Hilang`. Terlambat = `Dipinjam` dan rencana kembali < hari ini. Jatuh tempo hari ini = rencana kembali = hari ini.
- Halaman: form catat peminjaman + tabel sedang dipinjam (Aset, Peminjam, Tempo) dengan tombol terima kembali + riwayat.

## 8. Kelengkapan Ruangan
- **Atur standar**: pilih gedung → ruangan (keduanya bisa ditambah), lalu input daftar standar per ruangan. Bisa **salin standar dari ruangan lain** lalu disesuaikan.
- **Baris standar**: nama perlengkapan, sumber (departemen), jumlah standar, cara hitung, keterangan pemenuhan.
  - `QR`: pilih jenis aset; jumlah "Ada" otomatis = aset jenis itu dengan lokasi asal di ruangan tersebut, status bukan `Dipinjam`/`Perbaikan`/`Hilang`/`Tidak Layak Pakai`.
  - `Manual`: jumlah "Ada" diinput staf saat cek.
  - **Keterangan pemenuhan**, salah satu:
    - **Dari stok SHD**: ditautkan ke item Stok Habis Pakai (misal "ATK peserta" ↔ stok "Pulpen"). Muncul tombol **Isi dari stok**.
    - Form standar (T13): pemenuhan "Dari stok SHD" memakai kolom ketik-dengan-saran untuk memilih item stok; satu stok boleh ditautkan ke banyak ruangan. Pemenuhan hanya **satu** dari: stok SHD, ambil dari, atau minta ke unit lain. Validasi: stok SHD (dan "ambil dari") hanya boleh jika sumber standar = SHD. Jika item stok dihapus, tautannya lepas otomatis dan standar tetap ada.
    - **Ambil dari** (sumber SHD tanpa stok): teks bebas, misal "Gudang Gedung IHS".
    - **Minta ke unit lain** (sumber unit lain, misal tisu dari Dept A): mengacu ke katalog permintaan. Barang ini tidak disimpan SHD, hanya tercatat di Kelengkapan Ruangan.
  - **Keputusan (T4)**: database hanya menjamin paling banyak satu jenis pemenuhan per baris (`CHECK num_nonnulls(consumable_id, fulfillment_note, catalog_id) <= 1`, boleh kosong). Konsistensi sumber vs jenis pemenuhan (sumber SHD → stok SHD/ambil dari; sumber unit lain → minta ke unit lain) divalidasi di FormRequest pada T13.
- **Isi dari stok**: jumlah yang diisi = min(kekurangan, stok tersedia). Stok berkurang dan jumlah "Ada" ruangan bertambah dalam satu transaksi. Jika stok tidak cukup, sisa tetap tercatat kurang. Contoh: kurang 5, stok 3 → ruangan +3 (masih kurang 2), stok jadi 0.
- **Cek ruangan**: staf mengisi jumlah Ada untuk baris Manual lalu **Simpan hasil cek** → ruangan menyimpan waktu & staf pengecek terakhir (hanya hasil terakhir).
- **Tabel checklist**: Perlengkapan, Sumber, Standar, Ada, Selisih, Hasil (`Sesuai`/`Kurang`), Ambil dari / Minta ke.
- Ruangan kurang menampilkan pesan kekurangan beserta aksi per baris: **Isi dari stok** (item stok SHD) atau **Buat permintaan** (item unit lain, form terisi otomatis).
- Daftar ruangan dikelompokkan per gedung dengan ringkasan "Kurang N item" / "Lengkap".
- **URL halaman (frontend)**: `/kelengkapan-ruangan` otomatis redirect ke gedung & ruangan pertama, lalu `/kelengkapan-ruangan/{slug-gedung}/{slug-ruangan}` (mis. `/kelengkapan-ruangan/gedung-ihs/ruang-kelas-1`). Frontend mencocokkan slug ke id dari data gedung & ruangan yang sudah dimuat; **API tetap memakai id**. Slug gedung unik, slug ruangan unik per gedung, dibuat otomatis dari nama (bentrok → `-2`) dan **tidak berubah** saat nama diganti.

## 9. Permintaan ke Unit Lain
- **Katalog perlengkapan unit lain**: item, unit penyedia, cara permintaan (teks bebas dengan saran; default Memo, Form, Email, WhatsApp, Lainnya, ditambah cara yang pernah diketik), perlu dikembalikan (Ya/Tidak), lead time (hari), catatan.
- **Riwayat permintaan** (proses permintaan tetap di luar sistem, staf hanya mencatat): tanggal, item, jumlah, untuk kegiatan, ruangan terkait (opsional), unit penyedia, status, catatan.
- **Status**: `Diajukan` → `Diterima` → `Dikembalikan` (hanya jika perlu dikembalikan); atau `Dibatalkan`. Tampilan **"Belum direspons"** = `Diajukan` yang melewati lead time (dihitung, bukan disimpan).
- **Daftar saran permintaan** (pengingat, dikelompokkan per unit penyedia):
  - kekurangan kelengkapan ruangan yang pemenuhannya "minta ke unit lain";
  - stok habis pakai yang Menipis/Habis, ke unit di kolom "minta ke".
  Dari saran, staf bisa langsung **Buat permintaan** (form terisi otomatis).

## 10. Stok Habis Pakai
- Hanya barang yang **disimpan SHD** (pensil, tinta, solasi, dll.). Barang yang tidak disimpan SHD (misal tisu dari Dept A) tidak masuk stok, hanya di Kelengkapan Ruangan.
- Field: nama (**unik tanpa membedakan huruf besar/kecil**, unique index `lower(name)`), kategori (teks bebas), satuan, jumlah, minimum, **minta ke** (unit penyedia untuk isi ulang), katalog permintaan (opsional, harus dari unit yang sama dengan "minta ke"), lokasi simpan (gedung/ruangan, opsional), catatan.
- Jumlah diubah langsung oleh staf (tanpa catatan masuk/keluar) lewat **ubah jumlah**: salah satu dari `quantity` (set langsung, ≥ 0) atau `adjustment` (tambah/kurang, ≠ 0); keduanya/tidak satu pun ditolak. Dalam transaksi + lock, hasil tidak boleh negatif ("Stok tidak cukup (tersisa N)."). Ubah data biasa tidak mengubah jumlah. Jumlah juga berkurang otomatis lewat **Isi dari stok** di Kelengkapan Ruangan.
- Status (dihitung, tidak disimpan; `ConsumableStatus` + scope `withStatus` dipakai ulang dashboard & saran permintaan): `Aman` (> minimum), `Menipis` (≤ minimum dan > 0), `Habis` (0).
- Menipis/Habis → **peringatan di dashboard** dan **masuk daftar saran permintaan** ke unit "minta ke".
- **Hapus = hard delete permanen** (pengecualian di CLAUDE.md). Jika barang dipakai di standar ruangan (pemenuhan "Dari stok SHD"), hapus tetap diizinkan: tautan `consumable_id` di standar terkait dikosongkan dalam transaksi yang sama (FK `ON DELETE SET NULL`), baris standar, jumlah standar, dan "Ada" tetap utuh; respons memuat daftar ruangan terdampak. Frontend memanggil `GET /consumables/{id}/usage` lebih dulu untuk peringatan "Hapus permanen. Barang ini dipakai di standar: [daftar ruangan]. Tautannya akan dilepas."; detail stok menampilkan "Dipakai di ruangan: ...".
- Barang habis pakai **tidak dihitung** dalam total aset.

## 11. Dashboard
Kartu:
- Total aset tercatat (rincian milik SHD · dari unit lain), **tidak menghitung** aset `Tidak Layak Pakai`; jumlah Tidak Layak Pakai ditampilkan terpisah bila perlu
- Sedang dipinjam (jatuh tempo hari ini, terlambat)
- Perlu perbaikan (menunggu konfirmasi peminjam)
- Ruangan belum lengkap (dari N ruangan di M gedung)
- Perlu servis (lewat/mendekati tanggal)
- Stok habis pakai menipis

Panel: peminjaman jatuh tempo/terlambat, kesiapan ruangan per gedung, permintaan terbaru beserta status, aset perlu servis, stok menipis.

## 12. Ekspor laporan (PDF & Excel)
- Laporan Aset (sesuai filter Daftar Aset)
- Laporan Peminjaman (periode tanggal)
- Laporan Kelengkapan Ruangan (semua ruangan / per gedung)
- Laporan Permintaan (periode)
- Laporan Stok Habis Pakai

## 13. Skema database
Lihat `docs/erd.dbml` (sumber kebenaran skema). Ringkasan tabel:
`users`, `departments`, `buildings`, `rooms`, `categories`, `asset_types`, `assets`, `asset_files`, `asset_events`, `loans`, `room_standards`, `request_catalog`, `supply_requests`, `consumables` (+ `personal_access_tokens`, `migrations`).

## 14. Kontrak API (prefix `/api`, Bearer token kecuali login)
**Auth**: `POST /login`, `POST /logout`, `GET /user`

**Master & lookup**: `GET/POST /departments`, `GET/POST /buildings`, `GET/POST /buildings/{id}/rooms`, `GET /rooms`, `GET/POST /categories`, `GET/POST/PUT /asset-types`, `GET /lookups/borrower-units?search=`, `GET /lookups/request-methods?search=`

**Publik (tanpa token, rate limit)**: `GET /public/assets/{token}` — data halaman QR (§6)

**Dashboard**: `GET /dashboard`

**Aset**
| Method | Endpoint | Fungsi |
|---|---|---|
| GET | /assets | Daftar + filter (`category_id`, `building_id`, `room_id`, `source_department_id`, `condition`, `status`, `search` nama/kode/S-N/kode lama) + paginasi (`per_page` ≤ 100); Tidak Layak Pakai ikut tampil |
| POST | /assets | Tambah (kode otomatis, status dari kondisi, riwayat Registrasi) |
| GET | /assets/{id} | Detail + peminjaman aktif + lokasi saat ini + kode lama |
| GET | /assets/{id}/delete-impact | Dampak hapus: `loans_count`, `active_loan`, `events_count`, `files_count`, `affected_room_standards`, `requires_confirm_code` |
| GET | /assets/{id}/code-preview?asset_type_id= | Pratinjau kode baru jika jenis diganti |
| PUT | /assets/{id} | Ubah data (pindah lokasi tercatat; ganti jenis → kode baru, `meta.code_changed`; status/kondisi/kode tidak bisa diubah) |
| PATCH | /assets/{id}/location | Pindah lokasi (`room_id`, `note`) |
| POST | /assets/{id}/report | Catat kerusakan (`type=rusak`, `condition`, `status` jika Rusak Ringan) / kehilangan (`type=hilang`) / tidak layak pakai (`type=tidak_layak_pakai`); `note` wajib, `party` opsional |
| POST | /assets/{id}/found | Tandai ditemukan (khusus Hilang): `condition`, `status` jika Rusak Ringan, `note`, `party` |
| POST | /assets/{id}/services | Catat servis: `service_date`, `vendor`, `condition`, `status` jika Rusak Ringan, `next_service_date`, `note` |
| DELETE | /assets/{id} | Hapus aset permanen (semua status) beserta data turunan; `confirm_code` = kode aset wajib jika punya riwayat pinjam |
| GET | /assets/{id}/events | Riwayat aset, terbaru dulu |
| GET/POST | /assets/{id}/files | Daftar/upload file |
| DELETE | /asset-files/{id} | Hapus file |
| GET | /assets/scan/{value} | Data scan (kode / kode lama / token) + `meta.actions` + `meta.matched_by` |
| GET | /assets/{id}/label | PDF label satu aset (`?download=1` untuk unduh) |
| POST | /assets/labels | PDF label banyak aset (`ids[]`, maks. 120; `?download=1`) |

**Peminjaman**: `GET /loans` (`status`, `overdue`, `due_today`, `pending_confirmation`, `from`/`to` tanggal pinjam, `asset_id`, `search` peminjam/unit/kode/nama aset; aktif urut tempo terdekat), `POST /loans`, `GET /loans/{id}`, `PATCH /loans/{id}/return` (`result` kembali|hilang, `return_date`, `return_condition`, `status` jika Rusak Ringan, `return_room_id`, `note`), `PATCH /loans/{id}/cancel` (`cancel_reason`), `PATCH /loans/{id}/confirm-damage` (`damage_confirmation_note`, `damage_confirmation_document`)

**Kelengkapan**: `GET /room-readiness` (per gedung), `GET /rooms/{id}/checklist`, `POST /rooms/{id}/standards`, `PUT /room-standards/{id}`, `DELETE /room-standards/{id}`, `POST /rooms/{id}/standards/copy` (from_room_id), `POST /rooms/{id}/check` (simpan hasil cek), `POST /room-standards/{id}/fill-from-stock` (isi dari stok SHD)

**Permintaan**: `GET/POST/PUT/DELETE /request-catalog`, `GET/POST /supply-requests`, `PUT /supply-requests/{id}`, `PATCH /supply-requests/{id}/status`, `GET /supply-requests/suggestions`

**Stok habis pakai**: `GET /consumables` (`status`, `category`, `provider_department_id`, `building_id`, `room_id`, `search`), `POST /consumables`, `GET /consumables/{id}` (+ `meta.used_in_rooms`), `GET /consumables/{id}/usage`, `PUT /consumables/{id}` (tanpa `quantity`), `PATCH /consumables/{id}/quantity` (`quantity` atau `adjustment`), `DELETE /consumables/{id}` (permanen, `detached_rooms`)

**Ekspor**: `GET /reports/{type}?format=pdf|xlsx&...filter` (type: assets, loans, rooms, requests, consumables)

Kode status: 200/201, 401, 404, 422 (validasi/aturan bisnis), 413 (file terlalu besar).

## 15. Non-fungsional
- Token Sanctum 480 menit, simpan di `localStorage`, 401 → login.
- RLS aktif di semua tabel Supabase.
- Semua URL & penyimpanan dari env: `APP_URL`, `FRONTEND_URL`, `QR_BASE_URL`, `FILESYSTEM_DISK`; frontend `VITE_API_URL`.
- `.env.example` lengkap tanpa kredensial; README berisi langkah deploy.
- UI Bahasa Indonesia, responsif.

## 16. Fase 2 — Lisensi Digital
Nama layanan, vendor, jenis lisensi, jumlah seat, pengguna/penanggung jawab, tanggal mulai & berakhir, biaya, status, peringatan mendekati kedaluwarsa.
