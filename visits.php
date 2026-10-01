<?php
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
}
$_mkPage = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')));
$_mkAllow = ['login.php','logout.php','auth.php','auth-telegram.php','auth-bale.php','auth-eitaa.php','request-otp.php','verify-otp.php','admin-login.php','admin-logout.php','telegram.php','bale.php','eitaa.php','telegram-relay.php','identity-sync.php','bale-ok.php','r.php'];
if (
    $_mkPage !== ''
    && !in_array($_mkPage, $_mkAllow, true)
    && strncmp($_mkPage, 'admin-', 6) !== 0
    && empty($_SESSION['user_id'])
    && empty($_SESSION['reg_telegram_id'])
    && empty($_SESSION['reg_bale_id'])
    && empty($_SESSION['reg_eitaa_id'])
    && empty($_SESSION['user_phone'])
    && empty($_SESSION['is_admin'])
) {
    $here = (string) ($_SERVER['REQUEST_URI'] ?? $_mkPage);
    $here = preg_replace('#^/+#', '', $here) ?? $_mkPage;
    if ($here === '' || strpos($here, 'login.php') === 0) {
        $here = 'home.php';
    }
    if (!headers_sent()) {
        header('Location: login.php?redirect=' . rawurlencode($here), true, 302);
    }
    exit;
}
unset($_mkPage, $_mkAllow);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/jalali-lib.php';
require_once __DIR__ . '/visit-request-lib.php';

$identity = function_exists('melkinoCurrentIdentity') ? melkinoCurrentIdentity() : ['user_id' => null, 'telegram_id' => '', 'phone' => '', 'user' => []];
$logged = !empty($identity['user_id'])
    || trim((string) ($identity['telegram_id'] ?? '')) !== ''
    || trim((string) ($identity['phone'] ?? '')) !== '';

$items = [];
$statuses = function_exists('melkinoVisitStatuses')
    ? melkinoVisitStatuses()
    : ['new' => 'جدید'];
if ($logged && isset($pdo) && $pdo instanceof PDO) {
    try {
        if (function_exists('melkinoEnsureVisitRequestSchema')) {
            melkinoEnsureVisitRequestSchema();
        }
    } catch (Throwable $eSchema) {
    }
    if (!function_exists('melkinoVisitOwnerWhere')) {
        $where = '';
        $params = [];
    } else {
        [$where, $params] = melkinoVisitOwnerWhere($identity, 'vr');
    }
    if ($where !== '') {
        try {
            $st = $pdo->prepare('SELECT vr.* FROM visit_requests vr WHERE ' . $where . ' ORDER BY vr.created_at DESC, vr.id DESC');
            $st->execute($params);
            $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            try {
                [$where2, $params2] = melkinoVisitOwnerWhere($identity);
                $st = $pdo->prepare('SELECT * FROM visit_requests WHERE ' . $where2 . ' ORDER BY created_at DESC, id DESC');
                $st->execute($params2);
                $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e2) {
                $items = [];
            }
        }
        $adIds = [];
        foreach ($items as $row) {
            $adIds[] = (string) ($row['ad_id'] ?? '');
        }
        if ($adIds && function_exists('melkinoVisitLoadAdsMap')) {
            $adsMap = melkinoVisitLoadAdsMap($pdo, $adIds);
            $amenMap = function_exists('melkinoVisitLoadAmenitiesMap')
                ? melkinoVisitLoadAmenitiesMap($pdo, $adIds)
                : [];
            foreach ($items as $i => $row) {
                $aid = (string) ($row['ad_id'] ?? '');
                $adRow = $adsMap[$aid] ?? ((ctype_digit($aid) && isset($adsMap[(string) (int) $aid])) ? $adsMap[(string) (int) $aid] : null);
                if (is_array($adRow)) {
                    $items[$i]['_ad'] = $adRow;
                }
                $items[$i]['_amenities'] = $amenMap[$aid] ?? $amenMap[(string) (int) $aid] ?? [];
            }
        }
    }
}

$fa = static function ($v): string {
    $v = trim((string) $v);
    if ($v === '') {
        return '';
    }
    return function_exists('melkinoFaDigits') ? melkinoFaDigits($v) : $v;
};

if (!function_exists('melkinoVisitsPageDigits')) {
    function melkinoVisitsPageDigits(string $v): string
    {
        return strtr(trim($v), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}

if (!function_exists('melkinoVisitsPageFa')) {
    /** ارقام فارسی با strtr آرایه‌ای — strtr سه‌آرگومانی UTF-8 را خراب می‌کند. */
    function melkinoVisitsPageFa(string $v): string
    {
        return strtr($v, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}

if (!function_exists('melkinoVisitsPageDate')) {
    /** تاریخ کارت — مستقل از باگ‌های قدیمی WhenParts */
    function melkinoVisitsPageDate(array $row): array
    {
        $slots = ['morning' => 'صبح', 'evening' => 'عصر'];
        $slotKey = trim((string) ($row['time_slot'] ?? ''));
        $slot = $slots[$slotKey] ?? ($slotKey !== '' ? $slotKey : 'صبح');
        $weekday = trim((string) ($row['weekday'] ?? ''));
        $rawG = $row['preferred_date'] ?? '';
        if ($rawG instanceof DateTimeInterface) {
            $rawG = $rawG->format('Y-m-d');
        }
        $g = melkinoVisitsPageDigits((string) $rawG);
        $fa = melkinoVisitsPageDigits((string) ($row['preferred_date_fa'] ?? ''));
        $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        $jy = 0;
        $jm = 0;
        $jd = 0;
        $apply = static function (string $s) use (&$jy, &$jm, &$jd): void {
            if ($jy > 0 || !preg_match('/(\d{4})[\/\.\-](\d{1,2})[\/\.\-](\d{1,2})/', $s, $m)) {
                return;
            }
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
                return;
            }
            if ($y >= 1700 && $y <= 2500 && function_exists('melkinoGregorianToJalali')) {
                [$jy, $jm, $jd] = melkinoGregorianToJalali($y, $mo, $d);
                return;
            }
            if ($y >= 1300 && $y <= 1599) {
                $jy = $y;
                $jm = $mo;
                $jd = $d;
            }
        };
        $apply($g);
        $apply($fa);
        if ($jy <= 0 && $g !== '' && strpos($g, '0000') !== 0) {
            try {
                $dt = new DateTimeImmutable($g);
                $yy = (int) $dt->format('Y');
                if ($yy >= 1700 && function_exists('melkinoGregorianToJalali')) {
                    [$jy, $jm, $jd] = melkinoGregorianToJalali($yy, (int) $dt->format('n'), (int) $dt->format('j'));
                }
            } catch (Throwable $e) {
            }
        }
        if ($jy <= 0 && $fa !== '') {
            foreach ($months as $i => $name) {
                if (preg_match('/(\d{1,2})\s*' . preg_quote($name, '/') . '(?:\s+(\d{4}))?/u', $fa, $mm)) {
                    $jd = (int) $mm[1];
                    $jm = $i + 1;
                    $jy = isset($mm[2]) && $mm[2] !== '' ? (int) $mm[2] : (function_exists('melkinoJalaliCurrentYear') ? (int) melkinoJalaliCurrentYear() : 1405);
                    break;
                }
            }
        }
        if ($jy <= 0 && $weekday !== '') {
            try {
                $tz = new DateTimeZone('Asia/Tehran');
                $base = new DateTimeImmutable('now', $tz);
                $rawC = trim((string) ($row['created_at'] ?? ''));
                if ($rawC !== '' && strpos($rawC, '0000') !== 0) {
                    try {
                        $base = new DateTimeImmutable($rawC, $tz);
                    } catch (Throwable $e) {
                    }
                }
                $names = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
                $want = str_replace([' ', '‌', 'ي', 'ك'], ['', '', 'ی', 'ک'], $weekday);
                for ($i = 0; $i <= 14; $i++) {
                    $cand = $base->modify('+' . $i . ' day');
                    $have = str_replace([' ', '‌', 'ي', 'ك'], ['', '', 'ی', 'ک'], $names[(int) $cand->format('w')] ?? '');
                    if ($have === $want && function_exists('melkinoGregorianToJalali')) {
                        [$jy, $jm, $jd] = melkinoGregorianToJalali(
                            (int) $cand->format('Y'),
                            (int) $cand->format('n'),
                            (int) $cand->format('j')
                        );
                        break;
                    }
                }
            } catch (Throwable $e) {
            }
        }
        $dateLabel = '';
        if ($jd >= 1 && $jm >= 1 && $jm <= 12) {
            $dayFa = melkinoVisitsPageFa((string) $jd);
            $yearFa = $jy > 0 ? melkinoVisitsPageFa((string) $jy) : '';
            $dateLabel = trim($dayFa . ' ' . ($months[$jm - 1] ?? '') . ' ' . $yearFa);
        }
        if ($dateLabel === '' && $fa !== '') {
            $dateLabel = melkinoVisitsPageFa($fa);
        }
        if ($dateLabel === '') {
            $dateLabel = '—';
        }
        return [
            'weekday' => $weekday !== '' ? $weekday : '—',
            'date' => $dateLabel,
            'slot' => $slot,
        ];
    }
}

if (!function_exists('melkinoVisitsPageFacts')) {
    /** مشخصات کارت — خالی‌ها حذف؛ متراژ از عنوان «۷۰ متری»؛ کد از ad_id */
    function melkinoVisitsPageFacts(array $it, array $ad, string $adTitle, string $adId): array
    {
        $clean = static function ($v): string {
            $v = trim((string) $v);
            if ($v !== '' && function_exists('mb_check_encoding') && !mb_check_encoding($v, 'UTF-8')) {
                return '';
            }
            $v = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], '', $v);
            $v = trim($v);
            if ($v === '' || $v === '—' || $v === '-' || preg_match('/^(متر|تومان|اتاق|سال)$/u', $v)) {
                return '';
            }
            return $v;
        };
        $faN = static function ($v): string {
            $v = trim((string) $v);
            return function_exists('melkinoVisitsPageFa') ? melkinoVisitsPageFa($v) : $v;
        };
        $raw = [];
        $out = [];
        $have = [];
        foreach ($raw as $f) {
            $lab = trim((string) ($f[0] ?? ''));
            $val = $clean($f[1] ?? '');
            if ($lab === '' || $val === '') {
                continue;
            }
            $out[] = [$lab, $val];
            $have[$lab] = true;
        }
        $live = is_array($it['_ad'] ?? null) ? $it['_ad'] : [];
        $snap = [];
        if (!empty($it['ad_snapshot'])) {
            $d = json_decode((string) $it['ad_snapshot'], true);
            if (is_array($d)) {
                $snap = $d;
            }
        }
        $details = [];
        $pd = $live['property_details'] ?? ($ad['property_details'] ?? null);
        if (is_string($pd) && $pd !== '') {
            $dec = json_decode($pd, true);
            $details = is_array($dec) ? $dec : [];
        } elseif (is_array($pd)) {
            $details = $pd;
        }
        $pick = static function (array $keys) use ($live, $ad, $snap, $details): string {
            foreach ($keys as $k) {
                foreach ([$details, $live, $ad, $snap] as $src) {
                    if (!is_array($src) || !isset($src[$k])) {
                        continue;
                    }
                    $v = $src[$k];
                    if (is_array($v)) {
                        $v = $v['value'] ?? $v['val'] ?? $v['text'] ?? '';
                    }
                    $v = trim((string) $v);
                    if ($v !== '' && $v !== '0' && $v !== '۰' && $v !== '0.0') {
                        return $v;
                    }
                }
            }
            return '';
        };
        $titleN = melkinoVisitsPageDigits($adTitle);
        if (empty($have['متراژ'])) {
            $area = $pick(['area', 'area_apt', 'built_area', 'land_area', 'office_area']);
            if ($area === '' && preg_match('/(\d+(?:\.\d+)?)\s*متر/u', $titleN, $tm)) {
                $area = $tm[1];
            }
            if ($area !== '') {
                $out[] = ['متراژ', $faN($area) . (preg_match('/متر/u', $area) ? '' : ' متر')];
                $have['متراژ'] = true;
            }
        }
        if (empty($have['خواب'])) {
            $rooms = $pick(['rooms', 'rooms_apt', 'rooms_villa', 'office_rooms']);
            if ($rooms === '' && preg_match('/(\d+)\s*(?:خواب|اتاق)/u', $titleN, $tr)) {
                $rooms = $tr[1];
            }
            if ($rooms !== '') {
                $out[] = ['خواب', $faN($rooms)];
                $have['خواب'] = true;
            }
        }
        if (empty($have['طبقه'])) {
            $floor = $pick(['floor', 'floor_apt', 'office_floor']);
            if ($floor !== '') {
                $out[] = ['طبقه', $faN($floor)];
                $have['طبقه'] = true;
            }
        }
        if (empty($have['سال ساخت'])) {
            $year = $pick(['year', 'year_apt', 'year_villa', 'office_year', 'build_year']);
            if ($year !== '') {
                $out[] = ['سال ساخت', $faN($year)];
                $have['سال ساخت'] = true;
                if (empty($have['سن بنا']) && function_exists('melkinoBuildingAge')) {
                    $ageN = melkinoBuildingAge($year);
                    if ($ageN !== null) {
                        $out[] = ['سن بنا', $faN((string) $ageN)];
                        $have['سن بنا'] = true;
                    }
                }
            }
        }
        $money = static function ($raw) use ($faN): string {
            $s = melkinoVisitsPageDigits(trim((string) $raw));
            $s = str_replace([',', '٬', '،', ' ', 'تومان', 'ریال'], '', $s);
            if ($s === '' || !is_numeric($s)) {
                return '';
            }
            $n = (float) $s;
            if ($n <= 0) {
                return '';
            }
            return $faN(number_format($n, 0, '.', ',')) . ' تومان';
        };
        $tx = $pick(['transaction_type', 'transactionType']);
        $txN = str_replace(['‌', ' ', 'ي', 'ك'], ['', '', 'ی', 'ک'], $tx);
        $isRent = $txN !== '' && mb_strpos($txN, 'فروش') === false
            && (mb_strpos($txN, 'رهن') !== false || mb_strpos($txN, 'اجاره') !== false);
        if ($isRent) {
            if (empty($have['رهن'])) {
                $dep = $money($pick(['deposit']));
                if ($dep !== '') {
                    $out[] = ['رهن', $dep];
                    $have['رهن'] = true;
                }
            }
            if (empty($have['اجاره'])) {
                $rent = $money($pick(['rent_monthly', 'rent']));
                if ($rent !== '') {
                    $out[] = ['اجاره', $rent];
                    $have['اجاره'] = true;
                }
            }
        } elseif (empty($have['قیمت'])) {
            $price = $money($pick(['price_sell', 'total_price', 'display_price', 'price']));
            if ($price !== '') {
                $out[] = ['قیمت', $price];
                $have['قیمت'] = true;
            }
        }
        if (empty($have['کد آگهی']) && $adId !== '' && $adId !== '0') {
            $out[] = ['کد آگهی', $faN($adId)];
        }
        return $out;
    }
}

require_once __DIR__ . '/header.php';
?>
<style>
.vr-page {
    width: 100%;
    max-width: 640px;
    margin: 0 auto;
    box-sizing: border-box;
    padding-top: 10px;
    padding-bottom: calc(var(--bottom-nav-height, 75px) + 28px + env(safe-area-inset-bottom, 0px));
    padding-inline: max(14px, env(safe-area-inset-left, 0px), env(safe-area-inset-right, 0px));
}
.vr-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}
.vr-head h1 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -.02em;
}
.vr-back {
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
.vr-card {
    position: relative;
    overflow: hidden;
    background: var(--surface);
    border: 1px solid rgba(212, 175, 55, .28);
    border-radius: 20px;
    padding: 16px 16px 14px;
    margin-bottom: 12px;
    box-shadow:
        0 12px 28px rgba(0,0,0,.10),
        inset 0 1px 0 rgba(240, 216, 120, .18);
}
[data-theme="dark"] .vr-card {
    background: linear-gradient(165deg, rgba(212,175,55,.11) 0%, rgba(18,24,22,.98) 38%);
    border-color: rgba(212, 175, 55, .32);
}
.vr-card::before {
    content: "";
    position: absolute;
    top: 10px;
    bottom: 10px;
    inset-inline-start: 0;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: linear-gradient(180deg, #f0d878, #c89d32);
}
.vr-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
}
.vr-badge {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 999px;
    background: linear-gradient(135deg, #c89d32, #f0d878);
    color: #3a2a00;
    box-shadow: 0 4px 10px rgba(200,157,50,.28);
}
.vr-when-row {
    display: grid;
    grid-template-columns: 1fr 1.4fr 1fr;
    gap: 8px;
    margin: 0 0 12px;
}
.vr-when-item {
    background: rgba(212, 175, 55, .10);
    border: 1px solid rgba(212, 175, 55, .28);
    border-radius: 12px;
    padding: 8px 8px 7px;
    text-align: center;
    min-width: 0;
}
.vr-when-item b {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 3px;
}
.vr-when-item span {
    display: block;
    font-size: 12px;
    font-weight: 800;
    color: var(--gold, #E5B842);
    line-height: 1.5;
    white-space: normal;
    min-height: 1.5em;
    overflow: visible;
}
.vr-title {
    font-weight: 800;
    font-size: 15px;
    line-height: 1.55;
    margin: 0 0 10px;
    color: var(--text-primary);
}
.vr-facts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 10px;
    margin: 0 0 12px;
}
.vr-fact {
    background: rgba(0,0,0,.04);
    border: 1px solid rgba(212, 175, 55, .14);
    border-radius: 12px;
    padding: 8px 10px;
    min-width: 0;
}
[data-theme="dark"] .vr-fact {
    background: rgba(255,255,255,.04);
}
.vr-fact b {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 2px;
}
.vr-fact span {
    display: block;
    font-size: 12px;
    font-weight: 800;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.vr-alt {
    font-size: 12px;
    line-height: 1.8;
    color: var(--text-secondary);
    margin: 0 0 12px;
    padding: 8px 10px;
    border-radius: 12px;
    border: 1px dashed rgba(212, 175, 55, .32);
}
.vr-actions {
    display: flex;
    gap: 8px;
    align-items: stretch;
}
.vr-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    border-radius: 12px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    border: 0;
    -webkit-tap-highlight-color: transparent;
}
.vr-btn-gold {
    background: linear-gradient(135deg, #c89d32, #f0d878);
    color: #3a2a00;
}
.vr-btn-ghost {
    background: transparent;
    color: #e07070;
    border: 1px solid rgba(224,112,112,.35);
}
.vr-empty {
    text-align: center;
    padding: 48px 12px;
    color: var(--text-secondary);
    line-height: 1.9;
}
.vr-code {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .04em;
    color: var(--gold, #E5B842);
    direction: ltr;
    unicode-bidi: isolate;
}
.vr-lock-note {
    margin-top: 10px;
    font-size: 11px;
    line-height: 1.9;
    color: var(--text-secondary);
    background: rgba(212,175,55,.08);
    border: 1px dashed rgba(212,175,55,.28);
    border-radius: 12px;
    padding: 8px 10px;
}
.vr-acc {
    margin: 0 0 12px;
    border: 1px solid rgba(212,175,55,.22);
    border-radius: 14px;
    background: rgba(0,0,0,.03);
    overflow: hidden;
}
[data-theme="dark"] .vr-acc {
    background: rgba(255,255,255,.03);
}
.vr-acc summary {
    cursor: pointer;
    list-style: none;
    padding: 10px 12px;
    font-size: 13px;
    font-weight: 800;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.vr-acc summary::-webkit-details-marker { display: none; }
.vr-acc summary::after {
    content: "⌄";
    font-size: 14px;
    color: var(--gold, #E5B842);
    transition: transform .15s ease;
}
.vr-acc[open] summary::after { transform: rotate(180deg); }
.vr-acc-body { padding: 0 12px 12px; }
.vr-amen-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.vr-amen {
    font-size: 11px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(212,175,55,.14);
    border: 1px solid rgba(212,175,55,.28);
    color: var(--text-primary);
}
.vr-acc .vr-facts { margin: 0; }
</style>
<div class="main-content" style="padding:0;">
<div class="vr-page">
    <div class="vr-head">
        <h1>درخواست‌های بازدید</h1>
        <a class="vr-back" href="profile.php">بازگشت به پروفایل</a>
    </div>
    <?php if (!$logged): ?>
        <div class="vr-empty">برای دیدن درخواست‌های بازدید وارد حساب شوید.<br>
            <a href="login.php" style="color:var(--gold,#c9a65f);font-weight:800;">ورود</a>
        </div>
    <?php elseif (!$items): ?>
        <div class="vr-empty">هنوز درخواست بازدیدی ثبت نکرده‌اید.</div>
    <?php else: ?>
        <?php foreach ($items as $it): ?>
            <?php
                $stKey = 'new';
                $stLabel = 'جدید';
                $ad = [];
                $adTitle = 'آگهی';
                $adId = '';
                $whenParts = ['weekday' => '—', 'date' => '—', 'slot' => '—'];
                $dateShow = '—';
                $alt = '';
                $id = (int) ($it['id'] ?? 0);
                $track = 'VR-' . str_pad((string) max(0, $id), 5, '0', STR_PAD_LEFT);
                $canDelete = true;
                $facts = [];
                $amenities = [];
                $extra = [];
                try {
                    $stKey = function_exists('melkinoVisitNormalizeStatus')
                        ? melkinoVisitNormalizeStatus((string) ($it['status'] ?? 'new'))
                        : trim((string) ($it['status'] ?? 'new'));
                    if ($stKey === '') {
                        $stKey = 'new';
                    }
                    $stLabel = $statuses[$stKey] ?? $stKey;
                    $ad = function_exists('melkinoVisitResolveAdInfo') ? melkinoVisitResolveAdInfo($it) : [];
                    $adTitle = trim((string) ($ad['title'] ?? ($it['ad_title'] ?? '')));
                    if ($adTitle === '') {
                        $adTitle = 'آگهی';
                    }
                    $adId = trim((string) ($it['ad_id'] ?? ($ad['id'] ?? '')));
                    $whenParts = function_exists('melkinoVisitsPageDate')
                        ? melkinoVisitsPageDate($it)
                        : ['weekday' => '—', 'date' => '—', 'slot' => '—'];
                    $dateShow = trim((string) ($whenParts['date'] ?? ''));
                    if ($dateShow === '') {
                        $dateShow = '—';
                    }
                    $alt = trim((string) ($it['alternative_datetime'] ?? ''));
                    if (isset($pdo) && $pdo instanceof PDO && function_exists('melkinoVisitEnsureTracking')) {
                        melkinoVisitEnsureTracking($pdo, $it);
                    }
                    $track = trim((string) ($it['tracking_code'] ?? ''));
                    if ($track === '' && function_exists('melkinoVisitTrackingCode')) {
                        $track = melkinoVisitTrackingCode($id);
                    }
                    if ($track === '') {
                        $track = 'VR-' . str_pad((string) max(0, $id), 5, '0', STR_PAD_LEFT);
                    }
                    $canDelete = ($stKey === 'new');
                    $facts = function_exists('melkinoVisitsPageFacts')
                        ? melkinoVisitsPageFacts($it, is_array($ad) ? $ad : [], $adTitle, $adId)
                        : [];
                    $amenities = function_exists('melkinoVisitAmenityList')
                        ? melkinoVisitAmenityList($it, is_array($it['_amenities'] ?? null) ? $it['_amenities'] : [])
                        : [];
                    $extra = function_exists('melkinoVisitExtraDetails') ? melkinoVisitExtraDetails($it) : [];
                } catch (Throwable $eCard) {
                }
            ?>
            <article class="vr-card" id="vrCard<?= $id ?>">
                <div class="vr-card-top">
                    <span class="vr-badge"><?= htmlspecialchars($stLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="vr-code"><?= htmlspecialchars($track, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <h2 class="vr-title"><?= htmlspecialchars($adTitle, ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="vr-when-row">
                    <div class="vr-when-item">
                        <b>روز</b>
                        <span><?= htmlspecialchars($whenParts['weekday'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="vr-when-item">
                        <b>تاریخ</b>
                        <span><?= htmlspecialchars($dateShow, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="vr-when-item">
                        <b>زمان</b>
                        <span><?= htmlspecialchars($whenParts['slot'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <?php if ($facts): ?>
                <div class="vr-facts">
                    <?php foreach ($facts as $fact): ?>
                        <?php
                            $fv = trim((string) ($fact[1] ?? ''));
                            if ($fv === '' || $fv === '—' || $fv === '-' || preg_match('/^(متر|تومان|اتاق|سال)$/u', $fv)) {
                                continue;
                            }
                        ?>
                        <div class="vr-fact">
                            <b><?= htmlspecialchars($fact[0], ENT_QUOTES, 'UTF-8') ?></b>
                            <span><?= htmlspecialchars($fv, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <details class="vr-acc">
                    <summary>جزئیات</summary>
                    <div class="vr-acc-body">
                        <?php if ($amenities): ?>
                            <div class="vr-amen-wrap">
                                <?php foreach ($amenities as $am): ?>
                                    <span class="vr-amen"><?= htmlspecialchars((string) $am, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($extra): ?>
                            <div class="vr-facts">
                                <?php foreach ($extra as $fact): ?>
                                    <?php
                                        $ev = trim((string) ($fact[1] ?? ''));
                                        if ($ev === '' || $ev === '—') {
                                            continue;
                                        }
                                    ?>
                                    <div class="vr-fact">
                                        <b><?= htmlspecialchars($fact[0], ENT_QUOTES, 'UTF-8') ?></b>
                                        <span><?= htmlspecialchars($ev, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!$amenities && !$extra): ?>
                            <div style="font-size:12px;color:var(--text-secondary);padding-top:2px;">امکاناتی برای این ملک ثبت نشده است.</div>
                        <?php endif; ?>
                    </div>
                </details>
                <?php if ($alt !== ''): ?>
                    <div class="vr-alt">زمان جایگزین: <?= nl2br(htmlspecialchars($alt, ENT_QUOTES, 'UTF-8')) ?></div>
                <?php endif; ?>
                <div class="vr-actions">
                    <?php if ($adId !== ''): ?>
                        <a class="vr-btn vr-btn-gold" href="property-details.php?id=<?= htmlspecialchars(urlencode($adId), ENT_QUOTES, 'UTF-8') ?>">مشاهده آگهی</a>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                        <button type="button" class="vr-btn vr-btn-ghost" onclick="melkinoDeleteVisit(<?= $id ?>)">حذف درخواست</button>
                    <?php endif; ?>
                </div>
                <?php if (!$canDelete): ?>
                    <div class="vr-lock-note">به علت انجام پیگیری امکان حذف سیستمی نمی باشد جهت حذف درخواست بازدید با ملکینو تماس بگیرید.</div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
<script>
function melkinoDeleteVisit(id) {
    if (!id) return;
    if (!confirm('این درخواست بازدید حذف شود؟')) return;
    var fd = new FormData();
    fd.append('id', String(id));
    if (window.MELKINO_CSRF) fd.append('csrf_token', window.MELKINO_CSRF);
    var headers = {};
    if (window.MELKINO_CSRF) headers['X-CSRF-Token'] = window.MELKINO_CSRF;
    fetch('visit-request-api.php?action=delete', { method: 'POST', body: fd, credentials: 'same-origin', headers: headers })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                var el = document.getElementById('vrCard' + id);
                if (el) el.remove();
                if (!document.querySelector('.vr-card')) location.reload();
            } else {
                alert((data && data.message) ? data.message : 'حذف نشد.');
            }
        })
        .catch(function () { alert('حذف نشد.'); });
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
