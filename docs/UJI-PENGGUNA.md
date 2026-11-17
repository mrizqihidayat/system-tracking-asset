# Uji Coba Pengguna per Fitur & Kuesioner SUS — Sistem Informasi Aset SHD

Panduan singkat untuk menguji aplikasi **per fitur** dengan pengguna sungguhan, lalu mengisi kuesioner SUS (System Usability Scale). Tiap fitur hanya punya 3–6 langkah pendek, dan tiap peserta tidak harus mencoba semuanya: pilih **5–6 fitur yang paling dekat dengan pekerjaannya** supaya satu sesi selesai dalam **±30 menit**. Disarankan 5 peserta atau lebih (staf SHD).

File ini punya tiga bagian. Fasilitator memakai Bagian A dan D; peserta cukup menerima Bagian B dan C (cetak mulai dari garis "UNTUK PESERTA").

---

## BAGIAN A — Untuk fasilitator

### Persiapan (±10 menit, sekali saja)
1. Jalankan semuanya dengan satu perintah: `cd backend && composer run dev`.
2. Jika database baru: `php artisan migrate --seed`, lalu **`php artisan employees:sync`** (mengisi data pegawai dan departemen; wajib supaya pegawai bisa login). Detailnya ada di `README.md`.
3. Buka aplikasi di `http://localhost:5173`. Untuk fitur 11 (tampilan HP) siapkan satu HP; fitur Scan QR membutuhkan kamera.
4. Siapkan akun peserta (password awal sama dengan NIK):

   | Peserta | Username | Password |
   |---|---|---|
   | P1 | `90100001` | `90100001` |
   | P2 | `90100002` | `90100002` |
   | P3 | `90100003` | `90100003` |

   Peserta ke-4 dan seterusnya memakai `staf1` / `inalum2026` (akun eksternal; tab Akun di Pengaturan tidak tersedia untuknya). Jika password sebuah akun sudah diganti, minta pegawai SHD lain mereset akun itu di **Pengaturan > Akun**.
5. Data demo sebaiknya masih segar. Jika sudah berantakan, minta pengembang mereset database demo (perintahnya dijalankan pengembang sendiri) lalu ulangi langkah 2.

### Cara memandu
- Bacakan pengantar Bagian B dan tunjukkan **fitur mana saja** yang dikerjakan peserta ini. Jangan menunjukkan tombol; yang diuji aplikasinya, bukan pesertanya.
- Duduk di samping dan amati. Bantu hanya jika peserta buntu lebih dari **1 menit**, dan beri tanda "dibantu".
- Garis **Hasil** di tiap fitur boleh diisi peserta atau fasilitator. Cukup satu tanda dan satu baris catatan (apa yang membingungkan, salah klik, atau kata yang tidak dimengerti).
- Setelah fitur selesai, minta peserta mengisi Bagian C **sendiri dan spontan**.

---

## UNTUK PESERTA — cetak mulai dari sini

## BAGIAN B — Fitur yang dicoba

Kode peserta: ______  Perangkat: laptop / HP  Tanggal: ______

**Terima kasih sudah membantu.** Yang kami uji adalah **aplikasinya, bukan Anda**: jika ada yang membingungkan, itu kekurangan aplikasi dan itu informasi yang sangat berguna. Tidak ada jawaban benar atau salah. Silakan bicara keras bila ada yang membingungkan. Semua data yang dipakai hanya data contoh.

Kerjakan fitur yang ditunjuk pendamping. Setiap fitur berisi hal yang perlu Anda **coba**; carilah sendiri caranya. Setelah selesai, beri tanda pada **Hasil**.

### 1. Masuk & akun
- Masuk memakai akun yang diberikan pendamping.
- (Opsional) Ganti password Anda, lalu keluar dan masuk lagi.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 2. Dashboard
- Lihat ringkasan di halaman awal: berapa peminjaman yang terlambat atau jatuh tempo, dan barang apa yang stoknya menipis?
- Dari salah satu kartu atau panel, pindah ke halaman yang terkait.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 3. Daftar Aset
- Cari sebuah aset dengan kata kunci, lalu persempit dengan filter (mis. gedung atau status).
- Buka detail aset dan baca riwayatnya.
- **Tambahkan aset baru**: sebuah *Proyektor*, sumber *SHD*, di salah satu ruangan di Gedung IHS, kondisi Baik. Catat kode aset yang diberikan sistem.
- Ubah satu data aset tadi (mis. merek), lalu catat sebuah **kerusakan** atau **servis** dari halaman detailnya.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 4. Label QR & Scan QR
- Pilih beberapa aset di Daftar Aset, lalu **cetak label QR** (cukup sampai tampil, tidak perlu dicetak sungguhan).
- (Opsional, butuh kamera) Pakai **Scan QR** di Dashboard untuk memindai salah satu label, lalu lihat aksi yang tersedia untuk aset itu.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 5. Peminjaman
- **Catat peminjaman** sebuah aset yang tersedia untuk seorang pegawai (cari namanya, mis. ketik "Dewi"; departemennya terisi sendiri).
- Catat satu lagi dengan **tanggal pinjam di masa depan** (pemesanan), lalu **serahkan** pemesanan itu.
- **Terima kembali** salah satu aset dengan kondisi **Rusak Ringan**, sertakan foto kondisinya (foto apa saja untuk percobaan).
- Lihat riwayat peminjaman, dan coba **batalkan** satu peminjaman.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 6. Kelengkapan Ruangan
- Pilih gedung dan ruangan, lalu baca apakah ruangan itu lengkap atau masih ada yang kurang.
- Pada ruangan yang masih kurang, **isi satu barang dari stok**.
- Catat jumlah satu barang habis pakai yang ada di ruangan itu, lalu **tandai sudah dicek**.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 7. Permintaan ke Unit Lain
- Dari bagian **Saran permintaan**, buat permintaan ke satu departemen untuk beberapa barang sekaligus.
- **Catat permintaan baru** secara manual. Pada kolom Item, coba mengetik nama barang yang belum ada di daftar, dan tambahkan ke katalog.
- Ubah status satu permintaan menjadi **Diterima**, dan coba terima **sebagian** saja dari jumlah yang diminta.
- Pakai pencarian dan filter di riwayat, lalu buka tab **Katalog perlengkapan**.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 8. Stok Habis Pakai
- **Tambah satu barang** habis pakai baru.
- **Catat transaksi** untuk barang itu: satu barang masuk dan satu pemakaian, lalu buka **kartu stoknya**.
- Perhatikan status stok (Aman, Menipis, atau Habis) dan cari sebuah barang dengan kata kunci.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 9. Data Master
- Tambahkan sebuah **gedung** dan sebuah **ruangan** di dalamnya.
- Di bagian **Departemen**, ubah nama lengkap satu departemen, dan lihat informasi sinkron terakhir di atasnya.
- Tambahkan sebuah **jenis aset** baru beserta kategorinya.

(Hanya akun pegawai) Buka **Pengaturan > Akun**:
- Cari seorang pegawai dengan nama atau NIK, lalu coba filter **Jenis** dan **Status**.
- Tambahkan satu **akun eksternal** lewat tombol "+ Tambah akun eksternal".
- Lewat menu ⋮, buka **Lihat riwayat** pada sebuah akun.

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 10. Ekspor laporan
- Dari salah satu halaman daftar (mis. Daftar Aset atau Peminjaman), pilih sebuah filter lalu **unduh laporan Excel**.
- Buka file hasil unduhan: apakah isinya sesuai filter yang Anda pilih?

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

### 11. Tampilan di HP (opsional, bila memakai HP)
- Buka aplikasi di HP, pindah antarmenu, dan coba satu fitur di atas (mis. Daftar Aset atau Peminjaman).
- Perhatikan: apakah tulisan terbaca, tombol mudah dipencet, dan tidak ada bagian yang terpotong?

Hasil: ☐ Lancar ☐ Ada kendala ☐ Gagal  Catatan: ______________________________________

---

## BAGIAN C — Kuesioner SUS

Isilah **setelah fitur yang ditunjuk selesai dicoba**. Untuk setiap pernyataan, lingkari angka yang paling menggambarkan pendapat Anda tentang aplikasi tadi. Jawablah spontan; jangan melewatkan satu pun pernyataan.

**1 = Sangat tidak setuju  ·  2 = Tidak setuju  ·  3 = Netral  ·  4 = Setuju  ·  5 = Sangat setuju**

Kode peserta: ______  (jangan tulis nama)  Jabatan / lama bekerja di SHD (opsional): ______________

| No. | Pernyataan | 1 | 2 | 3 | 4 | 5 |
|---|---|---|---|---|---|---|
| 1 | Saya pikir saya akan sering menggunakan aplikasi ini. | ○ | ○ | ○ | ○ | ○ |
| 2 | Saya merasa aplikasi ini terlalu rumit padahal sebenarnya bisa dibuat lebih sederhana. | ○ | ○ | ○ | ○ | ○ |
| 3 | Saya pikir aplikasi ini mudah digunakan. | ○ | ○ | ○ | ○ | ○ |
| 4 | Saya pikir saya membutuhkan bantuan orang teknis untuk dapat menggunakan aplikasi ini. | ○ | ○ | ○ | ○ | ○ |
| 5 | Saya merasa berbagai fungsi dalam aplikasi ini terpadu dengan baik. | ○ | ○ | ○ | ○ | ○ |
| 6 | Saya pikir ada terlalu banyak hal yang tidak konsisten dalam aplikasi ini. | ○ | ○ | ○ | ○ | ○ |
| 7 | Saya membayangkan sebagian besar orang akan belajar menggunakan aplikasi ini dengan cepat. | ○ | ○ | ○ | ○ | ○ |
| 8 | Saya merasa aplikasi ini sangat membingungkan untuk digunakan. | ○ | ○ | ○ | ○ | ○ |
| 9 | Saya merasa sangat percaya diri menggunakan aplikasi ini. | ○ | ○ | ○ | ○ | ○ |
| 10 | Saya perlu belajar banyak hal sebelum saya bisa menggunakan aplikasi ini dengan baik. | ○ | ○ | ○ | ○ | ○ |

**Dua pertanyaan terbuka (boleh singkat):**

1. Fitur mana yang paling memudahkan Anda? ______________________________________________

2. Fitur mana yang paling membingungkan atau ingin Anda ubah? ________________________________

---

## BAGIAN D — Menghitung skor SUS (fasilitator)

Skor dihitung **per peserta**, lalu dirata-rata.

1. **Nomor ganjil (1, 3, 5, 7, 9):** skor = angka yang dilingkari **− 1**.
2. **Nomor genap (2, 4, 6, 8, 10):** skor = **5 − angka** yang dilingkari.
3. Jumlahkan kesepuluh skor (hasilnya 0–40), lalu **kalikan 2,5**. Hasilnya skor SUS peserta (0–100).
4. Skor akhir = **rata-rata** skor semua peserta.

Contoh: jawaban 4, 2, 5, 1, 4, 2, 5, 1, 4, 2 → ganjil: 3+4+3+4+3 = 17; genap: 3+4+3+4+3 = 17; jumlah 34 × 2,5 = **85**.

Mudah dihitung di spreadsheet: tulis jawaban 10 kolom (J1–J10), lalu skor =
`((J1-1)+(5-J2)+(J3-1)+(5-J4)+(J5-1)+(5-J6)+(J7-1)+(5-J8)+(J9-1)+(5-J10))*2.5`

### Cara membaca hasil
| Skor SUS | Arti kasar |
|---|---|
| di bawah 51 | Buruk, perlu perbaikan besar |
| 51 – 67 | Di bawah rata-rata |
| **68** | **Rata-rata umum** (patokan) |
| 69 – 79 | Baik |
| 80 – 89 | Sangat baik |
| 90 ke atas | Luar biasa |

### Merangkum hasil per fitur
Skor SUS menilai aplikasi secara keseluruhan. Untuk tahu **fitur mana yang perlu diperbaiki**, rangkum Bagian B: hitung berapa peserta yang menandai "Ada kendala" atau "Gagal" di tiap fitur, dan kumpulkan catatan yang disebut dua peserta atau lebih. Fitur dengan kendala terbanyak dan jawaban terbuka nomor 2 menjadi prioritas perbaikan.

| Fitur | Jumlah peserta mencoba | Lancar | Ada kendala | Gagal | Catatan yang berulang |
|---|---|---|---|---|---|
| 1. Masuk & akun | | | | | |
| 2. Dashboard | | | | | |
| 3. Daftar Aset | | | | | |
| 4. Label QR & Scan QR | | | | | |
| 5. Peminjaman | | | | | |
| 6. Kelengkapan Ruangan | | | | | |
| 7. Permintaan ke Unit Lain | | | | | |
| 8. Stok Habis Pakai | | | | | |
| 9. Data Master | | | | | |
| 10. Ekspor laporan | | | | | |
| 11. Tampilan di HP | | | | | |
