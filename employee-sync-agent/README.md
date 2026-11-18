# Agen Sinkron Pegawai — Asset Tracking System Departemen SHD

Panduan ini untuk tim IT yang memasang agen di server perusahaan. Semua yang dibutuhkan ada di folder ini.

## 1. Apa fungsi agen ini

Aplikasi Asset Tracking SHD berjalan di internet. Aplikasi butuh data pegawai (untuk login pegawai SHD dan pilihan nama peminjam), tetapi API pegawai hanya bisa diakses dari jaringan internal perusahaan.

Agen ini menjembatani keduanya. Setiap malam pukul **23.59**, agen yang dipasang di server perusahaan:

1. mengambil data pegawai dari API pegawai internal,
2. membuang kolom yang tidak dipakai,
3. mengirim sisanya ke aplikasi lewat HTTPS.

```
[Jaringan internal]                                         [Internet]
API pegawai  <-- 1. GET --  Agen (server ini)  -- 3. POST HTTPS -->  Aplikasi Asset Tracking
```

Yang perlu diketahui:
- Agen hanya membuat **koneksi keluar**. Tidak ada port yang perlu dibuka ke dalam, dan API pegawai tetap tertutup dari internet.
- Agen tidak memakai database, web server, atau composer. Isinya satu file PHP (`sync.php`) dan satu file konfigurasi (`.env`).
- Agen tidak menyimpan data pegawai di disk. Log hanya berisi jumlah dan status, tanpa data pegawai, API key, atau token.

## 2. Data yang dikirim dan ke mana

Agen hanya mengirim kolom berikut, dengan nama persis seperti di API pegawai:

| Kolom | Isi |
|---|---|
| `nik` | Nomor induk karyawan |
| `nama` | Nama pegawai |
| `grup` | Grup |
| `divisi` | Divisi |
| `departemen` | Kode departemen |
| `seksi` | Seksi |
| `jabatan` | Jabatan |
| `email` | Email kantor |

Data dikirim lewat HTTPS ke `{BACKEND_URL}/api/internal/employees/push` dengan token rahasia di header `Authorization`.

**Kolom lain sengaja tidak disalin.** Kolom seperti `tglLahir`, `jenkel`, `agama`, `phone`, `tglMPP`, dan `tglPensiun` dibuang di server ini dan tidak pernah keluar dari jaringan perusahaan, karena aplikasi tidak memakainya. Kalau suatu saat aplikasi membutuhkan kolom tambahan, tim pengembang aplikasi menambah kolomnya di tabel `employees` aplikasi, lalu menambah namanya di daftar `EMPLOYEE_FIELDS` di bagian atas `sync.php`. Selain daftar itu, `sync.php` tidak perlu diubah.

## 3. Syarat sistem

- **PHP 7.4 atau lebih baru** (disarankan PHP 8.1+), versi command line (CLI). Bisa Windows maupun Linux.
- **Ekstensi PHP `curl` dan `json`.** `json` sudah bawaan sejak PHP 8. Di Windows, `curl` biasanya perlu diaktifkan dengan menghapus tanda `;` di depan `extension=curl` pada `php.ini`.
- **Akses jaringan:**
  - ke API pegawai internal (alamat di `EMPLOYEE_API_URL`);
  - keluar ke internet lewat HTTPS (port 443) ke alamat aplikasi (`BACKEND_URL`), langsung atau lewat proxy perusahaan.

Cara mengecek:

```
php -v        # versi PHP
php -m        # daftar ekstensi; pastikan ada "curl" dan "json"
```

## 4. Instalasi

1. Salin folder `employee-sync-agent` ke server, misalnya:
   - Linux: `/opt/employee-sync-agent`
   - Windows: `C:\employee-sync-agent`
2. Di folder itu, salin `.env.example` menjadi `.env`.
   - Linux: `cp .env.example .env`
   - Windows: `copy .env.example .env`
3. Buka `.env` dan isi. Penjelasan setiap baris ada di dalam file:
   - `EMPLOYEE_API_URL`, `EMPLOYEE_API_KEY` (dan `EMPLOYEE_API_KEY_HEADER` / `EMPLOYEE_API_DATA_KEY` bila berbeda dari bawaan): dari pengelola API pegawai.
   - `BACKEND_URL` dan `EMPLOYEE_PUSH_TOKEN`: dari tim pengembang aplikasi. Token diberikan lewat jalur aman, jangan lewat chat grup atau email biasa.
   - `PROXY_URL`: bila server wajib lewat proxy untuk ke internet.
4. Batasi akses file `.env` karena berisi rahasia.
   - Linux: `chmod 600 .env` (pemiliknya user yang menjalankan jadwal).
   - Windows: atur izin folder agar hanya Administrator dan akun penjalan jadwal yang bisa membaca.

## 5. Uji dengan `--dry-run`

Uji coba **tidak mengirim data pegawai** dan tidak mengubah apa pun di aplikasi. Yang dicek: API pegawai bisa diambil, kolomnya cocok, aplikasi bisa dihubungi, dan token diterima.

```
cd /opt/employee-sync-agent            # Windows: cd C:\employee-sync-agent
php sync.php --dry-run
```

Contoh hasil yang benar:

```
[2026-11-20 10:15:02] INFO Uji coba (--dry-run, agen v1.0): data pegawai TIDAK dikirim ke aplikasi.
[2026-11-20 10:15:02] INFO 1/2 Mengambil data dari API pegawai (alamat-api-pegawai-internal)...
[2026-11-20 10:15:03] INFO OK: 2.345 pegawai, ukuran kiriman 512 KB.
[2026-11-20 10:15:03] INFO Kolom yang akan dikirim: nik, nama, grup, divisi, departemen, seksi, jabatan, email.
[2026-11-20 10:15:03] INFO 2/2 Mengecek koneksi dan token ke aplikasi (alamat-backend-aplikasi.contoh)...
[2026-11-20 10:15:04] INFO OK: aplikasi terjangkau dan token diterima.
[2026-11-20 10:15:04] INFO Uji coba selesai: semua siap. Jalankan `php sync.php` (tanpa --dry-run) untuk sinkron sungguhan.
```

Kalau ada baris `GAGAL` atau `PERINGATAN`, baca pesannya. Penyebab umum ada di bagian 9.

Setelah uji coba berhasil, jalankan sinkron pertama secara manual:

```
php sync.php
```

Hasil yang benar diakhiri baris `Sinkronisasi berhasil: ... pegawai (... baru, ... diperbarui, ...)`. Tim aplikasi bisa melihat hasilnya di aplikasi: **Pengaturan > Data Master > Departemen**, baris "Sinkron terakhir".

## 6. Jadwal harian 23.59 di Linux (cron)

1. Pastikan zona waktu server WIB: jalankan `timedatectl` dan lihat `Time zone: Asia/Jakarta`. Kalau berbeda, sesuaikan jam di jadwal atau tambahkan baris `CRON_TZ=Asia/Jakarta` di atas jadwal (didukung cronie/RHEL; tidak semua cron mendukungnya).
2. Buka jadwal milik user yang akan menjalankan agen (user ini harus bisa membaca `.env` dan menulis `sync.log`):
   ```
   crontab -e
   ```
3. Tambahkan satu baris (sesuaikan lokasi PHP dari `which php` dan lokasi folder):
   ```
   59 23 * * * /usr/bin/php /opt/employee-sync-agent/sync.php
   ```
4. Simpan, lalu cek dengan `crontab -l`.

Log tertulis di `sync.log` dalam folder agen (diatur `LOG_FILE` di `.env`).

## 7. Jadwal harian 23.59 di Windows (Task Scheduler)

**Lewat tampilan:**

1. Buka **Task Scheduler**, lalu pilih **Create Task** (bukan Create Basic Task).
2. Tab **General**: nama `Sinkron Pegawai Asset SHD`; pilih **Run whether user is logged on or not**.
3. Tab **Triggers** > **New**: **Daily**, mulai pukul **23:59:00**, **Recur every 1 days**.
4. Tab **Actions** > **New**: **Start a program**.
   - Program/script: lokasi `php.exe`, mis. `C:\php\php.exe`
   - Add arguments: `C:\employee-sync-agent\sync.php`
   - Start in: `C:\employee-sync-agent`
5. Tab **Settings**: centang **Stop the task if it runs longer than** 1 jam.
6. Simpan (Windows meminta password akun penjalan).

**Atau lewat Command Prompt (Run as administrator):**

```
schtasks /Create /TN "Sinkron Pegawai Asset SHD" /SC DAILY /ST 23:59 /RU SYSTEM /TR "\"C:\php\php.exe\" \"C:\employee-sync-agent\sync.php\""
```

Uji tanpa menunggu malam: klik kanan tugas > **Run**, atau `schtasks /Run /TN "Sinkron Pegawai Asset SHD"`.

Catatan: akun `SYSTEM` tidak memakai pengaturan proxy milik pengguna Windows. Kalau server lewat proxy, isi `PROXY_URL` di `.env`.

## 8. Membaca log dan exit code

Setiap baris log berisi waktu, tingkat, dan pesan:

- `INFO`: berjalan normal.
- `PERINGATAN`: tetap jalan, tetapi perlu dicek (mis. ada kolom yang tidak ditemukan di API).
- `GAGAL`: sinkron tidak berhasil. Data pegawai lama di aplikasi tetap dipakai, jadi aplikasi tetap bisa digunakan.

Exit code (Linux: `echo $?` setelah menjalankan; Windows: kolom **Last Run Result** di Task Scheduler):

| Exit code | Last Run Result | Arti |
|---|---|---|
| 0 | `0x0` | Berhasil |
| 1 | `0x1` | Gagal: API pegawai, jaringan, atau aplikasi bermasalah. Lihat baris `GAGAL` di log. |
| 2 | `0x2` | Konfigurasi salah: `.env` belum ada atau isinya tidak lengkap. Lihat pesan di log/layar. |

Kalau API pegawai gagal diambil, agen juga melaporkan kegagalannya ke aplikasi. Tim aplikasi akan melihat status "gagal" di baris Sinkron terakhir.

Ukuran log kecil (beberapa baris per malam), jadi tidak perlu dibersihkan rutin.

## 9. Masalah umum

| Pesan | Penyebab dan solusi |
|---|---|
| `SSL certificate problem` / `Sertifikat HTTPS tidak bisa diverifikasi` | PHP tidak menemukan daftar sertifikat CA (sering di Windows), atau proxy perusahaan memeriksa HTTPS. Unduh `cacert.pem` dari https://curl.se/docs/caextract.html, simpan di folder agen, isi `CA_BUNDLE=cacert.pem`. Bila proxy memakai sertifikat sendiri, tambahkan sertifikat CA perusahaan ke file yang sama. Jangan mematikan pemeriksaan sertifikat. |
| `Tidak bisa menghubungi aplikasi` / `Could not resolve host` / `timed out` | Server tidak bisa keluar ke internet. Isi `PROXY_URL` bila wajib lewat proxy, atau izinkan koneksi keluar HTTPS ke alamat `BACKEND_URL` di firewall. |
| `Tidak bisa menghubungi API pegawai` | Server tidak berada di jaringan yang bisa menjangkau API pegawai, atau `EMPLOYEE_API_URL` salah. |
| `API pegawai menjawab HTTP 401` atau `403` | `EMPLOYEE_API_KEY` atau `EMPLOYEE_API_KEY_HEADER` salah. |
| `Bentuk respons API pegawai tidak dikenal` | Respons API tidak berupa daftar pegawai. Bila dibungkus objek (mis. `{"data": [...]}`), isi `EMPLOYEE_API_DATA_KEY=data`. |
| `Kolom tidak ada di respons API pegawai: ...` | Nama kolom di API berbeda dari yang diharapkan. Hubungi tim pengembang aplikasi. |
| `Aplikasi menolak token (HTTP 401)` | `EMPLOYEE_PUSH_TOKEN` di `.env` tidak sama dengan yang di aplikasi. Periksa spasi atau karakter yang terpotong. |
| `Alamat penerima tidak ditemukan (HTTP 404)` | `BACKEND_URL` salah, atau token belum diisi di aplikasi. Hubungi tim pengembang aplikasi. |
| `Aplikasi sedang sibuk (HTTP 503)` | Sinkronisasi lain sedang berjalan. Agen otomatis mencoba lagi; kalau masih gagal, jadwal malam berikutnya akan menyusul. |
| `File konfigurasi tidak ditemukan` | `.env` belum dibuat di folder agen, atau kolom "Start in" di Task Scheduler salah. |

## 10. Keamanan

- `.env` berisi API key dan token. Jangan dibagikan, jangan disalin ke tempat lain, dan batasi izin bacanya (bagian 4).
- Mengganti token: tim aplikasi mengganti `EMPLOYEE_PUSH_TOKEN` di aplikasi, lalu tim IT mengganti nilai yang sama di `.env`. Tidak perlu memasang ulang agen.
- Agen tidak pernah mematikan pemeriksaan sertifikat HTTPS dan tidak mengikuti pengalihan alamat (redirect), sehingga token tidak terkirim ke alamat lain.
- Menghentikan agen: hapus jadwalnya (Linux: hapus baris di `crontab -e`; Windows: hapus tugas di Task Scheduler), lalu hapus folder agen.

## 11. Kontak jika gagal

Bila sinkron gagal dua malam berturut-turut, atau ada pesan yang tidak tercantum di bagian 9, hubungi tim pengembang aplikasi Asset Tracking SHD:

- Nama: _(isi sebelum serah terima)_
- Email / telepon: _(isi sebelum serah terima)_

Sertakan baris log terakhir dari `sync.log`. Log tidak berisi data pegawai atau rahasia, jadi aman dikirim.
