<?php
/**
 * melkino-fix-json.php — تعمیر یک‌بارمصرف ستون‌های JSON آلوده (راند ۱۵)
 * -----------------------------------------------------------------
 * باگ: هندلر ذخیرهٔ پنل ادمین، رشتهٔ tags/custom_fields را که خودش
 * JSON بود دوباره json_encode می‌کرد. با هر بار ذخیره یک لایه escape
 * اضافه می‌شد و طول مقدار به‌صورت نمایی رشد می‌کرد؛ در نتیجه خروجی
 * properties-data.php چند مگابایتی و بریده می‌شد و صفحهٔ «همهٔ
 * آگهی‌ها» خطای «اتصال دیتابیس» می‌داد (در حالی که دیتابیس سالم بود).
 *
 * این اسکریپت همهٔ آگهی‌ها را می‌پیماید، لایه‌های escape را تا هستهٔ
 * اصلی باز می‌کند و مقدار تمیز را ذخیره می‌کند. هیچ دادهٔ واقعی از
 * بین نمی‌رود — فقط پوسته‌های تکراری json_encode حذف می‌شوند.
 *
 * روش استفاده:
 *   ۱) وارد پنل ادمین شوید (همان مرورگر).
 *   ۲) این آدرس را باز کنید:  https://SITE/melkino-fix-json.php
 *   ۳) گزارش را ببینید؛ صفحهٔ «همهٔ آگهی‌ها» را رفرش کنید.
 *   ۴) همین فایل را از هاست حذف کنید.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_helpers.php';

header('Content-Type: text/html; charset=utf-8');

function fxj_e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$style = '<style>body{font-family:Tahoma,sans-serif;direction:rtl;max-width:900px;margin:24px auto;padding:0 16px;color:#1c2b2b;line-height:2}table{border-collapse:collapse;width:100%;font-size:13px}td,th{border:1px solid #ccc;padding:6px 8px;text-align:right}.ok{color:#0a7a55;font-weight:700}.bad{color:#b42318;font-weight:700}.muted{color:#667}h1{font-size:20px}</style>';

// ---- گارد دسترسی: فقط ادمین لاگین‌شده ----
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
$isAdmin = (($_SESSION['is_admin'] ?? false) === true);
if (!$isAdmin) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>غیرمجاز</title></head><body>'
        . '<h1>۴۰۳ — دسترسی غیرمجاز</h1>'
        . '<p>ابتدا در همان مرورگر وارد <b>پنل ادمین</b> شوید و بعد این صفحه را دوباره باز کنید.</p>'
        . '</body></html>';
    exit;
}

if (!$pdo instanceof PDO) {
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>خطا</title></head><body>' . $style
        . '<h1>❌ اتصال دیتابیس برقرار نیست</h1><p>config.secrets.php / تنظیمات اتصال را بررسی کنید.</p></body></html>';
    exit;
}

echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>تعمیر ستون‌های JSON</title></head><body>' . $style;
echo '<h1>🧅 تعمیر ستون‌های JSON آلوده (tags / custom_fields / property_details)</h1>';

try {
    $rows = $pdo->query('SELECT id, tags, custom_fields, property_details FROM ads')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo '<p class="bad">خطا در خواندن آگهی‌ها: ' . fxj_e($e->getMessage()) . '</p></body></html>';
    exit;
}

$cols = ['tags', 'custom_fields', 'property_details'];
$upd = $pdo->prepare('UPDATE ads SET tags = ?, custom_fields = ?, property_details = ? WHERE id = ?');

$total = count($rows);
$fixed = 0;
$bytesBefore = 0;
$bytesAfter = 0;
$details = [];

foreach ($rows as $row) {
    $dirty = false;
    $clean = [];
    foreach ($cols as $c) {
        $raw = $row[$c] ?? null;
        $bytesBefore += is_string($raw) ? strlen($raw) : 0;
        $arr = melkinoNormalizeJsonColumn($raw);
        $newJson = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // اگر مقدار ذخیره‌شده با نسخهٔ تمیز یکی نبود → آلوده بوده
        if ((string)$raw !== (string)$newJson) {
            $dirty = true;
        }
        $bytesAfter += strlen($newJson);
        $clean[$c] = $newJson;
    }
    if ($dirty) {
        $upd->execute([$clean['tags'], $clean['custom_fields'], $clean['property_details'], $row['id']]);
        $fixed++;
        $details[] = $row['id'];
    }
}

echo '<p>کل آگهی‌ها: <b>' . (int)$total . '</b> — تعمیرشدن: <b class="' . ($fixed ? 'bad' : 'ok') . '">' . (int)$fixed . '</b></p>';
echo '<p>حجم این سه ستون قبل از تعمیر: <b>' . number_format($bytesBefore) . '</b> بایت ← بعد از تعمیر: <b class="ok">' . number_format($bytesAfter) . '</b> بایت</p>';
if ($details) {
    echo '<p class="muted">آگهی‌های تعمیرشده: ' . fxj_e(implode('، ', $details)) . '</p>';
}
echo '<p class="ok">✅ انجام شد. حالا صفحهٔ «همهٔ آگهی‌ها» را رفرش کنید — باید کارت‌ها برگردند.</p>';
echo '<p class="bad">⚠️ همین فایل (melkino-fix-json.php) را از هاست حذف کنید.</p>';
echo '</body></html>';
