# Daftar Endpoint — Sistem Informasi Aset SHD

Daftar seluruh endpoint backend dan pemakaiannya di frontend web. Parameter dan bentuk respons ada di `docs/SPEC.md` §14.

Disusun pada 4 November 2026 (diperbarui 11 November 2026, T31) dari `php artisan route:list` dan kode frontend. Kolom "Dipakai frontend" berasal dari penelusuran kode, bukan dari pemantauan trafik.

## Ringkasan

- **94 endpoint API:** 92 dipakai frontend web, 2 tidak dipakai.
- **6 route non-API:** tidak ada yang dipakai frontend.
- **Alamat:** semua endpoint API berawalan `/api`. Frontend web mengambil alamat API dari `VITE_API_URL`.
- **Akses "Token":** wajib header `Authorization: Bearer {token}` dari `POST /api/login`. Tanpa token sah dijawab 401.
- **Format:** semua respons error berbentuk JSON. Path file di kolom "Dipakai frontend" relatif terhadap `frontend/src/`.

| Modul | Total | Dipakai | Tidak dipakai |
|---|---|---|---|
| Autentikasi | 3 | 3 | 0 |
| Halaman publik QR | 1 | 1 | 0 |
| Dashboard | 3 | 3 | 0 |
| Laporan | 1 | 1 | 0 |
| Data master: departemen | 5 | 5 | 0 |
| Data master: gedung & ruangan | 11 | 10 | 1 |
| Data master: kategori aset | 5 | 5 | 0 |
| Data master: jenis aset | 6 | 6 | 0 |
| Data master: kategori stok | 5 | 5 | 0 |
| Lookup | 2 | 1 | 1 |
| Aset | 20 | 20 | 0 |
| Peminjaman | 6 | 6 | 0 |
| Kelengkapan ruangan | 7 | 7 | 0 |
| Permintaan ke departemen lain | 11 | 11 | 0 |
| Stok habis pakai | 8 | 8 | 0 |
| **Total** | **94** | **92** | **2** |

## Endpoint API yang tidak dipakai frontend

"Tidak dipakai" hanya berarti tidak dipanggil oleh frontend web. Keduanya tetap dipertahankan, punya test, dan tercantum di SPEC §14. **Konfirmasi dulu ke tim HP sebelum menghapus.**

| Method | Endpoint | Fungsi | Keterangan |
|---|---|---|---|
| GET | `/api/buildings/{id}/rooms` | Daftar ruangan dalam satu gedung | Frontend memakai `GET /api/rooms?building_id={id}` |
| GET | `/api/lookups/borrower-units` | Saran departemen peminjam | Departemen di form pinjam kini teks bebas tanpa saran (SPEC §7) |

`GET /api/lookups/request-methods` juga sempat tidak dipakai. Sejak T26p form permintaan mengambil pilihannya dari endpoint ini, sehingga web dan versi HP memakai sumber yang sama. Endpoint ini tidak boleh dihapus.

## Batas permintaan

Melewati batas dijawab 429 dengan pesan Bahasa Indonesia dan header `Retry-After`, kecuali halaman publik yang masih memakai pesan bawaan Laravel.

| Endpoint | Batas | Dihitung per |
|---|---|---|
| `POST /api/login` | 5/menit | Kombinasi username + IP |
| `POST /api/login` | 20/menit | IP |
| `GET /api/public/assets/{token}` | 30/menit | IP |
| `GET /api/reports/{type}` | 10/menit | Pengguna |
| `GET /api/assets/{id}/label`, `POST /api/assets/labels` | 20/menit, jatah bersama | Pengguna |
| `POST /api/assets/{id}/files` | 30/menit | Pengguna |

Di balik reverse proxy, batas per IP hanya benar jika `TRUSTED_PROXIES` diisi. Tanpa itu semua pengunjung terbaca satu IP dan berbagi jatah.

## Aturan input

| Input | Aturan | Jika dilanggar |
|---|---|---|
| `username` pada login | Teks UTF-8 tanpa karakter NUL, maks. 50 karakter | 422 |
| `{token}` halaman publik | Tepat 12 karakter huruf dan angka | 404, tanpa query ke database |
| `search` pada `/api/lookups/*` | Teks, maks. 100 karakter | 422 |
| `quantity`, `min_quantity`, `standard_quantity`, `actual_quantity` | Maks. 2147483647 | 422 |
| `quantity` pada `POST /api/consumables/{id}/transactions` | Bukan 0; antara 1 dan 2147483647 (Koreksi: boleh negatif); hasil akhir tidak boleh negatif atau melewati batas | 422 |
| Nama file unggahan | UTF-8 sah, maks. 255 karakter | 422 |

Nama file saat diunduh mengikuti tipe isi sebenarnya: `evil.html` berisi PDF diunduh sebagai `evil.pdf`. Nama asli tetap ditampilkan di daftar file.

## Daftar endpoint API

### Autentikasi

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| POST | `/api/login` | Login, mengembalikan token dan data akun | Publik, maks. 5/menit per username + IP dan 20/menit per IP | Ya: Halaman Login (`pages/LoginPage.tsx`) |
| POST | `/api/logout` | Logout, mencabut token yang dipakai | Token | Ya: Tombol Keluar (`components/layout/AppLayout.tsx`) |
| GET | `/api/user` | Data akun yang sedang login | Token | Ya: Pemeriksaan sesi (`components/RequireAuth.tsx`) |

### Halaman publik QR

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/public/assets/{token}` | Data aset untuk halaman publik QR | Publik, maks. 30/menit per IP | Ya: Halaman publik (`pages/scan/PublicAssetPage.tsx`) |

### Dashboard

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/dashboard/cards` | Kartu ringkasan | Token | Ya: Dashboard (`lib/queries/dashboard.ts`) |
| GET | `/api/dashboard/room-readiness` | Panel kesiapan ruangan per gedung | Token | Ya: Dashboard (`lib/queries/dashboard.ts`) |
| GET | `/api/dashboard/panels` | Panel jatuh tempo, permintaan terbaru, perlu servis, stok menipis | Token | Ya: Dashboard (`lib/queries/dashboard.ts`) |

### Laporan

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/reports/{type}` | Unduh laporan Excel. `type`: `assets`, `loans`, `rooms`, `requests`, `consumables` | Token, maks. 10/menit per pengguna | Ya: Ekspor di Dashboard dan tiap halaman daftar (`lib/download.ts`) |

### Data master: departemen

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/departments` | Daftar departemen | Token | Ya: Dropdown di banyak form, Data Master (`lib/queries/master.ts`) |
| POST | `/api/departments` | Tambah departemen | Token | Ya: Form aset, Data Master (`lib/lookups.ts`, `pages/master/MasterModals.tsx`) |
| PUT/PATCH | `/api/departments/{id}` | Ubah departemen | Token | Ya: Data Master (`pages/master/MasterModals.tsx`), memakai PUT |
| DELETE | `/api/departments/{id}` | Hapus departemen, ditolak jika masih dipakai | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |
| GET | `/api/departments/{id}/usage` | Pemakaian departemen sebelum dihapus | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |

### Data master: gedung & ruangan

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/buildings` | Daftar gedung | Token | Ya: Dropdown lokasi, Data Master (`lib/queries/master.ts`) |
| POST | `/api/buildings` | Tambah gedung | Token | Ya: Form aset, Kelengkapan Ruangan, Data Master (`lib/lookups.ts`, `pages/master/MasterModals.tsx`) |
| PUT/PATCH | `/api/buildings/{id}` | Ubah gedung | Token | Ya: Data Master (`pages/master/MasterModals.tsx`), memakai PUT |
| DELETE | `/api/buildings/{id}` | Hapus gedung, ditolak jika masih dipakai | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |
| GET | `/api/buildings/{id}/usage` | Pemakaian gedung sebelum dihapus | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |
| GET | `/api/buildings/{id}/rooms` | Daftar ruangan dalam satu gedung | Token | **Tidak** |
| POST | `/api/buildings/{id}/rooms` | Tambah ruangan di gedung, `is_storage` opsional | Token | Ya: Form aset, Form stok, Kelengkapan Ruangan, Data Master (`lib/lookups.ts`, `pages/master/MasterModals.tsx`) |
| GET | `/api/rooms` | Daftar ruangan, filter `building_id` | Token | Ya: Dropdown ruangan, Data Master (`lib/queries/master.ts`) |
| PUT | `/api/rooms/{id}` | Ubah nama & tanda gudang ruangan | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |
| DELETE | `/api/rooms/{id}` | Hapus ruangan, ditolak jika masih dipakai | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |
| GET | `/api/rooms/{id}/usage` | Pemakaian ruangan sebelum dihapus | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |

### Data master: kategori aset

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/categories` | Daftar kategori aset + jumlah jenis | Token | Ya: Daftar Aset (filter), popup tambah jenis, Data Master (`lib/queries/master.ts`) |
| POST | `/api/categories` | Tambah kategori aset | Token | Ya: Popup tambah jenis di form aset, Data Master (`lib/lookups.ts`, `pages/master/MasterModals.tsx`) |
| PUT/PATCH | `/api/categories/{id}` | Ubah nama kategori aset | Token | Ya: Data Master (`pages/master/MasterModals.tsx`), memakai PUT |
| DELETE | `/api/categories/{id}` | Hapus kategori aset, ditolak jika masih dipakai jenis | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |
| GET | `/api/categories/{id}/usage` | Pemakaian kategori aset sebelum dihapus | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |

### Data master: jenis aset

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/asset-types` | Daftar jenis aset | Token | Ya: Form aset, standar ruangan, Data Master (`lib/queries/master.ts`) |
| POST | `/api/asset-types` | Tambah jenis aset (`category_id` wajib) | Token | Ya: Form aset, Data Master (`lib/lookups.ts`, `pages/master/MasterModals.tsx`) |
| PUT/PATCH | `/api/asset-types/{id}` | Ubah nama, kategori, atau kode jenis aset | Token | Ya: Data Master (`pages/master/MasterModals.tsx`), memakai PUT |
| DELETE | `/api/asset-types/{id}` | Hapus jenis aset, ditolak jika masih dipakai | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |
| GET | `/api/asset-types/{id}/next-code` | Pratinjau kode aset berikutnya, tanpa memakai nomor | Token | Ya: Form aset (`pages/assets/AssetFormPage.tsx`) |
| GET | `/api/asset-types/{id}/usage` | Pemakaian jenis aset sebelum dihapus | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |

### Data master: kategori stok

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/consumable-categories` | Daftar kategori stok | Token | Ya: Form stok, Data Master (`lib/queries/master.ts`) |
| POST | `/api/consumable-categories` | Tambah kategori stok | Token | Ya: Form stok, Data Master (`lib/lookups.ts`, `pages/master/MasterModals.tsx`) |
| PUT/PATCH | `/api/consumable-categories/{id}` | Ubah kategori stok | Token | Ya: Data Master (`pages/master/MasterModals.tsx`), memakai PUT |
| DELETE | `/api/consumable-categories/{id}` | Hapus kategori stok, ditolak jika masih dipakai | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |
| GET | `/api/consumable-categories/{id}/usage` | Pemakaian kategori stok sebelum dihapus | Token | Ya: Data Master (`pages/master/MasterModals.tsx`) |

### Lookup

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/lookups/borrower-units` | Saran departemen peminjam | Token | **Tidak** |
| GET | `/api/lookups/request-methods` | Pilihan cara permintaan, satu sumber untuk web dan versi HP | Token | Ya: Form permintaan (`lib/queries/master.ts`) |

### Aset

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/assets` | Daftar aset, filter dan pencarian | Token | Ya: Daftar Aset, pilih aset di form pinjam (`lib/queries/lists.ts`, `pages/loans/AssetPicker.tsx`) |
| POST | `/api/assets` | Tambah aset | Token | Ya: Form aset (`pages/assets/AssetFormPage.tsx`) |
| GET | `/api/assets/{id}` | Detail aset dan aksi yang tersedia | Token | Ya: Detail aset, form ubah, form pinjam (`lib/queries/lists.ts`, `pages/loans/LoanForm.tsx`) |
| GET | `/api/assets/{id}/delete-impact` | Dampak penghapusan aset | Token | Ya: Popup hapus (`pages/assets/modals/DeleteAssetModal.tsx`) |
| GET | `/api/assets/{id}/code-preview` | Pratinjau kode baru saat jenis diganti | Token | Ya: Form ubah (`pages/assets/AssetFormPage.tsx`) |
| PUT | `/api/assets/{id}` | Ubah data aset | Token | Ya: Form ubah (`pages/assets/AssetFormPage.tsx`) |
| DELETE | `/api/assets/{id}` | Hapus aset permanen | Token | Ya: Popup hapus (`pages/assets/modals/DeleteAssetModal.tsx`) |
| GET | `/api/assets/{id}/events` | Riwayat aset | Token | Ya: Detail aset (`lib/queries/lists.ts`) |
| POST | `/api/assets/{id}/report` | Catat kerusakan, kehilangan, atau tidak layak pakai | Token | Ya: Popup catat (`pages/assets/modals/ReportModal.tsx`) |
| POST | `/api/assets/{id}/found` | Tandai ditemukan | Token | Ya: Popup ditemukan (`pages/assets/modals/FoundModal.tsx`) |
| POST | `/api/assets/{id}/services` | Catat servis | Token | Ya: Popup servis (`pages/assets/modals/ServiceModal.tsx`) |
| PATCH | `/api/assets/{id}/location` | Pindah lokasi | Token | Ya: Popup pindah (`pages/assets/modals/MoveLocationModal.tsx`) |
| GET | `/api/assets/{id}/files` | Daftar foto dan dokumen | Token | Ya: Detail aset, form ubah (`lib/queries/lists.ts`) |
| POST | `/api/assets/{id}/files` | Unggah foto atau dokumen | Token, maks. 30/menit per pengguna | Ya: Form ubah (`pages/assets/AssetFilesManager.tsx`) |
| DELETE | `/api/asset-files/{id}` | Hapus file | Token | Ya: Form ubah (`pages/assets/AssetFilesManager.tsx`) |
| GET | `/api/asset-files/{id}/download` | Ambil isi file. URL-nya dikirim backend lewat `download_url` | Token | Ya: Detail aset, form ubah (`pages/assets/AssetDetailPage.tsx`, `pages/assets/AssetFilesManager.tsx`) |
| GET | `/api/assets/scan/{value}` | Cari aset dari kode, kode lama, atau token QR | Token | Ya: Scan QR (`pages/scan/useScanLookup.ts`) |
| GET | `/api/assets/{id}/qr` | Gambar QR | Token | Ya: Detail aset (`pages/assets/AssetDetailPage.tsx`) |
| GET | `/api/assets/{id}/label` | PDF label satu aset | Token, maks. 20/menit per pengguna | Ya: Detail aset (`pages/assets/AssetDetailPage.tsx`) |
| POST | `/api/assets/labels` | PDF label banyak aset, maks. 120 | Token, maks. 20/menit per pengguna | Ya: Daftar Aset (`pages/assets/AssetListPage.tsx`) |

### Peminjaman

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/loans` | Daftar peminjaman, filter dan pencarian | Token | Ya: Peminjaman (`lib/queries/lists.ts`) |
| POST | `/api/loans` | Catat peminjaman; tanggal pinjam di masa depan menjadi pemesanan `Direncanakan` | Token | Ya: Form pinjam (`pages/loans/LoanFormModal.tsx`) |
| GET | `/api/loans/{id}` | Detail satu peminjaman | Token | Ya: Terima kembali dari hasil scan (`pages/loans/LoanPage.tsx`) |
| PATCH | `/api/loans/{id}/return` | Terima kembali, atau hilang saat dipinjam | Token | Ya: Popup terima kembali (`pages/loans/ReturnLoanModal.tsx`) |
| PATCH | `/api/loans/{id}/hand-over` | Serahkan pemesanan `Direncanakan` (aset menjadi Dipinjam) | Token | Ya: Popup serahkan (`pages/loans/HandOverModal.tsx`) |
| PATCH | `/api/loans/{id}/cancel` | Batalkan peminjaman atau pemesanan | Token | Ya: Popup batal (`pages/loans/CancelLoanModal.tsx`) |

### Kelengkapan ruangan

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/room-readiness` | Ringkasan kesiapan per gedung dan ruangan | Token | Ya: Kelengkapan Ruangan (`lib/queries/lists.ts`) |
| GET | `/api/rooms/{id}/checklist` | Aset per jenis dan barang habis pakai satu ruangan | Token | Ya: Kelengkapan Ruangan (`lib/queries/lists.ts`) |
| POST | `/api/rooms/{id}/standards` | Tambah standar ruangan | Token | Ya: Form standar (`pages/rooms/StandardFormModal.tsx`) |
| POST | `/api/rooms/{id}/check` | Simpan hasil cek ruangan | Token | Ya: Kelengkapan Ruangan (`pages/rooms/RoomsPage.tsx`) |
| PUT | `/api/room-standards/{id}` | Ubah standar ruangan | Token | Ya: Form standar (`pages/rooms/StandardFormModal.tsx`) |
| DELETE | `/api/room-standards/{id}` | Hapus standar ruangan | Token | Ya: Form standar, Kelengkapan Ruangan (`pages/rooms/StandardFormModal.tsx`, `pages/rooms/RoomsPage.tsx`) |
| POST | `/api/room-standards/{id}/fill-from-stock` | Isi kekurangan dari stok (`quantity`, `note` opsional) | Token | Ya: Kelengkapan Ruangan (`pages/rooms/FillFromStockModal.tsx`) |

### Permintaan ke departemen lain

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/request-catalog` | Daftar katalog | Token | Ya: Permintaan, form standar (`lib/queries/master.ts`) |
| POST | `/api/request-catalog` | Tambah item katalog | Token | Ya: Form katalog (`pages/requests/CatalogFormModal.tsx`) |
| PUT/PATCH | `/api/request-catalog/{id}` | Ubah item katalog | Token | Ya: Form katalog (`pages/requests/CatalogFormModal.tsx`), memakai PUT |
| DELETE | `/api/request-catalog/{id}` | Hapus item katalog dan melepas tautannya | Token | Ya: Permintaan (`pages/requests/RequestsPage.tsx`) |
| GET | `/api/request-catalog/{id}/usage` | Pemakaian item katalog sebelum dihapus | Token | Ya: Permintaan (`pages/requests/RequestsPage.tsx`) |
| GET | `/api/supply-requests` | Riwayat permintaan, filter dan pencarian | Token | Ya: Permintaan (`lib/queries/lists.ts`) |
| POST | `/api/supply-requests` | Catat permintaan | Token | Ya: Form permintaan (`pages/requests/RequestFormModal.tsx`) |
| GET | `/api/supply-requests/suggestions` | Saran permintaan dari stok Menipis/Habis per departemen penyedia | Token | Ya: Permintaan (`lib/queries/lists.ts`) |
| PUT | `/api/supply-requests/{id}` | Ubah permintaan yang masih Diajukan | Token | Ya: Form permintaan (`pages/requests/RequestFormModal.tsx`) |
| GET | `/api/supply-requests/{id}/status-impact` | Dampak perubahan status ke stok dan ruangan | Token | Ya: Popup status (`lib/queries/lists.ts`) |
| PATCH | `/api/supply-requests/{id}/status` | Ubah status permintaan | Token | Ya: Popup status (`pages/requests/RequestsPage.tsx`) |

### Stok habis pakai

| Method | Endpoint | Fungsi | Akses | Dipakai frontend |
|---|---|---|---|---|
| GET | `/api/consumables` | Daftar barang, filter dan pencarian | Token | Ya: Stok, form standar (`lib/queries/lists.ts`) |
| POST | `/api/consumables` | Tambah barang | Token | Ya: Form stok (`pages/consumables/ConsumableFormModal.tsx`) |
| GET | `/api/consumables/{id}` | Detail barang dan ruangan pemakainya | Token | Ya: Form stok (`pages/consumables/ConsumableFormModal.tsx`) |
| GET | `/api/consumables/{id}/usage` | Pemakaian barang sebelum dihapus | Token | Ya: Stok (`pages/consumables/ConsumablesPage.tsx`) |
| PUT | `/api/consumables/{id}` | Ubah data barang, tanpa jumlah | Token | Ya: Form stok (`pages/consumables/ConsumableFormModal.tsx`) |
| GET | `/api/consumables/{id}/transactions` | Kartu stok: riwayat transaksi barang | Token | Ya: Stok (`pages/consumables/StockCardModal.tsx`) |
| POST | `/api/consumables/{id}/transactions` | Catat transaksi stok | Token | Ya: Stok (`pages/consumables/StockTransactionModal.tsx`) |
| DELETE | `/api/consumables/{id}` | Hapus barang permanen jika hanya punya saldo awal | Token | Ya: Stok (`pages/consumables/ConsumablesPage.tsx`) |

## Route non-API

Route berikut tidak berawalan `/api` dan tidak dipanggil frontend.

| Method | Route | Fungsi | Dipakai frontend |
|---|---|---|---|
| GET | `/` | Penanda aplikasi hidup, berisi nama aplikasi dan status | **Tidak** |
| GET | `/up` | Health check bawaan Laravel, bisa dipakai pemantauan server | **Tidak** |
| GET | `/sanctum/csrf-cookie` | Bawaan Sanctum. Aplikasi memakai Bearer token, jadi tidak diperlukan | **Tidak** |
| GET | `/storage/{path}` | Bawaan Laravel untuk disk `local`. Wajib tanda tangan URL; tanpa itu ditolak | **Tidak** |
| PUT | `/storage/{path}` | Bawaan Laravel untuk disk `local`. Wajib tanda tangan URL; tanpa itu ditolak | **Tidak** |
| POST | `/_boost/browser-logs` | Alat bantu dev Laravel Boost. Hanya aktif di mode local atau debug, harus 404 di production | **Tidak** |

## Catatan

- **Query yang tersedia tetapi tidak dipakai frontend:** `?download=1` pada `/api/asset-files/{id}/download`, `/api/assets/{id}/label`, dan `/api/assets/labels`.
- **PUT/PATCH:** route yang ditulis PUT/PATCH menerima keduanya. Frontend selalu memakai PUT.
- **Aksi Batalkan pada hasil scan:** `meta.actions` bisa berisi `batalkan`, tetapi prosesnya tetap lewat `PATCH /api/loans/{id}/cancel`, tidak ada endpoint tersendiri.
- **Daftar ini perlu diperbarui** setiap kali route atau pemanggilan API di frontend berubah.
