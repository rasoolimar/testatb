<?php
/**
 * Simple health check to verify deployment.
 * - Checks file permissions
 * - Checks DB readability/writability
 * - Checks Telegram getMe() (if BOT_TOKEN present)
 */
header('Content-Type: text/plain; charset=UTF-8');
require __DIR__ . '/config.php';

$status = [];

$status[] = "PHP version: " . PHP_VERSION;

// Paths
$status[] = "DB_FILE: " . DB_FILE;
$status[] = "LOG_FILE: " . LOG_FILE;

// Filesystem checks
$status[] = "storage exists: " . (is_dir(__DIR__ . '/storage') ? 'YES' : 'NO');
$status[] = "logs exists: " . (is_dir(__DIR__ . '/logs') ? 'YES' : 'NO');
$status[] = "DB readable: " . (is_readable(DB_FILE) ? 'YES' : 'NO');
$status[] = "DB writable: " . (is_writable(DB_FILE) ? 'YES' : 'NO (fix perms 0666/owner)');
$status[] = "LOG dir writable: " . (is_writable(__DIR__ . '/logs') ? 'YES' : 'NO (chmod 0775)');
$status[] = "Token present: " . (BOT_TOKEN ? ('YES (' . substr(BOT_TOKEN, 0, 7) . '...hidden)') : 'NO');

// getMe()
if (BOT_TOKEN) {
    $api = "https://api.telegram.org/bot" . BOT_TOKEN . "/getMe";
    $ctx = stream_context_create(['http'=>['method'=>'GET','timeout'=>10]]);
    $res = @file_get_contents($api, false, $ctx);
    $status[] = "getMe(): " . ($res ?: 'NO RESPONSE (maybe firewall/SSL problem)');
} else {
    $status[] = "getMe(): skipped (no token)";
}

echo implode("\n", $status);
