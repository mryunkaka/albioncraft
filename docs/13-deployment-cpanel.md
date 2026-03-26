# Deploy ke cPanel Shared Hosting (AlbionCraft)

Dokumen ini menjelaskan cara deploy project ini ke cPanel shared hosting dengan struktur:
- Repo aplikasi: `/home/hark8423/public_html/albioncraft`
- Document Root domain: `/home/hark8423/public_html/albioncraft/public`
- Domain: `http://albion.harikenangan.my.id/`

## 1. Setting Domain Document Root
Pastikan domain/subdomain diarahkan ke folder `public`:
`/public_html/albioncraft/public`

Ini penting supaya:
- `public/index.php` menjadi entry point
- `.htaccess` di `public/` bisa rewrite request ke front controller

## 2. Clone Repo
Di cPanel Terminal atau SSH:
```bash
cd /home/hark8423/public_html
git clone https://github.com/mryunkaka/albioncraft albioncraft
```

Jika folder sudah ada:
```bash
cd /home/hark8423/public_html/albioncraft
git remote -v
```

## 3. Deploy Script (Cron + Manual)
File deploy sederhana (mengikuti pola yang sudah terbukti jalan seperti `deploy-sigaji.php`) ada di repo:
- `deploy-albion.php`

Rekomendasi lokasi di hosting:
- `/home/hark8423/public_html/deploy-albion.php`

Script ini akan melakukan `git pull` untuk repo yang ada di:
- `/home/hark8423/public_html/albioncraft`

### Cron
Rekomendasi interval:
- tiap 5 sampai 15 menit (lebih aman untuk shared hosting)

Contoh (tiap menit, sama seperti referensi Anda):
```text
* * * * * /usr/bin/php /home/hark8423/public_html/deploy-albion.php
```

## 3.1 Catatan Cron: Jangan Pakai URL
Cron command harus berupa:
- `/usr/bin/php /path/script.php`

Bukan URL dan tidak memakai query string seperti `?token=...`.

Log deploy:
- `/home/hark8423/git-deploy-albion.log`

## 4. Jika Deploy Gagal (Umum di Shared Hosting)
Penyebab paling sering:
- `shell_exec` dimatikan (`disable_functions`)
- `git` tidak tersedia untuk proses PHP

Solusi:
- Gunakan fitur cPanel `Git Version Control` untuk pull manual
- Gunakan SSH/Terminal untuk `git pull` manual
- Minta provider mengaktifkan akses `git` untuk user Anda

## 5. Checklist Setelah Deploy
- Akses `http://albion.harikenangan.my.id/calculator`
- Pastikan `.htaccess` berfungsi (route selain file statik tidak 404)
- Jika ada 500 error: cek error log domain di cPanel

## 6. Troubleshooting: 403 Forbidden "Server unable to read htaccess file"
Jika halaman domain menampilkan:
`Forbidden` dan pesan `Server unable to read htaccess file, denying access to be safe`
artinya Apache mencoba membaca `.htaccess` di document root, tetapi gagal (biasanya karena permission/ownership).

Langkah perbaikan (di cPanel File Manager):
1. Pastikan "Show Hidden Files (dotfiles)" aktif.
2. Buka folder document root domain:
   `/home/hark8423/public_html/albioncraft/public`
3. Pastikan file ini ada:
   - `.htaccess`
   - `index.php`
4. Set permission yang aman:
   - Folder `albioncraft/` = `755`
   - Folder `public/` = `755`
   - File `.htaccess` = `644`
   - File `index.php` = `644`
5. Jika masih 403, cek apakah ada `.htaccess` di parent folder (`/public_html/albioncraft/` atau `/public_html/`) dengan permission salah.

Langkah diagnosa cepat:
- Rename sementara `.htaccess` menjadi `htaccess.bak` lalu coba buka:
  `http://albion.harikenangan.my.id/index.php`
  Jika ini berhasil, berarti masalahnya spesifik di `.htaccess` (permission atau rule).

Catatan:
- Di shared hosting, permission `777` sering dianggap tidak aman dan bisa memicu 403. Gunakan `755/644`.
- Jika provider mematikan `AllowOverride` atau ada WAF, `.htaccess` bisa ditolak. Dalam kasus ini Anda perlu minta support hosting mengaktifkan `.htaccess` untuk domain Anda.
