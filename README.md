# ATB Club Bot (v1.0.5) — Structured

این پکیج نسخه‌ی 1.0.5 بات شماست که به ساختار پوشه‌ای زیر تبدیل شده:

```
README.md
config.php
config.local.php       ← توکن و تنظیمات محلی
index.php              ← ورودی وبهوک
test_webhook.php       ← تست سلامت نصب
logs/                  ← لاگ‌ها
src/
  bot.php              ← کد اصلی بات (پچ‌شده برای استفاده از config)
storage/
  atb_db.json          ← دیتابیس JSON (آپلود شما)
```

## نصب سریع روی هاست (public_html)
1) **همه فایل‌ها را در پوشه‌ی دامنه آپلود و از حالت ZIP خارج کنید.**
2) فایل `config.local.php` را باز کنید و **توکن ربات** را بررسی/ویرایش کنید.
3) دسترسی‌ها:
   - `storage/atb_db.json` → قابل‌نوشتن (مثلاً 0666)
   - `logs/` → قابل‌نوشتن (0775)
4) تست: آدرس `https://DOMAIN/test_webhook.php` را باز کنید. اگر `getMe()` هم OK شد یعنی توکن درست است.

## ست کردن Webhook
**نکته مهم:** تلگرام فقط روی HTTPS با **گواهی معتبر (CA-signed)** وبهوک را قبول می‌کند. اگر SSL شما self-signed باشد خطای
`SSL routines::certificate verify failed` می‌گیرید.

مثال (از طریق مرورگر):
```
https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://DOMAIN/index.php&secret_token=AtbSecret123
```

مثال (curl در ترمینال):
```bash
curl -sS "https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook" \
     -d "url=https://DOMAIN/index.php" \
     -d "secret_token=AtbSecret123" \
     -d "drop_pending_updates=true"
```
برای امنیت بیشتر می‌توانید در **فایروال** فقط ترافیک تلگرام را به `index.php` اجازه دهید.

## درباره پچ‌ها
- قسمت تنظیمات ابتدای `src/bot.php` طوری پچ شد که به جای هاردکد، از مقادیر `config.php / config.local.php` استفاده کند.
- مسیر دیتابیس و لاگ به `storage/` و `logs/` منتقل شد.
- هیچ قابلیت/منویی حذف نشده؛ منطق دیتابیس JSON شما بدون تغییر حفظ شده است.
- برای اطمینان از سازگاری، اگر فایل `storage/atb_db.json` موجود نباشد، برنامه خودش آن را می‌سازد.

## دیباگ
- اگر `test_webhook.php` روی بخش `getMe()` خروجی نداد، مشکل از SSL/فایروال یا هاست است.
- خطاها در `logs/atb_log.txt` نوشته می‌شود (وقتی DEBUG=true باشد verbose می‌شود).

---
© ATB Club Bot 1.0.5 (Structured)
