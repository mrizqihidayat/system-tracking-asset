# Flowchart — Sistem Informasi Aset SHD

Disusun dari kode frontend & backend per 9 November 2026 (termasuk T30 R1: catat perubahan barang ruangan, Tandai sudah dicek, kartu ruangan; tampilan gudang; slug ikut nama). Diperbarui 13 November 2026 (T33: login NIK / username, wajib ganti password, Data Master > Akun; R1: password awal = NIK, kelola akun khusus akun pegawai SHD; R2: tanpa wajib ganti password; T34: salinan pegawai & departemen dari API pegawai).

Versi visual: **`docs/flowchart.drawio`** (buka dengan ekstensi Draw.io di VS Code; satu tab per alur). Isinya dibuat dari blok Mermaid di bawah: setelah mengubah file ini, jalankan `python docs/scripts/flowchart_drawio.py` dari root repo untuk membuat ulang `flowchart.drawio`.

Simbol: `([ ])` mulai/selesai, `[ ]` langkah, `{ }` keputusan, `[( )]` proses sistem/database, `[[ ]]` lanjut ke alur lain. Di draw.io, panah putus-putus = kembali ke langkah sebelumnya.

## 1. Alur Utama

```mermaid
flowchart TD
    A([Mulai]) --> B{Cara masuk}
    B -- Kamera HP memindai label QR --> Q[[Halaman publik QR]]
    Q -- Hanya melihat --> Z1
    Q -- Aksi staf, login bila perlu --> M1
    B -- Staf membuka web --> C{Token tersimpan & valid?}
    C -- Ya --> E
    C -- Tidak --> D[Input username atau NIK & password]
    D --> D1[[Login & Akun]]
    D1 -- Gagal --> D
    D1 -- Berhasil --> E[Dashboard: 6 kartu ringkasan & panel; kartu dan panel menaut ke halaman terkait]
    E --> F{Pilih menu / aksi}
    F --> M1[[Scan QR]]
    F --> M2[[Daftar Aset]]
    F --> M3[[Peminjaman]]
    F --> M4[[Kelengkapan Ruangan]]
    F --> M5[[Permintaan ke Departemen Lain]]
    F --> M6[[Stok Habis Pakai]]
    F --> M7[[Data Master]]
    F -- Ekspor laporan --> X1[Pilih laporan Aset, Peminjaman, Kelengkapan Ruangan, Permintaan, atau Stok] --> X2[(Buat file .xlsx sesuai filter aktif, lalu unduh)] --> F
    F -- Keluar --> Z[(Hapus token)] --> Z1([Selesai])
```

## 2. Daftar Aset

```mermaid
flowchart TD
    A([Mulai]) --> B[Daftar aset: Kode, Nama, Kategori, Lokasi, Sumber, Kondisi, Status; filter kategori, gedung, ruangan, status; cari nama, kode, S/N, kode lama]
    B --> C{Pilih aksi}
    C -- Tambah aset --> T1[Input jenis, nama, merek, S/N, spesifikasi, sumber, PIC, gedung & ruangan, tgl perolehan, servis berikutnya, kondisi; kategori tampil otomatis; jenis baru lewat popup kategori; pratinjau Kode otomatis]
    T1 --> T1a{Kondisi}
    T1a -- Baik --> T1b[Status Tersedia]
    T1a -- Rusak Berat --> T1c[Status Perbaikan]
    T1a -- Rusak Ringan --> T1d[Staf pilih Tersedia default / Perbaikan]
    T1b & T1c & T1d --> T2{Data valid?}
    T2 -- Tidak --> T1
    T2 -- Ya --> T3[(Simpan: kode SHD-JENIS-NNN, token QR, riwayat Registrasi)] --> D[[Detail Aset]]
    C -- Buka detail --> D
    C -- Cetak label QR --> L1[Centang aset, bisa pilih semua di halaman]
    L1 --> L1a{Terpilih 1 sampai 120 aset?}
    L1a -- Tidak --> L1b[Tombol cetak nonaktif] --> L1
    L1a -- Ya --> L2[(PDF A4, 12 label per halaman: QR 5x5 cm berisi link /aset/token + kode)] --> B
    C -- Ekspor Excel --> X1[(Laporan Aset .xlsx sesuai filter aktif)] --> B
    C -- Pindah menu --> Z([Selesai])
```

## 3. Detail Aset

```mermaid
flowchart TD
    A([Mulai]) --> D1[Data aset, lokasi saat ini, standar ruangan, kode lama, foto & dokumen, label QR, riwayat]
    D1 --> S{Status aset menentukan tombol}
    S -- Tersedia --> S1[Catat peminjaman, kerusakan, kehilangan, tidak layak pakai, servis, Pindah lokasi]
    S -- Perbaikan --> S2[Catat kerusakan, kehilangan, tidak layak pakai, servis, Pindah lokasi]
    S -- Dipinjam --> S3[Catat kerusakan, Pindah lokasi; terima kembali lewat Peminjaman]
    S -- Hilang --> S4[Tandai ditemukan, Pindah lokasi]
    S -- Tidak Layak Pakai --> S5[Pindah lokasi]
    S1 & S2 & S3 & S4 & S5 --> AK{Pilih aksi; Ubah data & Hapus aset selalu ada}
    AK -- Lihat file / label QR --> V1[(Ambil file atau PDF label, buka di tab baru)] --> D1
    AK -- Ubah data --> E1[Form ubah tanpa kondisi & status; kelola foto & dokumen JPG/PNG/WebP/PDF maks. 4 MB]
    E1 --> E0{Jenis diganti?}
    E0 -- Ya --> E2[Peringatan kode berganti, pratinjau code-preview] --> E4[(Kode baru dari jenis baru, kode lama tetap bisa dicari, riwayat Ubah Data)] --> D1
    E0 -- Tidak --> E5[(Simpan, riwayat Ubah Data / Pindah Lokasi)] --> D1
    AK -- Catat kerusakan --> R2[Rusak Ringan / Rusak Berat, catatan wajib, pihak pelapor default SHD]
    R2 --> R2a{Sedang dipinjam?}
    R2a -- Ya --> R3a[(Kondisi diperbarui, status tetap Dipinjam, riwayat Lapor Rusak)] --> D1
    R2a -- Tidak --> K1
    AK -- Catat kehilangan --> R1[Catatan wajib; aset Dipinjam ditolak] --> R4[(Status Hilang, riwayat Lapor Hilang)] --> D1
    AK -- Catat tidak layak pakai --> TL2[Peringatan permanen, catatan wajib] --> TL3[(Status Tidak Layak Pakai, riwayat)] --> D1
    AK -- Tandai ditemukan --> F1[Pilih kondisi, pihak & catatan] --> K1
    AK -- Catat servis --> S6[Tanggal maks. hari ini, vendor, kondisi setelah servis, servis berikutnya, catatan] --> K1
    K1{Kondisi → status}
    K1 -- Baik --> K2[Tersedia]
    K1 -- Rusak Berat --> K3[Perbaikan]
    K1 -- Rusak Ringan --> K4[Staf pilih Tersedia default / Perbaikan]
    K2 & K3 & K4 --> K5[(Simpan + riwayat Lapor Rusak / Ditemukan / Servis)] --> K6{Dari tandai ditemukan & Tersedia?}
    K6 -- Tidak --> D1
    K6 -- Ya, Langsung pinjamkan --> P[[Peminjaman: form terisi aset]]
    AK -- Catat peminjaman --> P
    AK -- Pindah lokasi --> G1[Pilih / tambah gedung & ruangan tujuan, catatan] --> G2{Ruangan sama dengan lokasi asal?}
    G2 -- Ya --> G3[Tolak] --> G1
    G2 -- Tidak --> G4[(Update lokasi asal, riwayat Pindah Lokasi)] --> D1
    AK -- Hapus aset --> H1[(Ambil delete-impact)] --> H2[Popup: jumlah pinjaman, riwayat, file, pinjaman aktif, standar ruangan terpengaruh]
    H2 --> H3{Punya riwayat pinjam?}
    H3 -- Ya --> H4[Wajib mengetik kode aset] --> H6
    H3 -- Tidak --> H5[Konfirmasi hapus permanen] --> H6
    H6[(Hard delete: pinjaman, riwayat, file, kode lama, aset)] --> L[[Daftar Aset]]
```

## 4. Scan QR

```mermaid
flowchart TD
    A([Mulai]) --> A1{Cara scan}
    A1 -- Kamera HP biasa --> Q1[Buka link QR /aset/token tanpa login]
    Q1 --> Q2{Token valid & aset masih ada?}
    Q2 -- Tidak --> Q2a[Pesan Aset tidak ditemukan] --> Z
    Q2 -- Ya --> Q3[Halaman publik read-only (tanpa tombol aksi)]
    Q3 -- Hanya melihat --> Z
    Q3 -- Menekan aksi --> Q4{Sudah login?}
    Q4 -- Belum --> Q5[Login, lalu kembali otomatis] --> Q6
    Q4 -- Sudah --> Q6{Aksi berlaku untuk status terkini?}
    Q6 -- Tidak --> Q6a[Popup hasil dengan pesan alasan] --> D
    Q6 -- Ya, Pinjam --> P
    Q6 -- Ya, Terima kembali --> R
    Q6 -- Ya, aksi lain --> FA
    A1 -- Menu Scan QR --> B[Arahkan kamera atau ketik kode]
    B --> C{Ditemukan dari kode, kode lama, atau token?}
    C -- Tidak --> C1[Pesan Aset tidak ditemukan] --> B
    C -- Ya --> D[Popup hasil: data aset, lokasi pinjam & peminjam jika dipinjam, peringatan kode lama]
    D --> G{Pilih aksi sesuai status}
    G -- Tutup --> B
    G -- Buka detail --> DA[[Detail Aset]]
    G -- Pinjam --> P[[Peminjaman: form terisi aset]]
    G -- Terima kembali --> G1{Ada peminjaman aktif?}
    G1 -- Tidak --> G2[Pesan Aset ini tidak punya peminjaman aktif] --> D
    G1 -- Ya --> R[[Peminjaman: modal terima kembali]]
    G -- Aksi lain --> FA{Form aksi}
    FA -- Batal --> D
    FA -- Pindah lokasi --> F1[(Update lokasi asal, riwayat Pindah Lokasi)] --> H
    FA -- Kerusakan / kehilangan / tidak layak pakai / ditemukan --> F2[[Form & aturan sama dengan Detail Aset]] --> H
    H[(Muat ulang data aset)] --> D
    Z([Selesai])
```

## 5. Peminjaman

```mermaid
flowchart TD
    A([Mulai]) --> B[Tabel Direncanakan + jadwal, Sedang dipinjam + tempo, Riwayat + filter; tombol + Catat peminjaman]
    B --> C{Pilih proses}
    C -- Catat peminjaman --> P1[Popup form: aset terisi dari detail / scan, atau ketik kode / scan kamera]
    P1 --> P4[Input peminjam, departemen, keperluan, lokasi tujuan teks bebas, tgl pinjam default hari ini, rencana kembali, no. approval; kondisi keluar otomatis]
    P4 --> P5{Tgl pinjam tidak sebelum aset terdaftar; rencana kembali >= tgl pinjam?}
    P5 -- Tidak --> P4
    P5 -- Ya --> P7{Tgl pinjam > hari ini?}
    P7 -- Ya, pemesanan --> Q1{Aset bukan Hilang / Tidak Layak Pakai?}
    P7 -- Tidak --> P2{Aset Tersedia?}
    P2 -- Tidak --> P3[Pesan aset tidak dapat dipinjam / dipesan] --> P1
    Q1 -- Tidak --> P3
    P2 -- Ya --> P8{Periode bentrok dengan Direncanakan / Dipinjam lain?}
    Q1 -- Ya --> P9{Periode bentrok dengan Direncanakan / Dipinjam lain?}
    P8 -- Ya --> P10[422: sebut peminjam & periode yang bentrok] --> P4
    P9 -- Ya --> P10
    P8 -- Tidak --> P6[(Transaksi Dipinjam, status aset Dipinjam, riwayat Dipinjam)] --> B
    P9 -- Tidak --> Q2[(Transaksi Direncanakan; status aset & kelengkapan ruangan tidak berubah; tanpa riwayat aset)] --> B
    C -- Serahkan pemesanan --> S1[Modal: tanggal penyerahan default hari ini, tidak di masa depan, boleh sebelum / setelah tgl rencana]
    S1 --> S2{Aset Tersedia & periode penyerahan s.d. rencana kembali tidak bentrok?}
    S2 -- Tidak --> S3[Pesan alasan; tetap Direncanakan] --> B
    S2 -- Ya --> S4[(Transaksi Dipinjam + tanggal penyerahan, kondisi keluar diperbarui, status aset Dipinjam, riwayat Dipinjam)] --> B
    C -- Batalkan pemesanan --> Q3[Alasan wajib] --> Q4[(Transaksi Dibatalkan; status aset tidak berubah, tanpa riwayat aset)] --> B
    C -- Terima kembali --> R1[Modal terima kembali]
    R1 --> R1a{Hasil}
    R1a -- Hilang saat dipinjam --> H1[Tanggal & catatan wajib] --> H2[(Transaksi Hilang, status aset Hilang, riwayat Lapor Hilang pihak = peminjam)] --> B
    R1a -- Kembali --> R2[Tanggal, kondisi, ruangan penyimpanan default lokasi asal]
    R2 --> R3{Kondisi}
    R3 -- Baik --> R4[Status Tersedia]
    R3 -- Rusak Ringan --> R6[Pilih Tersedia default / Perbaikan, catatan wajib]
    R3 -- Rusak Berat --> R5[Pilih Perbaikan default / Tidak Layak Pakai, catatan wajib]
    R4 & R5 & R6 --> R10[(Transaksi Dikembalikan; ruangan berbeda → lokasi asal pindah + riwayat Pindah Lokasi)] --> B
    C -- Batalkan --> X2[Alasan wajib] --> X3[(Transaksi Dibatalkan, status mengikuti kondisi, riwayat Dibatalkan)] --> B
    C -- Ekspor Excel --> E1[(Laporan Peminjaman .xlsx)] --> B
    C -- Pindah menu --> Z([Selesai])
```

## 6. Kelengkapan Ruangan

```mermaid
flowchart TD
    A([Mulai]) --> A1[Buka /kelengkapan-ruangan/gedung/ruangan; slug mengikuti nama, tanpa slug → ruangan pertama]
    A1 --> G0{Ruangan gudang?}
    G0 -- Ya --> GD[Badge Gudang: tabel Aset tersimpan & Stok tersimpan, tanpa standar dan tanpa cek] --> C
    G0 -- Tidak --> B[Aset di ruangan & Barang habis pakai: Standar, Jumlah, Selisih, Hasil; kotak peringatan jika kurang]
    B --> C{Pilih aksi}
    C -- Tambah gedung / ruangan --> N1[Input nama] --> N2[(Simpan, slug otomatis, buka data baru)] --> A1
    C -- Tambah / ubah standar jenis aset --> S1[Pilih jenis tanpa standar, jumlah standar] --> S4[(Simpan standar; Jumlah aset dihitung otomatis tanpa Dipinjam, Perbaikan, Hilang)] --> B
    C -- Hapus standar jenis aset --> S3[Konfirmasi] --> S3a[(Standar kembali 0, aset tidak berubah)] --> B
    C -- Tambah barang --> T1[Pilih barang dari Stok, standar minimal 1] --> T2[(Jumlah = saldo terakhir ruangan + barang atau 0; status Belum dicek)] --> B
    C -- Catat perubahan --> P1[Popup: catatan sekarang, jumlah sekarang, selisih otomatis]
    P1 --> P2{Selisih}
    P2 -- Sama, baris sudah dicek --> P2a[Tombol Simpan nonaktif] --> B
    P2 -- Sama, baris Belum dicek --> P2b[(Konfirmasi jumlah: tandai baris dicek, tanpa transaksi)] --> B
    P2 -- Bertambah, belum ada riwayat --> P3[(Transaksi Saldo awal)] --> P9
    P2 -- Bertambah --> P4[Keterangan wajib] --> P5[(Transaksi Koreksi)] --> P9
    P2 -- Berkurang --> P6[Pilih alasan Terpakai, Hilang, Rusak, Kedaluwarsa; keterangan boleh kosong] --> P7[(Transaksi sesuai alasan)] --> P9
    P9[(Jumlah = saldo transaksi, baris ditandai dicek; status ruangan tidak berubah)] --> K6
    K6{Barang Kurang?}
    K6 -- Tidak --> B
    K6 -- Ya, stok masih ada --> F1[Isi dari stok: jumlah default kekurangan, maks. sisa stok, peringatan melebihi standar] --> F2[(Pemakaian di kartu stok + Diisi di kartu ruangan, satu transaksi DB)] --> B
    K6 -- Ya, stok 0 --> K9[Ajukan permintaan: form terisi barang, penyedia, jumlah, ruangan] --> K10[[Permintaan]]
    C -- Kartu ruangan --> KR[Riwayat transaksi barang di ruangan ini, termasuk sebelum pernah dilepas] --> B
    C -- Lepas barang --> L1[Konfirmasi: riwayat tetap tersimpan] --> L2[(Hapus standar; stok & riwayat ruangan tetap)] --> B
    C -- Tandai sudah dicek --> M1[Dialog ringkas: aset Kurang, barang Kurang, barang Belum dicek; atau konfirmasi singkat] --> M2[(Semua barang ditandai dicek tanpa transaksi; waktu & staf cek ruangan disimpan)] --> B
    C -- Ekspor Excel --> X1[(Laporan Kelengkapan .xlsx: sheet Ringkasan & Detail)] --> B
    C -- Pindah menu --> Z([Selesai])
```

## 7. Permintaan ke Departemen Lain

```mermaid
flowchart TD
    A([Mulai]) --> B[Saran dari stok Menipis/Habis per penyedia, katalog, riwayat + filter; Diajukan lewat lead time tampil Belum direspons]
    B --> C{Pilih aksi}
    C -- Kelola katalog --> K0{Aksi katalog}
    K0 -- Tambah / Ubah --> K1[Item, penyedia, perlu dikembalikan, lead time, catatan] --> K2[(Simpan katalog)] --> B
    K0 -- Hapus --> K4[Popup jumlah permintaan yang tautannya dilepas] --> K5[(Hapus katalog, riwayat tetap)] --> B
    C -- Buat permintaan --> P0{Dibuka dari}
    P0 -- Tombol Buat permintaan --> P1[Form kosong]
    P0 -- Saran / Kelengkapan Ruangan / Stok --> P1a[Form terisi barang, penyedia, jumlah, ruangan; tertaut barang stok]
    P1 & P1a --> P2[Item katalog atau item lain, pengembalian, cara permintaan, jumlah, kegiatan, ruangan, catatan]
    P2 --> P3[(Simpan status Diajukan; snapshot lead time & pengembalian)] --> B
    C -- Aksi baris riwayat --> U0{Status permintaan}
    U0 -- Dikembalikan / Dibatalkan --> U3[Tanpa aksi] --> B
    U0 -- Diajukan, termasuk Belum direspons: terbuka --> U1{Aksi}
    U1 -- Catat barang diterima --> M1[Isi jumlah yang datang, popup dampak] --> I1[(Barang Masuk ke stok gudang sebanyak yang diterima; kurang dari yang diminta = Diterima sebagian, selesai; ruangan diisi lewat Isi dari stok)] --> B
    U1 -- Tandai Dibatalkan --> I2[(Dibatalkan)] --> B
    U0 -- Diterima --> U2{Aksi}
    U2 -- Tandai Dikembalikan / Dibatalkan --> M3[Popup dampak] --> I3[(Koreksi mengurangi stok, minimal 0)] --> B
    U0 -- Diterima sebagian: selesai --> U4{Perlu dikembalikan?}
    U4 -- Ya --> U5[Tandai Dikembalikan, popup dampak] --> I4[(Koreksi mengurangi stok sejumlah yang diterima, minimal 0)] --> B
    U4 -- Tidak --> U6[Final: tanpa aksi; sisa diajukan ulang lewat Saran permintaan] --> B
    C -- Ekspor Excel --> X1[(Laporan Permintaan .xlsx)] --> B
    C -- Pindah menu --> Z([Selesai])
```

## 8. Stok Habis Pakai

```mermaid
flowchart TD
    A([Mulai]) --> B[Daftar barang: jumlah, minimum, status, lokasi simpan gudang, penyedia; filter & cari]
    B --> C{Pilih aksi}
    C -- Tambah / Ubah data --> T1[Nama unik, kategori, satuan, minimum, minta ke, lokasi simpan ruangan gudang; jumlah awal = Saldo awal] --> T2[(Simpan)] --> CEK
    C -- Catat transaksi --> J0[Barang Masuk, Pemakaian dengan ruangan tujuan opsional, Koreksi keterangan wajib, Penghapusan alasan wajib]
    J0 --> J3{Hasil >= 0?}
    J3 -- Tidak --> J4[Tolak: Stok tidak cukup] --> B
    J3 -- Ya --> J5[(Simpan transaksi & jumlah stok dengan lock; Pemakaian tidak mengubah jumlah di ruangan)] --> CEK
    C -- Kartu stok --> KS[Riwayat transaksi, terbaru dulu] --> B
    R0[Isi dari stok di Kelengkapan Ruangan] --> R1[(Pemakaian dengan ruangan tujuan + Diisi di kartu ruangan)] --> CEK
    R2[Status permintaan tertaut berubah] --> R3[(Barang Masuk saat diterima; Koreksi saat Dikembalikan / Dibatalkan)] --> CEK
    C -- Hapus --> H00{Ada transaksi selain saldo awal?}
    H00 -- Ya --> H01[Tolak: catat Penghapusan agar stok 0] --> B
    H00 -- Tidak --> H2[Konfirmasi, sebut ruangan yang memakai] --> H4[(Hard delete beserta standar ruangan yang memakainya)] --> B
    C -- Ekspor Excel --> X1[(Laporan Stok .xlsx: sheet Stok & Transaksi)] --> B
    C -- Pindah menu --> Z([Selesai])
    CEK{Jumlah <= minimum?}
    CEK -- Tidak --> S1[Status Aman] --> B
    CEK -- Ya --> S2[Menipis, atau Habis jika 0] --> S3[Peringatan dashboard + saran permintaan] --> S4{Penyedia departemen lain?}
    S4 -- Tidak --> B
    S4 -- Ya --> S5[Tautan Ajukan permintaan, atau Diajukan · tanggal jika sudah ada] --> S6[[Permintaan]]
```

## 9. Data Master

Tab **Data Master** di halaman Pengaturan (§12), terbuka untuk semua akun. Bagian Akun hanya untuk akun pegawai SHD.

```mermaid
flowchart TD
    A([Mulai: Pengaturan > Data Master]) --> B[Bagian Gedung & Ruangan, Departemen, Kategori & Jenis aset, Kategori stok, Akun]
    B --> C{Pilih aksi}
    C -- Bagian Akun, khusus pegawai SHD --> AK[[Kelola akun]]
    C -- Departemen SHD --> C1[Tidak dapat diubah atau dihapus] --> B
    C -- Tambah / Ubah --> T1[Nama; ruangan: tanda gudang; jenis aset: kategori wajib & kode singkat] --> T2{Data valid?}
    T2 -- Tidak --> T3[Pesan, mis. nama sudah ada, kode pernah dipakai jenis lain, gudang masih dipakai stok] --> T1
    T2 -- Ya --> T4[(Simpan; slug gedung & ruangan ikut nama baru)] --> B
    C -- Hapus --> H1[(Ambil pemakaian: aset, peminjaman, permintaan, standar, stok, riwayat stok & riwayat barang ruangan)] --> H2{Masih dipakai?}
    H2 -- Ya --> H3[Popup rincian pemakaian, tanpa tombol hapus] --> B
    H2 -- Tidak --> H4[Konfirmasi] --> H5[(Hapus permanen)] --> B
    C -- Pindah menu --> Z([Selesai])
```

## 10. Login & Akun

### 10a. Login, penguncian, wajib ganti password, wajib isi email

```mermaid
flowchart TD
    A([Mulai]) --> B[Input username atau NIK & password]
    B -- Lupa password? --> LP[[Lupa password via OTP]]
    B --> L0{NIK/username sedang dikunci?}
    L0 -- Ya --> L1[Pesan: Akun dikunci sementara, coba lagi dalam N menit; password benar pun ditolak] --> B
    L0 -- Tidak --> C{Angka saja?}
    C -- Tidak: username --> M1{Akun bersama ada & password benar?}
    M1 -- Tidak --> X[(Catat satu kali salah)]
    X --> X1{Sudah mencapai batas salah dari Pengaturan? bawaan 5}
    X1 -- Belum --> X2[Pesan: NIK/Username atau password salah] --> B
    X1 -- Ya --> X3[(Kunci NIK/username selama lama kunci dari Pengaturan, bawaan 15 menit)] --> L1
    M1 -- Ya --> AKT{Akun aktif?}
    C -- Ya: NIK --> N1{Akun pegawai sudah ada?}
    N1 -- Ya --> N2{Password tersimpan benar? awalnya NIK}
    N2 -- Tidak --> X
    N2 -- Ya --> N3{Salinan pegawai sudah terisi?}
    N3 -- Belum pernah sinkron --> N4[(Catat warning, cek SHD dilewati)] --> AKT
    N3 -- Sudah --> SHD
    N1 -- Tidak: login pertama --> F1{Salinan pegawai sudah terisi?}
    F1 -- Belum pernah sinkron --> F2[Pesan: Data pegawai belum tersedia, hubungi admin] --> B
    F1 -- Ya --> F3{Password = NIK?}
    F3 -- Tidak --> X
    F3 -- Ya --> SHD{Pegawai ada & departemen SHD?}
    SHD -- Tidak: tidak ada, nonaktif, atau bukan SHD --> S1[Pesan: Hanya pegawai Departemen SHD] --> B
    SHD -- Ya --> S2[(Buat akun pegawai bila belum ada, password = NIK; sinkron nama & departemen; isi email dari salinan pegawai bila email akun kosong)] --> AKT
    AKT -- Tidak --> AK1[Pesan: Akun dinonaktifkan] --> B
    AKT -- Ya --> OK[(Hapus hitungan salah password, buat token)]
    OK --> W1{Password baru direset staf SHD?}
    W1 -- Ya --> W2[Halaman Buat password baru: password saat ini + password baru 2x; hanya bisa Simpan atau Keluar]
    W2 --> W3{Valid: panjang minimal dari Pengaturan, bukan NIK / username, konfirmasi sama?}
    W3 -- Tidak --> W2
    W3 -- Ya --> W4[(Simpan password, kewajiban ganti selesai, sesi lain dicabut)] --> E1
    W1 -- Tidak: termasuk login pertama dengan password = NIK --> E1{Akun punya email, atau dikecualikan seperti staf1?}
    E1 -- Tidak --> E2[Halaman Isi email Anda: hanya bisa Simpan atau Keluar]
    E2 --> E3{Format valid & belum dipakai akun lain?}
    E3 -- Tidak --> E2
    E3 -- Ya --> E4[(Simpan email; setelah terisi hanya staf SHD yang bisa mengubahnya)] --> D
    E1 -- Ya --> D[[Dashboard]]
    D -- Menu akun: Ganti password, opsional --> G1[Password saat ini + password baru 2x] --> G2{Valid?}
    G2 -- Tidak --> G1
    G2 -- Ya --> G3[(Simpan password, sesi lain dicabut)] --> D
    D -- Menu akun: Keluar --> B
```

### 10b. Lupa password via OTP

```mermaid
flowchart TD
    A([Mulai: tautan Lupa password di halaman masuk]) --> B[Isi email]
    B --> C{Format email valid?}
    C -- Tidak --> B
    C -- Ya --> D[Jawaban selalu sama: jika email terdaftar, kode sudah dikirim]
    D -. setelah jawaban terkirim .-> S1{Email milik akun aktif & kiriman terakhir sudah lewat 60 detik?}
    S1 -- Tidak --> S2[Tidak ada email dikirim]
    S1 -- Ya --> S3[(Simpan hash OTP 6 angka, hitungan salah 0, masa berlaku dari Pengaturan)] --> S4[Kirim email OTP: judul web, logo, masa berlaku]
    D --> E[Halaman Masukkan kode: 6 angka; Kirim ulang aktif setelah 60 detik; teks Tidak menerima email? Hubungi staf SHD untuk reset password]
    E -- Kirim ulang kode --> D
    E -- Ganti email --> B
    E --> F{Kode benar, belum dipakai, belum kedaluwarsa, salah belum 5 kali?}
    F -- Tidak --> F1[(Tambah hitungan salah bila kodenya salah)] --> F2[Pesan: Kode OTP salah atau sudah tidak berlaku] --> E
    F -- Ya --> G[(OTP hangus; buat token reset sekali pakai, berlaku 10 menit)]
    G --> H[Halaman Buat password baru: password baru 2x]
    H --> I{Token reset masih berlaku?}
    I -- Tidak --> I1[Pesan: Sesi reset sudah tidak berlaku, minta kode OTP baru] --> B
    I -- Ya --> J{Password valid?}
    J -- Tidak --> H
    J -- Ya --> K[(Simpan password; cabut semua sesi; buka kunci akun; kewajiban ganti password selesai)]
    K --> L([Selesai: masuk dengan password baru])
```

### 10c. Kelola akun (Pengaturan > Data Master > Akun, khusus akun pegawai SHD)

```mermaid
flowchart TD
    A([Mulai]) --> AKUN{Akun pegawai SHD?}
    AKUN -- Tidak: akun bersama --> AK2[Bagian Akun tidak tampil; API menolak 403]
    AKUN -- Ya --> K{Aksi akun}
    K -- Tambah akun bersama --> K1[Username bukan angka saja, nama, departemen, email wajib & unik, password 2x] --> K2[(Simpan, tidak wajib ganti password)]
    K -- Ubah email akun mana pun --> K7[Email valid & belum dipakai akun lain] --> K8[(Simpan email: tujuan kode lupa password)]
    K -- Reset akun pegawai --> K3[(Password kembali ke NIK, sesi dicabut, kunci dibuka, wajib ganti password saat masuk berikutnya)]
    K -- Ubah password akun bersama --> K4[(Simpan, sesi dicabut, kunci dibuka, wajib ganti password saat masuk berikutnya)]
    K -- Nonaktifkan, bukan akun sendiri --> K5[(Soft delete, sesi dicabut)]
    K -- Aktifkan kembali --> K6[(Restore)]
```

## 11. Sinkronisasi pegawai & departemen

```mermaid
flowchart TD
    A([Jadwal harian 02.00 WIB, atau admin menjalankan employees:sync di server]) --> L{Sinkronisasi lain sedang berjalan?}
    L -- Ya --> L1[Ditolak: sedang berjalan] --> Z([Selesai])
    L -- Tidak --> B[GET EMPLOYEE_API_URL dengan API key di header]
    B --> C{API menjawab dengan benar?}
    C -- Tidak: mati, 401, timeout, bentuk salah --> F[(Catat run gagal + pesan; data lama tetap dipakai)] --> Z
    C -- Ya --> D[Baca nik, nama, grup, divisi, departemen, seksi, jabatan, email; lewati baris tanpa nik/nama]
    D --> E{Ada pegawai valid?}
    E -- Tidak: daftar kosong --> G[(Catat run dibatalkan; tidak ada yang dinonaktifkan)] --> Z
    E -- Ya --> H[(Satu transaksi: upsert pegawai per nik, aktifkan kembali yang muncul lagi)]
    H --> I[(Pegawai yang tidak ada di API jadi tidak aktif, tidak dihapus)]
    I --> J[(Departemen dari nilai unik departemen kecuali tanda hubung: buat yang baru, aktifkan kembali, nonaktifkan yang hilang kecuali SHD; nama lengkap lokal tidak ditimpa)]
    J --> K[(Catat run berhasil + jumlah; kosongkan cache master)] --> Z
    Z --> V[Data Master > Departemen menampilkan Sinkron terakhir, hanya baca]
```

## 12. Pengaturan

Menu **Pengaturan** terbuka untuk semua akun; tab yang tampil dan izin di backend berbeda per jenis akun.

```mermaid
flowchart TD
    A([Mulai: menu Pengaturan]) --> B{Akun pegawai SHD?}
    B -- Tidak: akun bersama --> M[Hanya tab Data Master tanpa bagian Akun; API pengaturan & akun menolak 403] --> DM[[Data Master]]
    B -- Ya --> C{Pilih tab}
    C -- Data Master --> DM
    C -- Tampilan --> T1[Pilih paket warna atau warna utama & warna sidebar sendiri]
    T1 --> T2[Contoh tampilan berubah; aplikasi belum berubah]
    T2 --> T3{Tulisan sulit dibaca dengan warna itu?}
    T3 -- Ya --> T4[Peringatan kontras; tetap boleh disimpan] --> T5
    T3 -- Tidak --> T5{Aksi}
    T5 -- Kembalikan ke default --> T1
    T5 -- Simpan --> S
    C -- Konten --> K1{Yang diubah}
    K1 -- Logo / ikon tab browser --> K2{PNG, JPG, atau WebP & paling besar 1 MB?}
    K2 -- Tidak --> K3[Pesan kesalahan] --> K1
    K2 -- Ya --> K4[(Simpan gambar ke penyimpanan file, gambar lama dihapus)] --> S2
    K1 -- Pakai bawaan --> K5[(Hapus gambar)] --> S2
    K1 -- Judul web, deskripsi halaman masuk, footer, kop laporan Excel, teks label QR maks. 40 huruf --> S
    C -- Keamanan --> X1[Batas salah password 3-10, lama akun dikunci 1-1440 menit, panjang password 8-32, masa berlaku OTP 5-60 menit] --> S
    S{Nilai valid?}
    S -- Tidak --> S1[Pesan per kolom] --> C
    S -- Ya --> S2[(Simpan nilai yang berubah; catat siapa, apa, kapan; kosongkan cache pengaturan)]
    S2 --> S3[Warna, judul, logo, dan ikon langsung berlaku tanpa build ulang; kop & teks label dipakai laporan dan label berikutnya; aturan keamanan dipakai login dan OTP berikutnya]
    S3 --> Z([Selesai])
```

Ikon dan nama aplikasi yang sudah dipasang di HP (PWA) tidak ikut berubah dari Pengaturan; keduanya hanya berubah lewat build ulang frontend.
