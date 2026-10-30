# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Satu jenis pengguna: **Staf/Admin Aset Departemen SHD** di PT INALUM, tanpa role. SHD adalah departemen pelatihan/pengembangan SDM yang mengelola fasilitas pelatihan karyawan (gedung pelatihan, ruang kelas, alat pelatihan, perlengkapan peserta). Akun dibuat lewat seeder.

Staf bekerja di dua situasi: di meja kantor (PC/laptop) untuk mendata aset, mencatat peminjaman, dan mengekspor laporan; dan di lapangan (ruang kelas, gudang) untuk scan QR dan cek kelengkapan ruangan.

Audiens kedua: siapa pun yang memindai label QR di aset membuka halaman publik read-only `/aset/{token}` tanpa login. Peminjam dan unit lain tidak memakai sistem; staf mencatat atas nama mereka.

## Product Purpose

Sistem pencatatan aset internal SHD yang menggantikan pencatatan tersebar:
1. Mendata aset per unit secara terpusat dengan QR, lokasi, kondisi, dan dokumen.
2. Memantau ketersediaan, peminjaman, perbaikan, dan jadwal servis.
3. Mencatat riwayat lengkap tiap aset sebagai bukti akuntabilitas dan data pendukung pengadaan.
4. Memantau kesiapan ruangan pelatihan terhadap standar perlengkapan dan mencatat permintaan ke unit lain.
5. Memantau stok barang habis pakai.

Berhasil bila staf bisa menjawab "aset ini di mana, kondisinya apa, siapa yang pinjam, ruangan mana yang belum siap" dengan cepat, dan riwayatnya bisa dipertanggungjawabkan.

## Positioning

Bukan sistem aset generik: dibangun untuk kesiapan fasilitas pelatihan. Kelengkapan ruangan diukur terhadap standar per ruangan (dihitung otomatis dari aset ber-QR atau dicek manual), terhubung ke stok habis pakai SHD dan ke permintaan ke unit penyedia lain. Setiap perubahan aset tercatat di satu riwayat gabungan.

## Operating Context

- Modul Fase 1: Dashboard, Daftar Aset, Scan QR, Label QR (PDF), Peminjaman, Kelengkapan Ruangan, Permintaan ke Unit Lain, Stok Habis Pakai, Ekspor laporan Excel, Data Master. Fase 2 (opsional): Lisensi Digital.
- Label QR dicetak di A4 (12 label per lembar) dan ditempel di aset; scan memakai webcam laptop saat demo, HP saat deploy (butuh HTTPS).
- Approval peminjaman, konfirmasi kerusakan, dan proses permintaan ke unit lain terjadi di luar sistem (memo, form, email, WhatsApp); sistem hanya mencatat nomor dan statusnya.
- Laporan diekspor ke Excel untuk dibawa ke rapat/pengadaan.
- Prototype magang; demo awal November 2026. Tim lain akan deploy dan membuat versi HP.

## Capabilities and Constraints

- Acuan aturan bisnis & API: `docs/SPEC.md`. Isi tabel/field minimal mengikuti `docs/mockup-aset-shd.pdf` (gaya UI mockup tidak mengikat).
- Frontend: React + TypeScript (Vite), Tailwind v4, React Router, axios, lucide-react. Backend Laravel API-only (Sanctum Bearer token).
- **Semua halaman wajib responsif** (desktop dan HP), termasuk tabel, form, modal, dan halaman scan.
- UI sepenuhnya Bahasa Indonesia. Nilai status/kondisi memakai istilah SPEC: kondisi `Baik`/`Rusak Ringan`/`Rusak Berat`; status aset `Tersedia`/`Dipinjam`/`Perbaikan`/`Hilang`/`Tidak Layak Pakai`; hasil cek `Sesuai`/`Kurang`/`Belum dicek`; stok `Aman`/`Menipis`/`Habis`.
- Aksi pada aset bergantung status (lihat SPEC §6); hapus aset permanen dengan konfirmasi kode.
- Data publik halaman QR masih menunggu konfirmasi mentor.

## Brand Commitments

- **Wajib memakai identitas korporat INALUM** (logo dan/atau warna resmi). Nama aplikasi di UI: "Aset SHD".
- File logo dan panduan warna resmi INALUM **belum ada di repo**; harus diminta ke pengguna, jangan dibuat atau ditebak.

## Evidence on Hand

- `docs/mockup-aset-shd.pdf`: mockup awal (acuan isi, bukan gaya).
- Data contoh dari seeder backend (gedung, ruangan, kategori, aset contoh).
- Tidak ada testimoni, metrik pemakaian, atau logo INALUM di repo; jangan dibuat-buat.

## Product Principles

1. Status aset harus terbaca sekilas: kondisi, status, dan lokasi selalu jelas di setiap tampilan aset.
2. Setiap aksi meninggalkan jejak: alur UI mengarah ke aksi resmi (pinjam, lapor, servis), bukan edit bebas.
3. Cepat di lapangan, lengkap di meja: scan dan cek ruangan harus nyaman di HP; daftar, laporan, dan data master nyaman di desktop.
4. Bahasa staf, bukan bahasa sistem: istilah sesuai SPEC dan pesan singkat Bahasa Indonesia.

## Accessibility & Inclusion

Tidak ada standar formal yang ditetapkan. Minimal: kontras teks terbaca di bawah pencahayaan ruangan/gudang, target sentuh layak untuk HP, status tidak hanya dibedakan lewat warna.
