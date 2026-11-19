# Laporan Regression Test — setelah perbaikan UAT (T45)

Tanggal: 20 November 2026 · Kode tidak diubah selama pengujian ini.

## Ringkasan

- **Tidak ada fitur yang tadinya jalan lalu error karena perbaikan T45.** Semua test otomatis lulus, 15 temuan UAT yang diperbaiki terbukti beres di laptop dan HP, dan alur utama dari database kosong berjalan dari login sampai Dashboard.
- **Tidak ada cacat aplikasi baru.** Ada 3 catatan kecil dan 1 selisih dokumen (bagian "Temuan"), semuanya prioritas Rendah dan sudah ada sebelum T45.
- Pengujian ini memakai database SQLite sementara. Supabase tidak disentuh.

Acuan temuan UAT: file `docs/UAT.md` tidak ada di repo. Yang dipakai adalah `docs/uat/Laporan-UAT-Aset-SHD.md`.

## Cara pengujian

| Hal | Cara |
|---|---|
| Database | Dua file SQLite sementara di folder sementara, lewat `backend/.env.local` (sudah dihapus). Satu berisi data contoh (untuk uji ulang temuan UAT), satu kosong (migrasi tanpa seed + `employees:sync`, untuk alur utama). |
| Peramban | Edge lewat Playwright, laptop 1366×768 dan HP 390×844. |
| API pegawai | Tiruan lokal (`postman/mocks/employee-api`), data fiktif. |
| Yang dicatat | Setiap galat JavaScript, pesan galat di konsol, dan permintaan API yang dijawab 4xx/5xx. |

## 1. Test otomatis

| Pemeriksaan | Hasil |
|---|---|
| `php artisan test` (backend) | **Pass** — 711 test, 5.019 pernyataan, 0 gagal |
| `npm test` (frontend) | **Pass** — 47 test, 0 gagal |
| `npm run lint` | **Pass** |
| `tsc -b` (pemeriksaan tipe) | **Pass** |
| `npm run build` | **Pass** |
| `vendor/bin/pint` | **Pass** (dijalankan saat perbaikan; tidak diulang karena kode tidak berubah) |

Tidak ada test yang gagal.

## 2. Uji ulang temuan UAT yang diperbaiki (15 temuan)

Lulus berarti hasilnya sesuai keputusan 18 November. Dijalankan di data contoh.

| ID | Yang diuji | Laptop | HP |
|---|---|---|---|
| K1 | Akun eksternal mencari NIK lengkap dan nama di saran peminjam | Pass | Pass |
| K4 | Tab Akun tanpa NIK; cari NIK kosong, cari nama berhasil | Pass | Pass |
| D1 | Jam Excel mengikuti WIB (sudah dibaca dari file) | Pass | – |
| U5 | Dashboard = Daftar Aset (32), "4 dari 5 ruangan belum lengkap · 3 gudang tidak dinilai", Kelengkapan Ruangan "8 ruangan (5 dinilai, 3 gudang)" | Pass | Pass |
| A5 | Tanggal pinjam di atas aset; aset tidak tersedia tampil dengan alasan dan tidak bisa dipilih | Pass | Pass |
| A3 | Teks "Foto kondisi wajib untuk barang rusak" dan gulir ke kolom foto | Pass | Pass |
| U3 | "Catat perubahan" di semua ukuran | Pass | Pass |
| A2 | Filter Dari – Sampai di Peminjaman dan Permintaan; rentang terbalik; satu baris di laptop | Pass | Pass |
| M1 | Kotak centang kartu aset di pojok kanan atas, area sentuh 44 px | – | Pass |
| M2 | Tab Pengaturan bisa digeser dengan gradasi; tab aktif terlihat | – | Pass |
| U1 | Foto aset bisa diunggah dari kotaknya; Foto kondisi tidak bisa diklik | Pass | Pass |
| U4 | Pesan kesalahan berbahasa Indonesia, tanpa pesan bawaan peramban | Pass | Pass |
| U6 | Teks 255 huruf tanpa spasi tidak membuat halaman bergeser | Pass | Pass |
| U7 | Halaman tidak ditemukan dan tombol kembali | Pass | Pass |
| U2 | "Data gagal dimuat" + Coba lagi di Dashboard dan daftar Stok | Pass | Pass* |

\* Skrip menandai U2 di HP gagal karena memilih salinan tabel yang tersembunyi di layar kecil. Tangkapan layar menunjukkan "Data gagal dimuat" dan tombol Coba lagi tampil benar di HP, jadi dinilai lulus.

## 3. Alur utama dari database kosong

Dikerjakan lewat tampilan aplikasi; pemeriksaan hasilnya lewat API. Satu perjalanan berurutan.

| No. | Skenario | Hasil | Keterangan |
|---|---|---|---|
| S0-1 | Login pegawai SHD (NIK) pertama kali | **Pass** | Akun dibuat otomatis, masuk Dashboard |
| S0-2 | 10 halaman utama dibuka saat database kosong | **Pass** | Tanpa pesan galat, tanpa NaN/undefined |
| S1-1 | Data Master: tambah gedung | **Pass** | |
| S1-2 | Data Master: tambah ruangan biasa dan ruangan gudang | **Pass** | Tanda gudang tersimpan |
| S1-3 | Data Master: tambah kategori dan jenis aset | **Pass** | Kode jenis otomatis `PRY` (lihat catatan C4) |
| S1-4 | Data Master: tambah kategori stok | **Pass** | |
| S1-5 | Data Master: tab Departemen dan strip sinkron terakhir | **Pass** | 17 departemen dari sinkron |
| S2 | Tambah aset | **Pass** | Kode `SHD-PRY-001`, status Tersedia, riwayat Registrasi |
| S3 | Peminjaman (pilih pegawai dari saran) | **Pass** | Status aset Dipinjam, Dashboard sedang dipinjam 1, departemen terisi otomatis |
| S4 | Terima kembali, kondisi Baik | **Pass** | Riwayat: Registrasi → Dipinjam → Dikembalikan |
| S4b | Terima kembali Rusak Ringan dengan foto | **Pass** | Simpan nonaktif tanpa foto; foto kondisi tersimpan; status Tersedia |
| S5 | Pemesanan tanggal depan, bentrok, serahkan, kembali | **Pass** | Aset tetap Tersedia saat dipesan; bentrok ditolak 422 |
| S6-1 | Tambah barang stok (kategori, penyedia, gudang) | **Pass** | |
| S6-2 | Stok masuk, pemakaian, pemakaian berlebih | **Pass** | 10 → 15 → 3 (Menipis); pemakaian berlebih ditolak "Stok tidak cukup (tersisa N)." (pesan muncul ±1,5 detik di server uji yang lambat; dinilai lulus setelah dicek langsung) |
| S7 | Saran permintaan → buat permintaan → catat barang diterima | **Pass** | Permintaan Diajukan → Diterima, stok bertambah 2 |
| S8 | Kelengkapan ruangan: standar, Catat perubahan, Isi dari stok, Tandai sudah dicek | **Pass** | Ada di ruangan 5/5; stok berkurang 3 |
| S9 | Dashboard setelah semua transaksi | **Pass** | Total aset sama dengan Daftar Aset; stok menipis sama dengan Stok |
| S10-1 | Ekspor 5 laporan Excel, termasuk dengan periode | **Pass** | Semua 200 dan berformat xlsx |
| S10-2 | QR, label PDF, scan kode huruf kecil, halaman publik | **Pass** | |
| S11-1 | Akun eksternal: hanya Data Master, `/accounts` dan `/settings` 403, cari NIK kosong | **Pass** | |
| S11-2 | Ganti password: salah, sama dengan NIK, berhasil, login ulang | **Pass** | 422, 422, 200, 200 |
| S12 | 9 halaman utama di HP setelah ada data | **Pass** | Tidak ada halaman yang bergeser ke samping |

Alur ini perlu beberapa kali dijalankan. Penyebab semua kegagalan di putaran awal adalah skrip uji, bukan aplikasi: skrip berasumsi kode jenis `PRJ`, mengetik di kolom sebelum daftarnya selesai dimuat, dan terlalu cepat membaca pesan. Putaran terakhir bersih.

## 4. Temuan (galat dan penyebabnya)

Tidak ada fitur yang rusak. Yang ditemukan:

### C1 · Kolom gedung/ruangan/kategori menawarkan "Tambah" sementara daftar masih dimuat (Rendah)

- **Gejala:** di form Tambah barang stok, tepat setelah memilih gedung (atau membuka kolom Kategori), daftar ruangan atau kategori butuh ±1 detik untuk datang. Selama itu, mengetik nama yang sebenarnya sudah ada ("Gudang IHS", "ATK") hanya menampilkan "Tambah 'Gudang'" tanpa pilihan yang ada. Jika diklik, server menjawab 422 "Nama sudah digunakan" dan kolom tetap kosong.
- **Penyebab:** daftar diambil setelah gedung dipilih dan komponen pilihan tidak menandai bahwa daftar masih dimuat. Terbukti: setelah menunggu 1,5 detik pilihan "Gudang IHS" muncul.
- **Sudah ada sebelum T45:** komponen `EntityCombobox`/`Combobox` dan daftar master tidak diubah T45. Di server sungguhan dampaknya lebih kecil karena daftar cepat dimuat.
- **Saran:** tampilkan "Memuat…" dan matikan opsi Tambah sampai daftar tiba; atau, bila server menjawab 422 karena nama sudah ada, pilih item yang sudah ada.

### C2 · 422 di konsol dari `GET /supply-requests/{id}/status-impact` (Rendah, hanya konsol)

- **Gejala:** setelah menandai permintaan Diterima, konsol peramban mencatat satu permintaan `status-impact?status=Diterima` dijawab 422. Tidak ada pesan galat di layar.
- **Penyebab:** terbukti bahwa server menolak permintaan itu dengan benar ("Status Diterima tidak dapat diubah ke Diterima.") dan permintaan sudah berstatus Diterima saat itu. Dugaan kuat: setelah status berubah, keterangan dampak yang sudah dimuat ikut dimuat ulang untuk permintaan yang statusnya sudah tercapai. Kode yang memicunya belum ditelusuri.
- **Sudah ada sebelum T45:** kode pengambilan dampak (`prefetch.ts`, `lists.ts`) tidak diubah T45.
- **Saran:** jangan memuat ulang keterangan dampak untuk status yang sudah tercapai, atau abaikan 422 untuk pengambilan latar belakang ini.

### C3 · Server uji bawaan melayani satu permintaan sekali waktu (Rendah, hanya lingkungan uji)

- **Gejala:** halaman yang memuat banyak data terasa lambat saat diuji, dan beberapa langkah uji butuh waktu tunggu lebih lama.
- **Penyebab:** `php artisan serve` memakai server bawaan PHP. Server sungguhan (FrankenPHP/PHP-FPM) tidak punya batas ini.
- **Saran:** tidak perlu diperbaiki di aplikasi; hanya diingat saat menjalankan UAT pengguna di satu laptop: kalau terasa lambat, beri jeda.

### C4 · Contoh kode jenis di SPEC tidak sama dengan hasil pembuatan otomatis (Rendah, dokumen)

- **Gejala:** SPEC §4 menulis "Proyektor → PRJ", tetapi jenis "Proyektor" yang dibuat dari database kosong mendapat kode `PRY`. Data contoh (seed) memakai `PRJ` karena diisi manual.
- **Penyebab:** aturan pembuat kode (huruf pertama + 2 konsonan berikutnya, Y dihitung konsonan) menghasilkan `PRY`. Catatan T6 di TASKS.md sudah menyebut seeder memakai `PRJ` agar sesuai contoh SPEC.
- **Akibat:** kode aset di database kosong akan `SHD-PRY-001`, berbeda dari contoh `SHD-PRJ-001` di dokumen dan data contoh.
- **Saran:** ubah contoh di SPEC menjadi `PRY`, atau tulis `PRJ` manual di Data Master sebelum membuat aset. Yang mana, keputusan Anda.

## 5. Yang tidak diuji

- **PostgreSQL/Supabase.** Semua pengujian memakai SQLite. Batasan CHECK, RLS, dan indeks unik sebagian hanya berlaku di PostgreSQL (lihat catatan T4 di TASKS.md), dan perilaku kolom waktu setelah perubahan ke WIB belum dibuktikan di sana. Disarankan satu kali jalan singkat di Supabase uji setelah database di-seed ulang.
- **Scan QR dengan kamera** dan pengiriman email OTP lewat SMTP sungguhan.
- **Lupa password lewat OTP di layar.** Hanya tercakup test otomatis backend pada pengujian ini.
- **Alur lengkap di HP dari database kosong.** Di HP hanya diperiksa bahwa 9 halaman utama terbuka tanpa bergeser ke samping; alur ketik-dan-simpan penuh diuji di laptop, dan 15 temuan UAT diuji di kedua ukuran.
- **Unduhan Excel dengan periode dibuka di peramban.** Periode di kop dijaga test otomatis backend.

## 6. Kondisi setelah pengujian

Server uji, file `.env.local`, dan foto yang diunggah selama pengujian sudah dihapus. Tidak ada perubahan kode. Satu-satunya file baru dari pengujian ini adalah laporan ini.
