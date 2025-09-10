<?php
/**
 * ATB Club Bot — v2.0.9 (Modularized)
 * Global Config & Constants
 */
if (!defined('ATB_VERSION')) define('ATB_VERSION','2.1.0');

// تابع کمکی برای گرفتن مقدار env یا دیفالت
function env($key, $default = null) {
    $v = getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

// ---- اول لوکال را لود کن (اگر وجود داشت) ----
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    require $local;
}

// ---- تعریف ثابت‌ها (اگر هنوز تعریف نشده‌اند) ----
if (!defined('BOT_TOKEN'))           define('BOT_TOKEN', env("BOT_TOKEN", ""));
if (!defined('PRIVATE_CHANNEL_INVITE')) define('PRIVATE_CHANNEL_INVITE', env("PRIVATE_CHANNEL_INVITE", "https://t.me/+hUV4tJ7n3b9kMDlk"));
if (!defined('PUBLIC_CHANNEL_ID'))   define('PUBLIC_CHANNEL_ID', env("PUBLIC_CHANNEL_ID", "@ATB_Club"));
if (!defined('SUPPORT_CHAT'))        define('SUPPORT_CHAT', env("SUPPORT_CHAT", "-1002827751730"));

if (!defined('VIP_PAYMENT_LINK'))    define('VIP_PAYMENT_LINK', env("VIP_PAYMENT_LINK", ""));
if (!defined('VIP_PRIVATE_INVITE'))  define('VIP_PRIVATE_INVITE', env("VIP_PRIVATE_INVITE", "https://t.me/+hUV4tJ7n3b9kMDlk"));

if (!defined('DB_FILE'))  define('DB_FILE', __DIR__ . "/storage/atb_db.json");
if (!defined('LOG_FILE')) define('LOG_FILE', __DIR__ . "/logs/atb_log.txt");
if (!defined('DEBUG'))    define('DEBUG', false);

if (!defined('ADMIN_SECRET_CODE')) define('ADMIN_SECRET_CODE', env('ADMIN_SECRET_CODE', 'ATB-ADMIN-2025'));

// شماره‌های ادمین
if (!isset($ADMIN_PHONE)) {
    $ADMIN_PHONE = ["09123079406", "+989150811691"];
}

// ---- Required validation ----
if (empty(BOT_TOKEN)) {
    error_log("ATB Bot: BOT_TOKEN is not set. Set BOT_TOKEN in environment or in config.local.php");
    http_response_code(500);
    echo "BOT_TOKEN not set";
    exit;
}

if (!defined('SAFE_MODE')) define('SAFE_MODE', false);
