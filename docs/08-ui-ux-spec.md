# UI/UX Specification

## Ringkasan
UI harus terasa bersih, ringan, cepat, dan fokus pada data. Warna utama biru dan putih. Desain harus mobile first dan tetap nyaman di tablet maupun desktop besar.

## Prinsip Visual
- Clean dan ringan.
- Banyak whitespace.
- Data-first, bukan widget-first.
- Hierarki visual jelas.
- Kontras cukup tinggi untuk angka profit/rugi.

## Design Tokens Awal
- Primary: biru
- Background: putih dan biru sangat muda
- Success: hijau
- Danger: merah
- Text utama: slate gelap
- Border: abu kebiruan tipis

## Komponen Wajib
Semua styling reusable diletakkan di `assets/components`.

### Button
- Primary
- Secondary
- Ghost
- Danger
- Loading state

### Input
- Text
- Number
- Password
- Search
- Error state

### Select
- Default select
- Disabled
- Error state

### Card
- Statistik dashboard
- Form section
- Result block

### Table
- Sticky header optional
- Horizontal scroll di mobile
- Empty state
- Loading state

### Modal
- Konfirmasi logout
- Extend subscription
- Info referral reward

## Heroicons
- Semua SVG lokal.
- Ikon dipakai untuk auth, dashboard summary, calculator, subscription, dan referral.

## Layout Halaman
### Auth
- Centered card.
- Form sederhana.
- Link pindah login/register.
- Input referral code pada register.

### Dashboard
- Ringkasan 3-4 kartu utama.
- Quick calc mini form.
- Recent calculations.
- Subscription badge.

### Calculator
- Area input di atas.
- Hasil di bawah.
- Material rows dinamis.
- Desktop bisa 2 kolom.
- Mobile stack vertikal.
- Input disimpan ke LocalStorage agar tidak hilang saat refresh.
- Tombol `Clear` mengembalikan nilai default dan menghapus LocalStorage.
- Result utama dibuat seperti spreadsheet: 1 baris tabel ringkas (horizontal scroll di mobile).
- Detail Perhitungan bersifat collapsible untuk menampung tabel panjang (Field/Material/Iterasi) tanpa membuat page terlalu panjang.
- Table pada device kecil wajib horizontal-scroll dan **tidak wrap** teks penting (nama item/material, angka).

### Data Harga
- Search bar sticky.
- Filter plan-aware.
- Tabel besar dengan pagination server-side.
- Aksi simpan massal via AJAX.

### Subscription
- Kartu plan.
- Durasi harian, mingguan, bulanan, tahunan.
- Riwayat extend.

### Referral
- Card kode referral.
- Copy button.
- Tabel history reward.

## Responsiveness
### Mobile
- Single column.
- Form besar dan mudah disentuh.
- Tabel dibungkus horizontal scroll.
  - Kolom input tidak boleh keluar dari card (no overflow).
  - Material row dibuat stack (nama, qty, price, return type, delete) agar tidak kepotong.

### Tablet
- Mulai 2 kolom untuk beberapa section.

### Desktop
- Dashboard grid.
- Calculator split view.
- Tabel lebih lebar.

## State UI
- Loading
- Success
- Error validation
- Empty data
- Unauthorized plan access

## Interaksi AJAX
- Kalkulasi real-time dengan debounce ringan.
- Search/filter tabel dengan debounce.
- Semua form AJAX punya indikator loading.
- Error server ditampilkan jelas tanpa reload halaman.

## Aturan View
- Tidak ada inline style.
- Tidak ada CDN.
- Tidak ada class styling liar langsung di view bila itu bisa dijadikan reusable component class.

## Status Implementasi Saat Ini
- Tailwind CSS sudah diinstall lokal via NPM (tanpa CDN).
- Build output aktif ke `public/assets/app.css` lewat `npm run build`.
- Komponen reusable sudah dipecah di:
  - `assets/components/base.css`
  - `assets/components/layout.css`
  - `assets/components/form.css`
  - `assets/components/button.css`
  - `assets/components/card.css`
  - `assets/components/table.css`
  - `assets/components/modal.css`
  - `assets/components/widgets.css`
  - `assets/components/animations.css`
- View auth/dashboard/calculator sudah diarahkan memakai class komponen (tanpa inline styling).
