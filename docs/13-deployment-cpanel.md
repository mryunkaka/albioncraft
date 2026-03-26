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
File deploy sudah disediakan di repo:
- `deploy-albion.php`

Target folder repo di script:
- `/home/hark8423/public_html/albioncraft`

Script deploy ada di 2 lokasi (sudah disiapkan di repo):
- CLI/Cron (utama): `/home/hark8423/public_html/albioncraft/deploy-albion.php`
- Web trigger (opsional): `/home/hark8423/public_html/albioncraft/public/deploy-albion.php`

Alternatif paling sederhana (cron only, tanpa token):
- `/home/hark8423/public_html/deploy-cron.php`
- Script ini hanya boleh jalan via CLI, jika dibuka via browser akan 403.

### Token Untuk Manual (Wajib)
Agar script tidak bisa dieksekusi sembarang orang dari browser, manual deploy wajib token.

Buat file token:
- `/home/hark8423/public_html/albioncraft/.deploy-token`

Isi dengan string random panjang (contoh 32+ karakter).

Lalu akses:
```text
/deploy-albion.php?token=ISI_TOKEN_ANDA
```

### Cron
Rekomendasi interval:
- tiap 5 sampai 15 menit (lebih aman untuk shared hosting)

Contoh (tiap 5 menit):
```text
*/5 * * * * /usr/bin/php /home/hark8423/public_html/albioncraft/deploy-albion.php
```

Jika Anda memakai script sederhana `deploy-cron.php`:
```text
*/5 * * * * /usr/bin/php -q /home/hark8423/public_html/deploy-cron.php
```

## 3.1 Error Umum Cron: "Permission denied"
Jika log cron menampilkan:
`/usr/local/cpanel/bin/jailshell: ... deploy-albion.php: Permission denied`
biasanya karena cron mencoba mengeksekusi file `.php` langsung tanpa interpreter PHP.

Yang benar:
- Pakai `php /path/script.php` (contoh di atas).
- Permission file script cukup `644` (tidak perlu executable).

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
