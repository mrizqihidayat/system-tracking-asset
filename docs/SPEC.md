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
- **Foto & dokumen**: jenis `Foto Aset`, `Foto Kondisi`, `BAST`, `Garansi`, `Lainnya`; tipe JPG/PNG/WebP/PDF (dicek dari isi file), maks 5 MB per file (lebih besar → 413 "Ukuran file maksimal 5 MB."). Penyimpanan via Laravel Storage (disk `ASSET_DISK`: lokal saat dev, Supabase Storage/S3 saat deploy, cukup ganti env), path `assets/{asset_id}/{uuid}.{ext}`.
  - `Foto Aset`, `Foto Kondisi`, `BAST`, `Garansi`: **satu slot per aset**. Unggah ke jenis yang sudah terisi **mengganti** file lama dalam satu transaksi (baris aset dikunci); file lama dihapus dari storage setelah commit. Tidak ada riwayat file (Foto Kondisi hanya yang terakhir).
  - `Lainnya`: boleh banyak file (menumpuk, tanpa batas jumlah), nama file asli disimpan dan ditampilkan.
  - Hapus file satuan tersedia untuk semua jenis. Upload/hapus file **tidak** dicatat di riwayat aset. Halaman publik QR tidak menampilkan file.
  - File dibuka lewat `GET /asset-files/{id}/download` (stream lewat backend, butuh Bearer token; inline, `?download=1` untuk unduh); frontend mengambilnya sebagai blob.
- **Riwayat aset** (satu tabel gabungan): Registrasi, Dipinjam, Dikembalikan, Dibatalkan, Pindah Lokasi, Lapor Rusak, Lapor Hilang, Ditemukan, Tidak Layak Pakai, Servis, Ubah Data. Kolom: tanggal, kegiatan, pihak, kondisi, dicatat oleh, catatan.
- **Daftar Aset**: filter kategori, gedung, ruangan, sumber, kondisi, status, pencarian nama/kode. Kolom minimal: Kode, Nama, Kategori, Lokasi (ruangan + gedung), Sumber, Kondisi, Status.
- **Rute frontend**: halaman staf (butuh login) `/daftar-aset`, `/daftar-aset/baru`, `/daftar-aset/:id`, `/daftar-aset/:id/ubah`. Rute `/aset/{public_token}` khusus halaman publik QR (§6), tanpa login.

## 6. Scan QR & Label
- QR berisi link halaman publik `{QR_BASE_URL}/aset/{public_token}` (env; bukan id/kode). QR PNG, error correction M.
- **Scan staf** `GET /assets/scan/{value}`: `value` = kode aset, kode lama, atau `public_token` (frontend mengambil segmen terakhir URL QR); urutan pencocokan kode → kode lama → token (kode tidak membedakan huruf besar/kecil, token persis). Respons data aset + `meta.matched_by` (`code`/`previous_code`/`token`, untuk peringatan "kode lama") + `meta.actions` (aksi status + `batalkan` jika Dipinjam). Tidak ditemukan (termasuk aset yang sudah dihapus) → 404 "Aset tidak ditemukan."
- **Pindah lokasi** `PATCH /assets/{id}/location` (`room_id`, `note` opsional): semua status, ruangan sama ditolak, riwayat "Pindah Lokasi".
- **Halaman publik** `/aset/{public_token}` (frontend, tanpa login) memanggil `GET /api/public/assets/{token}` (read-only, rate limit 30/menit per IP; aset tidak ada/sudah dihapus → 404 "Aset tidak ditemukan."). Data: kode, nama, kategori, jenis, sumber, gedung & ruangan asal, lokasi saat ini, PIC, kondisi, status, tanggal perolehan, servis berikutnya, kode aset keuangan, apakah jenis aset termasuk standar ruangannya, dan aksi yang valid. Field publik diatur di satu tempat (`PublicAssetResource`) dan **masih menunggu konfirmasi mentor** soal data yang boleh tampil publik.
- Tombol aksi di halaman publik diberi keterangan **"Khusus staf SHD"**. Belum login → diarahkan ke login lalu kembali otomatis ke form aksi dengan aset terisi; sudah login → langsung ke form. Pinjam membuka form pinjam dengan aset terisi (`/peminjaman?asset_id={id}`), Terima kembali membuka modal terima kembali untuk peminjaman aktif aset itu (`/peminjaman?return_loan_id={id}`, juga dari hasil scan staf), aksi lain membuka modalnya di hasil scan. Aset tanpa peminjaman aktif → pesan "Aset ini tidak punya peminjaman aktif."; aksi yang tidak berlaku untuk status terkini → pesan di hasil scan. Semua aksi tetap hanya lewat endpoint ber-auth.
- Hasil scan: nama, kode, jenis, spesifikasi, sumber, lokasi asal, lokasi peminjaman (jika dipinjam), kondisi, status.
- Aksi sesuai status (`AssetStatus::allowedActions()`, dicatat staf SHD, bukan peminjam): `Tersedia` → **Pinjam**, **Pindah lokasi**, **Catat kerusakan**, **Catat kehilangan**, **Catat tidak layak pakai**; `Dipinjam` → **Terima kembali**, **Pindah lokasi**, **Catat kerusakan** (+ **Batalkan** di halaman staf); `Perbaikan` → **Pindah lokasi**, **Catat kerusakan**, **Catat kehilangan**, **Catat tidak layak pakai**; `Hilang` → **Tandai ditemukan**, **Pindah lokasi**; `Tidak Layak Pakai` → **Pindah lokasi** saja. **Hapus aset** berlaku untuk semua status (bukan bagian daftar aksi). Kunci aksi API: `pinjam`, `terima_kembali`, `pindah_lokasi`, `catat_kerusakan`, `catat_kehilangan`, `catat_tidak_layak_pakai`, `tandai_ditemukan`.
- Label PDF: QR 5×5 cm + kode kecil di bawah (garis potong putus-putus), grid **3 kolom × 4 baris = 12 label per A4** (margin 10 mm), urut kode. Satu aset → sel pertama halaman A4. Banyak aset maks. 120 per cetak; id aset yang tidak ada ditolak. Default tampil di browser (inline), `?download=1` untuk unduh.
- Demo dengan webcam laptop (`localhost`). Versi HP butuh HTTPS (tim deploy).

## 7. Peminjaman
- Dicatat staf atas nama peminjam. Approval atasan di luar sistem, nomornya dicatat.
- Satu transaksi = satu unit aset. Satu aset maksimal satu transaksi `Dipinjam` (lock + partial unique index).
- **Field pinjam**: aset (scan/ketik kode), nama peminjam, unit kerja (teks bebas, tanpa saran), keperluan/kegiatan, gedung & ruangan tujuan (opsional, bisa catatan lokasi bebas), tanggal pinjam, rencana kembali (wajib, ≥ tanggal pinjam), no. / tanggal approval (opsional, satu kolom teks bebas, disimpan di `approval_number`).
- **Kondisi saat keluar** diisi otomatis dari kondisi aset saat dipinjam (tidak diinput staf). Jika kondisi fisik berbeda, staf memakai "Catat kerusakan" dulu.
- **Tanggal pinjam** default hari ini; boleh tanggal lampau (pencatatan terlambat), tidak boleh di masa depan, dan tidak boleh sebelum aset terdaftar. Jika rencana kembali sudah lewat, transaksi otomatis tampil terlambat. `created_at` transaksi tetap mencatat waktu pencatatan sebenarnya.
- Syarat: aset ada dan status `Tersedia` (bukan Perbaikan/Hilang/Tidak Layak Pakai). Efek: status → `Dipinjam`, riwayat "Dipinjam" (pihak = peminjam, bertanggal pinjam).
- **Terima kembali** (`result=kembali`): tanggal kembali (default sekarang, ≥ tanggal pinjam, tidak di masa depan), kondisi saat kembali, ruangan penyimpanan (default lokasi asal, boleh diubah → lokasi asal ikut pindah, tercatat "Pindah Lokasi"), catatan (wajib jika tidak Baik).
  - Kondisi & status aset mengikuti aturan kondisi → status (§5): Baik → `Tersedia`, Rusak Ringan → pilihan staf `Tersedia` (default) / `Perbaikan`, Rusak Berat → pilihan staf `Perbaikan` (default) / `Tidak Layak Pakai` (permanen, ditambah riwayat "Tidak Layak Pakai" tertaut ke pinjaman). Hanya Rusak Berat yang bisa `Tidak Layak Pakai` lewat terima kembali.
  - Tanpa konfirmasi kerusakan: dicatat staf SHD. Riwayat peminjaman menampilkan status `Dikembalikan` + kondisi kembali jika tidak Baik.
- **Hilang saat dipinjam** (`result=hilang`): tanggal + catatan wajib. Transaksi berstatus `Hilang` (peminjam tetap tersimpan), status aset `Hilang`, riwayat "Lapor Hilang" tertaut ke pinjaman dengan pihak = nama peminjam. Detail aset (staf) menampilkan "Hilang saat dipinjam oleh [nama], [unit kerja], [tanggal]"; halaman publik tidak menampilkan data peminjam. Aset tetap bisa "Tandai ditemukan".
- **Batalkan**: hanya transaksi `Dipinjam`, alasan wajib → status aset mengikuti kondisi aset saat itu (aturan kondisi → status), riwayat "Dibatalkan".
- Jika aset dihapus (Hapus aset), semua transaksinya ikut terhapus permanen, termasuk transaksi `Dipinjam` yang masih aktif.
- Status transaksi: `Dipinjam`, `Dikembalikan`, `Dibatalkan`, `Hilang`. Terlambat = `Dipinjam` dan rencana kembali < hari ini. Jatuh tempo hari ini = rencana kembali = hari ini.
- Halaman: form catat peminjaman + tabel sedang dipinjam (Aset, Peminjam, Tempo) dengan tombol terima kembali + riwayat.

## 8. Kelengkapan Ruangan
- **Atur standar**: pilih gedung → ruangan (keduanya bisa ditambah), lalu input daftar standar per ruangan. Standar **diisi manual per ruangan** (tidak ada fitur salin dari ruangan lain). Ubah standar (`PUT`) hanya mengubah definisi; hapus standar = hard delete biasa (tanpa alasan).
- **Baris standar**: pilih **tipe** dulu, lalu nama perlengkapan (terisi dari pilihan, bisa diubah) dan jumlah standar.
  - **Aset** (`count_method = QR`): pilih jenis aset dan sumber (default Milik SHD). Jumlah "Ada" otomatis = aset jenis itu dengan lokasi asal di ruangan tersebut, status bukan `Dipinjam`/`Perbaikan`/`Hilang`/`Tidak Layak Pakai`. Jumlah ini tidak bisa diketik: jika aset tidak ada saat dicek, catat lewat detail aset (Kehilangan / Pindah lokasi). Kekurangan aset tidak punya aksi (aset tidak punya data penyedia). Label di tabel: kode aset yang terhitung (maks. 3 + "+N", klik menuju detail; kosong = "Belum ada aset") + keterangan aset yang tidak dihitung, misal "1 sedang dipinjam", "1 dalam perbaikan", "1 hilang" (Tidak Layak Pakai tidak ditampilkan). Semua diambil satu query per checklist (`assets`, `not_counted` di respons).
  - **Stok Habis Pakai** (`count_method = Manual` + `consumable_id`): pilih barang yang **sudah terdaftar di Stok Habis Pakai**; satu barang boleh ditautkan ke banyak ruangan. Sumber dan tujuan permintaan otomatis dari "minta ke" barang itu, tidak diisi sendiri; mengubah "minta ke" barang ikut memperbarui sumber di standar terkait. Label di tabel: `Stok Habis Pakai`.
  - **Barang unit lain** (`count_method = Manual` tanpa `consumable_id`, sumber bukan SHD): barang yang tidak disimpan SHD, misal tisu dari SGA. Pilih departemen penyedia dan katalog (opsional, harus dari departemen yang sama). Label di tabel: `Barang unit lain`. Saat kurang muncul **Buat permintaan** ke departemen itu; permintaan tertaut ke baris ini (`room_standard_id`).
  - Baris Manual (Stok Habis Pakai & Barang unit lain): jumlah "Ada" diinput staf saat cek (hanya lewat Simpan hasil cek). Selama `actual_quantity` kosong, baris berstatus **Belum dicek**: Selisih tidak dihitung, Hasil = `Belum dicek`, dan **tidak dihitung kurang** (ringkasan kesiapan, dashboard, saran permintaan). Mengisi 0 dengan sengaja tetap Hasil = `Kurang` (standar > 0).
  - Aksi saat kurang pada baris stok: stok masih ada (jumlah > 0) → **Isi dari stok**; stok 0 → **Buat permintaan** ke penyedianya.
  - **Perlu dilengkapi**: baris Manual bersumber SHD tanpa `consumable_id` (data lama, atau barangnya sudah dihapus). Tanpa aksi dan tidak masuk saran permintaan sampai diedit dan dipilih tipenya; jumlah "Ada" tetap bisa diisi dan kekurangannya tetap dihitung. Mengedit baris mengosongkan `fulfillment_note` lama.
  - **Keputusan (T26m, mengganti T26i)**: barang yang tidak disimpan SHD bukan bagian stok, melainkan baris Barang unit lain; `catalog_id` dipakai baris ini. Kolom `fulfillment_note` tetap ada untuk data lama, input baru ditolak (422).
- **Isi dari stok** (baris Manual yang sudah dicek dan kurang): jumlah yang diisi = min(kekurangan, stok tersedia). Stok berkurang dan jumlah "Ada" ruangan bertambah dalam satu transaksi. Jika stok tidak cukup, sisa tetap tercatat kurang. Contoh: kurang 5, stok 3 → ruangan +3 (masih kurang 2), stok jadi 0.
- **Cek ruangan**: staf mengisi jumlah Ada untuk baris Manual lalu **Simpan hasil cek** → ruangan menyimpan waktu & staf pengecek terakhir (hanya hasil terakhir).
- **Tabel checklist**: Perlengkapan, Sumber, Standar, Ada, Selisih, Hasil (`Sesuai`/`Kurang`/`Belum dicek`), Pemenuhan.
- Ruangan kurang menampilkan pesan kekurangan beserta aksi per baris: **Isi dari stok** atau **Buat permintaan** (form terisi otomatis dari katalog barang, atau nama barang + penyedia jika tanpa katalog).
- Daftar ruangan dikelompokkan per gedung dengan ringkasan "Kurang N item" / "Lengkap", ditambah jumlah item "Belum dicek" yang ditampilkan terpisah (Lengkap = tidak ada baris Kurang).
- **URL halaman (frontend)**: `/kelengkapan-ruangan` otomatis redirect ke gedung & ruangan pertama, lalu `/kelengkapan-ruangan/{slug-gedung}/{slug-ruangan}` (mis. `/kelengkapan-ruangan/gedung-ihs/ruang-kelas-1`). Frontend mencocokkan slug ke id dari data gedung & ruangan yang sudah dimuat; **API tetap memakai id**. Slug gedung unik, slug ruangan unik per gedung, dibuat otomatis dari nama (bentrok → `-2`) dan **tidak berubah** saat nama diganti.

## 9. Permintaan ke Unit Lain
- **Katalog perlengkapan unit lain**: item, unit penyedia, perlu dikembalikan (Ya/Tidak), lead time (hari), catatan. Katalog tidak menyimpan cara permintaan. **Hapus katalog selalu boleh**, termasuk yang sedang dipakai: dalam satu transaksi tautan `catalog_id` di permintaan, standar ruangan, dan stok dikosongkan (FK `ON DELETE SET NULL`), lalu katalog dihapus. Popup hapus menampilkan pemakaian: "Item ini dipakai di N permintaan, N standar ruangan, N stok. Tautannya akan dilepas, riwayat tetap tersimpan." Standar ruangan yang tautannya dilepas menjadi tanpa pemenuhan.
- **Snapshot di permintaan**: saat permintaan dibuat (dan diubah selama `Diajukan`) `lead_time_days` disalin dari katalog ke permintaan (tanpa katalog: kosong). **Pengembalian barang** dipilih wajib di form permintaan: "Tidak dikembalikan" atau "Dikembalikan", default mengikuti "perlu dikembalikan" katalog (di luar katalog: "Tidak dikembalikan"), dan pilihan ini yang disimpan ke `must_return`. Riwayat, "Belum direspons", dan aturan `Dikembalikan` memakai snapshot ini sehingga tidak berubah saat katalog diedit atau dihapus.
- **Riwayat permintaan** (proses permintaan tetap di luar sistem, staf hanya mencatat): tanggal, item, jumlah, untuk kegiatan, ruangan terkait (opsional), unit penyedia, **cara permintaan** (wajib per permintaan sebagai log jalur pengiriman; pilih Memo, Form, Email, WhatsApp, atau Lainnya. Lainnya memunculkan isian teks, misal Telegram, dan teks itu yang disimpan; nilai yang pernah diketik tidak muncul sebagai pilihan; permintaan sebelum aturan ini tampil "-"), status, catatan.
- **Status**: `Diajukan` → `Diterima` → `Dikembalikan` (hanya jika dipilih "Dikembalikan" di form permintaan); atau `Dibatalkan`. Tampilan **"Belum direspons"** = `Diajukan` yang melewati snapshot lead time (dihitung, bukan disimpan; tanpa lead time tidak pernah).
- **Status mengubah stok & kelengkapan** (permintaan yang tertaut barang stok lewat `consumable_id` atau baris Barang unit lain lewat `room_standard_id`, terisi otomatis dari Stok, Kelengkapan Ruangan, atau saran; item yang diganti di form melepas tautan; nama item memakai nama barang). Diterima dianggap penuh sesuai jumlah permintaan, semua dalam satu transaksi:
  - **Diterima**: barang stok → stok bertambah (lalu **Isi dari stok** ke ruangan); barang unit lain → Ada baris standar yang ditautkan bertambah (boleh melebihi standar). Tujuan dicatat di `applied_to` & `room_standard_id`.
  - **Dikembalikan** (hanya jika perlu dikembalikan) atau **Dibatalkan** setelah Diterima: mengurangi tujuan yang tercatat, tidak sampai di bawah 0; jika berhenti di 0 staf diminta mengecek manual. Jika barang/standarnya sudah dihapus, tidak ada yang dikurangi.
  - **Dibatalkan** dari Diajukan dan permintaan tanpa tautan: tidak mengubah apa pun.
  - Sebelum menyimpan, frontend menampilkan dampaknya (`GET /supply-requests/{id}/status-impact?status=`), misal "Ada Tisu di Ruang Kelas 1 akan bertambah 2" atau "Stok Pulpen akan bertambah 5". Dampak dimuat saat menu aksi baris dibuka dan disimpan sampai data permintaan/stok berubah; jika belum tersedia, popup menampilkan "Memeriksa dampak…". Backend tetap menghitung ulang saat disimpan.
- **Kolom "Untuk kegiatan"** di riwayat: kegiatan beserta ruangan terkait, misal "Pelatihan di Ruang Kelas 1 (Gedung IHS)".
- **Daftar saran permintaan** (pengingat, dikelompokkan per unit penyedia; menampilkan nama barang sebenarnya dengan keterangan katalog & alasannya, misal "Pensil 2B" / "ATK Isi Ulang · Stok menipis"; penanda "sudah ada permintaan Diajukan" dicocokkan per barang):
  - kekurangan kelengkapan ruangan pada Barang unit lain atau barang stok yang stoknya 0, ke penyedianya (baris Belum dicek dan Perlu dilengkapi tidak termasuk);
  - stok habis pakai yang Menipis/Habis, ke unit di kolom "minta ke"; barang yang sudah muncul dari kekurangan ruangan tidak diulang.
  Dari saran, staf bisa langsung **Buat permintaan** (form terisi otomatis).

## 10. Stok Habis Pakai
- Hanya barang yang **disimpan SHD** (pensil, tinta, solasi, dll.). Barang yang tidak disimpan SHD (misal tisu dari SGA) dicatat sebagai baris **Barang unit lain** di Kelengkapan Ruangan (§8), tidak masuk stok.
- **Permintaan dari stok**: jika "minta ke" bukan SHD dan status Menipis/Habis, kolom Departemen penyedia menampilkan tautan **Ajukan permintaan**; aksi yang sama ada di menu untuk semua barang dengan penyedia unit lain. Form Buat permintaan terisi item, departemen, katalog (jika ada), dan jumlah = minimum − jumlah (minimal 1), tertaut ke barang. Jika sudah ada permintaan Diajukan, tautan berganti **Diajukan · tanggal** yang membuka permintaan itu di riwayat (`?id=`). Jumlah stok berubah mengikuti status permintaan (§9); +/− dan set jumlah tetap tersedia.
- Field: nama (**unik tanpa membedakan huruf besar/kecil**, unique index `lower(name)`), kategori (teks bebas), satuan, jumlah, minimum, **minta ke** (unit penyedia untuk isi ulang), katalog permintaan (opsional, harus dari unit yang sama dengan "minta ke"), **lokasi simpan** (teks bebas, opsional, tidak tertaut ruangan; saran dari nilai yang pernah diinput), catatan. Kolom tabel: Nama, Kategori, Jumlah, Minimum, Status, Lokasi simpan, Departemen penyedia.
- Jumlah diubah langsung oleh staf (tanpa catatan masuk/keluar) lewat **ubah jumlah**: salah satu dari `quantity` (set langsung, ≥ 0) atau `adjustment` (tambah/kurang, ≠ 0); keduanya/tidak satu pun ditolak. Dalam transaksi + lock, hasil tidak boleh negatif ("Stok tidak cukup (tersisa N)."). Ubah data biasa tidak mengubah jumlah. Jumlah juga berkurang otomatis lewat **Isi dari stok** di Kelengkapan Ruangan.
- Status (dihitung, tidak disimpan; `ConsumableStatus` + scope `withStatus` dipakai ulang dashboard & saran permintaan): `Aman` (> minimum), `Menipis` (≤ minimum dan > 0), `Habis` (0).
- Menipis/Habis → **peringatan di dashboard** dan **masuk daftar saran permintaan** ke unit "minta ke".
- **Hapus = hard delete permanen** (pengecualian di CLAUDE.md). Jika barang dipakai di standar ruangan (tipe Stok Habis Pakai), hapus tetap diizinkan: tautan `consumable_id` di standar terkait dikosongkan dalam transaksi yang sama (FK `ON DELETE SET NULL`), baris standar, jumlah standar, dan "Ada" tetap utuh; respons memuat daftar ruangan terdampak. Frontend memanggil `GET /consumables/{id}/usage` lebih dulu untuk peringatan "Hapus permanen. Barang ini dipakai di standar: [daftar ruangan]. Tautannya akan dilepas."; detail stok menampilkan "Dipakai di ruangan: ...".
- Barang habis pakai **tidak dihitung** dalam total aset.

## 11. Dashboard
Kartu:
- Total aset tercatat (rincian milik SHD · dari unit lain), **tidak menghitung** aset `Tidak Layak Pakai`; jumlah Tidak Layak Pakai ditampilkan terpisah bila perlu
- Sedang dipinjam (jatuh tempo hari ini, terlambat)
- Perlu perbaikan (jumlah aset berstatus `Perbaikan`)
- Ruangan belum lengkap (dari N ruangan di M gedung; hanya baris Kurang, Belum dicek tidak dihitung)
- Perlu servis (lewat/mendekati tanggal)
- Stok habis pakai menipis

Panel: peminjaman jatuh tempo/terlambat, kesiapan ruangan per gedung, permintaan terbaru beserta status, aset perlu servis, stok menipis.

## 12. Ekspor laporan (Excel)
Laporan **hanya dalam format Excel (.xlsx)**; tidak ada PDF untuk laporan (PDF hanya untuk label QR, §6). Filter sama persis dengan daftar di layar (scope `filter()` model yang sama); tanpa paginasi. Baris atas file memuat judul, ringkasan filter ("Tanpa filter" jika kosong), tanggal cetak, dan nama staf; header tebal, kolom berlebar tetap, tanggal bertipe tanggal, header dibekukan. Teks selalu ditulis sebagai teks (data yang diawali `=` tidak menjadi rumus).
- **Laporan Aset**: filter Daftar Aset (§5; Tidak Layak Pakai ikut tampil). Kolom: kode, nama, kategori, jenis, merek, no. seri, sumber, gedung & ruangan asal, PIC, kondisi, status, tgl perolehan, servis berikutnya, kode aset keuangan.
- **Laporan Peminjaman**: `from`/`to` (tanggal pinjam; opsional, kosong = "Semua periode"), `status`, `overdue`, `due_today`, `asset_id`, `search`. Kolom: aset, peminjam, unit, keperluan, tujuan, tgl pinjam/rencana kembali/kembali, status, terlambat, kondisi keluar/kembali, catatan.
- **Laporan Kelengkapan Ruangan**: `building_id` (kosong = semua ruangan). Dua sheet: **Ringkasan** (per ruangan: total item, kurang, belum dicek, kesiapan "Lengkap"/"Kurang N item"/"Belum ada standar", terakhir dicek & oleh) dan **Detail** (per item: sumber, metode, standar, ada, selisih, hasil termasuk Belum dicek, pemenuhan). Ruangan tanpa standar tetap tampil di Ringkasan.
- **Laporan Permintaan**: `from`/`to` (tanggal permintaan; opsional), `status`, `unresponded`, `provider_department_id`, `room_id`, `search`. Status memakai tampilan "Belum direspons" (§9).
- **Laporan Stok Habis Pakai**: filter daftar stok (§10): `status`, `category`, `provider_department_id`, `search`.

## 13. Skema database
Lihat `docs/erd.dbml` (sumber kebenaran skema). Ringkasan tabel:
`users`, `departments`, `buildings`, `rooms`, `categories`, `asset_types`, `assets`, `asset_files`, `asset_events`, `loans`, `room_standards`, `request_catalog`, `supply_requests`, `consumables` (+ `personal_access_tokens`, `migrations`).

## 14. Kontrak API (prefix `/api`, Bearer token kecuali login)
**Auth**: `POST /login` (maks. 5 percobaan/menit per kombinasi username + IP; lebih → 429 "Terlalu banyak percobaan login. Coba lagi dalam N detik." + header `Retry-After`), `POST /logout`, `GET /user`

**Master & lookup**: `GET/POST/PUT/DELETE /departments`, `GET/POST/PUT/DELETE /buildings`, `GET/POST /buildings/{id}/rooms`, `GET /rooms`, `PUT/DELETE /rooms/{id}`, `GET/POST/PUT/DELETE /categories`, `GET/POST/PUT/DELETE /asset-types` (hapus ditolak 422 bila masih dipakai; departemen SHD tidak dapat diubah/dihapus), `GET /{departments|buildings|rooms|categories|asset-types}/{id}/usage` (pemakaian sebelum hapus untuk halaman Data Master: `items` [{label, count}] hanya yang > 0, `total`, `can_delete`, `reason` — daftar tabel sama dengan aturan hapus), `GET /lookups/borrower-units?search=`, `GET /lookups/request-methods?search=`, `GET /lookups/storage-locations?search=` (lokasi simpan yang pernah diinput, untuk saran)

**Publik (tanpa token, rate limit)**: `GET /public/assets/{token}` — data halaman QR (§6)

**Dashboard**: `GET /dashboard/cards`, `GET /dashboard/room-readiness`, `GET /dashboard/panels`

**Daftar (umum)**: endpoint daftar bisnis (`/assets`, `/loans`, `/consumables`, `/supply-requests`) memakai bentuk paginator Laravel (`data`, `links`, `meta`) dengan `page`, `per_page` (≤ 100), `search`, dan `sort` (`kolom` naik / `-kolom` turun; aset `code|name|status|condition|next_service_date`, peminjaman `borrow_date|due_date|borrower_name|status`, stok `name|category|quantity`, permintaan `request_date|item_name|quantity|status`; kolom lain → 422). Tanpa `sort` = urutan bawaan. Data master (`/departments`, `/buildings`, `/rooms`, `/categories`, `/asset-types`, `/request-catalog`) tetap `{data: [...]}` tanpa paginasi (untuk combobox) dan di-cache di server; dashboard, kesiapan ruangan, dan saran permintaan juga di-cache dan dikosongkan otomatis saat data terkait berubah.

**Aset**
| Method | Endpoint | Fungsi |
|---|---|---|
| GET | /assets | Daftar + filter (`category_id`, `building_id`, `room_id`, `source_department_id`, `condition`, `status`, `search` nama/kode/S-N/kode lama) + paginasi (`per_page` ≤ 100); Tidak Layak Pakai ikut tampil. Baris hanya berisi kolom tabel: `id`, `code`, `name`, `category_name`, `room_name`, `building_name`, `source_is_internal`, `condition`, `status`; data lengkap dari `GET /assets/{id}` |
| POST | /assets | Tambah (kode otomatis, status dari kondisi, riwayat Registrasi) |
| GET | /assets/{id} | Detail + peminjaman aktif + lokasi saat ini + kode lama + `room_standard` (`is_standard`, `item_name`, `quantity`: baris standar QR di ruangan asal untuk jenis aset ini; hanya di detail, tidak di daftar) + `meta.actions` (aksi sesuai status, sama seperti scan) |
| GET | /assets/{id}/qr | Gambar PNG QR (isi = URL publik `{QR_BASE_URL}/aset/{public_token}`, ECC M), butuh Bearer token; frontend mengambilnya sebagai blob |
| GET | /assets/{id}/delete-impact | Dampak hapus: `loans_count`, `active_loan`, `events_count`, `files_count`, `affected_room_standards`, `requires_confirm_code` |
| GET | /assets/{id}/code-preview?asset_type_id= | Pratinjau kode baru jika jenis diganti |
| PUT | /assets/{id} | Ubah data (pindah lokasi tercatat; ganti jenis → kode baru, `meta.code_changed`; status/kondisi/kode tidak bisa diubah) |
| PATCH | /assets/{id}/location | Pindah lokasi (`room_id`, `note`) |
| POST | /assets/{id}/report | Catat kerusakan (`type=rusak`, `condition`, `status` jika Rusak Ringan) / kehilangan (`type=hilang`) / tidak layak pakai (`type=tidak_layak_pakai`); `note` wajib, `party` opsional |
| POST | /assets/{id}/found | Tandai ditemukan (khusus Hilang): `condition`, `status` jika Rusak Ringan, `note`, `party` |
| POST | /assets/{id}/services | Catat servis: `service_date`, `vendor`, `condition`, `status` jika Rusak Ringan, `next_service_date`, `note` |
| DELETE | /assets/{id} | Hapus aset permanen (semua status) beserta data turunan; `confirm_code` = kode aset wajib jika punya riwayat pinjam |
| GET | /assets/{id}/events | Riwayat aset, terbaru dulu (paginasi, `per_page` bawaan 20) |
| GET | /assets/{id}/files | Daftar file (`file_type` opsional): slot sesuai urutan jenis, lalu Lainnya terbaru dulu |
| POST | /assets/{id}/files | Upload (`multipart`: `file_type`, `file`); slot terisi diganti (`meta.replaced`), Lainnya menumpuk; 413 jika > 5 MB |
| DELETE | /asset-files/{id} | Hapus file (semua jenis) |
| GET | /asset-files/{id}/download | Stream file (inline; `?download=1` unduh), 404 jika file fisik tidak ada |
| GET | /assets/scan/{value} | Data scan (kode / kode lama / token) + `meta.actions` + `meta.matched_by` |
| GET | /assets/{id}/label | PDF label satu aset (`?download=1` untuk unduh) |
| POST | /assets/labels | PDF label banyak aset (`ids[]`, maks. 120; `?download=1`) |

**Peminjaman**: `GET /loans` (`status`, `overdue`, `due_today`, `from`/`to` tanggal pinjam, `asset_id`, `search` peminjam/unit/kode/nama aset; aktif urut tempo terdekat), `POST /loans`, `GET /loans/{id}`, `PATCH /loans/{id}/return` (`result` kembali|hilang, `return_date`, `return_condition`, `status` jika Rusak Ringan/Berat, `return_room_id`, `note`), `PATCH /loans/{id}/cancel` (`cancel_reason`)

**Kelengkapan**: `GET /room-readiness` (per gedung), `GET /rooms/{id}/checklist`, `POST /rooms/{id}/standards`, `PUT /room-standards/{id}`, `DELETE /room-standards/{id}`, `POST /rooms/{id}/check` (simpan hasil cek), `POST /room-standards/{id}/fill-from-stock` (isi dari stok SHD)

**Permintaan**: `GET/POST/PUT/DELETE /request-catalog`, `GET /request-catalog/{id}/usage` (`{supply_requests, room_standards, consumables, total}`), `GET/POST /supply-requests` (`GET`: `id` untuk satu permintaan; `POST`/`PUT`: `consumable_id` atau `room_standard_id` opsional, `must_return` opsional, default mengikuti katalog), `PUT /supply-requests/{id}`, `GET /supply-requests/{id}/status-impact?status=` (`{message, clamped}`), `PATCH /supply-requests/{id}/status` (+ `meta.impact`), `GET /supply-requests/suggestions` (`POST/PUT /supply-requests` wajib `request_method` teks maks. 50; daftar pilihan tetap di `GET /lookups/request-methods`; `GET /supply-requests` filter: `status`, `unresponded`, `provider_department_id`, `room_id`, `from`/`to`, `search`; edit hanya saat Diajukan; respons permintaan memuat snapshot `lead_time_days` & `must_return`; `DELETE /request-catalog/{id}` selalu boleh dan melepas tautan)

**Stok habis pakai**: `GET /consumables` (`status`, `category`, `provider_department_id`, `search`; baris memuat `storage_location` & `open_request`), `POST /consumables`, `GET /consumables/{id}` (+ `meta.used_in_rooms`), `GET /consumables/{id}/usage`, `PUT /consumables/{id}` (tanpa `quantity`), `PATCH /consumables/{id}/quantity` (`quantity` atau `adjustment`), `DELETE /consumables/{id}` (permanen, `detached_rooms`; baris standar terkait menjadi Perlu dilengkapi)

**Dashboard**: `GET /dashboard/cards` (semua kartu), `GET /dashboard/room-readiness` (panel kesiapan per gedung), `GET /dashboard/panels` (jatuh tempo, permintaan terbaru, perlu servis, stok menipis) (§11; kartu "Ruangan belum lengkap" memakai semua ruangan/gedung sebagai penyebut, ruangan tanpa standar dianggap lengkap; "Perlu perbaikan" = aset Perbaikan; "Stok menipis" = Menipis + Habis)

**Ekspor**: `GET /reports/{type}?...filter` (type: assets, loans, rooms, requests, consumables) → unduhan `laporan-{jenis}-{yyyymmdd}.xlsx`. Tidak ada parameter `format`; jika dikirim hanya `xlsx` yang diterima (selain itu 422). Type tidak dikenal → 404; filter tidak valid → 422.

Kode status: 200/201, 401, 404, 422 (validasi/aturan bisnis), 413 (file terlalu besar).

## 15. Non-fungsional
- Token Sanctum 480 menit, simpan di `localStorage`, 401 → login.
- Login dibatasi 5 percobaan per menit per kombinasi username (tanpa beda huruf besar/kecil) + IP (rate limiter `login`); setiap percobaan dihitung, termasuk yang berhasil. Frontend menampilkan pesan 429 dari server. Halaman publik QR dibatasi 30/menit per IP (§6). `GET /` menjawab `{"app": ..., "status": "ok"}` untuk cek hidup.
- RLS aktif di semua tabel Supabase.
- Semua URL & penyimpanan dari env: `APP_URL`, `FRONTEND_URL`, `QR_BASE_URL`, `FILESYSTEM_DISK`; frontend `VITE_API_URL`.
- `.env.example` lengkap tanpa kredensial; README berisi langkah deploy.
- UI Bahasa Indonesia, responsif.

## 16. Fase 2 — Lisensi Digital
Nama layanan, vendor, jenis lisensi, jumlah seat, pengguna/penanggung jawab, tanggal mulai & berakhir, biaya, status, peringatan mendekati kedaluwarsa.
