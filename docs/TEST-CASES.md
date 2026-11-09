# Test Case Manual — Asset Tracking System SHD

Daftar uji manual per fitur dan per alur, untuk dicoba langsung di aplikasi (bahan T27 QA & demo). Acuan aturan: `docs/SPEC.md`.

Cara pakai: kerjakan berurutan dari atas (beberapa test memakai data dari test sebelumnya). Centang `[x]` jika hasil sesuai; jika tidak, tulis temuan di bagian **Catatan hasil uji** paling bawah beserta ID test-nya.

---

## 0. Persiapan

- Backend: `cd backend && php artisan migrate` (pastikan semua migration sudah jalan), lalu `php artisan serve` → http://localhost:8000
- Frontend: `cd frontend && npm run dev` → http://localhost:5173
- Data demo bersih (opsional, menghapus semua data): jalankan sendiri `php artisan migrate:fresh --seed`.
- Akun demo: username `staf1`, password `inalum2026`.
- Siapkan: 1 file JPG/PNG < 5 MB, 1 file PDF < 5 MB, 1 file > 5 MB, webcam (untuk scan QR).
- Nama ruangan di bawah memakai data demo (Ruang Kelas 1, Ruang Kelas 2, Gudang IHS, dst.). Jika nama di database kamu sudah diganti (mis. Ruang Akasia), pakai ruangan yang setara.

Konvensi: **Langkah** → **Hasil yang diharapkan**.

---

## 1. Login & sesi

- [ ] **TC-AUTH-01 Login berhasil** — Buka `/login`, isi `staf1` / `inalum2026`, klik Masuk → Masuk ke Dashboard, nama staf tampil di sidebar.
- [ ] **TC-AUTH-02 Login gagal** — Isi password salah → Pesan error Bahasa Indonesia, tetap di halaman login.
- [ ] **TC-AUTH-03 Halaman butuh login** — Logout, lalu buka `/daftar-aset` langsung → Diarahkan ke `/login`.
- [ ] **TC-AUTH-04 Logout** — Klik Keluar di sidebar → Kembali ke login; tombol Back browser tidak membuka halaman staf lagi.

## 2. Dashboard

- [ ] **TC-DSH-01 Kartu ringkasan** — Buka Dashboard → Tampil kartu Total aset (rincian milik SHD/departemen lain), Sedang dipinjam, Perlu perbaikan, Ruangan belum lengkap, Perlu servis, Stok menipis.
- [ ] **TC-DSH-02 Tidak Layak Pakai tidak dihitung** — Catat satu aset Tidak Layak Pakai (TC-AST-17), kembali ke Dashboard → Total aset berkurang 1.
- [ ] **TC-DSH-03 Gudang tidak dihitung kesiapan** — Bandingkan kartu Ruangan belum lengkap dengan jumlah ruangan → Ruangan bertanda gudang tidak ikut dihitung.
- [ ] **TC-DSH-04 Panel** — Cek panel peminjaman jatuh tempo/terlambat, kesiapan ruangan per gedung, permintaan terbaru, aset perlu servis, stok menipis → Isinya sesuai data di halaman masing-masing; tautan membuka halaman terkait.

## 3. Data Master (`Pengaturan → Data Master`)

### Gedung & Ruangan
- [ ] **TC-MST-01 Tambah gedung & ruangan** — Tab Gedung & Ruangan, tambah gedung "Gedung Uji", lalu ruangan "Ruang Uji" → Muncul di daftar; juga muncul sebagai chip di Kelengkapan Ruangan.
- [ ] **TC-MST-02 Nama ganda ditolak** — Tambah ruangan "ruang uji" (huruf kecil) di gedung yang sama → Ditolak (nama sudah ada); di gedung lain boleh.
- [ ] **TC-MST-03 Tanda gudang** — Tambah ruangan dengan centang Gudang → Label Gudang tampil; di Kelengkapan Ruangan chip-nya berikon gudang dan ada di urutan akhir.
- [ ] **TC-MST-04 Tanda gudang tidak bisa dilepas jika dipakai stok** — Ubah Gudang IHS, hilangkan centang Gudang → Ditolak dengan pesan jumlah barang yang tersimpan di sana.
- [ ] **TC-MST-05 Ganti nama → URL ikut berubah** — Ganti nama "Ruang Uji" jadi "Ruang Uji Baru", buka Kelengkapan Ruangan dan pilih ruangan itu → URL memakai `ruang-uji-baru`. Tautan lama `.../ruang-uji` diarahkan ke ruangan pertama.
- [ ] **TC-MST-06 Hapus ruangan yang dipakai** — Hapus ruangan yang punya aset → Popup menampilkan pemakaian; hapus ditolak. Hapus ruangan kosong → berhasil.

### Departemen
- [ ] **TC-MST-07 Tambah/ubah departemen** — Tambah "Dept Uji", ubah namanya → Tersimpan; muncul di pilihan sumber aset & penyedia stok.
- [ ] **TC-MST-08 Hapus departemen terpakai** — Hapus SGA → Ditolak, popup menyebut pemakaian (aset/stok/permintaan).

### Kategori & Jenis aset
- [ ] **TC-MST-09 Tambah kategori & jenis** — Tambah kategori "Uji", lalu jenis "Kamera" di kategori itu → Kode jenis otomatis 3 huruf (mis. KMR).
- [ ] **TC-MST-10 Ubah kode jenis** — Ubah kode Kamera jadi "CAM" → Aset baru jenis Kamera berkode `SHD-CAM-001`; aset lama tidak berubah.
- [ ] **TC-MST-11 Kode jenis pernah dipakai** — Ubah kode jenis lain menjadi "PRJ" → Ditolak "Kode pernah dipakai jenis lain."
- [ ] **TC-MST-12 Hapus kategori terpakai** — Hapus kategori yang masih punya jenis → Ditolak (422).

### Kategori stok
- [ ] **TC-MST-13 Kategori stok** — Tambah/ubah kategori stok; hapus yang masih dipakai barang → Tambah/ubah berhasil, hapus terpakai ditolak.

## 4. Daftar Aset & Detail Aset

### Tambah & ubah
- [ ] **TC-AST-01 Tambah aset** — Daftar Aset → Tambah aset; pilih jenis Proyektor, isi nama, merek, sumber SHD, gedung & ruangan, tanggal perolehan, kondisi Baik → Tersimpan, kode `SHD-PRJ-NNN` (nomor berikutnya), status Tersedia, riwayat "Registrasi".
- [ ] **TC-AST-02 Pratinjau kode** — Saat memilih jenis di form → Tampil "Kode otomatis: SHD-…" di bawah nama aset.
- [ ] **TC-AST-03 Kategori otomatis** — Pilih jenis → Kategori tampil otomatis (hanya baca).
- [ ] **TC-AST-04 Tambah jenis dari form** — Ketik nama jenis baru "Drone" di pilihan jenis → Muncul `+ Tambah jenis 'Drone'`; popup minta kategori; setelah simpan jenis langsung terpilih.
- [ ] **TC-AST-05 Kondisi Rusak Berat saat tambah** — Tambah aset dengan kondisi Rusak Berat → Status otomatis Perbaikan.
- [ ] **TC-AST-06 Kondisi Rusak Ringan** — Pilih Rusak Ringan → Staf bisa memilih status Tersedia (default) atau Perbaikan.
- [ ] **TC-AST-07 Ubah data** — Ubah merek & catatan aset → Riwayat "Ubah Data" berisi daftar perubahan. Status & kondisi tidak bisa diubah dari form ini.
- [ ] **TC-AST-08 Ganti jenis** — Ubah jenis aset dari Proyektor ke Laptop → Peringatan kode baru sebelum simpan; kode berganti `SHD-LPT-…`; riwayat mencatat "Kode: lama → baru".
- [ ] **TC-AST-09 Cari pakai kode lama** — Cari kode lama dari TC-AST-08 di Daftar Aset / Scan → Aset tetap ditemukan (dengan peringatan kode lama di hasil scan).

### Daftar & filter
- [ ] **TC-AST-10 Filter & cari** — Coba Cari (nama/kode/nomor seri), filter Kategori, Gedung, Ruangan, Status → Hasil sesuai filter; kolom Kode, Nama, Kategori, Lokasi, Sumber, Kondisi, Status.
- [ ] **TC-AST-11 Ekspor Excel daftar aset** — Dengan filter aktif, klik Ekspor Excel → File .xlsx berisi baris sesuai filter, ringkasan filter di atas.

### Foto & dokumen
- [ ] **TC-AST-12 Unggah foto & PDF** — Di detail aset unggah Foto Aset (JPG) dan BAST (PDF) → Tampil; bisa dibuka/diunduh.
- [ ] **TC-AST-13 Slot tunggal diganti** — Unggah Foto Aset lagi → Foto lama diganti (bukan bertambah).
- [ ] **TC-AST-14 Lainnya menumpuk** — Unggah 2 file jenis Lainnya → Keduanya tampil dengan nama asli.
- [ ] **TC-AST-15 File terlalu besar** — Unggah file > 5 MB → Ditolak "Ukuran file maksimal 5 MB."
- [ ] **TC-AST-16 Hapus file** — Hapus satu file → Hilang dari daftar; riwayat aset tidak bertambah.

### Aksi status
- [ ] **TC-AST-17 Catat tidak layak pakai** — Aset Tersedia → Catat tidak layak pakai, isi catatan → Status Tidak Layak Pakai; aksi lain hilang kecuali Pindah lokasi & Hapus.
- [ ] **TC-AST-18 Catat kerusakan** — Aset Tersedia → Catat kerusakan, Rusak Berat + catatan; kolom Pihak pelapor boleh kosong → Status Perbaikan, riwayat "Lapor Rusak" dengan pihak SHD.
- [ ] **TC-AST-19 Catat kerusakan aset Dipinjam** — Aset Dipinjam → Catat kerusakan Rusak Ringan → Hanya kondisi berubah, status tetap Dipinjam.
- [ ] **TC-AST-20 Catat kehilangan** — Aset Tersedia → Catat kehilangan + catatan → Status Hilang, riwayat "Lapor Hilang".
- [ ] **TC-AST-21 Kehilangan aset Dipinjam ditolak** — Aset Dipinjam → Catat kehilangan → Ditolak "Aset sedang dipinjam. Selesaikan lewat peminjaman."
- [ ] **TC-AST-22 Tandai ditemukan** — Aset Hilang → Tandai ditemukan, kondisi Baik → Status Tersedia (tidak langsung Dipinjam); muncul tawaran "Langsung pinjamkan".
- [ ] **TC-AST-23 Servis** — Aset Perbaikan → Catat servis (tanggal ≤ hari ini, vendor, kondisi Baik, servis berikutnya > tanggal servis) → Status Tersedia, riwayat "Servis" dengan pihak = vendor.
- [ ] **TC-AST-24 Servis ditolak** — Coba servis aset Dipinjam/Hilang → Ditolak.
- [ ] **TC-AST-25 Pindah lokasi** — Pindah aset ke ruangan lain → Lokasi berubah, riwayat "Pindah Lokasi". Pindah ke ruangan yang sama → ditolak.

### Hapus aset
- [ ] **TC-AST-26 Hapus aset tanpa riwayat pinjam** — Hapus aset baru dari TC-AST-01 → Popup dampak tampil; terhapus tanpa kode konfirmasi.
- [ ] **TC-AST-27 Hapus aset dengan riwayat pinjam** — Hapus aset yang pernah dipinjam → Wajib ketik kode aset; kode salah → "Kode konfirmasi tidak cocok."; kode benar → terhapus beserta riwayatnya. Nomor kodenya tidak dipakai ulang untuk aset baru.

## 5. Scan QR & Label

- [ ] **TC-QR-01 Cetak label satu aset** — Detail aset → Cetak label → PDF A4, QR 5×5 cm + kode di sel pertama.
- [ ] **TC-QR-02 Cetak banyak label** — Daftar Aset, pilih beberapa aset → Cetak label → 12 label per halaman, urut kode.
- [ ] **TC-QR-03 Scan kamera** — Menu Scan, arahkan label ke webcam → Hasil scan: nama, kode, jenis, lokasi, kondisi, status, dan tombol aksi sesuai status.
- [ ] **TC-QR-04 Scan ketik kode** — Ketik kode aset di halaman Scan → Hasil sama dengan TC-QR-03; kode tidak dikenal → "Aset tidak ditemukan."
- [ ] **TC-QR-05 Aksi per status** — Scan aset Tersedia/Dipinjam/Perbaikan/Hilang/Tidak Layak Pakai → Tombol aksi sesuai SPEC §6 (mis. Hilang hanya Tandai ditemukan & Pindah lokasi).
- [ ] **TC-QR-06 Halaman publik tanpa login** — Logout, buka URL QR `/aset/{token}` → Data aset tampil tanpa file & tanpa data peminjam; tombol aksi berlabel "Khusus staf SHD".
- [ ] **TC-QR-07 Aksi dari halaman publik** — Dari halaman publik klik Pinjam saat belum login → Diarahkan login, lalu otomatis ke form pinjam dengan aset terisi.
- [ ] **TC-QR-08 Terima kembali dari scan** — Scan aset Dipinjam → Terima kembali → Modal terima kembali langsung terbuka untuk peminjaman aktifnya.

## 6. Peminjaman

- [ ] **TC-LN-01 Catat peminjaman** — Peminjaman → + Catat peminjaman; pilih aset Tersedia, isi peminjam, departemen, keperluan, lokasi tujuan, rencana kembali → Tersimpan; status aset Dipinjam; muncul di tabel Sedang dipinjam; riwayat aset "Dipinjam".
- [ ] **TC-LN-02 Tanggal pinjam masa depan ditolak** — Isi tanggal pinjam besok (ketik manual jika picker membatasi) → Ditolak "Tanggal pinjam tidak boleh di masa depan."
- [ ] **TC-LN-03 Tanggal pinjam lampau** — Isi tanggal pinjam 3 hari lalu, rencana kembali kemarin → Tersimpan dan tampil Terlambat.
- [ ] **TC-LN-04 Tanggal sebelum aset terdaftar** — Isi tanggal pinjam sebelum tanggal registrasi aset → Ditolak.
- [ ] **TC-LN-05 Rencana kembali sebelum tanggal pinjam** — Rencana kembali < tanggal pinjam → Ditolak.
- [ ] **TC-LN-06 Aset tidak Tersedia** — Coba pinjam aset Perbaikan/Hilang/Dipinjam → Tidak bisa dipilih atau ditolak.
- [ ] **TC-LN-07 Kondisi keluar otomatis** — Pinjam aset Rusak Ringan (status Tersedia) → Kondisi saat keluar terisi Rusak Ringan otomatis.
- [ ] **TC-LN-08 Terima kembali Baik** — Terima kembali, kondisi Baik → Status Tersedia; riwayat peminjaman Dikembalikan.
- [ ] **TC-LN-09 Terima kembali Rusak Ringan** — Kondisi Rusak Ringan, catatan wajib, pilih Perbaikan → Status Perbaikan; riwayat menampilkan kondisi kembali.
- [ ] **TC-LN-10 Terima kembali Rusak Berat → Tidak Layak Pakai** — Kondisi Rusak Berat, pilih Tidak Layak Pakai → Status Tidak Layak Pakai + riwayat "Tidak Layak Pakai".
- [ ] **TC-LN-11 Ruangan penyimpanan berbeda** — Saat terima kembali pilih ruangan lain → Lokasi asal ikut pindah, riwayat "Pindah Lokasi".
- [ ] **TC-LN-12 Hilang saat dipinjam** — Terima kembali dengan hasil Hilang + catatan → Status aset Hilang; detail aset menampilkan "Hilang saat dipinjam oleh …"; halaman publik tidak menampilkan peminjam.
- [ ] **TC-LN-13 Batalkan peminjaman** — Menu Batalkan, isi alasan → Status aset kembali sesuai kondisi; riwayat "Dibatalkan".
- [ ] **TC-LN-14 Tanggal kembali** — Isi tanggal kembali besok atau sebelum tanggal pinjam → Ditolak dengan pesan yang sesuai.
- [ ] **TC-LN-15 Ekspor peminjaman** — Ekspor Excel dengan filter periode/status → File berisi baris sesuai filter.

## 7. Kelengkapan Ruangan

### Tampilan & navigasi
- [ ] **TC-RM-01 Pilih gedung & ruangan** — Klik chip gedung lalu ruangan → URL `/kelengkapan-ruangan/{gedung}/{ruangan}`; lencana merah −N pada ruangan yang kurang.
- [ ] **TC-RM-02 Tambah gedung/ruangan dari halaman** — Klik + di chip gedung/ruangan → Tersimpan dan langsung terpilih.
- [ ] **TC-RM-03 Kotak kekurangan** — Ruangan dengan item Kurang → Kotak peringatan oranye "… kurang N item".

### Aset di ruangan
- [ ] **TC-RM-04 Aset otomatis per jenis** — Ruangan dengan aset → Tabel Jenis aset (kode bisa diklik), Standar, Jumlah, Selisih, Hasil, Kondisi.
- [ ] **TC-RM-05 Kondisi** — Jenis dengan satu aset → Lencana kondisi; jenis dengan banyak aset → "3 Baik", "1 Rusak Ringan"; hover menampilkan kodenya.
- [ ] **TC-RM-06 Aset dipinjam tidak dihitung** — Pinjamkan satu aset di ruangan → Jumlah turun 1, keterangan "1 sedang dipinjam" di bawah nama jenis.
- [ ] **TC-RM-07 Tambah standar jenis** — + Tambah standar jenis, pilih jenis, jumlah 2 → Baris dengan Standar 2 dan Hasil sesuai. Jenis yang sudah punya standar tidak muncul di pilihan; jenis yang ada asetnya tapi standar 0 tetap muncul.
- [ ] **TC-RM-08 Ubah standar jenis** — Ikon pensil → ubah jumlah → Tersimpan.
- [ ] **TC-RM-09 Hapus standar jenis** — Ikon hapus pada jenis yang punya standar → Konfirmasi; standar kembali 0 (baris tetap jika ada aset, tanpa Selisih/Hasil). Jenis standar 0 tidak punya ikon hapus.

### Barang habis pakai
- [ ] **TC-RM-10 Tambah barang** — + Tambah barang, pilih barang dari Stok, standar 3 → Baris baru, Jumlah = catatan (0 jika belum pernah ada), Hasil Belum dicek, tidak dihitung kurang.
- [ ] **TC-RM-11 Catat perubahan pertama (Saldo awal)** — Catat perubahan, jumlah sekarang 5 → Teks "dicatat sebagai saldo awal"; Simpan → Jumlah 5, Hasil dihitung; Kartu ruangan: Saldo awal +5.
- [ ] **TC-RM-12 Berkurang dengan alasan** — Catat perubahan 5 → 3, alasan Terpakai → Kartu ruangan Terpakai −2; di bawah nama barang "Terpakai 2 … pada cek {tanggal}".
- [ ] **TC-RM-13 Alasan Hilang/Rusak/Kedaluwarsa** — Ulangi dengan alasan Hilang, lalu Rusak, lalu Kedaluwarsa → Tercatat sesuai jenisnya; "Terpakai terakhir" tidak berubah oleh Hilang/Rusak/Kedaluwarsa.
- [ ] **TC-RM-14 Bertambah wajib keterangan** — Catat perubahan menambah jumlah → Keterangan wajib; tanpa keterangan tombol Simpan nonaktif; dengan keterangan → tercatat Koreksi.
- [ ] **TC-RM-15 Jumlah sama** — Buka Catat perubahan baris yang sudah dicek tanpa mengubah angka → Tombol Simpan nonaktif.
- [ ] **TC-RM-16 Konfirmasi jumlah** — Baris Belum dicek, buka popup tanpa mengubah angka → Tombol "Konfirmasi jumlah"; simpan → Hasil tidak lagi Belum dicek, Kartu ruangan tidak bertambah.
- [ ] **TC-RM-17 Isi dari stok** — Baris Kurang dengan stok > 0 → Isi dari stok, jumlah default = kekurangan → Jumlah ruangan naik; kartu ruangan "Diisi"; kartu stok "Pemakaian" dengan ruangan tujuan; stok berkurang.
- [ ] **TC-RM-18 Isi melebihi standar** — Isi lebih dari kekurangan → Peringatan "Melebihi standar ruangan", tetap bisa disimpan.
- [ ] **TC-RM-19 Isi melebihi stok** — Isi lebih dari sisa stok → Ditolak "Stok tidak cukup (tersisa N)."
- [ ] **TC-RM-20 Stok kurang dari kekurangan** — Kurang 5, stok 3 → Popup menampilkan sisa kekurangan + tautan Ajukan permintaan; setelah diisi masih Kurang 2.
- [ ] **TC-RM-21 Stok habis → Ajukan permintaan** — Baris Kurang dengan stok 0 → Aksi "Ajukan permintaan" membuka form permintaan terisi barang, departemen penyedia, jumlah kekurangan, ruangan.
- [ ] **TC-RM-22 Isi dari stok barang Belum dicek** — Baris Belum dicek → Tombol Isi dari stok tidak muncul (API menolak "Barang belum dicek…").
- [ ] **TC-RM-23 Lepas barang** — Ikon hapus → Popup menyebut riwayat tetap tersimpan; baris hilang, stok tidak berubah.
- [ ] **TC-RM-24 Tambah lagi setelah dilepas** — Tambahkan barang yang sama lagi → Jumlah = catatan terakhir, Hasil Belum dicek; Kartu ruangan masih menampilkan riwayat lama.
- [ ] **TC-RM-25 Ganti barang pada baris** — Pensil → ganti barang → Jumlah mengikuti catatan barang baru di ruangan itu, Belum dicek.

### Tandai sudah dicek & status ruangan
- [ ] **TC-RM-26 Tandai sudah dicek (semua sesuai)** — Ruangan tanpa Kurang/Belum dicek → Dialog konfirmasi singkat; setelah simpan header "Dicek {waktu} oleh Staf SHD".
- [ ] **TC-RM-27 Tandai sudah dicek dengan ringkasan** — Ruangan dengan aset Kurang dan barang Belum dicek → Dialog menampilkan "Ada N aset Kurang. Aset hilang dicatat lewat detail aset." dan "N barang belum dicek akan dianggap sesuai catatan."; setelah simpan Belum dicek hilang, jumlah barang tidak berubah, tidak ada transaksi baru.
- [ ] **TC-RM-28 Status ruangan hanya dari tombol** — Catat perubahan beberapa barang → Teks "Dicek … oleh …" di header tidak berubah sampai Tandai sudah dicek ditekan.
- [ ] **TC-RM-29 Ruangan hanya aset** — Ruangan tanpa barang habis pakai → Tandai sudah dicek tetap bisa.

### Gudang
- [ ] **TC-RM-30 Tampilan gudang** — Pilih ruangan gudang → Header badge "Gudang" + "Gudang tidak dicek terhadap standar"; hanya tabel Aset tersimpan & Stok tersimpan (berdampingan di layar lebar); tanpa tombol Tandai sudah dicek.
- [ ] **TC-RM-31 Stok tersimpan** — Status Aman/Menipis/Habis sesuai jumlah vs minimum barang di halaman Stok.

### Lainnya
- [ ] **TC-RM-32 Hapus ruangan dengan riwayat barang** — Lepas semua barang dari ruangan yang punya riwayat, coba hapus ruangan di Data Master → Ditolak (riwayat barang ruangan).
- [ ] **TC-RM-33 Ekspor kelengkapan** — Ekspor Excel per gedung → Sheet Ringkasan & Detail; gudang berlabel "Gudang".
- [ ] **TC-RM-34 Tampilan HP** — Perkecil browser ke lebar HP → Tabel jadi kartu; chip bisa digeser; tidak ada scroll horizontal halaman.

## 8. Permintaan ke Departemen Lain

- [ ] **TC-RQ-01 Buat permintaan dari katalog** — Permintaan → Buat permintaan, pilih item katalog → Departemen & pilihan pengembalian terisi dari katalog; cara permintaan wajib.
- [ ] **TC-RQ-02 Cara permintaan Lainnya** — Pilih Lainnya, ketik "Telegram" → Tersimpan "Telegram".
- [ ] **TC-RQ-03 Permintaan dari stok** — Halaman Stok, barang Menipis dengan penyedia bukan SHD → Ajukan permintaan → Form terisi barang, departemen, jumlah = minimum − jumlah; tertaut ke barang.
- [ ] **TC-RQ-04 Penanda permintaan terbuka** — Setelah TC-RQ-03 → Di Stok tautan berganti "Diajukan · tanggal" yang membuka permintaan itu.
- [ ] **TC-RQ-05 Pratinjau dampak status** — Menu aksi → Catat barang diterima → Popup menampilkan dampak, mis. "Stok … akan bertambah N".
- [ ] **TC-RQ-06 Diterima penuh** — Terima semua jumlah → Status Diterima; stok bertambah lewat Barang Masuk (cek kartu stok).
- [ ] **TC-RQ-07 Diterima sebagian** — Terima 3 dari 5 → Status Diterima sebagian ("diterima 3 dari 5"); bisa diterima lagi untuk sisanya.
- [ ] **TC-RQ-08 Batalkan sisa** — Dari Diterima sebagian → Batalkan sisa → Stok yang sudah masuk tetap.
- [ ] **TC-RQ-09 Dikembalikan** — Permintaan "Dikembalikan" yang sudah Diterima → Tandai Dikembalikan → Stok berkurang lewat Koreksi.
- [ ] **TC-RQ-10 Dibatalkan setelah Diterima** — Tandai Dibatalkan → Stok berkurang (tidak sampai negatif).
- [ ] **TC-RQ-11 Barang tidak masuk ke ruangan** — Permintaan dengan ruangan terkait Diterima → Stok gudang bertambah; jumlah di Kelengkapan Ruangan tidak berubah (isi lewat Isi dari stok).
- [ ] **TC-RQ-12 Belum direspons** — Permintaan Diajukan dari katalog yang melewati lead time → Tampil "Belum direspons".
- [ ] **TC-RQ-13 Saran permintaan** — Daftar saran → Hanya barang stok Menipis/Habis, dikelompokkan per departemen; tombol Buat permintaan.
- [ ] **TC-RQ-14 Katalog** — Tambah/ubah/hapus item katalog; hapus item yang dipakai → Popup menyebut jumlah permintaan; setelah hapus, riwayat permintaan tetap ada.
- [ ] **TC-RQ-15 Ekspor permintaan** — Ekspor Excel dengan filter → File sesuai filter.

## 9. Stok Habis Pakai

- [ ] **TC-STK-01 Tambah barang** — Stok → Tambah barang (nama, kategori, satuan, jumlah awal 10, minimum 3, minta ke, lokasi simpan gudang) → Tersimpan; kartu stok berisi "Saldo awal".
- [ ] **TC-STK-02 Lokasi simpan harus gudang** — Pilih ruangan bukan gudang → Ditolak "Lokasi simpan harus ruangan gudang."
- [ ] **TC-STK-03 Nama ganda** — Tambah barang dengan nama sama beda huruf besar/kecil → Ditolak.
- [ ] **TC-STK-04 Barang Masuk** — Catat transaksi Barang Masuk 5 → Jumlah +5, kartu stok bertambah.
- [ ] **TC-STK-05 Pemakaian** — Catat Pemakaian 2 dengan ruangan tujuan → Jumlah −2; ada petunjuk "Untuk mengisi ruangan, gunakan Isi dari stok di Kelengkapan Ruangan."; jumlah di Kelengkapan Ruangan tidak berubah.
- [ ] **TC-STK-06 Koreksi** — Catat Koreksi tanpa keterangan → Keterangan wajib; dengan keterangan → tersimpan.
- [ ] **TC-STK-07 Penghapusan** — Catat Penghapusan, alasan Rusak → Jumlah berkurang; alasan Lainnya tanpa keterangan → ditolak.
- [ ] **TC-STK-08 Stok tidak boleh negatif** — Pemakaian melebihi jumlah → Ditolak "Stok tidak cukup (tersisa N)."
- [ ] **TC-STK-09 Status Aman/Menipis/Habis** — Atur jumlah di atas, sama dengan, dan 0 terhadap minimum → Status berubah; Menipis/Habis muncul di Dashboard & saran permintaan.
- [ ] **TC-STK-10 Kartu stok** — Buka Kartu stok → Riwayat terbaru dulu: jenis, jumlah, saldo, keterangan, ruangan, pencatat.
- [ ] **TC-STK-11 Hapus barang salah input** — Hapus barang yang hanya punya saldo awal → Terhapus (popup menyebut ruangan yang memakainya).
- [ ] **TC-STK-12 Hapus barang dengan riwayat** — Hapus barang yang punya transaksi lain → Ditolak "Barang sudah punya riwayat transaksi. Catat Penghapusan agar stok menjadi 0."
- [ ] **TC-STK-13 Ekspor stok** — Ekspor Excel → Sheet Stok & Transaksi.

## 10. Validasi & tampilan umum

- [ ] **TC-GEN-01 Pesan error** — Kosongkan kolom wajib di beberapa form → Pesan singkat Bahasa Indonesia di bawah kolom.
- [ ] **TC-GEN-02 Label opsional** — Cek form Catat perubahan, Isi dari stok, Lapor kerusakan → Label tanpa tulisan "(opsional)".
- [ ] **TC-GEN-03 Halaman tidak ada** — Buka `/xyz` → "Halaman tidak ditemukan".
- [ ] **TC-GEN-04 Tampilan HP** — Buka tiap menu di lebar HP → Sidebar jadi menu, tabel jadi kartu, tidak ada scroll horizontal.

---

## 11. Alur end-to-end

Jalankan setelah fitur per bagian lolos. Tiap alur menyambung beberapa fitur.

- [ ] **ALUR-01 Siklus hidup aset**
  1. Tambah aset Proyektor baru di Ruang Kelas 1 (TC-AST-01) → kode terbit, status Tersedia.
  2. Cetak label (TC-QR-01), scan dengan webcam (TC-QR-03).
  3. Dari hasil scan, Pinjam → isi form → status Dipinjam; Kelengkapan Ruangan: Jumlah Proyektor di Ruang Kelas 1 turun, "1 sedang dipinjam".
  4. Terima kembali kondisi Rusak Ringan, pilih Perbaikan (TC-LN-09).
  5. Catat servis kondisi Baik (TC-AST-23) → Tersedia; Kelengkapan Ruangan kembali normal.
  6. Detail aset → Riwayat urut: Registrasi, Dipinjam, Dikembalikan, Servis.

- [ ] **ALUR-02 Aset hilang lalu ditemukan**
  1. Pinjamkan aset, lalu Terima kembali dengan hasil Hilang (TC-LN-12).
  2. Dashboard & Daftar Aset: status Hilang; Kelengkapan Ruangan: "1 hilang".
  3. Tandai ditemukan kondisi Baik → klik "Langsung pinjamkan" → form pinjam terbuka dengan aset terisi.

- [ ] **ALUR-03 Cek ruangan kurang → isi dari stok → permintaan**
  1. Kelengkapan Ruangan Ruang Kelas 1: tambah barang Tisu standar 5 (Belum dicek).
  2. Catat perubahan: jumlah sekarang 1 → Hasil Kurang, kotak peringatan muncul.
  3. Isi dari stok sebanyak sisa stok Tisu (jika stok < 4, popup menampilkan sisa kekurangan).
  4. Klik Ajukan permintaan untuk sisa kekurangan → form permintaan terisi → simpan.
  5. Permintaan → Catat barang diterima → stok Tisu bertambah (TC-RQ-06).
  6. Kembali ke Kelengkapan Ruangan → Isi dari stok sisa kekurangan → Hasil Sesuai.
  7. Tandai sudah dicek → header menampilkan waktu & staf.
  8. Kartu ruangan Tisu: Saldo awal, Diisi, Diisi; Kartu stok Tisu: Pemakaian (×2) dan Barang Masuk.

- [ ] **ALUR-04 Pemakaian barang di ruangan antar cek**
  1. Barang di ruangan sudah dicek dengan jumlah 10.
  2. Beberapa hari kemudian (atau langsung), Catat perubahan ke 6, alasan Terpakai → "Terpakai 4 … pada cek {tanggal}".
  3. Catat perubahan ke 5, alasan Rusak → "Terpakai terakhir" tetap 4.
  4. Lepas barang, tambahkan lagi → Jumlah 5, Belum dicek; Kartu ruangan lengkap dari awal.

- [ ] **ALUR-05 Stok menipis → saran → permintaan**
  1. Stok: catat Pemakaian sampai barang ≤ minimum → status Menipis.
  2. Dashboard: kartu & panel stok menipis menampilkan barang itu.
  3. Permintaan → Saran: barang muncul di bawah departemen penyedianya → Buat permintaan.
  4. Terima sebagian (TC-RQ-07), lalu terima sisanya → status Diterima, stok Aman lagi.

- [ ] **ALUR-06 Data master berubah, data lain ikut**
  1. Ganti nama ruangan di Data Master (TC-MST-05).
  2. Cek Daftar Aset (kolom Lokasi), Kelengkapan Ruangan (chip & URL), detail aset → nama baru tampil di semua tempat.
  3. Ganti kode jenis (TC-MST-10), tambah aset jenis itu → kode memakai kode jenis baru; aset lama tetap.

- [ ] **ALUR-07 Laporan untuk demo**
  1. Ekspor kelima laporan (Aset, Peminjaman, Kelengkapan Ruangan, Permintaan, Stok).
  2. Buka di Excel → judul, ringkasan filter, tanggal cetak, nama staf; header dibekukan; data cocok dengan layar.

---

## Catatan hasil uji

| ID test | Tanggal | Hasil (Lolos/Gagal) | Temuan / langkah reproduksi |
|---|---|---|---|
| | | | |
