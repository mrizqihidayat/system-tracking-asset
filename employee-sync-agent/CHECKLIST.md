# Checklist Serah Terima — Agen Sinkron Pegawai

Centang setiap langkah saat serah terima. Rincian setiap langkah ada di `README.md`.

**Tim pengembang aplikasi (sebelum menyerahkan)**
- [ ] `EMPLOYEE_PUSH_TOKEN` sudah diisi di aplikasi (acak, minimal 32 karakter), dan aplikasi sudah di-deploy ulang.
- [ ] `BACKEND_URL` dan token diberikan ke tim IT lewat jalur aman.
- [ ] Nama dan kontak di README bagian 11 sudah diisi.

**Tim IT perusahaan (di server)**
- [ ] PHP 7.4+ (CLI) terpasang: `php -v`.
- [ ] Ekstensi `curl` dan `json` aktif: `php -m`.
- [ ] Folder `employee-sync-agent` tersalin ke server.
- [ ] `.env` dibuat dari `.env.example` dan terisi (API pegawai, `BACKEND_URL`, `EMPLOYEE_PUSH_TOKEN`, proxy bila perlu).
- [ ] Izin baca `.env` dibatasi (Linux: `chmod 600 .env`).
- [ ] `php sync.php --dry-run` berhasil (exit code 0, tanpa baris `GAGAL`).
- [ ] `php sync.php` (sinkron pertama, manual) berhasil.
- [ ] Jadwal harian 23.59 terpasang (cron atau Task Scheduler) dan sudah dicoba jalan sekali.

**Bersama (di aplikasi)**
- [ ] Sinkron pertama terlihat di aplikasi: Pengaturan > Data Master > Departemen, "Sinkron terakhir" berstatus berhasil.
- [ ] Satu pegawai SHD berhasil login pertama kali dengan NIK.
- [ ] Keesokan harinya, "Sinkron terakhir" menunjukkan jadwal 23.59 berjalan.

Diserahkan oleh: ______________________   Diterima oleh: ______________________   Tanggal: ____________
