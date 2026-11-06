# Flowchart — Sistem Informasi Aset SHD

Disusun dari kode frontend & backend per 4 November 2026 (termasuk T26n).

Tempel tiap blok ke mermaid.live atau draw.io (Arrange → Insert → Advanced → Mermaid).

Simbol:
- Silinder `[( )]` = proses sistem/database.
- Kotak ganda `[[ ]]` = lanjut ke bagian lain.

## 1. Alur Utama

```mermaid
flowchart TD
    A([Start]) --> B{Cara masuk}
    B -- Kamera HP memindai label QR --> Q[[Halaman publik QR]]
    Q -- Hanya melihat --> Z1
    Q -- Aksi staf, login bila perlu --> M1
    B -- Staf membuka web --> C{Token tersimpan & valid?}
    C -- Ya --> E
    C -- Tidak --> D[Input username & password]
    D --> D1{Login valid?}
    D1 -- Tidak --> D2[Tampilkan pesan gagal; lebih dari 5 percobaan per menit harus menunggu] --> D
    D1 -- Ya --> E[Dashboard: 6 kartu ringkasan, jatuh tempo & terlambat, permintaan terbaru, kelengkapan ruangan, stok menipis, aset perlu servis; kartu & panel menaut ke halaman terkait]
    E --> F{Pilih menu / aksi}
    F --> M1[[Scan QR, tombol di Dashboard]]
    F --> M2[[Daftar Aset, juga lewat kolom cari Dashboard]]
    F --> M3[[Peminjaman]]
    F --> M4[[Kelengkapan Ruangan]]
    F --> M5[[Permintaan ke Departemen Lain]]
    F --> M6[[Stok Habis Pakai]]
    F --> M7[[Data Master]]
    F -- Ekspor laporan --> X1[Pilih Laporan Aset, Peminjaman, Kelengkapan Ruangan, Permintaan, atau Stok Habis Pakai] --> X2[(Buat file .xlsx tanpa filter, lalu unduh)] --> F
    M1 & M2 & M3 & M4 & M5 & M6 & M7 --> F
    F -- Keluar --> Z[(Hapus token)] --> Z1([End])
```

Menu Lisensi Digital tampil nonaktif di sidebar (Fase 2).

## 2. Daftar Aset

```mermaid
flowchart TD
    A([Mulai]) --> B[Daftar aset berhalaman: kolom Kode, Nama, Kategori, Lokasi, Sumber nama departemen, Kondisi, Status; filter kategori, gedung, ruangan, status; cari nama, kode, S/N, kode lama]
    B --> C{Pilih aksi}

    C -- Tambah aset --> T1[Input jenis, nama, merek, S/N, spesifikasi, sumber, PIC, gedung & ruangan, kode keuangan, tgl perolehan, servis berikutnya, kondisi, catatan; kategori tampil otomatis dari jenis; jenis, sumber, gedung, ruangan baru bisa langsung ditambah; jenis baru lewat popup pilih kategori wajib, kategori baru bisa ditambah, kode otomatis; tampil Kode otomatis di bawah nama]
    T1 --> T1a{Kondisi}
    T1a -- Baik --> T1b[Status Tersedia]
    T1a -- Rusak Berat --> T1c[Status Perbaikan]
    T1a -- Rusak Ringan --> T1d[Staf pilih Tersedia default / Perbaikan]
    T1b & T1c & T1d --> T2{Data valid?}
    T2 -- Tidak --> T1
    T2 -- Ya --> T3[(Simpan, kode otomatis SHD-JENIS-NNN, token QR, riwayat Registrasi)] --> D[[Detail Aset]]

    C -- Buka detail --> D

    C -- Cetak label QR --> L1[Centang aset, bisa pilih semua di halaman ini]
    L1 --> L1a{Terpilih 1 sampai 120 aset?}
    L1a -- Tidak --> L1b[Tombol cetak nonaktif] --> L1
    L1a -- Ya --> L2[(PDF A4, 12 label per halaman: QR 5x5 cm berisi link /aset/token + kode, urut kode)]
    L2 --> L3[PDF terbuka di tab baru; cetak, potong, tempel, uji scan kamera HP] --> B

    C -- Pindah menu --> Z([Selesai])
    C -- Ekspor Excel --> X1[(Laporan Aset .xlsx sesuai filter aktif)] --> B
```

## 3. Detail Aset

```mermaid
flowchart TD
    A([Mulai]) --> D1[Data aset, lokasi saat ini, standar ruangan, kode lama, foto & dokumen, label QR, riwayat]
    D1 --> S{Status aset menentukan tombol}
    S -- Tersedia --> S1[Catat peminjaman, Catat kerusakan, Catat kehilangan, Catat tidak layak pakai, Catat servis, Pindah lokasi]
    S -- Perbaikan --> S2[Catat kerusakan, Catat kehilangan, Catat tidak layak pakai, Catat servis, Pindah lokasi]
    S -- Dipinjam --> S3[Catat kerusakan, Pindah lokasi; terima kembali lewat halaman Peminjaman]
    S -- Hilang --> S4[Tandai ditemukan, Pindah lokasi]
    S -- Tidak Layak Pakai --> S5[Pindah lokasi]
    S1 & S2 & S3 & S4 & S5 --> AK{Pilih aksi; Ubah data & Hapus aset selalu tersedia}

    AK -- Lihat file / label QR --> V1[(Ambil file atau PDF label, buka di tab baru)] --> D1

    AK -- Ubah data --> E1[Form ubah: tanpa kondisi & status; jenis baru bisa langsung ditambah lewat popup kategori; tampil Kode otomatis di bawah nama]
    E1 -- Kelola foto & dokumen --> E6[(Unggah, ganti slot, atau hapus file: JPG, PNG, WebP, PDF maks. 5 MB; langsung tersimpan, tanpa riwayat)] --> E1
    E1 -- Batal --> D1
    E1 -- Ubah isian --> E0{Jenis diganti?}
    E0 -- Ya --> E2[Peringatan: kode lama berganti ke kode baru, pratinjau code-preview] --> E3{Simpan perubahan?}
    E3 -- Tidak --> D1
    E3 -- Ya --> E4[(Kode baru dari nomor berikutnya jenis baru, kode lama disimpan & tetap bisa dicari, riwayat Ubah Data)] --> D1
    E0 -- Tidak --> E5[(Simpan, riwayat Ubah Data / Pindah Lokasi)] --> D1

    AK -- Catat kerusakan --> R2[Pilih Rusak Ringan / Rusak Berat, catatan wajib, pihak pelapor opsional]
    R2 --> R2a{Sedang dipinjam?}
    R2a -- Ya --> R3a[(Kondisi diperbarui, status tetap Dipinjam, riwayat Lapor Rusak)] --> D1
    R2a -- Tidak --> K1

    AK -- Catat kehilangan --> R1[Catatan wajib] --> R4[(Status Hilang, kondisi tetap, riwayat Lapor Hilang)] --> D1

    AK -- Catat tidak layak pakai --> TL2[Peringatan permanen, catatan wajib, pihak pelapor opsional] --> TL3[(Status Tidak Layak Pakai, riwayat Tidak Layak Pakai)] --> D1

    AK -- Tandai ditemukan --> F1[Pilih kondisi, pihak & catatan opsional] --> K1

    AK -- Catat servis --> S6[Input tanggal maks. hari ini, vendor, kondisi setelah servis, servis berikutnya opsional, catatan] --> K1

    K1{Kondisi → status}
    K1 -- Baik --> K2[Tersedia]
    K1 -- Rusak Berat --> K3[Perbaikan]
    K1 -- Rusak Ringan --> K4[Staf pilih Tersedia default / Perbaikan]
    K2 & K3 & K4 --> K5[(Simpan + riwayat Lapor Rusak / Ditemukan / Servis)] --> K6{Dari tandai ditemukan & Tersedia?}
    K6 -- Tidak --> D1
    K6 -- Ya --> K7{Pilih Langsung pinjamkan?}
    K7 -- Tidak --> D1
    K7 -- Ya --> P

    AK -- Catat peminjaman --> P[[Peminjaman: form terisi aset]]

    AK -- Pindah lokasi --> G1[Pilih / tambah gedung & ruangan tujuan, catatan opsional] --> G2{Ruangan sama dengan lokasi asal?}
    G2 -- Ya --> G3[Tolak] --> G1
    G2 -- Tidak --> G4[(Update lokasi asal, riwayat Pindah Lokasi)] --> D1

    AK -- Hapus aset --> H1[(Ambil delete-impact)] --> H2[Popup: jumlah pinjaman, riwayat, file; pinjaman aktif & peminjam; standar ruangan terpengaruh]
    H2 --> H3{Punya riwayat pinjam?}
    H3 -- Ya --> H4[Staf wajib mengetik kode aset; tombol hapus aktif setelah cocok] --> H6
    H3 -- Tidak --> H5[Konfirmasi Hapus permanen] --> H6
    H6[(Hard delete: pinjaman, riwayat, file + storage, kode lama, aset)] --> L[[Daftar Aset]]
```

## 4. Scan QR

```mermaid
flowchart TD
    A([Mulai]) --> A1{Cara scan}

    A1 -- Kamera HP biasa --> Q1[Buka link QR /aset/public_token tanpa login]
    Q1 --> Q2{Token valid & aset masih ada?}
    Q2 -- Tidak --> Q2a[Pesan Aset tidak ditemukan] --> Z
    Q2 -- Ya --> Q3[Halaman publik: data aset read-only + tombol aksi Khusus staf SHD]
    Q3 -- Hanya melihat --> Z
    Q3 -- Menekan aksi --> Q4{Sudah login?}
    Q4 -- Belum --> Q5[Login, lalu kembali otomatis] --> Q6
    Q4 -- Sudah --> Q6{Aksi masih berlaku untuk status aset terkini?}
    Q6 -- Tidak --> Q6a[Popup hasil dengan pesan alasannya, mis. aset tidak punya peminjaman aktif] --> D
    Q6 -- Ya, Pinjam --> P
    Q6 -- Ya, Terima kembali --> R
    Q6 -- Ya, aksi lain --> FA

    A1 -- Tombol Scan QR di Dashboard --> B[Arahkan kamera atau ketik kode; jika kamera gagal, ketik kode]
    B --> C{Aset ditemukan dari kode, kode lama, atau token?}
    C -- Tidak --> C1[Pesan Aset tidak ditemukan] --> B
    C -- Ya --> D[Popup hasil: nama, kode, status, kondisi, jenis, spesifikasi, sumber, lokasi asal; jika dipinjam: lokasi pinjam & peminjam; peringatan jika memakai kode lama]
    D --> E{Status aset}
    E -- Tersedia --> E1[Pinjam, Pindah lokasi, Catat kerusakan, Catat kehilangan, Catat tidak layak pakai]
    E -- Dipinjam --> E2[Terima kembali, Pindah lokasi, Catat kerusakan]
    E -- Perbaikan --> E3[Pindah lokasi, Catat kerusakan, Catat kehilangan, Catat tidak layak pakai]
    E -- Hilang --> E4[Tandai ditemukan, Pindah lokasi]
    E -- Tidak Layak Pakai --> E5[Pindah lokasi]
    E1 & E2 & E3 & E4 & E5 --> G{Pilih aksi}

    G -- Tutup --> B
    G -- Buka detail aset --> DA[[Detail Aset]]
    G -- Pinjam --> P[[Peminjaman: form terisi aset]]
    G -- Terima kembali --> G1{Ada peminjaman aktif?}
    G1 -- Tidak --> G2[Pesan Aset ini tidak punya peminjaman aktif, popup tetap terbuka] --> D
    G1 -- Ya --> R[[Peminjaman: modal terima kembali terbuka]]
    G -- Aksi lain --> FA{Form aksi}

    FA -- Batal --> D

    FA -- Pindah lokasi --> F1[Pilih / tambah gedung & ruangan tujuan, catatan opsional] --> F1a[(Update lokasi asal, riwayat Pindah Lokasi)] --> H
    FA -- Catat kerusakan / kehilangan / tidak layak pakai --> F2[[Form & aturan sama dengan Detail Aset]] --> H
    FA -- Tandai ditemukan --> F3[[Form & aturan sama dengan Detail Aset]] --> F4{Status menjadi Tersedia?}
    F4 -- Tidak --> H
    F4 -- Ya --> F5{Pilih Langsung pinjamkan?}
    F5 -- Ya --> P
    F5 -- Tidak --> H
    H[(Muat ulang data aset)] --> D

    Z([Selesai])
```

## 5. Peminjaman

```mermaid
flowchart TD
    A([Mulai]) --> B[Form catat peminjaman, tabel Sedang dipinjam + tempo, riwayat + filter status & cari]
    B --> C{Pilih proses}

    C -- Pinjam --> P1[Aset terisi otomatis dari detail / scan, atau ketik kode / nama dengan saran aset Tersedia, atau scan kamera]
    P1 --> P2{Aset ada & status Tersedia?}
    P2 -- Tidak --> P3[Pesan aset tidak dapat dipinjam] --> P1
    P2 -- Ya --> P3a{Kondisi fisik sama dengan catatan?}
    P3a -- Tidak --> P3b[[Catat kerusakan di Detail Aset dulu]] --> P1
    P3a -- Ya --> P4[Input peminjam, departemen, keperluan, gedung & ruangan tujuan atau catatan lokasi, tgl pinjam default hari ini, rencana kembali, no. / tanggal approval; kondisi keluar otomatis]
    P4 --> P5{Tgl pinjam tidak di masa depan & tidak sebelum aset terdaftar; rencana kembali >= tgl pinjam?}
    P5 -- Tidak --> P4
    P5 -- Ya --> P6[(Transaksi Dipinjam, status aset Dipinjam, riwayat Dipinjam)] --> B

    C -- Terima kembali --> R0{Dibuka dari}
    R0 -- Baris Sedang dipinjam --> R1[Modal terima kembali]
    R0 -- Hasil scan --> R0a{Peminjaman masih aktif?}
    R0a -- Tidak --> R0b[Pesan peminjaman sudah selesai atau tidak ditemukan, tanpa modal] --> B
    R0a -- Ya --> R1
    R1 --> R1a{Hasil}
    R1a -- Hilang saat dipinjam --> H1[Input tanggal & catatan wajib] --> H2[(Transaksi Hilang, status aset Hilang, riwayat Lapor Hilang pihak = peminjam)] --> B
    R1a -- Kembali --> R2[Input tanggal, kondisi, gedung & ruangan penyimpanan default lokasi asal]
    R2 --> R3{Kondisi}
    R3 -- Baik --> R4[Status Tersedia, catatan opsional]
    R3 -- Rusak Ringan --> R6[Staf pilih Tersedia default / Perbaikan, catatan wajib]
    R3 -- Rusak Berat --> R5[Staf pilih Perbaikan default / Tidak Layak Pakai, catatan wajib]
    R4 & R5 & R6 --> R10[(Transaksi Dikembalikan, riwayat Dikembalikan; jika ruangan berbeda, lokasi asal pindah + riwayat Pindah Lokasi; jika Tidak Layak Pakai, riwayat Tidak Layak Pakai)] --> B

    C -- Batalkan --> X1[Menu baris Sedang dipinjam: Batalkan] --> X2[Input alasan wajib] --> X3[(Transaksi Dibatalkan, status aset mengikuti kondisi aset, riwayat Dibatalkan)] --> B

    C -- Ekspor Excel --> E1[(Laporan Peminjaman .xlsx sesuai status & pencarian aktif)] --> B
    C -- Pindah menu --> Z([Selesai])
```

## 6. Kelengkapan Ruangan

```mermaid
flowchart TD
    A([Mulai]) --> A1[Otomatis membuka gedung & ruangan pertama]
    A1 --> B[Pilih gedung & ruangan, lencana jumlah kurang, semua ruangan tampil; dua bagian: Aset di ruangan & Barang habis pakai]
    B --> C{Pilih aksi}

    C -- Tambah gedung / ruangan --> N1[Input nama, lantai ditulis di nama ruangan] --> N2[(Simpan, slug otomatis, buka data baru)] --> B

    C -- Atur standar jenis aset --> S1[Isi jumlah standar; bawaan 0, tampil jumlah Ada tanpa Hasil] --> S2{Standar}
    S2 -- 0 --> S3[(Hapus baris standar bila ada)] --> B
    S2 -- Lebih dari 0 --> S4[(Simpan: tampil Standar, Ada, Selisih, Hasil; tetap tampil walau aset 0)] --> B

    C -- Tambah / Ubah barang --> S5[Pilih barang dari Stok, jumlah standar minimal 1] --> S6[(Simpan, sekali per ruangan)] --> B
    C -- Lepas barang --> S7[Konfirmasi] --> S8[(Hapus baris standar, stok tidak berubah)] --> B

    C -- Cek ruangan --> K3[(Ada aset dihitung otomatis: lokasi asal di ruangan ini, bukan Dipinjam, Perbaikan, Hilang, Tidak Layak Pakai)]
    K3 --> K4[Stok yang tersimpan di ruangan ini hanya informasi; staf mengisi Ada barang habis pakai; selisih & hasil langsung terhitung di layar]
    K4 --> K5[(Simpan hasil cek + waktu & staf; isian kosong = Belum dicek, bukan kurang)]
    K5 --> K6{Ada baris Kurang dengan standar lebih dari 0?}
    K6 -- Tidak --> B
    K6 -- Ya --> K7{Bagian}
    K7 -- Aset --> K8[Tanpa aksi kekurangan; kode aset menaut ke Detail Aset] --> B
    K7 -- Barang, stok masih ada --> F1[Klik Isi dari stok: popup jumlah default kekurangan, maks. sisa stok, peringatan jika melebihi standar, keterangan opsional; stok kurang → tautan Ajukan permintaan] --> F2[(Transaksi Pemakaian dengan ruangan tujuan dan keterangan, Ada bertambah)]
    F2 --> F3{Masih kurang?}
    F3 -- Tidak --> B
    F3 -- Ya --> F4[Sisa tetap tercatat kurang; stok 0 berganti ke Ajukan permintaan] --> B
    K7 -- Barang, stok 0 --> K9[Klik Ajukan permintaan: form terisi barang, penyedia, jumlah, ruangan] --> K10[[Permintaan]]

    C -- Ekspor Excel --> X1[(Laporan Kelengkapan .xlsx gedung aktif: sheet Ringkasan & Detail)] --> B
    C -- Pindah menu --> Z([Selesai])
```

## 7. Permintaan ke Departemen Lain

```mermaid
flowchart TD
    A([Mulai]) --> B[Saran permintaan dari stok Menipis/Habis per departemen penyedia, katalog, riwayat + filter status & cari; Diajukan yang lewat lead time tampil Belum direspons]
    B --> C{Pilih aksi}

    C -- Kelola katalog --> K0{Aksi katalog}
    K0 -- Tambah / Ubah --> K1[Input item, departemen penyedia, perlu dikembalikan, lead time, catatan] --> K2[(Simpan katalog)] --> B
    K0 -- Hapus --> K3[(Ambil pemakaian katalog)] --> K4[Popup jumlah permintaan yang tautannya dilepas] --> K5[(Hapus katalog, tautan dikosongkan, riwayat tetap)] --> B

    C -- Buat permintaan --> P0{Dibuka dari}
    P0 -- Tombol Buat permintaan --> P1[Form kosong]
    P0 -- Saran / Kelengkapan Ruangan / Stok --> P1a[Form terisi nama barang, penyedia, jumlah, ruangan; tertaut barang stok, tanpa katalog]
    P1 & P1a --> P2[Pilih item katalog atau Item lain + departemen penyedia; pengembalian barang default dari katalog; cara permintaan Memo, Form, Email, WhatsApp, Lainnya; jumlah, tanggal, kegiatan, ruangan, catatan]
    P2 --> P3[(Simpan status Diajukan; item diganti melepas tautan; snapshot lead time & pengembalian)]
    P3 --> P4[Staf mengirim permintaan di luar sistem] --> B

    C -- Aksi baris riwayat --> U0{Status permintaan}
    U0 -- Dikembalikan / Dibatalkan --> U3[Tanpa aksi] --> B
    U0 -- Diterima sebagian --> U4{Aksi}
    U4 -- Catat barang diterima --> M1
    U4 -- Batalkan sisa --> M4[Popup dampak] --> I4[(Status Dibatalkan; stok yang sudah masuk tetap, tampil diterima N dari M)] --> B
    U0 -- Diajukan --> U1{Aksi}
    U1 -- Ubah --> P2
    U1 -- Catat barang diterima / Tandai Diterima --> M1[Barang stok: isi jumlah yang datang; catatan opsional] --> I1[(Barang stok: Barang Masuk sesuai jumlah; kurang dari sisa = Diterima sebagian, penuh = Diterima; tanpa tautan: Diterima, stok tidak berubah)] --> B
    U1 -- Tandai Dibatalkan --> M2[Popup dampak, catatan opsional] --> I2[(Status Dibatalkan; tidak mengubah apa pun)] --> B
    U0 -- Diterima --> U2{Aksi}
    U2 -- Tandai Dikembalikan, jika pengembalian dipilih Dikembalikan --> M3[Popup dampak, catatan opsional]
    U2 -- Tandai Dibatalkan --> M3
    M3 --> I3[(Status baru; transaksi Koreksi mengurangi jumlah yang sudah masuk, minimal 0; jika berhenti di 0 tampil peringatan cek manual)] --> B

    C -- Ekspor Excel --> X1[(Laporan Permintaan .xlsx sesuai status & pencarian aktif)] --> B
    C -- Pindah menu --> Z([Selesai])
```

## 8. Stok Habis Pakai

```mermaid
flowchart TD
    A([Mulai]) --> B[Daftar barang: jumlah, minimum, status, lokasi simpan ruangan & gedung, departemen penyedia; filter status, gedung, ruangan & cari]
    B --> C{Pilih aksi}

    C -- Tambah / Ubah data --> T1[Input nama unik, kategori pilih atau tambah baru, satuan, minimum, minta ke, lokasi simpan gedung lalu ruangan gudang, ruangan baru otomatis gudang, catatan; jumlah awal hanya saat tambah, dicatat sebagai saldo awal] --> T2[(Simpan)] --> CEK

    C -- Catat transaksi --> J0[Pilih jenis: Barang Masuk, Pemakaian ruangan opsional, Koreksi keterangan wajib, Penghapusan alasan wajib; jumlah, keterangan]
    J0 --> J3{Hasil >= 0?}
    J3 -- Tidak --> J4[Tolak: stok tidak cukup] --> B
    J3 -- Ya --> J5[(Simpan transaksi & jumlah stok dengan lock)] --> CEK

    C -- Kartu stok --> KS[Riwayat transaksi barang, terbaru lebih dulu] --> B

    R0[Isi dari stok di Kelengkapan Ruangan] --> R1[(Transaksi Pemakaian dengan ruangan tujuan, tidak pernah negatif)] --> CEK
    R2[Status permintaan tertaut barang ini berubah] --> R3[(Barang datang: Barang Masuk sesuai jumlah diterima; Dikembalikan / Dibatalkan dari Diterima: Koreksi, minimal 0)] --> CEK

    C -- Hapus --> H00{Ada transaksi selain saldo awal?}
    H00 -- Ya --> H01[Tolak: catat Penghapusan agar stok menjadi 0; barang tetap tercatat] --> B
    H00 -- Tidak --> H0[(Ambil usage: ruangan yang standarnya memakai barang ini)]
    H0 --> H1{Dipakai di standar?}
    H1 -- Ya --> H2[Peringatan: hapus permanen, barang ikut terlepas dari ruangan]
    H1 -- Tidak --> H3[Konfirmasi hapus permanen]
    H2 & H3 --> H4[(Hard delete beserta baris standar ruangan yang memakainya)] --> B

    C -- Menu Ajukan / Lihat permintaan, penyedia departemen lain --> S6a[[Permintaan]]

    C -- Ekspor Excel --> X1[(Laporan Stok .xlsx sesuai status & pencarian aktif)] --> B
    C -- Pindah menu --> Z([Selesai])

    CEK{Jumlah <= minimum?}
    CEK -- Tidak --> S1[Status Aman] --> B
    CEK -- Ya --> S2[Status Menipis, atau Habis jika 0] --> S3[Peringatan dashboard + masuk saran permintaan ke departemen minta ke] --> S4{Penyedia departemen lain?}
    S4 -- Tidak --> B
    S4 -- Ya, belum ada permintaan --> S5a[Tautan Ajukan permintaan: form terisi item, departemen, jumlah, tanpa katalog] --> S6a
    S4 -- Ya, sudah Diajukan --> S7a[Tautan Diajukan · tanggal ke permintaan itu] --> S6a
```

## 9. Data Master

```mermaid
flowchart TD
    A([Mulai]) --> B[Tab Gedung & Ruangan, Departemen, Kategori & Jenis aset dikelompokkan per kategori, Kategori stok]
    B --> C{Pilih aksi}

    C -- Departemen SHD --> C1[Tanpa aksi: tidak dapat diubah atau dihapus] --> B

    C -- Tambah / Ubah --> T1[Input nama; jenis aset juga kategori wajib dan kode singkat: saat tambah kosong = otomatis, saat ubah hanya berlaku untuk aset baru] --> T2{Data valid?}
    T2 -- Tidak --> T3[Tampilkan pesan, mis. kode pernah dipakai jenis lain, kategori wajib dipilih] --> T1
    T2 -- Ya --> T4[(Simpan; slug gedung & ruangan tidak berubah)] --> B

    C -- Hapus --> H1[(Ambil pemakaian data)] --> H2{Masih dipakai?}
    H2 -- Ya --> H3[Popup rincian pemakaian, tanpa tombol hapus] --> B
    H2 -- Tidak --> H4[Konfirmasi Hapus permanen] --> H5[(Hapus permanen)] --> B

    C -- Pindah menu --> Z([Selesai])
```
