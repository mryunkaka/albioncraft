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
