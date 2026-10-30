---
name: Aset SHD
description: Aplikasi kerja aset Departemen SHD PT INALUM; shell biru INALUM, area kerja terang, status aset yang bicara.
colors:
  brand-50: "#eaf2f8"
  brand-100: "#d3e6f3"
  brand-200: "#a9cde6"
  brand-500: "#2b8fcb"
  brand-600: "#017cc2"
  brand-700: "#016aa6"
  brand-800: "#0e4f7e"
  brand-900: "#0b3f65"
  rail: "#0e4f7e"
  canvas: "#f5f7f9"
  surface: "#ffffff"
  slate-50: "#f8fafb"
  slate-100: "#eef1f4"
  slate-200: "#e3e8ed"
  slate-300: "#cdd5dd"
  slate-400: "#97a3ae"
  slate-500: "#5e6b77"
  slate-600: "#4b5763"
  slate-700: "#36424e"
  slate-800: "#1f2b37"
  slate-900: "#14212e"
  ok-50: "#eef6dd"
  ok-700: "#4a6a0b"
  warn-50: "#fef3e2"
  warn-700: "#93500a"
  bad-50: "#fdeceb"
  bad-700: "#b42318"
  info-50: "#e6f2fa"
  info-700: "#015e94"
  bar-ok: "#86ad2e"
  bar-bad: "#e5645b"
  bar-warn: "#ebaa3a"
typography:
  display:
    fontFamily: "Archivo, system-ui, 'Segoe UI', sans-serif"
    fontSize: "1.875rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "normal"
    fontFeature: "tnum"
  headline:
    fontFamily: "Archivo, system-ui, 'Segoe UI', sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.333
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Archivo, system-ui, 'Segoe UI', sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
  body:
    fontFamily: "Archivo, system-ui, 'Segoe UI', sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.429
  label:
    fontFamily: "Archivo, system-ui, 'Segoe UI', sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.333
  code:
    fontFamily: "'Chivo Mono', ui-monospace, monospace"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.333
rounded:
  sm: "4px"
  md: "8px"
  lg: "12px"
  sheet: "16px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "20px"
  2xl: "28px"
components:
  button-primary:
    backgroundColor: "{colors.brand-600}"
    textColor: "{colors.surface}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0 16px"
    height: "40px"
  button-primary-hover:
    backgroundColor: "{colors.brand-700}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.slate-800}"
    rounded: "{rounded.md}"
    padding: "0 16px"
    height: "40px"
  button-secondary-hover:
    backgroundColor: "{colors.slate-50}"
  button-danger:
    backgroundColor: "{colors.bad-700}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: "0 16px"
    height: "40px"
  button-ghost:
    textColor: "{colors.brand-700}"
    rounded: "{rounded.md}"
    padding: "0 16px"
    height: "40px"
  button-ghost-hover:
    backgroundColor: "{colors.brand-50}"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.slate-900}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
    height: "40px"
  panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "20px"
  asset-code:
    backgroundColor: "{colors.brand-50}"
    textColor: "{colors.brand-800}"
    typography: "{typography.code}"
    rounded: "{rounded.sm}"
    padding: "2px 6px"
  badge-ok:
    backgroundColor: "{colors.ok-50}"
    textColor: "{colors.ok-700}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-warn:
    backgroundColor: "{colors.warn-50}"
    textColor: "{colors.warn-700}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-bad:
    backgroundColor: "{colors.bad-50}"
    textColor: "{colors.bad-700}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-info:
    backgroundColor: "{colors.info-50}"
    textColor: "{colors.info-700}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-neutral:
    backgroundColor: "{colors.slate-100}"
    textColor: "{colors.slate-600}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  rail:
    backgroundColor: "{colors.rail}"
    textColor: "{colors.surface}"
    width: "240px"
    padding: "20px 12px"
  nav-item:
    rounded: "{rounded.md}"
    padding: "10px 12px"
  nav-item-active:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.brand-800}"
    rounded: "{rounded.md}"
    padding: "10px 12px"
---

# Design System: Aset SHD

## Overview

**Creative North Star: "Pelat Nama Aset"**

Aset SHD adalah aplikasi kerja yang terang dan tenang. Identitas INALUM dibawa oleh satu bidang biru: rail navigasi kiri (dan header atas di HP) dengan ikon INALUM asli di kotak putih bersudut. Sisanya area kerja putih di atas kanvas abu kebiruan yang sangat muda, sehingga yang paling bersuara di layar adalah status aset: pill lembut berwarna, angka merah untuk yang terlambat, dan kode aset sebagai pelat nama mono biru muda.

Kepadatannya sedang: tabel penuh di desktop, daftar kartu baris di HP, panel putih ber-border tipis tanpa bayangan saat diam dan tanpa kartu bersarang. Warna merah/hijau logo hanya muncul di ikon dan data (bar, status), tidak pernah di chrome. Sistem ini menolak dua default yang sudah diputuskan: sidebar gelap dengan kartu abu tebal, dan dashboard SaaS generik tanpa identitas.

**Key Characteristics:**
- Satu bidang biru INALUM (rail), selebihnya terang.
- Panel putih, border 1px slate-200, sudut 12px, datar saat diam.
- Kode aset selalu berupa pelat mono biru muda yang tidak patah baris.
- Status = pill lembut dengan teks gelap (kontras AA), selalu bertuliskan label, bukan hanya warna.
- Tabel berubah menjadi daftar kartu baris di bawah 768px; modal menjadi lembar dari bawah di HP.

## Colors

Netral bernada biru dengan satu aksen biru INALUM; warna status hanya untuk data.

### Primary
- **Biru INALUM** (brand-600): tombol utama, cincin fokus, caret, checkbox/radio (accent-color). Satu tombol utama per kepala halaman (mis. Scan QR).
- **Biru Tautan** (brand-700): link teks ("Lihat semua"), tombol ghost, hover tombol utama, tautan kembali.
- **Biru Rail** (rail / brand-800): latar sidebar desktop, header HP, panel kiri login, header halaman publik QR; juga teks menu aktif dan teks pelat kode aset.
- **Biru Pelat** (brand-50): latar pelat kode aset dan hover tombol ghost. brand-100 untuk ::selection dan ring fokus input; brand-200 untuk hover border kartu angka dashboard.

### Neutral
- **Tinta Malam** (slate-900): teks utama di seluruh area kerja.
- **Abu Sekunder** (slate-500): keterangan, header kolom, placeholder, teks kosong/memuat.
- **Garis Panel** (slate-200): border panel, tabel, modal, tombol sekunder. slate-100 untuk pembatas baris tabel; slate-300 untuk border input dan scrollbar.
- **Kanvas** (canvas): latar halaman di belakang panel putih.
- **Permukaan** (surface, putih): panel, tabel, modal, input, item menu aktif.

### Status (data, bukan chrome)
- **Hijau** (ok-50 / ok-700): Tersedia, Baik, Diterima, Sesuai.
- **Kuning** (warn-50 / warn-700): Perbaikan, Rusak Ringan, Menipis, Belum direspons.
- **Merah** (bad-50 / bad-700): Hilang, Rusak Berat, Terlambat, Habis, Kurang; juga tombol bahaya, pesan error, dan angka alarm di kartu dashboard.
- **Biru Info** (info-50 / info-700): Dipinjam.
- **Abu** (slate-100 / slate-600): Tidak Layak Pakai, Dikembalikan.
- **Isian bar** (bar-ok, bar-bad, bar-warn): hanya untuk isian bar data (lengkap/kurang per gedung), tidak pernah untuk teks.

### Named Rules
**The Satu Bidang Biru Rule.** Hanya rail (dan padanannya di HP/login/publik) yang berwarna penuh gelap. Area kerja tidak memakai latar biru tua atau gelap.

**The Status Lembut Rule.** Status ditulis sebagai teks gelap -700 di atas latar -50; jangan pakai pill penuh berwarna jenuh dengan teks putih.

## Typography

**Body Font:** Archivo (dengan system-ui, Segoe UI, sans-serif), bobot 400/500/600/700.
**Label/Mono Font:** Chivo Mono (dengan ui-monospace), bobot 500/600, khusus kode aset.

**Character:** Archivo memberi grotesk yang tegas dan teknis tanpa terasa dingin; Chivo Mono membuat kode aset terbaca seperti pelat yang terukir.

### Hierarchy
- **Display** (700, 30px, tabular-nums): angka kartu dashboard dan judul panel login. Angka alarm memakai bad-700.
- **Headline** (700, 24px, tracking -0.025em, text-balance): judul halaman di PageHeader dan login. Halaman publik QR memakai 20px untuk nama aset.
- **Title** (600, 16px): judul panel dan modal.
- **Body** (400/500, 14px): isi tabel, form, tombol (600), menu. Label form 500 slate-700.
- **Label** (600, 12px): pill status, header kolom tabel (slate-500), keterangan kecil.
- **Code** (Chivo Mono 600, 12px): pelat kode aset; naik ke 14px di kartu identitas halaman publik.

### Named Rules
**The Tanpa Huruf Kapital Rule.** Tidak ada label kapital ber-tracking lebar; header kolom dan seksi menu ditulis kalimat biasa 12px semibold.

**The Angka Rata Rule.** Tabel dan angka ringkasan memakai tabular-nums.

## Layout

Shell: rail tetap 240px di kiri pada >=1024px (layar lebih kecil: header biru 56px dengan tombol menu, rail menjadi laci 256px dengan overlay slate-900/40). Konten di `main` maks 1440px, padding 16px (HP), 24px (sm), 32px dan atas 28px (lg).

Ritme: jarak antar panel/blok 20px; dalam panel padding 20px; gap antar kontrol 8-12px; sel tabel 16px x 12px. Kepala halaman: judul + keterangan kiri, aksi kanan; di HP aksi turun ke bawah dan membungkus.

Dashboard: kartu angka 2 kolom (HP), 3 (md), 6 (>=1360px); di bawahnya dua kolom 1.6fr/1fr mulai xl, satu kolom di bawahnya.

Responsif tabel: di bawah 768px DataTable menjadi daftar kartu baris (judul kode+nama kiri atas, status kanan atas, sisanya pasangan label: nilai dalam grid 7.5rem/1fr). Target sentuh minimum 40px (tombol, input), tombol ikon 36-40px.

## Elevation & Depth

Datar saat diam: kedalaman dibangun dari kanvas vs permukaan putih dan border 1px. Bayangan hanya untuk lapisan yang melayang di atas konten, berwarna tinta (20 33 46) bukan hitam.

### Shadow Vocabulary
- **Popover** (`box-shadow: 0 8px 24px rgb(20 33 46 / 0.12)`): daftar Combobox dan DropdownMenu.
- **Toast** (`box-shadow: 0 8px 24px rgb(20 33 46 / 0.14)`): notifikasi di kanan atas.
- **Dialog** (`box-shadow: 0 16px 48px rgb(20 33 46 / 0.22)`): modal di tengah (>=640px).
- **Lembar bawah** (`box-shadow: 0 -8px 32px rgb(20 33 46 / 0.18)`): modal sebagai lembar dari bawah di HP.

### Named Rules
**The Datar Saat Diam Rule.** Panel, tabel, dan kartu tidak pernah berbayangan; bayangan hanya untuk popover, toast, dan modal. Hover pada kartu cukup dengan perubahan border/warna.

## Shapes

Sudut lembut bertingkat menurut ukuran: 4px untuk pelat kode aset dan tag kecil, 8px untuk tombol, input, item menu, kotak logo, 12px untuk panel, tabel, kartu angka, toast, dan dialog, 16px untuk sudut atas lembar bawah HP, dan pill penuh untuk status, avatar, dan bar data (tinggi 8px). Border selalu 1px; tidak ada border tebal atau aksen sisi.

## Components

### Buttons
Tegas dan ringkas.
- **Shape:** sudut 8px, tinggi minimum 40px, padding horizontal 16px, teks 14px semibold, ikon 16px dengan gap 8px, tidak patah baris.
- **Primary:** brand-600 dengan teks putih; hover brand-700.
- **Secondary:** putih, border slate-200, teks slate-800; hover border slate-300 dan latar slate-50.
- **Danger:** bad-700 teks putih (hover lebih gelap).
- **Ghost:** teks brand-700 tanpa latar; hover brand-50.
- **Disabled / loading:** opacity 50%, kursor dilarang; loading mengganti label dengan "Memproses…".
- `<Link>` yang berperan sebagai tombol memakai kelas tombol yang sama.

### Chips (status)
- **Style:** pill penuh, padding 2px 10px, 12px semibold, latar -50 dan teks -700, tidak patah baris.
- **Pemetaan:** status aset dan kondisi dipetakan tetap (lihat Colors > Status); label teks selalu ada.

### Cards / Containers
- **Corner Style:** 12px.
- **Background:** putih di atas canvas.
- **Shadow Strategy:** tanpa bayangan (lihat The Datar Saat Diam Rule).
- **Border:** 1px slate-200; baris di dalam dipisah slate-100.
- **Internal Padding:** 20px; kepala panel 16px atas / 12px bawah dengan judul 16px semibold kiri dan link brand-700 kanan. Panel berisi tabel/daftar memakai mode flush.
- **Kartu angka dashboard:** label 14px slate-500, angka 30px bold, catatan 12px; hover border brand-200 dan label brand-700.

### Inputs / Fields
- **Style:** putih, border 1px slate-300, sudut 8px, tinggi minimum 40px, padding 8px 12px, teks 14px; placeholder slate-500; hover border slate-400.
- **Focus:** border brand-600 dengan ring 2px brand-100.
- **Error / Disabled:** border bad-700 dengan ring bad-50 dan pesan 12px bad-700 di bawah; disabled latar slate-100 teks slate-500. Label 14px medium slate-700, tanda wajib `*` bad-700.

### Navigation
- **Rail:** biru rail, padding 20px 12px; Brand di atas (kotak putih 40px sudut 8px berisi ikon INALUM, "Aset SHD" 17px bold, "Sistem Informasi Aset" 12px).
- **Item:** ikon 18px + label 14px, padding 10px 12px, sudut 8px; diam teks biru pucat 500, hover latar putih 10% teks putih; aktif blok putih dengan teks brand-800 semibold.
- **Seksi & modul nonaktif:** judul seksi 12px semibold biru pucat; modul fase berikut diredupkan dengan tag kecil ber-border putih 25%.
- **HP:** header biru 56px dengan Brand ringkas; rail menjadi laci geser 200ms.

### Pelat Kode Aset (signature)
Setiap tempat aset disebut, kodenya tampil sebagai pelat: Chivo Mono 12px semibold, latar brand-50, teks brand-800, sudut 4px, padding 2px 6px, tidak pernah patah baris. Di daftar kartu HP pelat berada di baris judul di atas nama aset.

### Modal
Di HP lembar dari bawah (sudut atas 16px, maks 92dvh, footer mengikuti safe-area, tombol footer lebar penuh bertumpuk dengan aksi utama di atas); mulai 640px dialog di tengah maks 512px, sudut 12px. Kepala dan footer dipisah border slate-200; overlay slate-900/40; Escape menutup.

## Do's and Don'ts

### Do:
- **Do** tampilkan kode aset hanya lewat pelat mono (brand-50 / brand-800, sudut 4px) dan jaga agar kode dan status tidak patah baris di desktop.
- **Do** bangun halaman dari PageHeader + Panel putih ber-border 1px slate-200, sudut 12px, jarak antar panel 20px.
- **Do** pakai pill status lembut -50/-700 dengan label teks untuk setiap status, kondisi, hasil cek, dan stok.
- **Do** beri setiap tabel mode kartu baris di HP (kode+nama kiri atas, status kanan atas).
- **Do** jaga target sentuh minimal 40px dan fokus terlihat (outline 2px brand-600, offset 2px).

### Don't:
- **Don't** beri panel, tabel, atau kartu bayangan saat diam; bayangan hanya untuk popover, toast, dan modal.
- **Don't** pakai latar biru tua/gelap di area kerja; bidang gelap hanya rail dan padanannya.
- **Don't** pakai merah/hijau logo INALUM di chrome (tombol, navigasi, header); warna itu hanya untuk ikon dan data.
- **Don't** menumpuk kartu di dalam kartu.
- **Don't** pakai warna bar (bar-ok/bad/warn) sebagai warna teks.
