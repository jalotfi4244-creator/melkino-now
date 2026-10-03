<?php
/**
 * ابزار بررسی نصب به‌روزرسانی ملکینو — فقط خواندنی، بدون دیتابیس
 * روی هاست کنار بقیهٔ فایل‌ها آپلود کنید و در مرورگر باز کنید:
 *   yoursite.com/deploy-check.php
 * بعد از تأیید، همین فایل را از هاست حذف کنید.
 */
header('Content-Type: text/html; charset=utf-8');
$checks = [
    ['admin-panel.php',           'melkinoLogAdHistory',   'سابقهٔ کامل آگهی‌ها (تاریخچه)'],
    ['admin-ads.js',              'editAdVisitHours',      'فیلد «چه زمانی می‌شود بازدید کرد» در ویرایش ادمین'],
    ['admin-ads.js',              'mkpSellCandidate',      'اصلاح قیمت دو صفر اضافه در تب آگهی‌ها'],
    ['admin-partnership.php',     'site_publish',          'انتشار مشارکت در ساخت توسط ادمین'],
    ['admin-publish-logs.php',    'ads_history',           'تاریخچه در مودال سابقه'],
    ['visit-request-lib.php',     'melkinoVisitDayCounts', 'ظرفیت بازدید (کتابخانه)'],
    ['visit-request-api.php',     'admin_save_capacity',   'ظرفیت بازدید (API)'],
    ['admin-visits.php',          'vrCapMorning',          'مدیریت بازدیدها: ظرفیت/رنگ/ویرایش/بایگانی'],
    ['property-details.php',      'slots_state',           'غیرفعال شدن روز پُر در تقویم کاربر'],
    ['contact.php',               'nshn.ir',               'دکمهٔ مسیریابی (نسخهٔ درست‌شده)'],
    ['map.js',                    'mk-sheet-title',        'کارت مارکر تمام‌عرض + رنگ نوع ملک + کلیک مجدد'],
    ['map.css',                   'mk-sheet-price',        'استایل کارت مارکر'],
    ['map-polygon-picker.js',     'برای حذف هر نقطه',      'حذف نقطه با کلیک روی مارکر (جستجو/درخواست ملک)'],
    ['search.php',                'filemtime',             'شکستن کش خودکار اسکریپت نقشه'],
    ['search-results.php',        'MAP-REGION',            'فیلتر نتایج جستجو با محدودهٔ نقشه'],
    ['property-request.php',      'یعنی بدون فیلتر منطقه', 'موقعیت نقشهٔ اختیاری در درخواست ملک'],
    ['contact.php',               'در حال باز کردن برنامهٔ مسیریاب', 'مسیریابی: نسخهٔ نهایی (پرانتز واقعی + فیدبک)'],
    ['property-details.php',      'شمارهٔ تماسِ خودِ آگهی', 'تماس با مشاور: شمارهٔ خود آگهی به‌عنوان جایگزین'],
    ['admin-panel.php',           "@ line ' . \$e->getLine()", 'خطای واقعی ذخیرهٔ آگهی برای ادمین نمایش داده می‌شود'],
    ['profile.php',               'روز مانده تا قرار بازدید', 'تایمر قرار بازدید در حساب کاربری'],
    ['schema-fix.php',            'ALTER TABLE', 'ترمیم‌کنندهٔ دیتابیس (schema-fix.php)'],
    ['db_helpers.php',            'مقادیر خام (هنوز URL-encoded)', 'ورود مینی‌اپ: امضای RAW (فیکس خطای ورود تلگرام/بله)'],
    ['melkino-auth-gate.js',      'melkino_auth_reload', 'ورود: شفای خودکار رفرش'],
    ['login.php',                 'melkino_auth_reload', 'ورود: شفای خودکار در login.php'],
    ['admin-panel.php',           'transaction was implicitly closed before commit', 'ذخیرهٔ آگهی: فیکس تراکنش (DDL بیرون از تراکنش)'],
];
$byFile = [];
foreach ($checks as $c) { $byFile[$c[0]][] = $c; }
$html = [];
$okCount = 0; $total = count($checks);
foreach ($byFile as $file => $items) {
    $exists = is_file(__DIR__ . '/' . $file);
    $content = $exists ? (string)@file_get_contents(__DIR__ . '/' . $file) : '';
    $mtime = $exists ? @filemtime(__DIR__ . '/' . $file) : 0;
    $allOk = true;
    $rows = '';
    foreach ($items as $it) {
        $hit = $exists && strpos($content, $it[1]) !== false;
        if (!$hit) $allOk = false;
        $okCount += $hit ? 1 : 0;
        $rows .= '<tr><td style="padding:7px 10px;border-bottom:1px solid #eee">' . htmlspecialchars($it[2], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:7px 10px;border-bottom:1px solid #eee;text-align:center">' . ($hit
                ? '<span style="color:#0a7d32;font-weight:800">✓ جدید است</span>'
                : '<span style="color:#c0261f;font-weight:800">✗ قدیمی/نصب نشده</span>') . '</td></tr>';
    }
    $html[] = '<tr><td style="padding:7px 10px;border-bottom:1px solid #eee;font-weight:800;white-space:nowrap">' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8')
        . ($exists ? '<br><small style="color:#888;font-weight:400">آخرین تغییر روی هاست: ' . date('Y-m-d H:i', $mtime) . '</small>' : '<br><small style="color:#c0261f">فایل روی هاست نیست!</small>') . '</td>'
        . '<td style="padding:0"><table style="width:100%;border-collapse:collapse">' . $rows . '</table></td></tr>';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>بررسی نصب به‌روزرسانی — ملکینو</title>
</head>
<body style="font-family:Tahoma,Vazirmatn,sans-serif;background:#f5f6f8;margin:0;padding:24px 12px">
<div style="max-width:820px;margin:0 auto;background:#fff;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.08)">
    <h1 style="font-size:19px;margin:0 0 4px">بررسی نصب به‌روزرسانی ملکینو</h1>
    <p style="color:#555;font-size:13px;line-height:2;margin:0 0 14px">
        اگر پایین همه سطرها <b style="color:#0a7d32">✓ جدید است</b> بود یعنی فایل‌ها درست آپلود شده‌اند؛
        آن‌وقت فقط <b>Ctrl+F5</b> بزنید تا کش مرورگر هم خالی شود. هر سطر <b style="color:#c0261f">✗</b> یعنی آن فایل هنوز روی هاست
        نسخهٔ قدیمی است و باید از پکیج آپلود شود.<br>
        <b>نکتهٔ مهم:</b> فایل‌های داخل پکیج در «ریشهٔ» زیپ هستند — بعد از Extract محتویات را مستقیم داخل پوشهٔ سایت
        (مثل <code>public_html</code>) بریزید، نه داخل یک پوشهٔ تازه.<br>
        <b style="color:#c0261f">بعد از اتمام کار، فایل deploy-check.php را از هاست حذف کنید.</b>
    </p>
    <div style="background:#eef7f0;border:1px solid #bfe3c8;border-radius:10px;padding:10px 14px;font-size:14px;font-weight:800;margin-bottom:12px">
        نتیجه: <?= $okCount ?> از <?= $total ?> مورد به‌روز است
        <?= $okCount === $total ? '✅ همه‌چیز نصب است — مشکل کش مرورگر است، Ctrl+F5 بزنید.' : '⚠️ فایل‌های ✗ را آپلود کنید.' ?>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
        <?= implode('', $html) ?>
    </table>
</div>
</body>
</html>
