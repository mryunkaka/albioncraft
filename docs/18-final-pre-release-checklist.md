# Final Pre-Release Checklist

## Tujuan
Dokumen ini dipakai sebagai checklist akhir sebelum project dianggap siap untuk:
- QA manual final
- deploy production
- handoff lanjutan

Dokumen ini bukan pengganti roadmap, tetapi checkpoint ringkas untuk memastikan tidak ada langkah penting yang tertinggal.

## 1. Config & Environment
- [ ] File `.env` production sudah terisi benar.
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=0`
- [ ] kredensial database production valid
- [ ] `ADMIN_EMAILS` sudah diisi
- [ ] `AUTH_RATE_LIMIT_MAX_ATTEMPTS` sudah sesuai
- [ ] `AUTH_RATE_LIMIT_WINDOW_SECONDS` sudah sesuai
- [ ] `API_RATE_LIMIT_MAX_ATTEMPTS` sudah sesuai
- [ ] `API_RATE_LIMIT_WINDOW_SECONDS` sudah sesuai
- [ ] `SETUP_TOKEN` tidak dipakai sembarangan di production

## 2. Safety Check
- [ ] endpoint `/debug-db` menghasilkan `404` di production
- [ ] endpoint `/setup/seed` menghasilkan `404` di production
- [ ] tidak ada route debug lain yang terbuka
- [ ] CSRF aktif di form penting
- [ ] plan gating berjalan untuk route protected

## 3. Database & Seed
- [ ] tabel inti tersedia
- [ ] seed plan/features sudah benar
- [ ] sample city bonus tersedia
- [ ] sample item/recipe tersedia untuk QA manual
- [ ] index hardening sudah diterapkan pada DB target jika perlu

## 4. Automated Verification
Jalankan:

```powershell
php tests/run_all_tests.php
```

Checklist:
- [ ] `Calculation Engine` PASS
- [ ] `Subscription Referral Admin` PASS
- [ ] `Market Price Service` PASS
- [ ] `Dashboard History` PASS
- [ ] `Recipe Auto Fill` PASS
- [ ] `Recipe Auto Fill E2E` PASS
- [ ] `API Rate Limiter` PASS

## 5. Manual QA
Gunakan:
- `docs/16-manual-qa-checklist.md`

Checklist ringkas:
- [ ] `price-data` CRUD lolos
- [ ] bulk import/update lolos
- [ ] recipe auto-fill lolos
- [ ] auto harga dari `market_prices` user lolos
- [ ] calculate -> save history -> dashboard lolos
- [ ] plan gating `FREE/MEDIUM/PRO` lolos

## 6. Product/Usage Docs
- [ ] `docs/07-calculation-spec.md` sinkron
- [ ] `docs/11-calculation-engine-strict-rules.md` sinkron
- [ ] `docs/12-test-case-golden-data.md` sinkron
- [ ] `docs/16-manual-qa-checklist.md` tersedia
- [ ] `docs/17-market-analysis-guide.md` tersedia
- [ ] `docs/13-deployment-cpanel.md` sudah dibaca untuk deploy target

## 7. Feature Completion Snapshot
- [ ] auth foundation selesai
- [ ] subscription/referral foundation selesai untuk flow saat ini
- [ ] dashboard summary + history selesai
- [ ] price-data CRUD selesai
- [ ] bulk import/update dasar selesai
- [ ] recipe auto-fill selesai
- [ ] auto harga `market_prices` ke recipe auto-fill selesai
- [ ] end-to-end autofill -> calculate -> save history selesai
- [ ] auth rate limit selesai
- [ ] API calculate rate limit selesai

## 8. Release Decision
Project layak dianggap siap release MVP jika:
- semua automated test PASS
- QA manual inti PASS
- env production aman
- endpoint debug/setup tidak terbuka
- tidak ada blocker fungsional di flow utama

## 9. Jika Masih Ada Temuan
Jika QA manual menemukan issue:
1. catat issue
2. tentukan severity
3. perbaiki
4. rerun `php tests/run_all_tests.php`
5. ulangi checklist manual yang terdampak

## Penutup
Jika seluruh checklist ini sudah tercentang, project secara praktis sudah berada di fase:
- siap QA final
- siap deploy production dengan pengawasan normal
- siap handoff
