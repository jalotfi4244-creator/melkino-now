<?php
/**
 * اصلاح‌گر قیمت‌های اشتباه ملکینو — ابزار موقت ادمین (نسخهٔ ابزار: v1 / 2026-10-03)
 * ---------------------------------------------------------------
 * چی‌کار می‌کند: آگهی‌هایی که یکی از فیلدهای قیمتی‌شان بالای آستانه
 * (پیش‌فرض ۱۰۰ میلیارد تومان) است را فهرست می‌کند؛ ادمین مقدار درست را
 * وارد می‌کند (یا دکمه‌های ÷۱۰ و ÷۱۰۰ و ÷۱۰۰۰ را می‌زند) و ذخیره می‌کند.
 * با هر ذخیره، ستون display_price هم با همان اولویت فرم ثبت
 * (price_sell → total_price → deposit → rent_monthly) همگام می‌شود.
 * هیچ چیزی بدون تأیید صریح ادمین تغییر نمی‌کند.
 * باز کنید: yoursite.com/price-fix.php
 * ⚠️ بعد از کار، این فایل را از هاست حذف کنید.
 */
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/config.php';

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="font-family:Tahoma;padding:32px;text-align:center;line-height:2.2"><h2 style="color:#c0261f">این ابزار فقط برای ادمین است</h2>اول در <b>همین مرورگر</b> وارد پنل ادمین شوید<br>(باز کنید: yoursite.com/admin-panel.php ← ورود)<br>بعد این صفحه را دوباره باز کنید.</body></html>');
}

header('Content-Type: text/html; charset=utf-8');
@set_time_limit(60);

/* آستانهٔ شک به تومان — فیلدهای بالای این مقدار فهرست می‌شوند */
$THRESHOLD = 100000000000; // ۱۰۰ میلیارد تومان

/* فیلدهای قیمتی مجاز برای اصلاح */
$MONEY_FIELDS = ['price_sell', 'total_price', 'deposit', 'rent_monthly', 'full_rent', 'loan_amount', 'down_payment'];
$FIELD_LABELS = [
    'price_sell' => 'قیمت فروش', 'total_price' => 'قیمت کل', 'deposit' => 'ودیعه',
    'rent_monthly' => 'اجاره ماهانه', 'full_rent' => 'رهن کامل', 'loan_amount' => 'مبلغ وام',
    'down_payment' => 'پیش‌پرداخت',
];

function pfxCleanNumber($value): ?float {
    if ($value === null) return null;
    $s = trim((string)$value);
    if ($s === '') return null;
    $s = str_replace(
        ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩','٬','،',',',' ','تومان','ریال'],
        ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9','','','','','',''],
        $s
    );
    return is_numeric($s) ? (float)$s : null;
}

function pfxSmart($n): string {
    if ($n === null || $n <= 0) return '—';
    if ($n >= 1e9) { $x = $n / 1e9; return rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.') . ' میلیارد'; }
    if ($n >= 1e6) { $x = $n / 1e6; return rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.') . ' میلیون'; }
    return number_format((int)round($n));
}

function pfxFormat($n): string {
    if ($n === null || $n <= 0) return '—';
    return number_format((int)round($n)) . ' تومان';
}

$doneMsg = '';
$savedOldNew = [];

/* ================= ذخیره (POST) ================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $adId = trim((string)($_POST['ad_id'] ?? ''));
    if ($adId !== '') {
        $st = $pdo->prepare("SELECT id, price_sell, total_price, deposit, rent_monthly, full_rent, loan_amount, down_payment, display_price FROM ads WHERE id = ? LIMIT 1");
        $st->execute([$adId]);
        $ad = $st->fetch(PDO::FETCH_ASSOC);
        if ($ad) {
            $sets = [];
            $args = [];
            /* همهٔ فیلدهای قیمتی اول عددی تمیز می‌شوند تا اولویت‌سنجی
               display_price روی رشتهٔ خام مثل "0.00" (که در PHP truthy است)
               اشتباه نشود */
            $newVals = ['price_sell' => null, 'total_price' => null, 'deposit' => null, 'rent_monthly' => null, 'full_rent' => null, 'loan_amount' => null, 'down_payment' => null];
            foreach ($MONEY_FIELDS as $f) $newVals[$f] = pfxCleanNumber($ad[$f]);
            foreach ($MONEY_FIELDS as $f) {
                if (!array_key_exists($f, $_POST['prices'] ?? [])) continue;
                $new = pfxCleanNumber($_POST['prices'][$f]);
                $old = pfxCleanNumber($ad[$f]);
                if ($new === $old) continue; // بدون تغییر
                if ($new === null) {
                    $sets[] = "$f = NULL";
                } else {
                    $sets[] = "$f = ?";
                    $args[] = $new;
                }
                $savedOldNew[] = [$f, $old, $new];
                $newVals[$f] = $new;
            }
            if ($sets) {
                /* همگامی display_price با اولویت فرم ثبت — مثل bulk_sync پنل */
                $dp = $newVals['price_sell'] ?: ($newVals['total_price'] ?: ($newVals['deposit'] ?: $newVals['rent_monthly']));
                $sets[] = "display_price = " . ($dp === null ? "NULL" : "?");
                if ($dp !== null) $args[] = $dp;
                $args[] = $adId;
                $up = $pdo->prepare("UPDATE ads SET " . implode(', ', $sets) . " WHERE id = ?");
                $up->execute($args);
            }
            $doneMsg = $savedOldNew ? 'ذخیره شد ✓' : 'تغییری داده نشد';
        } else {
            $doneMsg = 'آگهی پیدا نشد';
        }
    }
    $_SESSION['pfx_last'] = $savedOldNew;
    header('Location: ' . basename(__FILE__) . '?done=' . urlencode($doneMsg));
    exit;
}

/* ================= فهرست آگهی‌های مشکوک ================= */
$rows = [];
$all = $pdo->query("SELECT id, title, transaction_type, status, price_sell, total_price, deposit, rent_monthly, full_rent, loan_amount, down_payment, display_price FROM ads ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($all as $ad) {
    $susp = [];
    foreach ($MONEY_FIELDS as $f) {
        $v = pfxCleanNumber($ad[$f]);
        if ($v !== null && $v >= $THRESHOLD) $susp[$f] = $v;
    }
    if ($susp) {
        $rows[] = ['ad' => $ad, 'susp' => $susp];
    }
}
$totalSusp = 0;
foreach ($rows as $r) $totalSusp += count($r['susp']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>اصلاح قیمت‌های اشتباه ملکینو</title></head>
<body style="font-family:Tahoma,Vazirmatn,sans-serif;background:#f5f6f8;margin:0;padding:24px 12px">
<div style="max-width:980px;margin:0 auto;background:#fff;border-radius:14px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,.08)">
<h1 style="font-size:19px;margin:0 0 4px">اصلاح قیمت‌های اشتباه ملکینو <span style="color:#888;font-size:11px;font-weight:400;white-space:nowrap">نسخهٔ ابزار: v2 (2026-10-03)</span></h1>
<p style="color:#555;font-size:13px;line-height:2;margin:0 0 12px">
فقط فیلدهایی که بالای <b><?= pfxFormat($THRESHOLD) ?></b> هستند فهرست شده‌اند. مقدار درست را تایپ کنید یا یکی از دکمه‌های تقسیم را بزنید و بعد «ذخیره» را بزنید.
با هر ذخیره، <b>display_price</b> (قیمتی که هوم نشان می‌دهد) هم خودکار همگام می‌شود.<br>
⚠️ بعد از اتمام، این فایل را از هاست حذف کنید.
</p>
<?php if (isset($_GET['done'])): ?>
<div style="background:#eef7f0;border:1px solid #bfe3c8;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:800;margin-bottom:12px">
    <?= htmlspecialchars((string)$_GET['done'], ENT_QUOTES, 'UTF-8') ?>
    <?php if (!empty($_SESSION['pfx_last'])): ?>
        <div style="font-weight:400;color:#555;margin-top:6px"><?php
            foreach ($_SESSION['pfx_last'] as $x) {
                echo htmlspecialchars($FIELD_LABELS[$x[0]] ?? $x[0], ENT_QUOTES, 'UTF-8') . ': '
                    . htmlspecialchars(pfxFormat($x[1]), ENT_QUOTES, 'UTF-8') . ' ← '
                    . htmlspecialchars(pfxFormat($x[2]), ENT_QUOTES, 'UTF-8') . '<br>';
            }
            unset($_SESSION['pfx_last']);
        ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>
<div style="background:#f4f6f8;border:1px solid #dde3ea;border-radius:10px;padding:10px 14px;font-size:13px;font-weight:800;margin-bottom:12px">
    آگهی نیازمند بازبینی: <?= count($rows) ?> · فیلد بالای آستانه: <?= (int)$totalSusp ?>
</div>
<?php if (!$rows): ?>
<p style="color:#0a7d32;font-weight:800;font-size:14px">هیچ فیلد قیمتی بالای آستانه نیست ✅</p>
<?php endif; ?>
<?php foreach ($rows as $r): $ad = $r['ad']; ?>
<div style="border:1px solid #e2e6eb;border-radius:12px;padding:14px;margin-bottom:14px">
    <div style="font-size:14px;font-weight:800;margin-bottom:2px">
        <?= htmlspecialchars((string)$ad['title'], ENT_QUOTES, 'UTF-8') ?>
        <span style="color:#888;font-weight:400;font-size:12px">(شناسهٔ <?= htmlspecialchars((string)$ad['id'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$ad['transaction_type'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)$ad['status'], ENT_QUOTES, 'UTF-8') ?>)</span>
    </div>
    <form method="post" action="" onsubmit="return pfxConfirm(this);">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="ad_id" value="<?= htmlspecialchars((string)$ad['id'], ENT_QUOTES, 'UTF-8') ?>">
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin-top:8px">
            <tr style="color:#888;font-size:11px"><td>فیلد</td><td>مقدار فعلی</td><td>مقدار درست</td><td>تقسیم سریع</td></tr>
            <?php foreach ($MONEY_FIELDS as $f): $cur = pfxCleanNumber($ad[$f]); ?>
            <tr>
                <td style="padding:6px 4px;font-weight:700;white-space:nowrap"><?= $FIELD_LABELS[$f] ?></td>
                <td style="padding:6px 4px;<?= ($cur !== null && $cur >= $THRESHOLD) ? 'color:#c0261f;font-weight:800' : 'color:#666' ?>"><?= pfxFormat($cur) ?></td>
                <td style="padding:6px 4px">
                    <input type="text" inputmode="numeric" name="prices[<?= $f ?>]" data-field="<?= $f ?>"
                           value="<?= $cur !== null ? number_format((int)round($cur)) : '' ?>"
                           style="width:190px;padding:7px 9px;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit;font-size:13px" dir="ltr">
                </td>
                <td style="padding:6px 4px;white-space:nowrap">
                    <?php if ($cur !== null && $cur > 0): foreach ([10, 100, 1000] as $__d): $__r = $cur / $__d; ?>
                    <button type="button" class="pfx-set" data-v="<?= htmlspecialchars((string)$__r, ENT_QUOTES, 'UTF-8') ?>" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:7px;background:#f0f9ff;cursor:pointer;font-family:inherit;font-size:12px;white-space:nowrap"><?= htmlspecialchars(pfxSmart($__r), ENT_QUOTES, 'UTF-8') ?></button>
                    <?php endforeach; else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
            <button type="submit" style="padding:9px 18px;border:0;border-radius:9px;background:#064e4e;color:#fff;font-family:inherit;font-weight:800;cursor:pointer">ذخیرهٔ این آگهی</button>
            <span style="color:#888;font-size:11px">با ذخیره، display_price هم اصلاح می‌شود</span>
        </div>
    </form>
</div>
<?php endforeach; ?>
<script>
function pfxGroup(n) { return isFinite(n) ? Math.round(n).toLocaleString('en-US') : ''; }
function pfxParse(txt) {
    var s = String(txt || '').replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
                             .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
                             .replace(/[٬،,\s]/g, '');
    return s === '' ? null : parseInt(s, 10);
}
document.querySelectorAll('.pfx-set').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = btn.closest('tr').querySelector('input[type=text]');
        var v = parseFloat(btn.getAttribute('data-v'));
        if (!isFinite(v) || v <= 0) return;
        input.value = Math.round(v).toLocaleString('en-US');
    });
});
function pfxConfirm(form) {
    var changes = [];
    form.querySelectorAll('input[type=text]').forEach(function (inp) {
        var init = inp.getAttribute('value');
        if ((inp.value || '') !== (init || '')) {
            changes.push((inp.getAttribute('data-field')) + ': ' + (pfxGroup(pfxParse(init)) || '—') + ' ← ' + (pfxGroup(pfxParse(inp.value)) || '—'));
        }
    });
    if (!changes.length) { alert('تغییری داده نشده.'); return false; }
    return confirm('ذخیره شود؟\n' + changes.join('\n'));
}
</script>
</div></body></html>
