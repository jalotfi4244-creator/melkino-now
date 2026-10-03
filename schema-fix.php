<?php
/**
 * ترمیم‌کنندهٔ دیتابیس ملکینو — یک‌کلیک، فقط برای ادمین
 * ---------------------------------------------------------------
 * کاری که می‌کند: هر جدول/ستونی که روی دیتابیس هاست جا افتاده را
 * می‌سازد (بازخوانی melkino-database.sql + همهٔ ALTERهای تضمینی کد).
 * کاملاً idempotent است — هر چند بار اجرا کنید مشکلی ندارد و به
 * داده‌های موجود چیزی خراب نمی‌کند.
 * باز کنید: yoursite.com/schema-fix.php
 * ⚠️ بعد از کار، فایل را از هاست حذف کنید.
 */
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/config.php';

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="font-family:Tahoma;padding:40px;text-align:center">فقط ادمین. اول وارد پنل ادمین شوید، بعد این صفحه را باز کنید.</body></html>');
}

header('Content-Type: text/html; charset=utf-8');
@set_time_limit(120);

global $pdo;
$okIc  = '<span style="color:#0a7d32;font-weight:800">✓</span>';
$skipIc= '<span style="color:#888;font-weight:700">–</span>';
$badIc = '<span style="color:#c0261f;font-weight:800">✗</span>';
$rows = [];
$errs = 0; $created = 0; $altered = 0; $skipped = 0;

/* ---------- ۱) اجرای CREATE TABLEهای فایل اسکیما ---------- */
$sqlFile = __DIR__ . '/melkino-database.sql';
if (is_file($sqlFile)) {
    $sql = (string)@file_get_contents($sqlFile);
    // خطوط کامنت حذف می‌شوند؛ ورودی «CREATE TABLE IF NOT EXISTS» در سربرگ
    // راهنما نباید به‌عنوان دستور شناخته شود (ارسال ۱۰۶۴).
    $sql = preg_replace('/^\s*--[^\n]*$/m', '', $sql);
    // فقط دستورهای CREATE (بقیه مثل INSERT ادمین اولیه را دست نمی‌زنیم تا حساب موجود تغییر نکند)
    if (preg_match_all('/CREATE TABLE IF NOT EXISTS[^;]+;/s', $sql, $m)) {
        foreach ($m[0] as $stmt) {
            $name = '';
            if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?([a-z_0-9]+)`?/i', $stmt, $n)) $name = $n[1];
            try {
                $pdo->exec($stmt);
                $rows[] = [$okIc . ' جدول', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), 'موجود/ساخته شد'];
                $created++;
            } catch (Throwable $e) {
                $errs++;
                $rows[] = [$badIc . ' جدول', htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), htmlspecialchars(substr($e->getMessage(), 0, 90), ENT_QUOTES, 'UTF-8')];
            }
        }
    }
} else {
    $rows[] = [$badIc . ' فایل', 'melkino-database.sql', 'کنار این فایل پیدا نشد'];
}

/* ---------- ۲) ALTERهای تضمینی — ستون‌هایی که کد در نصب‌های قدیمی لازم دارد ---------- */
$ENSURE = [
    'ads' => [
        'telegram_channel_id VARCHAR(191) NULL', 'telegram_message_id VARCHAR(100) NULL',
        'telegram_published_at DATETIME NULL', 'bale_channel_id VARCHAR(191) NULL',
        'bale_message_id VARCHAR(100) NULL', 'bale_published_at DATETIME NULL',
        'created_by_telegram_id VARCHAR(30) NULL', 'default_image_no TINYINT NULL DEFAULT NULL',
        'melkino_visited TINYINT(1) NOT NULL DEFAULT 0', 'melkino_rating DECIMAL(3,1) NULL',
        'melkino_review TEXT NULL', 'building_age INT NULL DEFAULT NULL',
    ],
    'users' => [
        'telegram_first_name VARCHAR(100) NULL', 'telegram_last_name VARCHAR(100) NULL',
        'telegram_username VARCHAR(100) NULL', 'telegram_language_code VARCHAR(10) NULL',
        'telegram_is_premium TINYINT(1) NOT NULL DEFAULT 0', 'telegram_photo_url VARCHAR(500) NULL',
        'telegram_auth_date DATETIME NULL', 'last_init_data TEXT NULL',
        'phone_verified TINYINT(1) NOT NULL DEFAULT 0', 'phone_locked TINYINT(1) NOT NULL DEFAULT 0',
        'first_name VARCHAR(100) NULL', 'last_name VARCHAR(100) NULL',
        'name_locked TINYINT(1) NOT NULL DEFAULT 0', 'eitaa_id VARCHAR(64) NULL', 'eitaa_username VARCHAR(191) NULL',
    ],
    'login_events' => [
        'user_id INT NULL', 'telegram_id VARCHAR(64) NULL', 'bale_id VARCHAR(64) NULL',
        'eitaa_id VARCHAR(64) NULL', 'username VARCHAR(191) NULL', 'name VARCHAR(191) NULL',
        'ip_address VARCHAR(45) NULL', 'ip VARCHAR(45) NULL', 'user_agent VARCHAR(1000) NULL',
        'platform VARCHAR(20) NULL', 'language_code VARCHAR(10) NULL',
    ],
    'partnership_requests' => [
        "value_from VARCHAR(40) NOT NULL DEFAULT ''", "value_to VARCHAR(40) NOT NULL DEFAULT ''",
        "deed_kind VARCHAR(40) NOT NULL DEFAULT ''", "direction VARCHAR(10) NOT NULL DEFAULT ''",
    ],
    'otp_codes' => ['code_hash VARCHAR(128) NULL DEFAULT NULL'],
    'notifications' => ['broadcast_id INT UNSIGNED NULL', 'read_at DATETIME NULL DEFAULT NULL'],
    'visit_requests' => [
        'tracking_code VARCHAR(30) NULL', 'archived TINYINT(1) NOT NULL DEFAULT 0',
        'requester_name VARCHAR(120) NULL', 'requester_phone VARCHAR(30) NULL',
    ],
    'comm_contacts' => [
        'telegram_id VARCHAR(64) NULL', 'bale_id VARCHAR(64) NULL',
        'telegram_username VARCHAR(100) NULL', 'bale_username VARCHAR(191) NULL',
    ],
    'comm_campaigns' => ['scheduled_at DATETIME NULL DEFAULT NULL'],
    'comm_deferred' => ['send_after DATETIME NULL'],
];
foreach ($ENSURE as $table => $cols) {
    foreach ($cols as $ddl) {
        // $ddl با نام ستون شروع می‌شود (برای ادغام اسکیما)؛ تعریف واقعی
        // بعد از نام است — نام دوبار در SQL تکرار نشود (خطای 4161)
        $col = trim(strtok($ddl, ' '));
        $def = trim(substr((string)$ddl, strlen($col)));
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
            $altered++;
            $rows[] = [$okIc . ' ستون', htmlspecialchars($table . '.' . $col, ENT_QUOTES, 'UTF-8'), 'اضافه شد'];
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (strpos($msg, '1060') !== false || stripos($msg, 'duplicate column') !== false) {
                $skipped++;
                $rows[] = [$skipIc . ' ستون', htmlspecialchars($table . '.' . $col, ENT_QUOTES, 'UTF-8'), 'از قبل موجود'];
            } else {
                $errs++;
                $rows[] = [$badIc . ' ستون', htmlspecialchars($table . '.' . $col, ENT_QUOTES, 'UTF-8'), htmlspecialchars(substr($msg, 0, 90), ENT_QUOTES, 'UTF-8')];
            }
        }
    }
}

/* ---------- ۳) ایندکس‌های کمکی (بی‌صدا اگر تکراری باشد) ---------- */
$KEYS = [
    'CREATE INDEX idx_vr_track ON visit_requests (tracking_code)' => 'visit_requests.tracking_code',
    'CREATE INDEX idx_broadcast ON notifications (broadcast_id)' => 'notifications.broadcast_id',
    'CREATE INDEX idx_lcl_ad ON location_change_log (ad_id, id)' => 'location_change_log.ad_id',
];
foreach ($KEYS as $ddl => $label) {
    try {
        $pdo->exec($ddl);
        $altered++;
        $rows[] = [$okIc . ' ایندکس', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), 'ساخته شد'];
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (strpos($msg, '1061') !== false || stripos($msg, 'duplicate key name') !== false) { $skipped++; }
        else { $rows[] = [$skipIc . ' ایندکس', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), 'نادیده گرفته شد']; }
    }
}

/* ---------- ۴) جمع‌بندی: جدول‌ها و ستون‌های حیاتی موجود ---------- */
$tableStatus = '';
try {
    $have = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) { $have[strtolower($t)] = true; }
    $need = ['ads','admins','images','users','settings','ad_revisions','ad_amenities','amenities','visit_requests','partnership_requests','property_requests','ads_history','channel_publish_logs','location_change_log','saved_searches','request_matches','request_match_feedback','sms_outbox','sms_optouts','notification_broadcasts','comm_contacts','comm_campaigns','otp_codes','login_tokens','login_events','favorites','compare_groups','compare_items','support_tickets','support_messages','promotions','melkino_audit_log'];
    $missing = array_filter($need, fn($t) => !isset($have[strtolower($t)]));
    $tableStatus = $missing
        ? '<span style="color:#c0261f;font-weight:800">هنوز جاافتاده: ' . htmlspecialchars(implode('، ', $missing), ENT_QUOTES, 'UTF-8') . '</span>'
        : '<span style="color:#0a7d32;font-weight:800">همهٔ جدول‌های حیاتی موجودند ✅</span>';
} catch (Throwable $e) {
    $tableStatus = '<span style="color:#c0261f">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ترمیم دیتابیس ملکینو</title></head>
<body style="font-family:Tahoma,Vazirmatn,sans-serif;background:#f5f6f8;margin:0;padding:24px 12px">
<div style="max-width:820px;margin:0 auto;background:#fff;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.08)">
<h1 style="font-size:19px;margin:0 0 4px">ترمیم دیتابیس ملکینو <span style="color:#888;font-size:11px;font-weight:400;white-space:nowrap">نسخهٔ ابزار: v2 (2026-10-03)</span></h1>
<p style="color:#555;font-size:13px;line-height:2;margin:0 0 12px">
جدول‌های جاافتاده ساخته شدند و ستون‌های جاافتاده اضافه شدند. این کار به داده‌های موجود آسیب نمی‌زند.
دسترسی فقط برای ادمین: <b><?= htmlspecialchars((string)($_SESSION['admin_username'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?></b><br>
⚠️ بعد از اتمام، این فایل را از هاست حذف کنید.
</p>
<div style="background:#eef7f0;border:1px solid #bfe3c8;border-radius:10px;padding:10px 14px;font-size:14px;font-weight:800;margin-bottom:12px">
    جدول‌ها: <?= (int)$created ?> · ستون/ایندکس اضافه‌شده: <?= (int)$altered ?> · از قبل موجود: <?= (int)$skipped ?> · خطا: <?= (int)$errs ?><br>
    وضعیت نهایی جدول‌های حیاتی: <?= $tableStatus ?>
</div>
<table style="width:100%;border-collapse:collapse;font-size:12.5px">
<?php foreach ($rows as $r): ?>
<tr><td style="padding:5px 8px;border-bottom:1px solid #eee;white-space:nowrap"><?= $r[0] ?></td>
<td style="padding:5px 8px;border-bottom:1px solid #eee;font-weight:700"><?= $r[1] ?></td>
<td style="padding:5px 8px;border-bottom:1px solid #eee;color:#555"><?= $r[2] ?></td></tr>
<?php endforeach; ?>
</table>
</div></body></html>
