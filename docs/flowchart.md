# Flowchart — Sistem Informasi Aset SHD

Tempel tiap blok ke mermaid.live atau draw.io (Arrange → Insert → Advanced → Mermaid).
Simbol silinder `[( )]` = proses sistem/database.

## 1. Alur Utama

```mermaid
flowchart TD
    A([Start]) --> B[Staf SHD membuka web]
    B --> C{Token masih valid?}
    C -- Ya --> E
    C -- Tidak --> D[Input username & password]
    D --> D1{Login valid?}
    D1 -- Tidak --> D2[Tampilkan pesan gagal] --> D
    D1 -- Ya --> E[Dashboard: ringkasan, jatuh tempo, servis, kesiapan ruangan, stok menipis]
    E --> F{Pilih menu}
    F -- Scan QR --> M1[[Scan QR]]
    F -- Daftar Aset --> M2[[Daftar Aset]]
    F -- Peminjaman --> M3[[Peminjaman]]
    F -- Kelengkapan Ruangan --> M4[[Kelengkapan Ruangan]]
    F -- Permintaan ke Unit Lain --> M5[[Permintaan]]
    F -- Stok Habis Pakai --> M6[[Stok Habis Pakai]]
    F -- Ekspor laporan --> M7[Pilih laporan, filter, format PDF/Excel] --> M8[(Generate file)] --> F
    M1 & M2 & M3 & M4 & M5 & M6 --> F
    F -- Logout --> Z[(Hapus token)] --> Z1([End])
```

## 2. Daftar Aset

```mermaid
flowchart TD
    A([Mulai]) --> B[Daftar aset + filter kategori, gedung, ruangan, sumber, kondisi, status, cari nama/kode/S-N/kode lama]
    B --> C{Pilih aksi}

    C -- Tambah --> T1[Input jenis, nama, merek, S/N, spesifikasi, sumber, gedung & ruangan, PIC, kode keuangan, tgl perolehan, servis berikutnya, kondisi]
    T1 --> T1a{Kondisi}
    T1a -- Baik --> T1b[Status Tersedia]
    T1a -- Rusak Berat --> T1c[Status Perbaikan]
    T1a -- Rusak Ringan --> T1d[Staf pilih Tersedia default / Perbaikan]
    T1b & T1c & T1d --> T2{Data valid?}
    T2 -- Tidak --> T1
    T2 -- Ya --> T3[(Simpan, kode otomatis SHD-JENIS-NNN, riwayat Registrasi)] --> B

    C -- Detail --> D1[Data aset, lokasi saat ini, kode lama, foto & dokumen, riwayat]
    D1 --> D2{Aksi di detail}
    D2 -- Ubah data --> E0{Jenis diganti?}
    E0 -- Ya --> E2[Peringatan: kode berubah ke kode baru, pratinjau code-preview] --> E3{Lanjut?}
    E3 -- Tidak --> D1
    E3 -- Ya --> E4[(Kode baru dari nomor berikutnya jenis baru, kode lama disimpan & tetap bisa dicari, riwayat Ubah Data)] --> D1
    E0 -- Tidak --> E1[(Simpan, riwayat Ubah Data / Pindah Lokasi)] --> D1
    D2 -- Catat kerusakan --> R0{Status Hilang?}
    R0 -- Ya --> R0a[Tolak: tandai ditemukan dulu] --> D1
    R0 -- Tidak --> R2[Pilih kondisi Rusak Ringan/Berat & catatan] --> R2a{Sedang dipinjam?}
    R2a -- Ya --> R3a[(Kondisi diperbarui, status tetap Dipinjam, riwayat Lapor Rusak)] --> D1
    R2a -- Tidak --> K1
    D2 -- Catat kehilangan --> R1{Sedang dipinjam?}
    R1 -- Ya --> R1a[Tolak: selesaikan lewat peminjaman] --> D1
    R1 -- Tidak --> R4[(Status Hilang, riwayat Lapor Hilang)] --> D1
    D2 -- Tandai ditemukan, khusus Hilang --> F1[Pilih kondisi] --> K1
    D2 -- Catat servis --> S0{Dipinjam / Hilang?}
    S0 -- Ya --> S0a[Tolak] --> D1
    S0 -- Tidak --> S1[Input tanggal, vendor, kondisi setelah servis, servis berikutnya] --> K1

    K1{Kondisi → status}
    K1 -- Baik --> K2[Tersedia]
    K1 -- Rusak Berat --> K3[Perbaikan]
    K1 -- Rusak Ringan --> K4[Staf pilih Tersedia default / Perbaikan]
    K2 & K3 & K4 --> K5[(Simpan + riwayat Lapor Rusak / Ditemukan / Servis)] --> K6{Dari tandai ditemukan & Tersedia?}
    K6 -- Ya --> K7[Tawarkan 'Langsung pinjamkan'] --> P
    K6 -- Tidak --> D1
    D2 -- Upload file --> U1[Pilih jenis & file] --> U2[(Simpan file)] --> D1
    D2 -- Catat peminjaman --> P[[Peminjaman]]
    D2 -- Catat tidak layak pakai --> TL0{Status Tersedia / Perbaikan?}
    TL0 -- Tidak --> TL1[Tolak; Dipinjam: selesaikan lewat peminjaman] --> D1
    TL0 -- Ya --> TL2[Input catatan] --> TL3[(Status Tidak Layak Pakai, riwayat Tidak Layak Pakai; hanya bisa Pindah lokasi / Hapus aset)] --> D1
    D2 -- Hapus aset --> H1[Ambil delete-impact: jumlah pinjaman, pinjaman aktif & peminjam, riwayat, file, standar ruangan terpengaruh]
    H1 --> H2{Punya riwayat pinjam / sedang dipinjam?}
    H2 -- Ya --> H3[Popup: staf wajib mengetik kode aset] --> H4{Kode cocok?}
    H4 -- Tidak --> H3a[Tolak: Kode konfirmasi tidak cocok] --> D1
    H2 -- Tidak --> H5[Popup konfirmasi biasa]
    H4 -- Ya --> H6
    H5 --> H6[(Hard delete permanen: pinjaman, riwayat, file + storage, kode lama, aset)] --> B

    C -- Cetak label --> L1[Pilih aset, maks. 120] --> L1a{Ada aset yang tidak ditemukan?}
    L1a -- Ya --> L1b[Tolak] --> L1
    L1a -- Tidak --> L2[(PDF A4 3x4 per halaman: QR 5x5 cm berisi link /aset/token + kode, urut kode)] --> L3[Cetak, potong, tempel, uji scan kamera HP] --> B
    C -- Kembali --> Z([Selesai])
```

## 3. Scan QR

```mermaid
flowchart TD
    A([Mulai]) --> A1{Cara scan}
    A1 -- Kamera HP biasa --> Q1[Buka link QR /aset/public_token]
    Q1 --> Q2{Token valid & aset masih ada?}
    Q2 -- Tidak --> C1
    Q2 -- Ya --> Q3[Halaman publik: data aset + tombol aksi 'Khusus staf SHD']
    Q3 --> Q4{Staf menekan aksi; sudah login?}
    Q4 -- Belum --> Q5[Login] --> Q6[Kembali otomatis ke form aksi, aset terisi]
    Q4 -- Sudah --> Q6
    Q6 --> E
    A1 -- Menu Scan staf --> B[Tekan Scan, arahkan kamera / ketik kode]
    B --> C{QR terbaca & aset ditemukan?}
    C -- Tidak --> C1[Aset tidak ditemukan] --> B
    C -- Ya --> D[Tampilkan nama, kode, spesifikasi, sumber, lokasi asal, lokasi pinjam, kondisi, status]
    D --> E{Status aset}
    E -- Tersedia --> E1[Pinjam / Pindah lokasi / Catat kerusakan / Catat kehilangan]
    E -- Dipinjam --> E2[Terima kembali / Batalkan staf / Pindah lokasi / Catat kerusakan]
    E -- Perbaikan --> E3[Pindah lokasi / Catat kerusakan / Catat kehilangan]
    E -- Hilang --> E4[Tandai ditemukan / Pindah lokasi]
    E1 -- Pinjam --> P[[Form Peminjaman]]
    E2 -- Terima kembali --> R[[Form Pengembalian]]
    E2 -- Batalkan --> X[[Form Pembatalan]]
    E1 & E2 & E3 & E4 -- Pindah lokasi --> G1[Pilih gedung & ruangan baru] --> G2[(Update lokasi asal, riwayat Pindah Lokasi)] --> Z
    E1 & E2 & E3 -- Catat kerusakan / kehilangan --> L[[Catat kerusakan / kehilangan]]
    E4 -- Tandai ditemukan --> T[[Tandai ditemukan]]
    P & R & X & L & T --> Z([Selesai])
```

## 4. Peminjaman

```mermaid
flowchart TD
    A([Mulai]) --> B{Pilih proses}

    B -- Pinjam --> P1[Scan QR / ketik kode aset]
    P1 --> P2{Aset ada & status Tersedia?}
    P2 -- Tidak --> P3[Tampilkan aset tidak bisa dipinjam] --> Z
    P2 -- Ya --> P3a{Kondisi fisik sama dengan catatan?}
    P3a -- Tidak --> P3b[[Catat kerusakan dulu]] --> P1
    P3a -- Ya --> P4[Input peminjam, unit kerja, keperluan, gedung & ruangan tujuan, tgl pinjam default hari ini, rencana kembali, no. approval; kondisi keluar otomatis]
    P4 --> P5{Tgl pinjam tidak di masa depan & tidak sebelum aset terdaftar; rencana kembali >= tgl pinjam?}
    P5 -- Tidak --> P4
    P5 -- Ya --> P6[(Transaksi Dipinjam, status aset Dipinjam, riwayat Dipinjam)] --> Z

    B -- Terima kembali --> R1[Pilih transaksi aktif / scan]
    R1 --> R1a{Hasil}
    R1a -- Hilang --> H1[Input tanggal & catatan kehilangan] --> H2[(Transaksi Hilang, status aset Hilang, riwayat Lapor Hilang pihak = peminjam; tanpa konfirmasi)] --> Z
    R1a -- Kembali --> R2[Cek fisik, pilih kondisi, ruangan penyimpanan default lokasi asal, tanggal kembali]
    R2 --> R3{Kondisi}
    R3 -- Baik --> R4[Status Tersedia]
    R3 -- Rusak Berat --> R5[Status Perbaikan, catatan wajib]
    R3 -- Rusak Ringan --> R6[Staf pilih Tersedia default / Perbaikan, catatan wajib]
    R4 & R5 & R6 --> R7{Lebih buruk dari kondisi keluar?}
    R7 -- Ya --> R8[Tandai Menunggu Konfirmasi]
    R7 -- Tidak --> R9[Tanpa konfirmasi]
    R8 & R9 --> R10[(Transaksi Dikembalikan, riwayat Dikembalikan; jika ruangan berbeda, lokasi asal pindah + riwayat Pindah Lokasi)] --> Z

    B -- Konfirmasi kerusakan --> K1[Peminjam mengonfirmasi di luar sistem] --> K2[Input catatan konfirmasi wajib, no. dokumen opsional] --> K3[(Tandai Dikonfirmasi, waktu & staf otomatis)] --> Z

    B -- Batalkan --> X1[Pilih transaksi Dipinjam] --> X2[Input alasan] --> X3[(Transaksi Dibatalkan, status aset mengikuti kondisi aset, riwayat Dibatalkan)] --> Z

    Z([Selesai])
```

## 5. Kelengkapan Ruangan

```mermaid
flowchart TD
    A([Mulai]) --> B[Daftar ruangan per gedung + status Lengkap/Kurang]
    B --> C{Pilih aksi}

    C -- Atur standar --> S1[Pilih / tambah gedung] --> S2[Pilih / tambah ruangan]
    S2 --> S6[Input perlengkapan, sumber, jumlah standar, cara hitung QR/Manual, pemenuhan: stok SHD / ambil dari / minta ke]
    S6 --> S7[(Simpan standar)] --> B

    C -- Cek ruangan --> K1[Pilih gedung & ruangan] --> K2[Checklist: standar, ada, selisih]
    K2 --> K3[(Jumlah ada QR dihitung otomatis)]
    K3 --> K4[Staf input jumlah ada untuk item Manual]
    K4 --> K5[(Simpan hasil cek + waktu & staf; item Manual kosong = Belum dicek, bukan kurang)]
    K5 --> K6{Ada kekurangan?}
    K6 -- Tidak --> B
    K6 -- Ya --> K7{Cara pemenuhan}
    K7 -- Stok SHD --> F1[Klik Isi dari stok] --> F2[(Isi = min kekurangan, stok; stok berkurang, Ada bertambah)]
    F2 --> F3{Masih kurang?}
    F3 -- Tidak --> B
    F3 -- Ya --> F4[Sisa tetap tercatat kurang, stok habis masuk saran permintaan] --> B
    K7 -- Ambil dari --> K8[Tampilkan lokasi pengambilan] --> B
    K7 -- Minta ke unit lain --> K9[Tampilkan unit penyedia & tombol Buat permintaan] --> K10[[Permintaan]]

    C -- Kembali --> Z([Selesai])
```

## 6. Permintaan ke Unit Lain

```mermaid
flowchart TD
    A([Mulai]) --> B[Katalog perlengkapan unit lain + riwayat permintaan]
    B --> C{Pilih aksi}

    C -- Kelola katalog --> K1[Input item, unit penyedia, cara permintaan, perlu dikembalikan, lead time] --> K2[(Simpan katalog)] --> B

    C -- Buat permintaan --> P1[Pilih item katalog / dari saran kekurangan]
    P1 --> P2[Input tanggal, jumlah, kegiatan, ruangan terkait]
    P2 --> P3[(Simpan status Diajukan)]
    P3 --> P4[Staf mengirim permintaan di luar sistem] --> B

    C -- Update status --> U1{Barang sudah diterima?}
    U1 -- Belum, lewat lead time --> U2[Tampil Belum direspons] --> B
    U1 -- Ya --> U3[(Status Diterima)] --> U4{Perlu dikembalikan?}
    U4 -- Tidak --> B
    U4 -- Ya --> U5[Setelah dipakai, barang dikembalikan] --> U6[(Status Dikembalikan)] --> B
    U1 -- Batal --> U7[(Status Dibatalkan)] --> B

    C -- Kembali --> Z([Selesai])
```

## 7. Stok Habis Pakai

```mermaid
flowchart TD
    A([Mulai]) --> B[Daftar barang habis pakai milik SHD + status]
    B --> C{Pilih aksi}
    C -- Tambah / Ubah --> T1[Input nama unik, kategori, satuan, jumlah awal, minimum, minta ke, katalog dari unit yang sama, lokasi simpan] --> T2[(Simpan)] --> CEK
    C -- Ubah jumlah --> J0{Cara}
    J0 -- Set langsung --> J1[Input jumlah baru >= 0]
    J0 -- Tambah / kurang --> J2[Input penyesuaian +/-]
    J1 & J2 --> J3{Hasil >= 0?}
    J3 -- Tidak --> J4[Tolak: stok tidak cukup] --> B
    J3 -- Ya --> J5[(Simpan dengan lock)] --> CEK
    R0[Isi dari stok di Kelengkapan Ruangan] --> R1[(Stok berkurang otomatis, tidak pernah negatif)] --> CEK
    C -- Hapus --> H0[Ambil usage: ruangan yang standarnya memakai barang ini]
    H0 --> H1{Dipakai di standar?}
    H1 -- Ya --> H2[Peringatan: hapus permanen, tautan standar akan dilepas]
    H1 -- Tidak --> H3[Konfirmasi hapus permanen]
    H2 & H3 --> H4[(Hard delete; consumable_id di standar terkait dikosongkan, standar tetap utuh)] --> B
    CEK{Jumlah <= minimum?}
    CEK -- Tidak --> S1[Status Aman] --> B
    CEK -- Ya --> S2[Status Menipis/Habis] --> S3[Peringatan dashboard + masuk saran permintaan ke unit 'minta ke'] --> B
    C -- Kembali --> Z([Selesai])
```
