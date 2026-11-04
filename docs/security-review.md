# Audit Keamanan — Sistem Informasi Aset SHD

## Ringkasan

| | |
|---|---|
| **Tanggal** | 4 November 2026 |
| **Kode yang diaudit** | Commit `bbed525` ditambah perubahan T26n yang belum di-commit |
| **Cakupan** | Backend Laravel dan frontend React: autentikasi & sesi, otorisasi, endpoint publik, validasi input & injeksi, upload & unduh file, konfigurasi, rahasia & repo, dependensi, ekspor Excel, logging |
| **Perubahan kode** | Tidak ada. Audit ini hanya menambah dokumen ini dan satu baris penunjuk di `docs/TASKS.md` |

| Tingkat | Jumlah |
|---|---|
| Kritis | 0 |
| Tinggi | 1 |
| Sedang | 6 |
| Rendah | 13 |
| Info | 6 |
| **Total** | **26** |

**Kesimpulan:** kode aplikasi cukup kuat. Uji injeksi, XSS, mass assignment, otorisasi, dan upload berbahaya tidak menembus apa pun. Risiko terbesar justru ada pada akun default (S01) dan konfigurasi deploy (S02–S06). Temuan S01 **naik menjadi Kritis jika repo GitHub bersifat publik**; visibilitas repo belum dicek karena di luar batas uji lokal.

## Cara membaca bukti

Kolom **Dasar** di tabel temuan dan tabel "Sudah aman" menjelaskan seberapa kuat buktinya:

| Dasar | Arti |
|---|---|
| **Uji langsung** | Terbukti lewat request atau browser ke server lokal |
| **Baca kode** | Hanya dari membaca kode atau konfigurasi, belum dibuktikan saat berjalan |
| **Uji + kode** | Sebagian terbukti lewat uji, sebagian dari kode. Rinciannya ada di kolom bukti |

### Lingkungan uji
- **Backend:** `php -S` dengan SQLite sementara. Database, cache, log, dan upload dipisahkan ke folder sementara di luar repo. Sebelum uji, token publik acak dari SQLite dijawab 200 dan token ngawur dijawab 404, yang membuktikan server memakai SQLite.
- **Konfigurasi yang dibandingkan:** production dengan `APP_DEBUG=false`, production dengan `APP_DEBUG=true`, dan local.
- **Frontend:** bundle production (`vite build`) di Chrome headless. Request ke host luar diblokir dan dicatat.
- **Tidak disentuh:** Supabase dan isi `.env`. Setelah audit, 0 file di `backend/storage`, `backend/bootstrap`, dan `backend/public` berubah.
- `composer audit` dan `npm audit` menghubungi registry paket, sesuai cakupan audit.

### Batasan
Hal berikut **belum diuji**, jadi jangan dianggap aman hanya karena tidak muncul sebagai temuan:
- **PostgreSQL:** semua uji memakai SQLite. Perilaku khusus PostgreSQL pada S11 adalah perkiraan.
- **Hosting production:** web server, TLS, reverse proxy, dan bucket S3 belum ada. Uji file sensitif hanya membuktikan perilaku `php -S` dan Laravel.
- **Nilai `.env` asli:** tidak dibaca, jadi nilai `APP_DEBUG` yang sedang dipakai tidak diketahui.
- **Aplikasi Excel:** file `.xlsx` diperiksa dari isi XML-nya, tidak dibuka di Excel.
- **RLS Supabase dan visibilitas repo GitHub:** tidak diverifikasi (S01, S26).
- **Serangan volume besar (DoS):** tidak dilakukan.

## Tabel temuan

Temuan diurutkan dari tingkat tertinggi.

| ID | Tingkat | Judul | Lokasi | Bukti singkat | Dampak | Rekomendasi | Dasar | Status |
|---|---|---|---|---|---|---|---|---|
| S01 | Tinggi | Akun default dengan kata sandi tertulis di repo, dan tidak ada cara menggantinya | `backend/database/seeders/DatabaseSeeder.php:13-17`, `backend/routes/api.php` | Username dan kata sandi yang dibaca dari seeder berhasil login (200). Kata sandi 10 karakter, pola umum, tanpa simbol. Tidak ada route ganti kata sandi. `updateOrCreate` mengembalikan kata sandi setiap seeder dijalankan. SPEC §2: akun dibuat lewat seeder | Siapa pun yang punya akses repo bisa login ke production dengan akses penuh, termasuk hapus permanen aset. **Kritis jika repo publik** | Buat akun lewat perintah artisan atau env tanpa nilai bawaan, tambah fitur ganti kata sandi, pisahkan data demo dari seeder production, ganti kata sandi jika sudah terlanjur dipakai, dan cek visibilitas repo | Uji + kode | Terbuka |
| S02 | Sedang | Templat env berisi nilai yang tidak aman untuk production | `backend/.env.example:2,4,18` | `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug`. Dengan debug nyala, respons 500 memuat teks SQL, path server, dan `trace`; dengan debug mati hanya "Server Error" | Tim deploy yang menyalin templat membocorkan struktur database dan path server, sekaligus mengaktifkan S12. TASKS juga mencatat toast error menampilkan SQL & host saat debug | Pakai nilai aman di templat atau beri komentar wajib, dan tulis di README deploy (T28) | Uji + kode | Terbuka |
| S03 | Sedang | Header keamanan tidak ada | Semua respons backend, `frontend/index.html`, `backend/public/.htaccess` | CSP, `X-Frame-Options`, HSTS, `X-Content-Type-Options`, `Referrer-Policy`, dan `Permissions-Policy` tidak ada di respons API, `/`, `/up`, maupun unduhan file. `index.html` tanpa meta CSP. `X-Powered-By: PHP/8.3.16` terlihat | Tanpa CSP, XSS di masa depan langsung bisa mencuri token (S04). Halaman bisa dibingkai (clickjacking) jika hosting juga tidak menambahkannya | Tambahkan di web server untuk frontend dan API (lihat Checklist), matikan `expose_php` | Uji + kode | Terbuka |
| S04 | Sedang | Token login disimpan di `localStorage` | `frontend/src/lib/auth.ts:19,32` | Token terbaca JavaScript halaman (`tokenTerbacaJS: true`), tidak ada di cookie. Token berlaku 480 menit dengan akses penuh | Satu celah XSS, termasuk dari dependensi pihak ketiga, cukup untuk mencuri sesi. Saat ini tidak ditemukan XSS, jadi risikonya bergantung pada kondisi masa depan | Prioritaskan CSP (S03). Pertimbangkan cookie `HttpOnly` lewat mode SPA Sanctum jika frontend dan API satu domain induk | Uji langsung | Terbuka |
| S05 | Sedang | Aplikasi tidak memaksa HTTPS | `backend/bootstrap/app.php`, `backend/app/Providers/AppServiceProvider.php` | Tidak ada `forceScheme`, redirect, atau HSTS di kode. Request HTTP biasa dilayani tanpa redirect | Jika reverse proxy salah konfigurasi, kata sandi dan token terkirim tanpa enkripsi | Paksa HTTPS di proxy, tambah HSTS, pakai URL `https` untuk `APP_URL`, `FRONTEND_URL`, `QR_BASE_URL` | Uji + kode | Terbuka |
| S06 | Sedang | Trusted proxy belum dikonfigurasi, rate limit akan salah sasaran di balik proxy | `backend/bootstrap/app.php:17-19`, `backend/app/Providers/AppServiceProvider.php:32,36` | Saat ini `X-Forwarded-For` diabaikan (baik: tidak bisa dipakai bypass). Namun di balik proxy, `$request->ip()` menjadi IP proxy | Semua pengunjung berbagi jatah: halaman QR 30 permintaan/menit untuk seluruh pengguna, dan satu orang bisa mengunci login username tertentu untuk semua orang | `trustProxies(at: <IP proxy>)` saat deploy, jangan memakai `*` | Uji + kode | Terbuka |
| S07 | Sedang | Halaman QR publik menampilkan nama PIC, kode keuangan, dan catatan lokasi pinjam | `backend/app/Http/Resources/PublicAssetResource.php:41,43,48` | Nilai uji `pic_name`, `finance_code`, dan `destination_note` muncul di respons publik tanpa login. Lokasi saat ini dan status dipinjam juga terlihat | Siapa pun yang memegang atau memotret label bisa melihat nama orang, kode keuangan, dan letak aset. Catatan lokasi berupa teks bebas sehingga bisa berisi nama peminjam | Minta konfirmasi mentor (masih terbuka di SPEC §6), hapus field yang tidak perlu, dan jangan tampilkan `note` | Uji langsung | Terbuka |
| S08 | Rendah | `username` berupa array memicu error 500 tanpa login | `backend/app/Providers/AppServiceProvider.php:36` | `POST /api/login` dengan `username` array, objek, atau `username[]` dijawab 500 karena nilainya diubah paksa ke string di dalam rate limiter. Tercatat "Array to string conversion" di log | Siapa pun bisa memenuhi log tanpa login, dan request ini tidak terkena hitungan throttle | Pakai nilai string saja di rate limiter (`is_string`), sehingga validasi menjawab 422 | Uji langsung | Terbuka |
| S09 | Rendah | Username bisa ditebak dari waktu respons login | `backend/app/Http/Controllers/AuthController.php:18-20` | Median 434 ms untuk username yang ada dan 217 ms untuk yang tidak ada (10 sampel). Rentangnya tidak bertumpuk. Isi pesan error sudah sama | Penyerang bisa mengetahui username yang valid lalu memfokuskan tebakan kata sandi | Jalankan `Hash::check` terhadap hash palsu saat username tidak ditemukan | Uji langsung | Terbuka |
| S10 | Rendah | Tidak ada batas login per IP atau per akun | `backend/app/Providers/AppServiceProvider.php:35-36` | 30 username berbeda dari satu IP: 0 kali 429. Batas hanya berlaku per kombinasi username + IP | Satu IP bisa mencoba banyak username, dan banyak IP bisa menyerang satu akun tanpa batas gabungan. Lebih berisiko bersama S01 | Tambah batas kedua per IP (mis. 20/menit), dan pertimbangkan batas per akun lintas IP | Uji + kode | Terbuka |
| S11 | Rendah | Input yang lolos validasi bisa memicu error 500 | `backend/app/Http/Controllers/LookupController.php:48`, `backend/app/Services/AssetFileService.php:59`, `backend/app/Http/Requests/UpdateConsumableQuantityRequest.php:23-24`, `backend/app/Http/Controllers/PublicAssetController.php` | **Teruji di SQLite:** `/lookups/request-methods?search[]=x` memberi 500. Nama file non UTF-8 memberi 500 dan barisnya tetap tersimpan, sehingga daftar file aset itu 500 sampai file dihapus. `quantity` 2147483648 dan `adjustment` 99999999999999 diterima; nama file 304 karakter diterima. **Perkiraan di PostgreSQL (belum diuji):** kolom `integer` dan `varchar(255)` akan menolak dengan 500; karakter NUL di token publik atau login juga bisa memberi 500 | Error 500 dan log berisik. Karakter NUL bisa dipicu tanpa login. Tidak ada kebocoran data | Validasi tipe string pada lookup, `max` pada jumlah, dan nama file (UTF-8 sah, maks. 255). Ulangi uji di PostgreSQL non-produksi | Uji + kode | Terbuka |
| S12 | Rendah | Route Boost tanpa login aktif saat debug atau mode local | `POST /_boost/browser-logs`, `backend/vendor/laravel/boost/src/BoostServiceProvider.php:199-215` | production + debug mati: 404. production + debug nyala: 200 `logged`. local + debug mati: 200 `logged` | Siapa pun bisa menulis ke log server jika konfigurasi salah (lihat S02) | Deploy dengan `composer install --no-dev` dan `APP_DEBUG=false`, lalu pastikan route ini 404 | Uji langsung | Terbuka |
| S13 | Rendah | `frontend/.env` ter-commit walaupun tercantum di `.gitignore` | `frontend/.env`, `frontend/.gitignore` | Satu commit riwayat, hanya berisi `VITE_API_URL` ke localhost (bukan rahasia). Bundle yang dibangun tanpa env memuat `localhost:8000` | Build production bisa mengarah ke localhost tanpa disadari, dan rahasia yang kelak ditambahkan ke file ini ikut ter-commit | `git rm --cached frontend/.env`, pakai `.env.example`, dan set `VITE_API_URL` saat build | Uji + kode | Terbuka |
| S14 | Rendah | Cookie tanpa atribut `Secure` | `GET /sanctum/csrf-cookie`, `backend/config/session.php:172` | `XSRF-TOKEN` dan cookie sesi tanpa `Secure`. `SESSION_SECURE_COOKIE` tidak ada di `.env.example`. Mode stateful Sanctum tidak aktif, jadi cookie ini tidak dipakai untuk login | Dampak kecil saat ini, tetapi menjadi nyata jika kelak memakai login berbasis cookie (S04) | Set `SESSION_SECURE_COOKIE=true` di production | Uji + kode | Terbuka |
| S15 | Rendah | Pemindai QR mengambil file wasm dari CDN pihak ketiga tanpa pemeriksaan integritas | `/scan`, `frontend/node_modules/zxing-wasm/dist/es/share.js:589` | Membuka `/scan` memicu request ke `fastly.jsdelivr.net`, di halaman yang punya akses ke token | Kamera bisa gagal jika jaringan memblokir CDN (belum diuji dengan kamera), dan ada risiko rantai pasok. CSP nantinya juga harus mengizinkan host ini | Self-host file wasm, dan uji di jaringan INALUM sebelum demo | Uji + kode | Terbuka |
| S16 | Rendah | `safeNext` tidak menolak `/\`, tab, dan baris baru | `frontend/src/pages/LoginPage.tsx:11-13` | 12 varian `next` pada 2 alur (24 uji di bundle production): tidak ada navigasi ke luar. Varian `/\evil` hanya tertahan oleh React Router ("External navigation is not allowed"), dan setelah login pengguna tertahan di `/login` | Belum bisa dieksploitasi, tetapi perlindungannya bergantung pada satu library | Tolak `\` dan karakter kontrol, lalu pastikan origin dari `new URL(next, location.origin)` sama | Uji + kode | Terbuka |
| S17 | Rendah | Nama file asli tidak diselaraskan dengan isi file, dan PDF aktif diterima | `backend/app/Services/AssetFileService.php:59`, `backend/app/Http/Controllers/AssetFileController.php:80-85` | PDF bernama `evil.html` diterima dan `?download=1` mengirim `filename=evil.html` (tipe tetap `application/pdf`). PDF berisi aksi JavaScript diterima. Frontend saat ini tidak memakai `?download=1` | Berkas tersimpan dengan ekstensi menyesatkan jika diunduh lewat API. Tidak berdampak pada file yang disimpan server (nama UUID) | Sesuaikan ekstensi nama unduhan dengan tipe sebenarnya, dan pakai validasi `extensions` | Uji + kode | Terbuka |
| S18 | Rendah | Log memuat binding SQL dan host database | `backend/storage/logs/laravel.log` (lokal), `backend/.env.example:18` | Error SQL mencatat nilai `username` (nilai uji muncul 2 kali). Log lokal memuat host Supabase pada error koneksi, tanpa kata sandi DB. File ini di-ignore git | Pembaca log mengetahui host DB dan username; kata sandi yang tidak sengaja diketik di kolom username bisa tercatat | `LOG_LEVEL=warning`, rotasi log, dan batasi akses file log di server | Uji + kode | Terbuka |
| S19 | Rendah | Jejak keamanan terbatas | `backend/app/Services/AssetService.php:199-214`, seluruh `backend/app` | Tidak ada pencatatan login atau gagal login (tidak ada `Log::` di `app/`). Hapus aset menghapus riwayatnya tanpa catatan siapa yang menghapus (keputusan pengguna, SPEC §5). Seeder hanya membuat satu akun, sehingga `recorded_by` tidak membedakan orang | Sulit menelusuri penyalahgunaan, padahal akuntabilitas adalah tujuan sistem (SPEC §1) | Catat login dan penghapusan aset ke log terpisah, dan buat akun per staf | Baca kode | Terbuka |
| S20 | Rendah | Dependensi dengan kerentanan yang diketahui | `frontend/package-lock.json`, `backend/composer.lock` | `follow-redirects` 1.16.1 (npm: high, lewat axios) tidak ikut bundle browser. `symfony/mime` v7.4.19 CVE-2026-45070 (medium, injeksi header email), sedangkan aplikasi tidak mengirim email. 12 paket moderate lain hanya dependensi dev (rantai eslint) | Tidak terjangkau dari alur aplikasi saat ini | `npm audit fix` dan `composer update symfony/mime`, lalu jalankan audit sebelum rilis | Uji + kode | Terbuka |
| S21 | Info | Tidak ada pembatasan akses antar staf (IDOR) | Semua route ber-auth | Staf B bisa membaca aset, mengunduh file, dan menghapus aset milik staf A (200). Ini sesuai SPEC §2 (tanpa role) | Semua staf setara, sehingga satu akun yang bocor berarti akses penuh | Tidak perlu diubah selama satu role. Tinjau ulang jika role ditambahkan | Uji langsung | Terbuka |
| S22 | Info | Token QR tidak bisa dicabut | `backend/app/Models/Asset.php:45-46` | Token dikunci agar tidak berubah (SPEC §5). Label yang bocor tetap berlaku sampai aset dihapus | Terbatas pada data di S07 | Terima sebagai keputusan desain, atau tambah rotasi token jika perlu | Baca kode | Terbuka |
| S23 | Info | Teks diawali `=` di Excel bisa menjadi rumus jika selnya diedit | `backend/app/Services/XlsxReportWriter.php:99` | Saat dibuka tetap teks (lihat "Sudah aman"), tetapi nilainya disimpan apa adanya tanpa awalan pengaman | Rumus bisa aktif jika staf mengedit sel itu atau mengonversi ke CSV lalu membukanya lagi. Perilaku di Excel belum diuji | Tambah awalan `'` jika ingin perlindungan berlapis | Uji + kode | Terbuka |
| S24 | Info | Tidak ada rate limit pada endpoint ber-auth yang berat | `backend/routes/api.php:43,88`, upload file | Laporan, label PDF (120 aset), dan upload `Lainnya` tidak dibatasi jumlahnya | Akun yang bocor bisa membebani server atau mengisi disk | Tambah `throttle` pada laporan, label, dan upload | Baca kode | Terbuka |
| S25 | Info | Halaman QR publik tidak meminta untuk tidak diindeks | `frontend/index.html`, `backend/public/robots.txt` | Tidak ada meta `robots`, dan `robots.txt` tidak membatasi apa pun | Tautan yang tersebar bisa muncul di mesin pencari | Tambah `noindex` di halaman publik | Uji + kode | Terbuka |
| S26 | Info | RLS Supabase belum diverifikasi di database asli | `backend/database/migrations/2026_10_13_000015_enable_rls_on_all_tables.php` | Migration mengaktifkan RLS di semua tabel `public`, dan `asset_code_histories` punya perintah sendiri. Tidak dicek ke Supabase sesuai batas audit | Jika RLS tidak aktif, data bisa terbaca lewat API Supabase | Jalankan query verifikasi di Checklist | Baca kode | Terbuka |

## Sudah aman

Hal berikut sudah diperiksa dan tidak bermasalah pada lingkungan uji.

### Autentikasi & sesi
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Pesan login gagal | Username salah dan kata sandi salah memberi respons yang sama persis | Uji langsung |
| Throttle login | Percobaan ke-6 dijawab 429 dengan `Retry-After`; kata sandi benar pun ditolak saat terkunci | Uji langsung |
| Bypass throttle | `X-Forwarded-For`, `X-Real-IP`, `Forwarded`, dan variasi besar/kecil atau spasi tidak bisa menghindarinya | Uji langsung |
| Masa berlaku token | Umur 479 menit: 200. Umur 481 menit: 401 | Uji langsung |
| Logout | Token langsung ditolak (401) setelah logout | Uji langsung |
| Penyimpanan token di server | Hash SHA-256, teks asli tidak tersimpan | Uji langsung |
| Token tidak sah | Token acak, tanpa skema, skema Basic, dan token lewat query semuanya 401 | Uji langsung |
| Data akun | `/api/user` dan respons login tidak memuat kata sandi | Uji langsung |
| Logout di frontend | Token hilang dari `localStorage`, cache data dibersihkan, dan kembali ke `/login` | Uji + kode |

### Otorisasi
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Semua route API | 180 kombinasi (85 route ber-auth, tanpa token dan dengan token palsu): semuanya 401 | Uji langsung |
| Unduh file tanpa token | 401, isi file tidak terkirim | Uji langsung |
| Data SHD | Departemen SHD tidak bisa diubah atau dihapus (422) | Uji langsung |

### Endpoint publik
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Kekuatan token | 12 karakter dari `Str::random` (generator acak aman), 62 jenis karakter, 71,5 bit, semua unik | Uji + kode |
| Enumerasi | Id, kode aset, token huruf kecil, wildcard, SQLi, NUL (di SQLite), dan token 5000 karakter: semuanya 404 tanpa data | Uji langsung |
| Rate limit | 429 pada permintaan ke-31; `X-Forwarded-For` acak tidak bisa menghindarinya | Uji langsung |
| Data yang tidak boleh tampil | Nama peminjam, unit, keperluan, no. approval, catatan aset, S/N, spesifikasi, merek, dan id internal tidak muncul | Uji langsung |
| Aset terhapus | Token lamanya 404 | Uji langsung |
| Metode lain | `POST` dan `DELETE` dijawab 405 | Uji langsung |

### Validasi input & injeksi
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Fuzz parameter daftar | 352 permutasi sort, filter, halaman, dan pencarian pada 4 endpoint: 0 error 5xx, 0 hasil dari payload SQLi | Uji langsung |
| Raw query | Semua nilai memakai binding. Interpolasi hanya berisi nama kolom atau SQL dari kode (`Search::likeSql`, `SupplyRequest.php:174-180`, `UniqueCaseInsensitive.php:31`) | Baca kode |
| Sort | Hanya daftar yang diizinkan; `password` dan `id;DROP TABLE users` dijawab 422 | Uji + kode |
| Wildcard pencarian | `%`, `_`, `!` tidak melebar: 0 dari 31 aset | Uji langsung |
| Mass assignment | `code`, `status`, `condition`, `public_token`, `id`, `created_at`, `is_internal`, `last_number`, `borrowed_by`, `applied_to`, `created_by`, `slug`, dan kolom sistem ruangan: semuanya ditolak atau diabaikan, diperiksa langsung di database | Uji langsung |
| Kode jenis aset | `<x>`, `../A`, `A'B`, dan pola lain dijawab 422 | Uji langsung |
| XSS tersimpan | Payload `<img onerror>`, `<script>`, dan `<svg onload>` di 14 halaman bundle production: **payload tampil sebagai teks di semua halaman, 0 tereksekusi** | Uji langsung |
| Render HTML | Tidak ada `dangerouslySetInnerHTML`, `innerHTML`, `eval`, atau `{!! !!}` | Baca kode |
| PDF label | `enable_php` dan `enable_remote` mati; hanya berisi kode aset yang polanya dibatasi | Baca kode |

### Upload & unduh file
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Tipe file dari isi | HTML, SVG, PHP, EXE, dan GIF yang disamarkan dengan nama atau tipe palsu: semuanya 422 | Uji langsung |
| File `.php` | Ditolak walaupun isinya PNG asli | Uji langsung |
| Nama penyimpanan | `assets/{id}/{uuid}.{ekstensi dari isi}`, tidak mengikuti nama dari pengguna | Uji langsung |
| Path traversal | `../../` dan `..\..\` dibuang dari nama file | Uji langsung |
| Injeksi header | Nama file dengan CRLF tidak menyisipkan header | Uji langsung |
| Batas ukuran | Tepat 5 MB: 201. 5 MB + 1 byte dan 8 MB: 413 | Uji langsung |
| Akses `/storage` | File nyata tanpa tanda tangan, tanda tangan palsu, `PUT`, dan traversal: semuanya 404 di mode production | Uji langsung |
| File sensitif | `/.env`, `/.git/config`, `composer.json`, dan file log: 404 tanpa isi rahasia. **Ulangi di web server production** | Uji langsung |
| Lokasi file | Disk private di luar `public/`, tanpa symlink `public/storage` | Uji + kode |

### Konfigurasi
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| CORS | Di Chrome, request dari origin lain diblokir dan origin yang diizinkan berhasil. Tanpa `Allow-Credentials` | Uji langsung |
| Error dengan debug mati | Hanya "Server Error", tanpa SQL, path, atau trace | Uji langsung |
| CSRF | Mode stateful Sanctum tidak aktif, hanya Bearer token | Baca kode |
| Cache respons | `Cache-Control: no-cache, private` | Uji langsung |
| Bundle frontend | Tanpa source map dan tanpa rahasia | Uji langsung |

### Rahasia & repo
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Riwayat git | 14 pola (APP_KEY, kata sandi DB, URL Postgres, host dan kunci Supabase, kunci AWS, private key, token GitHub dan lainnya): 0 commit | Uji langsung |
| `backend/.env` | Di-ignore dan tidak pernah di-commit | Uji langsung |
| `.env.example` | `APP_KEY`, kata sandi, dan kunci kosong (lihat S02 untuk nilai non-rahasia) | Baca kode |

### Dependensi
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Paket utama | `laravel/framework`, `laravel/sanctum`, `dompdf`, `openspout`, dan `php-qrcode` tidak dilaporkan rentan oleh audit | Uji langsung |

### Ekspor Excel
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Formula injection | Payload `=`, `+`, `-`, `@`, dan `=HYPERLINK(...)` di 5 laporan: 0 sel `<f>`, semua `inlineStr`. Ringkasan filter juga teks. Belum dibuka di aplikasi Excel (S23) | Uji langsung |
| File sementara | Dihapus setelah dikirim (`ReportController.php:26`) | Baca kode |

### Logging
| Pemeriksaan | Hasil | Dasar |
|---|---|---|
| Kata sandi & token | Saat error dipicu, kata sandi uji, token, dan header `Authorization` muncul 0 kali di log | Uji langsung |
| Kode aplikasi | Tidak ada pemanggilan `Log::` yang mencatat data sensitif | Baca kode |

## Checklist sebelum deploy

### Wajib sebelum go-live
- [ ] **Akun (S01):** jangan menjalankan `DatabaseSeeder` apa adanya di production. Buat akun dengan kata sandi kuat di luar repo.
- [ ] **Repo (S01):** pastikan repo GitHub privat. Jika pernah publik, anggap kata sandi seeder sudah bocor.
- [ ] **Environment (S02):** `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, `APP_KEY` baru.
- [ ] **Dependensi dev (S12):** `composer install --no-dev --optimize-autoloader`, lalu pastikan `POST /_boost/browser-logs` dijawab 404.
- [ ] **HTTPS (S05):** TLS aktif, HTTP dialihkan ke HTTPS. `APP_URL`, `FRONTEND_URL`, `QR_BASE_URL`, dan `VITE_API_URL` memakai `https`.
- [ ] **Proxy (S06):** `trustProxies` berisi IP proxy, lalu uji bahwa rate limit membaca IP pengunjung.
- [ ] **RLS (S26):** query berikut harus **kosong**:
  ```sql
  select tablename from pg_tables where schemaname = 'public' and not rowsecurity;
  ```
- [ ] **Data publik (S07):** konfirmasi mentor soal field yang boleh tampil di halaman QR.

### Konfigurasi server
- [ ] **Header (S03):** tambahkan di web server untuk frontend dan API, lalu uji semua halaman. CSP di bawah adalah titik awal; `connect-src` perlu menyertakan URL API, dan pemindai QR butuh izin wasm serta sumbernya (S15).
  ```
  Strict-Transport-Security: max-age=31536000; includeSubDomains
  X-Content-Type-Options: nosniff
  X-Frame-Options: DENY
  Referrer-Policy: strict-origin-when-cross-origin
  Permissions-Policy: camera=(self), microphone=(), geolocation=()
  Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'
  ```
- [ ] **Web server:** document root hanya `backend/public`, blokir dotfile, dan uji ulang `/.env`, `/.git/config`, serta `/storage/...`.
- [ ] **PHP:** `expose_php=Off`, `upload_max_filesize` minimal 6M, `post_max_size` minimal 8M.
- [ ] **Cookie (S14):** `SESSION_SECURE_COOKIE=true`.
- [ ] **Cache:** `CACHE_STORE` bukan `array`, karena throttle butuh penyimpanan yang bertahan antar-request.
- [ ] **Database:** `DB_SSLMODE=require`.
- [ ] **Storage:** jika memakai S3 atau Supabase Storage, bucket harus privat dan kuncinya hanya untuk bucket itu.
- [ ] **Scheduler:** cron `schedule:run` aktif agar token kedaluwarsa dibersihkan.
- [ ] **Log (S18):** rotasi aktif dan akses file dibatasi.

### Build & verifikasi
- [ ] **Frontend (S13):** `VITE_API_URL` diset saat build, lalu pastikan bundle tidak memuat `localhost`.
- [ ] **Audit dependensi (S20):** `composer audit` dan `npm audit --omit=dev` tanpa temuan yang relevan.
- [ ] **PostgreSQL (S11):** ulangi uji input tidak wajar di PostgreSQL non-produksi.
- [ ] **Pemindai QR (S15):** uji kamera di jaringan INALUM.
- [ ] **Uji akhir:** ulangi uji CORS dan tanpa token di server production.
