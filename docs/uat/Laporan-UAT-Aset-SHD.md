# Laporan UAT Sistem Informasi Aset SHD

Diperbarui 19 November 2026 · Laporan pertama 18 November 2026 · @SHD

Laporan pertama memuat 22 temuan dari 81 skenario yang diuji. Dari 22 temuan, 15 sudah teratasi dan tidak lagi ditampilkan di tabel. Tujuh temuan tidak dikerjakan sesuai keputusan 18 November dan tetap dicatat di bawah, dengan rincian 4 prioritas Sedang dan 3 prioritas Rendah. Kolom Keputusan sudah diisi sesuai keputusan itu. Semua perbaikan diuji ulang di browser pada laptop (1366×768) dan HP (390×844); hasilnya ada di bagian "Hasil uji ulang".

## Daftar temuan

| ID | Temuan | Area | Prioritas | Status | Keputusan |
|---|---|---|---|---|---|
| K2 | Akun eksternal (untuk departemen lain) bisa menghapus aset secara permanen dan mengubah data master. | Keamanan | Sedang | Tidak dikerjakan (18 Nov) | Tidak dikerjakan, 18 Nov |
| K3 | Halaman QR yang terbuka tanpa login menampilkan nama penanggung jawab dan kode aset keuangan. | Privasi | Sedang | Tidak dikerjakan (18 Nov) | Tidak dikerjakan, 18 Nov |
| A1 | Username yang diketik dengan huruf besar atau spasi ditolak sebagai "password salah", padahal keyboard HP sering membuat huruf pertama kapital. | Akun | Sedang | Tidak dikerjakan (18 Nov) | Tidak dikerjakan, 18 Nov |
| D2 | Tanggal yang tidak wajar diterima: tanggal perolehan 2030, tanggal permintaan 2030 atau 1990, rencana kembali 2099. | Data | Sedang | Tidak dikerjakan (18 Nov) | Tidak dikerjakan, 18 Nov |
| A4 | Jika dua tab atau dua staf mengubah data yang sama, yang kedua mendapat pesan teknis atau menimpa data tanpa peringatan. | Alur | Rendah | Tidak dikerjakan (18 Nov) | Tidak dikerjakan, 18 Nov |
| U8 | Kolom login berlabel "Username" tanpa petunjuk bahwa pegawai SHD mengisi NIK. | Akun | Rendah | Tidak dikerjakan (18 Nov) | Tidak dikerjakan, 18 Nov |
| M3 | Di HP, beberapa tautan hanya setinggi 14–20 px dan tombol Ubah/Hapus di Data Master berdempetan. | Mobile | Rendah | Tidak dikerjakan (18 Nov) | Tidak dikerjakan, 18 Nov |

## Rincian & usulan

**K2 · Akun eksternal punya hak penuh.** Akun eksternal dibuat untuk departemen lain, tetapi bisa menghapus aset permanen beserta riwayatnya, mengganti nama departemen, dan mengubah gedung, ruangan, serta jenis aset. Sesuai SPEC semua akun memang punya hak sama, tetapi risikonya besar. Usulan pilihan 1: akun eksternal hanya bisa melihat data dan mencatat peminjaman. Pilihan 2: hak tetap sama, tetapi Hapus aset dan Data Master khusus akun pegawai SHD.

**K3 · Halaman QR publik menampilkan data internal.** Siapa pun yang memindai label tanpa login bisa melihat nama penanggung jawab dan kode aset keuangan. Isi halaman ini masih menunggu konfirmasi mentor. Usulan: sembunyikan penanggung jawab dan kode keuangan sampai ada keputusan, atau ganti dengan "Hubungi Departemen SHD".

**A1 · Username peka huruf besar dan spasi.** Mengetik "Staf1" atau "staf1 " (dengan spasi) ditolak sebagai password salah. Ini paling sering terjadi di HP karena huruf pertama otomatis kapital, dan setiap percobaan dihitung: 5 kali salah membuat akun terkunci 15 menit. Usulan: abaikan huruf besar/kecil dan spasi di awal atau akhir username, dan matikan kapital otomatis di kolom Username. Sampai ini dikerjakan, ingatkan peserta HP menulis username dengan huruf kecil.

**D2 · Tanggal tidak wajar diterima.** Aset dengan tanggal perolehan 1 Januari 2030 tersimpan. Permintaan bertanggal 2030 dan 1990 juga diterima, dan permintaan 2030 akan selalu berada paling atas di riwayat. Rencana kembali peminjaman boleh diisi tahun 2099. Usulan: tolak tanggal perolehan dan tanggal permintaan setelah hari ini, dan batasi rencana kembali (misalnya maksimal 1 tahun) atau minta konfirmasi.

**A4 · Dua tab atau dua staf bersamaan.** Jika peminjaman sudah diterima kembali di tab lain, tab kedua mendapat pesan "Transaksi tidak berstatus Dipinjam." dan daftar tidak diperbarui. Dua staf yang mengubah data aset yang sama: perubahan terakhir menimpa yang pertama tanpa peringatan. Usulan: pesan yang lebih jelas ("Peminjaman ini sudah diselesaikan. Muat ulang halaman.") dan daftar dimuat ulang otomatis; untuk ubah data aset, beri peringatan bila data sudah diubah orang lain.

**U8 · Label Username tanpa petunjuk.** Pegawai SHD masuk dengan NIK, tetapi kolomnya hanya berlabel "Username". Usulan: tambahkan petunjuk di dalam kolom, misalnya "NIK atau username".

**M3 · Target sentuh kecil di HP.** Tautan "Catat transaksi" dan "Ajukan permintaan" di Stok setinggi 16 px, kode aset di Kelengkapan Ruangan 14 px, serta "Ubah" dan "Hapus" di Data Master 20 px dan berdempetan. Sebagian besar tombol lain 40 px, sedikit di bawah anjuran 44 px. Usulan: perbesar area sentuh menjadi minimal 44 px dan beri jarak antara Ubah dan Hapus.

## Hasil uji ulang

Setiap temuan yang diperbaiki diuji lagi di aplikasi dengan data baru. Lulus berarti hasilnya sesuai keputusan. Tanda – berarti tidak berlaku di ukuran itu.

| ID | Yang dicoba | Laptop | HP | Hasil |
|---|---|---|---|---|
| K1 | Akun eksternal mengetik NIK lengkap di Nama peminjam, lalu mengetik "rina". | Lulus | Lulus | NIK tidak menampilkan siapa pun; nama tetap muncul. Test otomatis memanggil 20 halaman data sebagai akun eksternal dan tidak menemukan NIK. |
| K4 | Buka Pengaturan > Akun, cari "9010", cari "yosef". | Lulus | Lulus | NIK tidak tampil; username akun eksternal tetap tampil; pencarian NIK kosong, pencarian nama berhasil. |
| D1 | Unduh Excel, lalu simulasi pukul 06.30 WIB di test otomatis. | Lulus | – | Kop file tertulis "Dicetak 19/11/2026 22:35", sama dengan jam WIB saat itu. Pukul 06.30 WIB sistem memakai tanggal yang benar untuk terlambat, jatuh tempo, transaksi stok, dan batas "tidak boleh setelah hari ini". |
| A2 | Filter Dari – Sampai di Peminjaman (10–14 Nov) dan Permintaan (1–10 Nov); rentang dibalik. | Lulus | Lulus | Peminjaman 8 menjadi 3, permintaan 7 menjadi 2. Rentang terbalik memunculkan pesan dan daftar tetap tampil. Periode di kop Excel dijaga test otomatis; unduhan Excel dengan periode tidak diulang di browser. |
| A3 | Terima kembali, pilih Rusak Ringan tanpa foto. | Lulus | Lulus | Teks "Foto kondisi wajib untuk barang rusak" tampil di dekat tombol Simpan yang nonaktif, dan layar bergulir ke kolom foto. Aturan wajib foto tidak berubah. |
| U1 | Buka detail aset tanpa foto; klik kotak Foto aset; lihat kotak Foto kondisi; tambah aset baru dengan foto. | Lulus | Lulus | Foto aset bisa langsung diunggah dari kotaknya (laptop; di HP kotak unggah tampil). Foto kondisi tidak bisa diklik dan menulis "Belum ada, foto diambil saat aset dikembalikan". Tambah aset dengan kolom Foto aset: aset tersimpan, foto masuk ke slot Foto aset dan tampil di detail, di laptop dan HP. |
| U2 | Putuskan koneksi data Dashboard dan Stok. | Lulus | Lulus | Tujuh bagian Dashboard menampilkan "Data gagal dimuat" dan tombol Coba lagi, begitu juga daftar Stok. Setiap bagian dimuat ulang sendiri-sendiri. |
| M1 | Daftar Aset di HP. | – | Lulus | Kotak centang di pojok kanan atas semua kartu (jarak ke tepi sama), tampilan tetap 16 px, area sentuh 44 px. |
| M2 | Pengaturan di HP, tab Tampilan lalu Data Master. | – | Lulus | Gradasi tampil di tepi tab; tab Data Master digulir sampai terlihat penuh. |
| A5 | Ketik "SHD-LPT" di form peminjaman. | Lulus | Lulus | Tanggal pinjam di atas pilihan aset. "SHD-LPT-004 · Perbaikan, tidak bisa dipinjam" dan "SHD-LPT-001 · Sedang dipinjam sampai 17 Nov" tampil dan tidak bisa dipilih. |
| U3 | Kelengkapan Ruangan. | Lulus | Lulus | Tombol bernama "Catat perubahan" di kedua ukuran; "Ubah jumlah" tidak ada lagi. |
| U4 | Simpan form Tambah aset yang kosong. | Lulus | Lulus | Empat pesan berbahasa Indonesia di bawah kolomnya, tanpa pesan bawaan browser. Nama kolom di pesan server sudah diterjemahkan dan dijaga test otomatis. |
| U5 | Bandingkan Dashboard dengan Daftar Aset dan Kelengkapan Ruangan. | Lulus | Lulus | Total aset 32 sama dengan Daftar Aset, dengan keterangan "1 tidak layak pakai". Kartu ruangan "4 dari 5 ruangan belum lengkap · 3 gudang tidak dinilai"; Kelengkapan Ruangan "8 ruangan di 2 gedung (5 dinilai, 3 gudang)". Test otomatis membandingkan setiap kartu dengan halaman tujuannya. |
| U6 | Aset dengan nama 255 huruf tanpa spasi, di detail, daftar, dan Dashboard. | Lulus | Lulus | Halaman tidak bergeser ke samping. Kata biasa di tabel tidak terpotong. |
| U7 | Buka alamat yang salah. | Lulus | Lulus | "Halaman yang Anda cari tidak ditemukan." dan tombol "Kembali ke Dashboard" yang berfungsi. |

Dua masalah baru muncul saat uji ulang dan sudah diperbaiki sebelum laporan ini: (1) di laptop, filter periode Permintaan turun ke baris kedua, jadi lebar kolom Cari, Penyedia, dan Status dikecilkan agar semuanya satu baris; (2) aturan potong teks panjang sempat memotong kata biasa di tabel ("Rahmawa-ti"), jadi sel tabel memakai aturan yang lebih lunak. Kedua perbaikan sudah lulus uji ulang.

## Perubahan sejak 18 November

Perubahan di luar temuan UAT:

- Waktu: sejak perbaikan D1, semua waktu di database tersimpan dalam WIB. Data lama yang ditulis dalam UTC tidak dipindahkan; database demo cukup di-seed ulang. Ini dicatat di CLAUDE.md, SPEC.md, dan TASKS.md.
- Login: kolom Username dan Password yang kosong kini memunculkan pesan di bawah kolomnya; ringkasan "(and 1 more error)" dari server berbahasa Indonesia ("dan 1 kesalahan lain").
- Tambah aset: nama aset yang kosong memunculkan pesan di bawah kolomnya.
- Peminjaman: bila tanggal diubah sesudah aset dipilih (misalnya dari pesan menjadi pinjam sekarang), aset yang tidak lagi boleh dipilih diberi pesan.
- Kelengkapan Ruangan: judul halaman menyebut jumlah ruangan dinilai dan gudang.
- Daftar akses (tab Akun): tiap baris punya penanda `key`, karena NIK tidak lagi dikirim.
- Halaman placeholder lama dihapus (diganti halaman "tidak ditemukan").
- Test: 17 test baru (backend 699 menjadi 711, frontend 42 menjadi 47). Semua lulus, begitu juga format kode, pemeriksa kode, dan build.
- Dokumen: SPEC.md, endpoints.md, CLAUDE.md, dan TASKS.md (T45) diperbarui.

## Skenario lapangan yang belum ditangani

| Skenario | Kondisi sekarang | Catatan |
|---|---|---|
| Satu kegiatan meminjam banyak aset sekaligus (proyektor, mikrofon, speaker) | Belum | Satu transaksi hanya untuk satu aset; staf mengisi form berulang untuk setiap unit. |
| Perpanjangan masa pinjam | Belum | Rencana kembali tidak bisa diubah setelah dicatat; pilihannya hanya batalkan lalu catat ulang, sehingga riwayat tercatat "Dibatalkan". |
| Peminjaman oleh pihak luar INALUM (vendor, instansi) | Sebagian | Nama dan departemen bisa diketik bebas (sudah dicoba), tetapi tidak ada kolom instansi, kontak, atau jaminan. |
| Aset dipinjam lintas gedung | Sebagian | Lokasi tujuan hanya teks bebas; lokasi aset tetap lokasi asal sampai kembali, dan tidak ada daftar aset per lokasi peminjaman. |
| Aset dipindah ruangan sementara tanpa dipinjam (misalnya dipakai acara sehari) | Sebagian | Pindah lokasi mengubah lokasi asal secara permanen; pemindahan sementara harus dicatat sebagai peminjaman. |
| Stok opname berkala | Sebagian | Selisih dicatat sebagai Koreksi satu per satu per barang; belum ada lembar opname, tanggal opname, atau rekap selisih. |
| Barang permintaan datang bertahap | Sebagian | Kiriman pertama menutup permintaan sebagai Diterima sebagian; sisanya harus diajukan sebagai permintaan baru. |
| Aset diservis di vendor selama beberapa hari | Sebagian | Status Perbaikan ada, tetapi tidak tercatat kapan dikirim, ke vendor mana, dan perkiraan selesai; servis baru dicatat setelah selesai. |
| Penghapusan aset resmi (berita acara pemusnahan atau lelang) | Sebagian | Hapus aset menghapus riwayat secara permanen, dan Tidak Layak Pakai tidak menyimpan nomor berita acara, sehingga bukti untuk audit hilang. |
| Pengingat jatuh tempo, jadwal servis, dan stok habis | Belum | Hanya tampil di Dashboard; tidak ada email atau WhatsApp ke staf maupun peminjam. |
| Pergantian penanggung jawab (pegawai pindah atau pensiun) | Belum | Penanggung jawab diisi per aset; tidak ada cara menggantinya untuk banyak aset sekaligus. |
| Jadwal cek ruangan rutin | Sebagian | Ada tombol Tandai sudah dicek, tetapi tidak ada jadwal atau penanda ruangan yang lama tidak dicek. |

Sebelum skenario ini dibangun, sebaiknya dikonfirmasi dulu ke staf SHD apakah memang terjadi.

## Usulan urutan

1. **Wajib sebelum UAT pengguna hari Kamis:** (a) seed ulang database demo, karena waktu kini disimpan dalam WIB dan data lama masih dalam UTC (jam riwayat akan terlihat 7 jam lebih awal); (b) ingatkan peserta HP menulis username dengan huruf kecil, karena A1 tidak dikerjakan dan lima kali salah mengunci akun 15 menit; (c) jalankan ulang `php artisan employees:sync` setelah seed, supaya pegawai bisa login.
2. **Sebelum rilis v1.0 hari Jumat:** (a) gabungkan perubahan zona waktu ke branch `for-deploy`: branch itu masih menulis `'timezone' => 'UTC'` langsung di `config/app.php`, jadi mengisi `APP_TIMEZONE` di Vercel saja belum cukup sebelum perubahan ini digabung (setelah digabung, `Dockerfile.vercel` sudah mengisinya); (b) kerjakan T43 (wajib ganti password saat login pertama), karena NIK masih menjadi password awal; (c) jika keputusan 18 November untuk K2, K3, dan D2 ditinjau ulang, kerjakan di sini.
3. **Bila sempat / versi berikutnya:** K2, K3, A1, D2, A4, U8, M3, serta skenario lapangan di atas setelah dikonfirmasi ke staf SHD.
