<?php
/**
 * عیب‌یاب سریع ملکینو — فایل مستقل و فقط-خواندنی
 * هیچ فایلی از سایت را اجرا نمی‌کند (فایل‌ها فقط به‌صورت متن خوانده می‌شوند)
 * باز کنید: yoursite.com/health.php
 * ⚠️ بعد از رفع مشکل، حذفش کنید.
 */
header('Content-Type: text/html; charset=utf-8');
error_reporting(0);
@ini_set('display_errors', '0');
@set_time_limit(30);

$ok   = '<span style="color:#0a7d32;font-weight:800">✓ سالم</span>';
$bad  = '<span style="color:#c0261f;font-weight:800">✗ مشکل</span>';
$warn = '<span style="color:#b26a00;font-weight:800">⚠ هشدار</span>';
$rows = [];
$concl = '';

/* ---------- سطح ۰: خود PHP ---------- */
$phpVer = PHP_VERSION;
$extPdo  = extension_loaded('pdo_mysql');
$extMys  = extension_loaded('mysqli');
$rows[] = ['موتور PHP', $phpVer . (($extPdo || $extMys) ? '' : ' — هیچ افزونهٔ MySQL لود نیست!')];
$rows[] = ['افزونهٔ PDO MySQL', $extPdo ? 'دارد' : 'ندارد'];
$rows[] = ['افزونهٔ MySQLi', $extMys ? 'دارد' : 'ندارد'];
if (version_compare($phpVer, '8.0', '<')) {
    $rows[] = ['نسخهٔ PHP', $bad . ' کد ملکینو به PHP 8+ نیاز دارد. از کنترل‌پنل هاست، نسخهٔ PHP سایت را روی 8.1 یا 8.2 بگذارید.'];
} else {
    $rows[] = ['نسخهٔ PHP', $ok . ' (۸ به بالا)'];
}

/* ---------- سطح ۱: پیدا کردن مشخصات دیتابیس از فایل‌های تنظیمات (فقط متن) ---------- */
function melkinoHealthParseEnvText(string $txt, array &$out): void {
    if (preg_match_all('/^\s*(DB_HOST|DB_NAME|DB_USER|DB_PASS)\s*=\s*(.+)$/m', $txt, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $v = trim($x[2]);
            if (strlen($v) > 1 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) $v = substr($v, 1, -1);
            if ($v !== '' && !isset($out[$x[1]])) $out[$x[1]] = $v;
        }
    }
    // define('DB_HOST', '...') یا putenv('DB_HOST=...') یا 'DB_HOST' => '...'
    // نکته: بین کلید و جداکننده ممکن است کوتیشنِ بسته باشد (فرم define)
    $keys = ['DB_HOST' => 'host', 'DB_NAME' => 'name', 'DB_USER' => 'user', 'DB_PASS' => 'pass'];
    foreach ($keys as $k => $slot) {
        if (isset($out[$k])) continue;
        if (preg_match('/\b' . $k . '\b[\'"]?\s*(?:,|=>|=)\s*[\'"]([^\'"]+)\'"/', $txt, $mm)) {
            if ($mm[1] !== '') $out[$k] = $mm[1];
        }
    }
}
$creds = [];
foreach ([__DIR__ . '/.env', __DIR__ . '/config.secrets.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) {
        melkinoHealthParseEnvText((string)@file_get_contents($f), $creds);
    }
}
$found = isset($creds['DB_HOST'], $creds['DB_NAME'], $creds['DB_USER']);
$rows[] = ['فایل تنظیمات دیتابیس', $found
    ? $ok . ' پیدا شد (host=' . htmlspecialchars($creds['DB_HOST'], ENT_QUOTES, 'UTF-8') . '، db=' . htmlspecialchars($creds['DB_NAME'], ENT_QUOTES, 'UTF-8') . ')'
    : $warn . ' از متن فایل‌ها خوانده نشد — <b>اگر «اتصال به MySQL» در ردیف بعدی سبز باشد، این ردیف مشکلی نیست</b> (فرمت تنظیمات سایت فقط از متن قابل تشخیص نیست).'];

/* ---------- سطح ۱ب: اتصال زنده از طریق config.php خود سایت (ملاک اصلی) ----------
   config.php همان مسیر بوت‌استرپی است که همهٔ صفحات سایت (و schema-fix.php)
   استفاده می‌کنند؛ اگر سایت کار می‌کند این هم کار می‌کند. */
if (!$pdo) {
    $__live = null;
    try {
        ob_start();
        require_once __DIR__ . '/config.php';
        if (function_exists('melkinoInitDbGlobal')) {
            @melkinoInitDbGlobal();
        }
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) $__live = $GLOBALS['pdo'];
        elseif (isset($pdo) && $pdo instanceof PDO) $__live = $pdo;
    } catch (Throwable $e) {
        $__live = null;
        $rows[] = ['اتصال به MySQL (config.php)', $bad . ' ' . htmlspecialchars('[' . $e->getCode() . '] ' . substr($e->getMessage(), 0, 90), ENT_QUOTES, 'UTF-8')];
    }
    if (ob_get_level() > 0) { try { ob_end_clean(); } catch (Throwable $e2) {} }
    if ($__live) {
        try {
            $__live->query('SELECT 1');
            $pdo = $__live;
            $rows[] = ['اتصال به MySQL', $ok . ' (اتصال زنده از طریق config.php سایت — ملاک اصلی)'];
        } catch (Throwable $e) {
            $rows[] = ['اتصال به MySQL (config.php)', $bad . ' ' . htmlspecialchars('[' . $e->getCode() . '] ' . substr($e->getMessage(), 0, 90), ENT_QUOTES, 'UTF-8')];
        }
    } elseif (empty($rows) || end($rows)[0] !== 'اتصال به MySQL (config.php)') {
        $rows[] = ['اتصال به MySQL (config.php)', $warn . ' config.php لود شد ولی شیء اتصال در دسترس نبود'];
    }
}

/* ---------- سطح ۲: اتصال به دیتابیس ---------- */
$pdo = null;
if ($found) {
    $host = $creds['DB_HOST']; $db = $creds['DB_NAME'];
    $user = $creds['DB_USER']; $pass = $creds['DB_PASS'] ?? '';
    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 6]
        );
        $rows[] = ['اتصال به MySQL', $ok];
    } catch (PDOException $e) {
        $code = $e->getCode(); $msg = $e->getMessage();
        $hint = '';
        if (strpos($msg, '2002') !== false || strpos($msg, 'timed out') !== false || strpos($msg, 'Connection refused') !== false) {
            $hint = 'سرور MySQL در دسترس نیست — روی هاست‌های رایگان (مثل اینفینیتی) این دقیقاً همان حالتی است که صفحات ۵۰۲ می‌دهند. ۱۰ تا ۳۰ دقیقه صبر کنید و دوباره امتحان کنید؛ اگر ادامه داشت از وضعیت سرورها (status.infinityfree.com) یا پشتیبانی هاست پیگیری کنید.';
        } elseif (strpos($msg, '1045') !== false || strpos($msg, '1044') !== false) {
            $hint = 'نام کاربری/رمز دیتابیس اشتباه است — در کنترل‌پنل هاست، دیتابیس و کاربرش را بازبینی کنید (.env یا config.secrets.php را اصلاح کنید).';
        } elseif (strpos($msg, '1049') !== false || strpos($msg, 'Unknown database') !== false) {
            $hint = 'دیتابیس با این نام وجود ندارد — در phpMyAdmin بسازید یا نامش را در تنظیمات درست کنید.';
        }
        $rows[] = ['اتصال به MySQL', $bad . ' ' . htmlspecialchars('[' . $code . '] ' . substr($msg, 0, 90), ENT_QUOTES, 'UTF-8') . '<br><small>' . $hint . '</small>'];
    }
}

/* ---------- سطح ۳: جدول‌ها ---------- */
if ($pdo) {
    try {
        $have = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) { $have[strtolower($t)] = true; }
        $need = ['ads','admins','images','users','settings','visit_requests','partnership_requests','property_requests','ads_history','channel_publish_logs','saved_searches','location_change_log','comm_contacts','sms_outbox','notification_broadcasts'];
        $missing = array_filter($need, fn($t) => !isset($have[strtolower($t)]));
        $rows[] = ['جدول‌ها', count($have) . ' جدول موجود است' . ($missing
            ? ' — ' . $bad . ' جاافتاده: ' . htmlspecialchars(implode('، ', $missing), ENT_QUOTES, 'UTF-8') . ' ← فایل melkino-database.sql را دوباره Import کنید (بی‌خطر است).'
            : ' ' . $ok . ' (همهٔ جدول‌های حیاتی هستند)')];
        if (isset($have['admins'])) {
            $n = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            $rows[] = ['حساب ادمین', $n > 0 ? $ok . ' (' . $n . ' حساب)' : $warn . ' هیچ ادمینی وجود ندارد — Import دوباره melkino-database.sql حساب اولیه را می‌سازد.'];
        }
    } catch (Throwable $e) {
        $rows[] = ['بررسی جدول‌ها', $bad . ' ' . htmlspecialchars(substr($e->getMessage(), 0, 90), ENT_QUOTES, 'UTF-8')];
    }
}

/* ---------- نتیجه‌گیری: ملاک = اتصال زنده، نه پیدا شدن متن فایل ---------- */
$dbFail = false;
foreach ($rows as $r) { if (strpos($r[1], '✗') !== false) $dbFail = true; }
if (!$pdo) $dbFail = true;
$concl = $dbFail
    ? '<b>تشخیص:</b> مشکل از سمت دیتابیس/تنظیمات است (طبق ردیف‌های ✗ بالا عمل کنید). تا وقتی اتصال MySQL برقرار نباشد، صفحات سایت می‌توانند ۵۰۲ بدهند.'
    : '<b>تشخیص:</b> PHP و دیتابیس هر دو سالم‌اند ✅ — اگر باز هم صفحه‌ای ۵۰۲ می‌دهد، حتماً مشکل موقتیِ خود سرور هاست است: ۱۵ دقیقه صبر کنید، بعد دوباره تست کنید (و Ctrl+F5 بزنید). اگر فقط «یک صفحهٔ» خاص ۵۰۲ می‌دهد، نام همان صفحه را به سازنده گزارش دهید.';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>عیب‌یاب ملکینو</title></head>
<body style="font-family:Tahoma,Vazirmatn,sans-serif;background:#f5f6f8;margin:0;padding:24px 12px">
<div style="max-width:780px;margin:0 auto;background:#fff;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.08)">
<h1 style="font-size:19px;margin:0 0 4px">عیب‌یاب ملکینو (health.php) <span style="color:#888;font-size:11px;font-weight:400;white-space:nowrap">نسخهٔ ابزار: v3 (2026-10-03)</span></h1>
<p style="color:#555;font-size:13px;line-height:2;margin:0 0 12px">
اگر <b>همین صفحه هم ۵۰۲ داد</b> یعنی مشکل از خود سرور هاست است، نه کد سایت — چند دقیقه بعد دوباره امتحان کنید و اگر ادامه داشت به پشتیبانی هاست تیکت بزنید.<br>
⚠️ پس از رفع مشکل، این فایل را از هاست حذف کنید.
</p>
<table style="width:100%;border-collapse:collapse;font-size:13.5px">
<?php foreach ($rows as $r): ?>
<tr><td style="padding:8px 10px;border-bottom:1px solid #eee;font-weight:800;white-space:nowrap;vertical-align:top"><?= htmlspecialchars($r[0], ENT_QUOTES, 'UTF-8') ?></td>
<td style="padding:8px 10px;border-bottom:1px solid #eee;line-height:1.9"><?= $r[1] ?></td></tr>
<?php endforeach; ?>
</table>
<div style="margin-top:14px;background:#eef4f9;border:1px solid #c8dff0;border-radius:10px;padding:12px 14px;font-size:13.5px;line-height:2"><?= $concl ?></div>
</div></body></html>
