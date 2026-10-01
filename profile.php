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

// نکته: config.php باید پیش از هر خروجی لود شود تا ریدایرکت‌های آن
// (اجبار HTTPS، حالت تعمیرات) و هندلر مرکزی خطا درست کار کنند.
// قبلاً header.php اول لود می‌شد (که خروجی HTML می‌دهد) و بعد config.php.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_helpers.php';

$userName  = trim((string)($_SESSION['user_name'] ?? '')) ?: 'کاربر ملکینو';
$userPhone = trim((string)($_SESSION['user_phone'] ?? ''));

/*
|--------------------------------------------------------------------------
| آمار فعلی
|--------------------------------------------------------------------------
| در این نسخه فعلاً از داده‌های موجود استفاده می‌کنیم.
| بعداً می‌توانیم این اعداد را مستقیماً از ads.json / requests.json
| یا دیتابیس محاسبه کنیم.
|--------------------------------------------------------------------------
*/

$myPropertiesCount = 0;
$myRequestsCount   = 0;
$myVisitsCount     = 0;
$favoriteCount     = 0;
$notificationCount = 0;
$matchCount = 0;

require_once __DIR__ . '/auth.php';
$identity = melkinoCurrentIdentity();

// تعداد ملک‌های داخل مقایسه (برای نشانِ کارت «مقایسه ملک‌ها»)
$compareProfileCount = 0;
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        require_once __DIR__ . '/compare-lib.php';
        melkinoEnsureCompareTables($pdo);
        $cmpIdentity = melkinoCurrentIdentity();
        melkinoCompareMergeGuest($pdo, $cmpIdentity);
        [$cmpWhere, $cmpParams, , , ] = melkinoCompareOwner($cmpIdentity, 'ci');
        if ($cmpWhere !== '') {
            $compareProfileCount = melkinoCompareCount($pdo, $cmpWhere, $cmpParams);
        }
    }
} catch (Throwable $e) {
    $compareProfileCount = 0;
}
if (!empty($identity['user']['name'])) { $userName=trim((string)$identity['user']['name']); $_SESSION['user_name']=$userName; }
if ($userPhone==='' && !empty($identity['phone'])) { $userPhone=trim((string)$identity['phone']); $_SESSION['user_phone']=$userPhone; }

// راند ۳۰: وضعیت شمارهٔ تماس + فیلدهای هویتی تلگرام — همه سمت سرور
// از دیتابیس خوانده می‌شوند (هرگز از localStorage/فرانت‌اند).
$__u30 = is_array($identity['user'] ?? null) ? $identity['user'] : [];
// اگر ادمین شماره را حذف کرده باشد، سشن باز نباید شمارهٔ قدیمی را نشان دهد
if (!empty($__u30) && array_key_exists('phone', $__u30)) {
    $__dbPhone30 = trim((string)($__u30['phone'] ?? ''));
    if ($__dbPhone30 !== $userPhone) {
        $userPhone = $__dbPhone30;
        if ($__dbPhone30 !== '') {
            $_SESSION['user_phone'] = $__dbPhone30;
        } else {
            unset($_SESSION['user_phone']);
        }
    }
}
$phoneVerified30 = ($userPhone !== '') && (!empty($__u30['phone_verified']) || !empty($__u30['phone_locked']));
$phoneLocked30   = ($userPhone !== '') && !empty($__u30['phone_locked']);
// راند ۳۱: نام/نام‌خانوادگیِ ثبت‌شده توسط خود کاربر (فارسی) و قفل‌شده
$nameLocked30    = !empty($__u30['name_locked']);
$isTgUser30      = !empty($identity['telegram_id']) || !empty($__u30['bale_id'] ?? '');
$tgUsername30    = trim((string)($__u30['telegram_username'] ?? ''));
if ($tgUsername30 === '') { $tgUsername30 = trim((string)($__u30['username'] ?? '')); }
$tgPhoto30       = trim((string)($__u30['photo_url'] ?? ''));

// قبلاً وقتی هیچ هویتی (نه شماره، نه تلگرام) در دسترس نبود — مثلاً
// درست بعد از خروج از حساب — پروفایل بی‌صدا خالی نمایش داده می‌شد.
// حالا در این حالت یک صفحه‌ی «ورود به حساب» واقعی نشان داده می‌شود.
if ($userPhone === '' && empty($identity['telegram_id']) && empty($identity['user_id'])) {
    require_once __DIR__ . '/header.php';
    ?>
    <div style="max-width:420px;margin:60px auto;padding:36px 28px;background:var(--bg-card,#16211F);border:1px solid var(--border,#223330);border-radius:20px;text-align:center;">
        <div style="font-size:40px;margin-bottom:10px;">👋</div>
        <h2 style="margin:0 0 8px;">به ملکینو خوش اومدی</h2>
        <p style="color:var(--text-secondary,#A8B1AE);line-height:1.9;margin:0 0 24px;">
            برای دیدن ملک‌ها و درخواست‌های ثبت‌شده‌ی خودت، وارد حساب کاربری‌ات شو.
        </p>
        <a href="login.php" style="display:block;padding:14px;border-radius:12px;background:linear-gradient(135deg,var(--primary,#0E7C6E),#0B5D5B);color:#fff;text-decoration:none;font-weight:700;">
            🔑 ورود به حساب کاربری
        </a>
    </div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}
if ($pdo instanceof PDO) {
    if ($userPhone !== '') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ads WHERE phone = ?");
        $stmt->execute([$userPhone]);
        $myPropertiesCount = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM property_requests WHERE phone = ?");
        $stmt->execute([$userPhone]);
        $myRequestsCount = (int)$stmt->fetchColumn();
    } elseif ($identity['user_id'] || $identity['telegram_id'] !== '') {
        $cond = $identity['user_id'] ? 'user_id = ?' : 'telegram_id = ?';
        $val = $identity['user_id'] ?: $identity['telegram_id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ads WHERE owner_user_id = ?");
        if ($identity['user_id']) { $stmt->execute([$identity['user_id']]); $myPropertiesCount = (int)$stmt->fetchColumn(); }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM property_requests WHERE $cond"); $stmt->execute([$val]); $myRequestsCount = (int)$stmt->fetchColumn();
    }
    try {
        require_once __DIR__ . '/visit-request-lib.php';
        melkinoEnsureVisitRequestSchema();
        [$vrWhere, $vrParams] = melkinoVisitOwnerWhere($identity);
        if ($vrWhere !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM visit_requests WHERE $vrWhere");
            $stmt->execute($vrParams);
            $myVisitsCount = (int)$stmt->fetchColumn();
        }
    } catch (Throwable $e) { $myVisitsCount = 0; }
    $favParts=[];$favParams=[];$notifParts=[];$notifParams=[];
    if($identity['user_id']){$favParts[]='user_id=?';$favParams[]=$identity['user_id'];$notifParts[]='user_id=?';$notifParams[]=$identity['user_id'];}
    if($identity['telegram_id']!==''){$favParts[]='telegram_id=?';$favParams[]=$identity['telegram_id'];$notifParts[]='telegram_id=?';$notifParams[]=$identity['telegram_id'];}
    if($favParts){$stmt=$pdo->prepare('SELECT COUNT(*) FROM favorites WHERE '.implode(' OR ',$favParts));$stmt->execute($favParams);$favoriteCount=(int)$stmt->fetchColumn();}
    if($notifParts){$stmt=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE is_read=0 AND ('.implode(' OR ',$notifParts).')');$stmt->execute($notifParams);$notificationCount=(int)$stmt->fetchColumn();}

    // Number of currently available matches for this user's requests.
    if ($userPhone !== '' || $identity['user_id'] || $identity['telegram_id'] !== '') {
        $parts=[]; $params=[];
        if ($identity['user_id']) { $parts[]='r.user_id=?'; $params[]=$identity['user_id']; }
        if ($identity['telegram_id'] !== '') { $parts[]='r.telegram_id=?'; $params[]=$identity['telegram_id']; }
        if ($userPhone !== '') { $parts[]="REPLACE(REPLACE(REPLACE(r.phone,' ',''),'-',''),'+','')=?"; $params[]=$userPhone; }
        if ($parts) {
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM request_matches rm JOIN property_requests r ON r.id=rm.request_id JOIN ads a ON a.id=rm.ad_id AND a.status='published' WHERE (".implode(' OR ',$parts).")");
            $stmt->execute($params); $matchCount=(int)$stmt->fetchColumn();
        }
    }
}

/*
|--------------------------------------------------------------------------
| علاقه‌مندی‌ها
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/header.php';
?>

<style>

    /* =========================================================
       MELKINO PROFILE
       ========================================================= */

    .main-content {
        flex: 1;
        overflow-y: auto;
        padding:
            0
            var(--space-3)
            140px
            var(--space-3);

        background:
            radial-gradient(
                circle at top right,
                rgba(201, 166, 95, 0.07),
                transparent 32%
            ),
            var(--bg);
    }


    .profile-page {
        width: 100%;
        max-width: 980px;
        margin: 0 auto;
    }


    /* =========================================================
       PROFILE HERO
       ========================================================= */

    .profile-hero {

        position: relative;
        overflow: hidden;

        margin-bottom: 16px;
        padding: 22px;

        border-radius: 24px;

        background:
            linear-gradient(
                135deg,
                #174e48,
                #0b3531
            );

        border:
            1px solid
            rgba(255,255,255,.08);

        box-shadow:
            0 15px 35px
            rgba(8,45,41,.18);

        color: #fff;
    }


    .profile-hero::before {

        content: "";

        position: absolute;

        width: 230px;
        height: 230px;

        left: -120px;
        top: -140px;

        border-radius: 50%;

        background:
            rgba(215,180,96,.08);

        pointer-events:
            none;
    }


    .profile-hero::after {

        content: "";

        position: absolute;

        width: 170px;
        height: 170px;

        right: -80px;
        bottom: -95px;

        border-radius: 50%;

        background:
            rgba(255,255,255,.035);

        pointer-events:
            none;
    }


    .profile-hero-content {

        position: relative;
        z-index: 2;

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 18px;
    }


    .profile-identity {

        display: flex;
        flex-direction: column;

        min-width: 0;
    }


    .profile-badge {

        display: inline-flex;
        align-items: center;

        width: fit-content;

        gap: 7px;

        padding:
            6px
            10px;

        margin-bottom: 10px;

        border-radius: 999px;

        background:
            rgba(215,180,96,.12);

        border:
            1px solid
            rgba(215,180,96,.18);

        color:
            #e8cd93;

        font-size: 10px;
        font-weight: 800;
    }


    .profile-badge svg {

        width: 14px;
        height: 14px;
    }


    .profile-name {

        margin: 0;

        color: #fff;

        font-size:
            clamp(22px, 4vw, 30px);

        font-weight: 850;

        line-height: 1.4;
    }


    .profile-phone {

        margin-top: 5px;

        color:
            rgba(255,255,255,.68);

        font-size: 13px;

        direction: ltr;
        text-align: right;
    }


    .profile-edit-btn {

        flex: 0 0 auto;

        display: inline-flex;
        align-items: center;

        gap: 7px;

        min-height: 42px;

        padding:
            0
            14px;

        border-radius: 11px;

        border:
            1px solid
            rgba(255,255,255,.12);

        background:
            rgba(255,255,255,.07);

        color: #fff;

        font-family:
            'Vazirmatn',
            sans-serif;

        font-size: 12px;

        font-weight: 800;

        cursor: pointer;

        transition:
            .2s ease;
    }


    .profile-edit-btn:hover {

        background:
            rgba(255,255,255,.12);
    }


    .profile-edit-btn svg {

        width: 16px;
        height: 16px;
    }


    /* =========================================================
       STATS
       ========================================================= */

    .profile-stats {

        display: grid;

        grid-template-columns:
            repeat(
                4,
                minmax(0,1fr)
            );

        gap: 10px;

        margin-bottom: 18px;
    }


    .profile-stat {

        background:
            var(--surface);

        border:
            1px solid
            var(--border);

        border-radius: 16px;

        padding: 14px;

        text-decoration: none;

        color: inherit;

        box-shadow:
            var(--shadow-card);

        transition:
            transform .2s ease,
            border-color .2s ease;
    }


    .profile-stat:hover {

        transform:
            translateY(-3px);

        border-color:
            rgba(191,157,87,.42);
    }


    .profile-stat-top {

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 10px;

        margin-bottom: 9px;
    }


    .profile-stat-icon {

        width: 38px;
        height: 38px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 12px;

        background:
            var(--gold-bg);

        color:
            var(--gold);
    }


    .profile-stat-icon svg {

        width: 18px;
        height: 18px;
    }


    .profile-stat-number {

        color:
            var(--text-primary);

        font-size: 24px;

        font-weight: 850;

        line-height: 1;
    }


    .profile-stat-title {

        color:
            var(--text-primary);

        font-size: 12px;

        font-weight: 800;
    }


    .profile-stat-subtitle {

        margin-top: 3px;

        color:
            var(--text-secondary);

        font-size: 10px;

        line-height: 1.7;
    }


    /* =========================================================
       SECTION
       ========================================================= */

    .profile-section {

        margin-bottom: 17px;
    }


    /* کارت سفیدِ هم‌شکل با بقیه‌ی کارت‌های پروفایل (آمار و منو) */
    .profile-card {

        background:
            var(--surface);

        border:
            1px solid
            var(--border);

        border-radius: 16px;

        padding: 14px;

        box-shadow:
            var(--shadow-card);
    }


    .profile-section-header {

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 10px;

        margin-bottom: 10px;
    }


    .profile-section-title {

        display: flex;
        align-items: center;

        gap: 9px;
    }


    .profile-section-icon {

        width: 36px;
        height: 36px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 11px;

        background:
            var(--gold-bg);

        color:
            var(--gold);
    }


    .profile-section-icon svg {

        width: 18px;
        height: 18px;
    }


    .profile-section-title h2 {

        margin: 0;

        color:
            var(--text-primary);

        font-size: 16px;

        font-weight: 850;
    }


    .profile-section-title p {

        margin: 2px 0 0;

        color:
            var(--text-secondary);

        font-size: 10px;
    }


    .profile-view-all {

        color:
            var(--primary);

        font-size: 11px;

        font-weight: 800;

        text-decoration: none;
    }


    /* =========================================================
       MENU
       ========================================================= */

    .profile-menu {

        display: grid;

        gap: 9px;
    }


    button.profile-menu-item {
        width: 100%;
        font: inherit;
        cursor: pointer;
        text-align: right;
        appearance: none;
    }

    .profile-menu-item {

        display: flex;
        align-items: center;
        justify-content: space-between;

        gap: 12px;

        padding:
            14px
            15px;

        border-radius: 15px;

        background:
            var(--surface);

        border:
            1px solid
            var(--border);

        color:
            var(--text-primary);

        text-decoration: none;

        box-shadow:
            var(--shadow-card);

        transition:
            transform .2s ease,
            border-color .2s ease,
            background .2s ease;
    }


    .profile-menu-item:hover {

        transform:
            translateX(-3px);

        border-color:
            rgba(191,157,87,.38);
    }


    .profile-menu-left {

        display: flex;
        align-items: center;

        gap: 12px;

        min-width: 0;
    }


    .profile-menu-icon {

        width: 42px;
        height: 42px;

        min-width: 42px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 13px;

        background:
            var(--gold-bg);

        color:
            var(--gold);
    }


    .profile-menu-icon svg {

        width: 20px;
        height: 20px;
    }


    .profile-menu-title {

        color:
            var(--text-primary);

        font-size: 13px;

        font-weight: 800;
    }


    .profile-menu-description {

        margin-top: 2px;

        color:
            var(--text-secondary);

        font-size: 10px;

        line-height: 1.7;
    }


    .profile-menu-arrow {

        color:
            var(--text-secondary);

        flex: 0 0 auto;
    }


    .profile-menu-arrow svg {

        width: 17px;
        height: 17px;

        transform:
            rotate(180deg);
    }


    /* =========================================================
       ADMIN
       ========================================================= */

    .profile-menu-item.admin {

        border-color:
            rgba(6,78,78,.24);

        background:
            linear-gradient(
                135deg,
                rgba(6,78,78,.07),
                rgba(212,175,55,.07)
            );
    }


    .profile-menu-item.admin
    .profile-menu-icon {

        background:
            rgba(6,78,78,.10);

        color:
            var(--primary);
    }


    .profile-menu-item.admin
    .profile-menu-title {

        color:
            var(--primary);
    }


    /* =========================================================
       LOGOUT
       ========================================================= */

    .logout-card {

        width: 100%;

        min-height: 54px;

        display: flex;
        align-items: center;
        justify-content: center;

        gap: 8px;

        margin-top: 3px;

        border-radius: 14px;

        border:
            1px solid
            rgba(220,38,38,.32);

        background:
            rgba(220,38,38,.035);

        color:
            var(--danger);

        font-family:
            'Vazirmatn',
            sans-serif;

        font-size: 13px;

        font-weight: 800;

        cursor: pointer;

        transition:
            .2s ease;
    }


    .logout-card:hover {

        background:
            rgba(220,38,38,.07);
    }


    .logout-card svg {

        width: 18px;
        height: 18px;
    }


    /* =========================================================
       EDIT PROFILE MODAL
       ========================================================= */

    .profile-modal-overlay {

        position: fixed;

        inset: 0;

        z-index: 5000;

        display: none;

        align-items: center;
        justify-content: center;

        padding: 18px;

        background:
            rgba(0,0,0,.48);

        backdrop-filter:
            blur(5px);
    }


    .profile-modal-overlay.active {

        display: flex;
    }


    .profile-modal {

        width: 100%;
        max-width: 440px;

        border-radius: 20px;

        background:
            var(--surface);

        border:
            1px solid
            var(--border);

        box-shadow:
            0 25px 70px
            rgba(0,0,0,.25);

        overflow: hidden;
    }


    .profile-modal-header {

        display: flex;
        align-items: center;
        justify-content: space-between;

        padding:
            16px 18px;

        border-bottom:
            1px solid
            var(--border);
    }


    .profile-modal-header h3 {

        margin: 0;

        color:
            var(--text-primary);

        font-size: 16px;
        font-weight: 850;
    }


    .profile-modal-close {

        width: 34px;
        height: 34px;

        border: 0;

        border-radius: 10px;

        background:
            var(--bg);

        color:
            var(--text-secondary);

        cursor: pointer;
    }


    .profile-modal-body {

        padding: 18px;
    }


    .profile-form-group {

        display: flex;
        flex-direction: column;

        gap: 7px;

        margin-bottom: 14px;
    }


    .profile-form-group label {

        color:
            var(--text-primary);

        font-size: 12px;

        font-weight: 800;
    }


    .profile-form-control {

        width: 100%;

        box-sizing: border-box;

        min-height: 46px;

        padding:
            0 12px;

        border-radius: 11px;

        border:
            1px solid
            var(--border);

        background:
            var(--bg);

        color:
            var(--text-primary);

        font-family:
            'Vazirmatn',
            sans-serif;

        font-size: 13px;

        outline: none;
    }


    .profile-form-control:focus {

        border-color:
            var(--primary);

        box-shadow:
            0 0 0 3px
            rgba(6,78,78,.08);
    }


    .profile-modal-footer {

        display: flex;

        gap: 8px;

        padding:
            12px 18px;

        border-top:
            1px solid
            var(--border);
    }


    .profile-modal-btn {

        flex: 1;

        min-height: 46px;

        border-radius: 11px;

        font-family:
            'Vazirmatn',
            sans-serif;

        font-size: 12px;

        font-weight: 800;

        cursor: pointer;
    }


    .profile-modal-btn.cancel {

        border:
            1px solid
            var(--border);

        background:
            var(--bg);

        color:
            var(--text-secondary);
    }


    .profile-modal-btn.save {

        border: 0;

        background:
            var(--primary);

        color: #fff;
    }


    /* =========================================================
       RESPONSIVE
       ========================================================= */

    @media (max-width: 960px) {
        .profile-page { max-width: 100%; }
        .profile-stats { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .profile-hero-content { flex-wrap: wrap; }
    }


    @media (max-width: 580px) {

        .main-content {

            padding-left: 10px;
            padding-right: 10px;
            padding-bottom: 150px;
        }


        .profile-hero {

            padding:
                18px;

            border-radius:
                20px;
        }


        .profile-hero-content {

            align-items:
                flex-start;
        }


        .profile-edit-btn {

            width: 42px;
            height: 42px;

            min-height: 42px;

            padding: 0;

            justify-content:
                center;
        }


        .profile-edit-btn span {

            display:
                none;
        }


        .profile-name {

            font-size: 22px;
        }


        .profile-phone {

            font-size: 11px;
        }


        .profile-stat {

            padding:
                12px;
        }


        .profile-stat-number {

            font-size: 21px;
        }


        .profile-menu-item {

            padding:
                12px;
        }
    }


    @media (max-width: 380px) {

        .profile-stats {

            grid-template-columns:
                1fr;

        }
    }


    .match-stat-number { font-size: 24px; line-height: 1; }


    /* =========================================================
       COMPARE (مقایسه ملک‌ها)
       ========================================================= */

    .compare-tabs {

        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .compare-tab {

        flex: 1 1 0;
        min-width: 110px;

        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;

        padding: 10px 8px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text-secondary);

        font-family: inherit;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
    }

    .compare-tab.active {

        color: var(--primary);
        border-color: var(--primary);
    }

    .compare-tab .cmp-count {

        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 999px;

        font-size: 11px;
        font-weight: 700;
        padding: 1px 8px;
        white-space: nowrap;
    }

    .compare-tab.active .cmp-count {

        border-color: var(--primary);
        color: var(--primary);
    }

    .compare-rename {

        background: none;
        border: none;
        cursor: pointer;

        font-size: 14px;
        padding: 2px 4px;
        line-height: 1;
    }

    .compare-items {

        display: flex;
        flex-direction: column;
        gap: 8px;

        margin-bottom: 12px;
    }

    .compare-empty {

        text-align: center;
        color: var(--text-secondary);

        padding: 18px 10px;

        font-size: 13px;
        line-height: 2.1;
    }

    .compare-item {

        display: flex;
        align-items: center;
        gap: 10px;

        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--surface);
    }

    .compare-item img {

        width: 56px;
        height: 56px;

        border-radius: 10px;
        object-fit: cover;

        flex: 0 0 auto;
        background: var(--bg);
    }

    .compare-item-noimg {

        width: 56px;
        height: 56px;

        border-radius: 10px;

        display: flex;
        align-items: center;
        justify-content: center;

        flex: 0 0 auto;
        background: var(--bg);

        font-size: 24px;
    }

    .compare-item-info {

        flex: 1;
        min-width: 0;
    }

    .compare-item-title {

        font-weight: 700;
        font-size: 13px;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .compare-item-sub {

        font-size: 11px;
        color: var(--text-secondary);

        margin-top: 3px;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .compare-item-btns {

        display: flex;
        gap: 4px;
        flex: 0 0 auto;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .compare-mini-btn {

        border: 1px solid var(--border);
        background: var(--bg);
        border-radius: 8px;

        font-size: 11px;
        font-family: inherit;
        font-weight: 700;
        color: var(--text-secondary);

        padding: 5px 8px;
        cursor: pointer;
        white-space: nowrap;
    }

    .compare-mini-btn.danger {

        color: #dc2626;
    }

    .compare-actions {

        display: flex;
        gap: 8px;

        margin-bottom: 12px;
    }

    .cmp-btn {

        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text-secondary);
        border-radius: 12px;

        font-family: inherit;
        font-weight: 700;
        font-size: 13px;

        padding: 11px 14px;
        cursor: pointer;
    }

    .cmp-btn-primary {

        flex: 1;

        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }

    .compare-score-wrap {

        overflow-x: auto;

        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .compare-score-table {

        width: 100%;
        min-width: 520px;

        border-collapse: collapse;
        font-size: 12px;
    }

    .compare-score-table th,
    .compare-score-table td {

        padding: 9px 10px;
        border-bottom: 1px solid var(--border);

        text-align: center;
        white-space: nowrap;
    }

    .compare-score-table thead th {

        background: var(--bg);
        font-weight: 800;
        font-size: 12px;
    }

    .compare-score-table tbody th {

        text-align: right;
        color: var(--text-secondary);
        font-weight: 600;
        background: var(--surface);

        position: sticky;
        right: 0;
    }

    .compare-score-table tr:last-child th,
    .compare-score-table tr:last-child td {

        border-bottom: none;
    }

    .compare-score-table td.winner-col {

        background: rgba(14, 124, 110, .09);
        font-weight: 800;
    }

    .compare-total {

        font-size: 17px;
        font-weight: 800;
        color: var(--primary);
    }

    .compare-prop-head {

        display: block;
        max-width: 130px;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;

        margin: 0 auto 4px;
    }

    .compare-view-link {

        color: var(--primary);
        font-weight: 700;
        text-decoration: none;
        font-size: 12px;
    }

    .compare-highlights {

        margin-top: 10px;
        padding: 10px 12px;

        border: 1px dashed var(--border);
        border-radius: 12px;

        font-size: 12px;
        line-height: 2.1;
        color: var(--text-secondary);
    }

    .compare-warn {

        margin-top: 10px;
        padding: 10px 12px;

        border-radius: 12px;
        background: rgba(217, 119, 6, .1);
        border: 1px solid rgba(217, 119, 6, .3);

        font-size: 12px;
        line-height: 2;
    }
</style>


<div class="main-content">

    <div class="profile-page">


        <!-- =====================================================
             PROFILE HERO
             ===================================================== -->

        <section class="profile-hero" data-tour="profile-page">

            <div class="profile-hero-content">

                <div class="profile-identity">

                    <div class="profile-badge">

                        <?php if ($tgPhoto30 !== ''): ?>
                        <img src="<?= htmlspecialchars($tgPhoto30, ENT_QUOTES, 'UTF-8') ?>" alt="عکس پروفایل" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" onerror="this.style.display='none'">
                        <?php else: ?>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M20 21a8 8 0 0 0-16 0"/>
                            <circle
                                cx="12"
                                cy="7"
                                r="4"
                            />
                        </svg>
                        <?php endif; ?>

                        حساب کاربری ملکینو

                    </div>


                    <h1 class="profile-name">

                        <?= htmlspecialchars(
                            $userName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h1>


                    <?php if ($tgUsername30 !== '' && !$nameLocked30): ?>
                        <div dir="ltr" style="font-size:12px;color:var(--text-secondary,#A8B1AE);margin-top:2px;">@<?= htmlspecialchars($tgUsername30, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>


                    <div class="profile-phone">

                        <?php if ($userPhone !== ''): ?>
                            <span dir="ltr"><?= htmlspecialchars($userPhone, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($phoneLocked30 || $phoneVerified30): ?>
                                <span style="display:inline-flex;align-items:center;gap:4px;margin-inline-start:8px;padding:3px 10px;border-radius:999px;background:rgba(34,197,94,.14);border:1px solid rgba(34,197,94,.35);color:#4ADE80;font-size:11px;font-weight:700;">✓ شماره تماس تأیید شده</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($userPhone === '' || !$nameLocked30): ?>
                            <button type="button" onclick="openProfileComplete()" style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:999px;border:1px solid rgba(217,119,6,.45);background:rgba(217,119,6,.12);color:#FFD47E;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;margin-inline-start:8px;">
                            <?php if ($userPhone === ''): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/><path d="m2 2 20 20"/></svg>
                                تکمیل شماره تماس
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>
                                تکمیل نام و نام خانوادگی
                            <?php endif; ?>
                        </button>
                        <?php endif; ?>

                    </div>

                </div>


                <!-- راند ۳۳: دکمهٔ «ویرایش نام» طبق درخواست حذف شد.
                     نام/شماره فقط از طریق جریان «تکمیل اطلاعات حساب» ثبت
                     و سپس قفل می‌شوند؛ تغییر بعد از آن فقط توسط ادمین است. -->

            </div>

        </section>


        <!-- =====================================================
             STATS
             ===================================================== -->

        <section class="profile-stats">


            <!-- املاک -->

            <a
                href="my-properties.php"
                class="profile-stat"
            >

                <div class="profile-stat-top">

                    <div class="profile-stat-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="m3 11 9-8 9 8"/>
                            <path d="M5 10v10h14V10"/>
                            <path d="M9 20v-6h6v6"/>
                        </svg>

                    </div>


                    <div class="profile-stat-number">

                        <?= (int)$myPropertiesCount ?>

                    </div>

                </div>


                <div class="profile-stat-title">
                    املاک من
                </div>


                <div class="profile-stat-subtitle">
                    مدیریت فایل‌های ثبت‌شده
                </div>

            </a>


            <!-- درخواست‌ها -->

            <a
                href="requests.php"
                class="profile-stat"
            >

                <div class="profile-stat-top">

                    <div class="profile-stat-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M4 4h16v16H4z"/>
                            <path d="M8 9h8"/>
                            <path d="M8 13h6"/>
                        </svg>

                    </div>


                    <div class="profile-stat-number">

                        <?= (int)$myRequestsCount ?>

                    </div>

                </div>


                <div class="profile-stat-title">
                    درخواست‌های من
                </div>


                <div class="profile-stat-subtitle">
                    درخواست‌های ملکی ثبت‌شده
                </div>

            </a>


            <!-- بازدید -->

            <a
                href="visits.php"
                class="profile-stat"
                data-tour="my-visits"
            >

                <div class="profile-stat-top">

                    <div class="profile-stat-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <rect x="3" y="5" width="18" height="16" rx="2"/>
                            <path d="M8 3v4M16 3v4M3 11h18"/>
                        </svg>

                    </div>


                    <div class="profile-stat-number">

                        <?= (int)$myVisitsCount ?>

                    </div>

                </div>


                <div class="profile-stat-title">
                    بازدید
                </div>

                <div class="profile-stat-subtitle">
                    درخواست‌های بازدید ملک
                </div>

            </a>


            <!-- علاقه‌مندی -->

            <a
                href="favorites.php"
                class="profile-stat"
            >

                <div class="profile-stat-top">

                    <div class="profile-stat-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M20.8 8.8a5.5 5.5 0 0 0-7.8-4.6L12 5.4l-1-1.2a5.5 5.5 0 0 0-7.8 4.6c0 4.2 4.5 7.1 8.8 11 8.3-7.2 8.8-10 8.8-11z"/>
                        </svg>

                    </div>


                    <div
                        class="profile-stat-number"
                        id="profileFavoriteCount"
                    >
                        <?= (int)$favoriteCount ?>
                    </div>

                </div>


                <div class="profile-stat-title">
                    ذخیره‌شده‌ها
                </div>


                <div class="profile-stat-subtitle">
                    فایل‌های مورد علاقه
                </div>

            </a>


            <!-- اعلان -->

            <a
                href="notifications.php"
                class="profile-stat"
            >

                <div class="profile-stat-top">

                    <div class="profile-stat-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                            <path d="M10 21h4"/>
                        </svg>

                    </div>


                    <div
                        class="profile-stat-number"
                        id="profileNotificationCount"
                    >
                        <?= (int)$notificationCount ?>
                    </div>

                </div>


                <div class="profile-stat-title">
                    اعلان‌ها
                </div>


                <div class="profile-stat-subtitle">
                    پیام‌ها و اطلاع‌رسانی‌ها
                </div>

            </a>

            <a href="my-request-matches.php" class="profile-stat" data-tour="matches">
                <div class="profile-stat-top">
                    <div class="profile-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="m21 21-4.3-4.3"/>
                            <path d="m8.4 11.2 1.9 1.9 3.5-3.9"/>
                        </svg>
                    </div>
                    <div class="profile-stat-number match-stat-number" id="profileMatchCount"><?= (int)$matchCount ?></div>
                </div>
                <div class="profile-stat-title">فایل‌های مناسب من</div>
                <div class="profile-stat-subtitle">مشاهده و مدیریت فایل‌های مطابق</div>
            </a>

            <a href="compare-page.php" class="profile-stat" data-tour="compare">
                <div class="profile-stat-top">
                    <div class="profile-stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 4v16"/>
                            <path d="M9 20h6"/>
                            <path d="M4 7h16"/>
                            <path d="M4 7 1.9 12.1a2.6 2.6 0 0 0 4.2 0L4 7Z"/>
                            <path d="M20 7l-2.1 5.1a2.6 2.6 0 0 0 4.2 0L20 7Z"/>
                        </svg>
                    </div>
                    <div class="profile-stat-number"><?= (int)$compareProfileCount ?></div>
                </div>
                <div class="profile-stat-title">مقایسه ملک‌ها</div>
                <div class="profile-stat-subtitle">مقایسهٔ کنار هم + امتیازدهی از ۱۰۰</div>
            </a>

        </section>


        <!-- =====================================================
             SUPPORT
             ===================================================== -->

        <section class="profile-section">

            <div class="profile-section-header">

                <div class="profile-section-title">

                    <div class="profile-section-icon">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>

                    </div>

                    <div>

                        <h2>
                            خدمات ملکینو
                        </h2>

                        <p>
                            ارتباط و دریافت راهنمایی
                        </p>

                    </div>

                </div>

            </div>


            <div class="profile-menu">


                <a
                    href="support.php"
                    class="profile-menu-item"
                    data-tour="support"
                >

                    <div class="profile-menu-left">

                        <div class="profile-menu-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>

                        </div>


                        <div>

                            <div class="profile-menu-title">
                                پشتیبانی
                            </div>

                            <div class="profile-menu-description">
                                سوال یا مشکلی دارید؟ با ما در ارتباط باشید
                            </div>

                        </div>

                    </div>


                    <div class="profile-menu-arrow">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>

                    </div>

                </a>


                <button type="button" class="profile-menu-item" id="mkTourReplayBtn">

                    <div class="profile-menu-left">

                        <div class="profile-menu-icon">

                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M12 8v5l3 2"/>
                            </svg>

                        </div>


                        <div>

                            <div class="profile-menu-title">
                                راهنمای ملکینو
                            </div>

                            <div class="profile-menu-description">
                                مشاهده دوباره راهنمای امکانات
                            </div>

                        </div>

                    </div>


                    <div class="profile-menu-arrow">

                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>

                    </div>

                </button>


                <a
                    href="contact.php"
                    class="profile-menu-item"
                    data-tour="contact"
                >

                    <div class="profile-menu-left">

                        <div class="profile-menu-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-2-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>

                        </div>


                        <div>

                            <div class="profile-menu-title">
                                ارتباط با ما
                            </div>

                            <div class="profile-menu-description">
                                تماس، شبکه‌های اجتماعی و اطلاعات دفتر ملکینو
                            </div>

                        </div>

                    </div>


                    <div class="profile-menu-arrow">

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>

                    </div>

                </a>


                <?php if (
                    isset($_SESSION['user_role']) &&
                    $_SESSION['user_role'] === 'admin'
                ): ?>

                    <a
                        href="admin-login.php"
                        class="profile-menu-item admin"
                    >

                        <div class="profile-menu-left">

                            <div class="profile-menu-icon">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                </svg>

                            </div>


                            <div>

                                <div class="profile-menu-title">
                                    پنل مدیریت
                                </div>

                                <div class="profile-menu-description">
                                    مدیریت آگهی‌ها، درخواست‌ها و تنظیمات ملکینو
                                </div>

                            </div>

                        </div>


                        <div class="profile-menu-arrow">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>

                        </div>

                    </a>

                <?php endif; ?>

            </div>

        </section>


        <!-- راند ۳۵: دکمهٔ «خروج از حساب کاربری» طبق درخواست از صفحهٔ پروفایل
     حذف شد. endpoint خروج (logout.php) و تابع سراسری logoutUser در
     header.php بدون تغییر باقی‌اند. -->

    </div>

</div>


<!-- =========================================================
     EDIT PROFILE MODAL
     ========================================================= -->

<div
    class="profile-modal-overlay"
    id="profileEditModal"
>

    <div
        class="profile-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="profileEditTitle"
    >

        <div class="profile-modal-header">

            <h3 id="profileEditTitle">
                ویرایش پروفایل
            </h3>


            <button
                type="button"
                class="profile-modal-close"
                onclick="closeProfileEdit()"
                aria-label="بستن"
            >
                ✕
            </button>

        </div>


        <div class="profile-modal-body">

            <div class="profile-form-group">

                <label for="editProfileName">
                    نام کاربری
                </label>

                <input
                    type="text"
                    id="editProfileName"
                    class="profile-form-control"
                    value="<?= htmlspecialchars(
                        $userName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="name"
                    <?= ($isTgUser30 || $nameLocked30) ? 'readonly' : '' ?>
                >

                <?php if ($nameLocked30): ?>
                    <div style="font-size:11px;color:#4ADE80;margin-top:6px;line-height:1.9;">
                        ✓ نام و نام خانوادگی شما تأیید و قفل شده است و قابل ویرایش نیست.
                    </div>
                <?php elseif ($isTgUser30): ?>
                    <div style="font-size:11px;color:var(--text-secondary,#A8B1AE);margin-top:6px;line-height:1.9;">
                        نام شما از حساب تلگرام/بله خوانده می‌شود. برای ثبت نام واقعی خود از دکمهٔ «تکمیل نام و نام خانوادگی» استفاده کنید.
                    </div>
                <?php endif; ?>

            </div>


            <div class="profile-form-group">

                <label for="editProfilePhone">
                    شماره موبایل
                </label>

                <!-- راند ۳۰: شمارهٔ ثبت‌شده قفل است (readonly) و فقط ادمین
                     می‌تواند آن را حذف/باز کند. اگر شماره‌ای ثبت نشده باشد،
                     از طریق کارت «ثبت شمارهٔ تماس» (با مرحلهٔ تأیید) ثبت می‌شود. -->
                <input
                    type="tel"
                    inputmode="numeric"
                    autocomplete="tel"
                    id="editProfilePhone"
                    class="profile-form-control"
                    placeholder="۰۹۱۲۳۴۵۶۷۸۹"
                    value="<?= htmlspecialchars(
                        $userPhone,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    dir="ltr"
                    readonly
                >

                <?php if ($phoneLocked30 || $phoneVerified30): ?>
                    <div style="font-size:11px;color:#4ADE80;margin-top:6px;line-height:1.9;">
                        ✓ شمارهٔ تماس تأیید و قفل شده است. برای تغییر آن باید ادمین شماره را حذف کند.
                    </div>
                <?php else: ?>
                    <div style="font-size:11px;color:var(--text-secondary,#A8B1AE);margin-top:6px;line-height:1.9;">
                        برای ثبت شمارهٔ تماس از دکمهٔ «تکمیل شماره تماس» در بالای صفحه استفاده کنید.
                    </div>
                <?php endif; ?>

            </div>

        </div>


        <div class="profile-modal-footer">

            <button
                type="button"
                class="profile-modal-btn cancel"
                onclick="closeProfileEdit()"
            >
                انصراف
            </button>


            <button
                type="button"
                class="profile-modal-btn save"
                onclick="saveProfileName()"
            >
                ذخیره تغییرات
            </button>

        </div>

    </div>

</div>


<!-- =========================================================
     PHONE REGISTER MODAL — راند ۳۰
     ثبت شمارهٔ تماس در دو مرحله: ورودی → تأیید نهایی → قفل
     ========================================================= -->

<div
    class="profile-modal-overlay"
    id="phoneRegisterModal"
>

    <div
        class="profile-modal"
        role="dialog"
        aria-modal="true"
    >

        <div class="profile-modal-header">

            <h3>
                تکمیل اطلاعات حساب
            </h3>


            <button
                type="button"
                class="profile-modal-close"
                onclick="closePhoneRegister()"
                aria-label="بستن"
            >
                ✕
            </button>

        </div>


        <div class="profile-modal-body">

            <div id="phoneRegStep1">

                <?php if (!$nameLocked30): ?>
                <div class="profile-form-group">

                    <label for="regFirstName">
                        نام
                    </label>

                    <input
                        type="text"
                        id="regFirstName"
                        class="profile-form-control"
                        placeholder="مثلاً: علی"
                        autocomplete="given-name"
                    >

                </div>


                <div class="profile-form-group">

                    <label for="regLastNameP">
                        نام خانوادگی
                    </label>

                    <input
                        type="text"
                        id="regLastNameP"
                        class="profile-form-control"
                        placeholder="مثلاً: رضایی"
                        autocomplete="family-name"
                    >

                    <div style="font-size:11px;color:var(--text-secondary,#A8B1AE);margin-top:8px;line-height:2;">
                        نام و نام خانوادگی را به <strong>فارسی</strong> وارد کنید
                        (بدون عدد و حروف انگلیسی). این نام در همهٔ آگهی‌ها و
                        درخواست‌های شما استفاده می‌شود و پس از تأیید قابل
                        ویرایش نیست.
                    </div>

                </div>
                <?php endif; ?>

                <?php if ($userPhone === ''): ?>
                <div class="profile-form-group">

                    <label for="phoneRegInput">
                        شماره موبایل
                    </label>

                    <input
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel"
                        id="phoneRegInput"
                        class="profile-form-control"
                        placeholder="۰۹۱۲۳۴۵۶۷۸۹"
                        dir="ltr"
                    >

                    <div style="font-size:11px;color:var(--text-secondary,#A8B1AE);margin-top:8px;line-height:2;">
                        شماره به فرمت استاندارد ۰۹xxxxxxxxx ذخیره می‌شود و
                        روی همین حساب قفل می‌شود. یک شماره فقط روی یک حساب
                        قابل ثبت است؛ ویرایش آن بعداً فقط توسط ادمین ممکن است.
                    </div>

                </div>
                <?php endif; ?>

            </div>


            <div id="phoneRegStep2" style="display:none;">

                <div style="padding:14px;border:1px dashed rgba(217,119,6,.5);border-radius:12px;background:rgba(217,119,6,.08);font-size:13px;line-height:2.3;color:var(--text-primary,#F3F4F6);text-align:center;">

                    آیا از صحت اطلاعات زیر مطمئن هستید؟

                    <div id="nameRegSummaryWrap" style="display:none;margin-top:4px;">
                        نام و نام خانوادگی:
                        <strong id="nameRegSummary" style="font-size:15px;"></strong>
                    </div>

                    <div id="phoneRegSummaryWrap" style="display:none;">
                        شمارهٔ تماس:
                        <strong dir="ltr" id="phoneRegNumber" style="display:inline-block;margin:4px 0;font-size:17px;letter-spacing:1.5px;"></strong>
                    </div>

                    <span style="color:#FFD47E;font-size:12px;">
                        ⚠ پس از تأیید، این اطلاعات قفل می‌شود و قابل ویرایش نخواهد بود.
                    </span>

                </div>

            </div>

        </div>


        <div class="profile-modal-footer">

            <button
                type="button"
                class="profile-modal-btn cancel"
                onclick="closePhoneRegister()"
            >
                انصراف
            </button>


            <button
                type="button"
                class="profile-modal-btn save"
                id="phoneRegNextBtn"
                onclick="phoneRegConfirmStep()"
            >
                ادامه
            </button>


            <button
                type="button"
                class="profile-modal-btn cancel"
                id="phoneRegBackBtn"
                style="display:none;"
                onclick="phoneRegBack()"
            >
                اصلاح
            </button>


            <button
                type="button"
                class="profile-modal-btn save"
                id="phoneRegSubmitBtn"
                style="display:none;"
                onclick="submitPhoneRegister()"
            >
                تأیید و ثبت
            </button>

        </div>

    </div>

</div>


<!-- =========================================================
     TOAST
     ========================================================= -->

<div
    id="profileToast"
    style="position:fixed;bottom:26px;right:50%;transform:translateX(50%) translateY(20px);background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px 18px;font-size:13px;color:var(--text-primary);box-shadow:0 12px 30px rgba(0,0,0,.18);opacity:0;pointer-events:none;transition:opacity .3s ease,transform .3s ease;z-index:9999;max-width:calc(100vw - 40px);text-align:center;"
></div>


<script>

    /* =========================================================
       PROFILE
       ========================================================= */

    function openProfileEdit() {

        const modal =
            document.getElementById(
                'profileEditModal'
            );


        if (modal) {

            modal.classList.add(
                'active'
            );


            setTimeout(
                function() {

                    const input =
                        document.getElementById(
                            'editProfileName'
                        );

                    if (input) {

                        input.focus();

                        input.select();

                    }

                },
                80
            );
        }
    }


    function closeProfileEdit() {

        const modal =
            document.getElementById(
                'profileEditModal'
            );


        if (modal) {

            modal.classList.remove(
                'active'
            );

        }

    }


    function saveProfileName() {

        const nameInput =
            document.getElementById('editProfileName');

        const phoneInput =
            document.getElementById('editProfilePhone');

        if (!nameInput) {
            return;
        }

        const name = nameInput.value.trim();

        if (!name) {
            alert('لطفاً نام خود را وارد کنید.');
            return;
        }

        let phone = phoneInput ? phoneInput.value.trim() : '';

        if (phone && window.melkinoNormalizePhone) {
            phone = window.melkinoNormalizePhone(phone);
        }

        if (phone && window.melkinoIsValidPhone && !window.melkinoIsValidPhone(phone)) {
            alert('شماره معتبر نیست. مثال: ۰۹۱۲۳۴۵۶۷۸۹');
            if (phoneInput) phoneInput.focus();
            return;
        }

        /* ذخیرهٔ نام + شماره در دیتابیس (مسیر امنِ session-based) */
        fetch(
            'profile-sync.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                cache: 'no-store',
                body: JSON.stringify({
                    action: 'update_contact',
                    name: name,
                    phone: phone
                })
            }
        )
        .then(function (response) {
            if (!response.ok) {
                throw new Error('خطا در ذخیرهٔ اطلاعات (HTTP ' + response.status + ')');
            }
            return response.json();
        })
        .then(function (result) {
            if (!result || result.success !== true) {
                throw new Error((result && result.message) || 'ذخیره انجام نشد.');
            }

            const nameEl = document.querySelector('.profile-name');
            if (nameEl) nameEl.textContent = name;

            closeProfileEdit();
            showProfileToast('✓ اطلاعات حساب ذخیره شد.');
        })
        .catch(function (e) {
            alert('❌ ' + (e && e.message ? e.message : 'ذخیره انجام نشد.'));
        });
    }

    /* =========================================================
       PHONE REGISTER — راند ۳۰
       دو مرحله: ورود شماره → تأیید نهایی → set_phone (قفل)
       ========================================================= */

    function openProfileComplete() {

        const modal =
            document.getElementById('phoneRegisterModal');

        if (!modal) { return; }

        phoneRegShowStep(1);

        const fi = document.getElementById('regFirstName');
        const li = document.getElementById('regLastNameP');
        const inp = document.getElementById('phoneRegInput');
        if (fi) { fi.value = ''; }
        if (li) { li.value = ''; }
        if (inp) { inp.value = ''; }

        modal.classList.add('active');

        setTimeout(function () {
            const first = fi || inp;
            if (first) { first.focus(); }
        }, 80);
    }

    /* نام قدیمی برای سازگاری با کدهای قبلی */
    function openPhoneRegister() {
        openProfileComplete();
    }


    function closePhoneRegister() {

        const modal =
            document.getElementById('phoneRegisterModal');

        if (modal) { modal.classList.remove('active'); }
    }


    function phoneRegShowStep(step) {

        const s1 = document.getElementById('phoneRegStep1');
        const s2 = document.getElementById('phoneRegStep2');
        const nextBtn = document.getElementById('phoneRegNextBtn');
        const backBtn = document.getElementById('phoneRegBackBtn');
        const submitBtn = document.getElementById('phoneRegSubmitBtn');

        if (s1) s1.style.display = step === 1 ? '' : 'none';
        if (s2) s2.style.display = step === 2 ? '' : 'none';
        if (nextBtn) nextBtn.style.display = step === 1 ? '' : 'none';
        if (backBtn) backBtn.style.display = step === 2 ? '' : 'none';
        if (submitBtn) {
            submitBtn.style.display = step === 2 ? '' : 'none';
            submitBtn.disabled = false;
            submitBtn.textContent = 'تأیید و ثبت';
        }
    }


    function phoneRegConfirmStep() {

        /* راند ۳۱: نام فارسی + شماره — هرکدام که در این مرحله نمایش داده می‌شود */
        const fi = document.getElementById('regFirstName');
        const li = document.getElementById('regLastNameP');
        const inp = document.getElementById('phoneRegInput');

        const faName = /^[\u0600-\u06FF\uFB50-\uFDFF\uFE70-\uFEFF\u200C\u200D\s]{2,60}$/;

        let fullName = '';
        if (fi && li) {
            const fn = fi.value.trim();
            const ln = li.value.trim();
            if (!fn || !ln) {
                alert('نام و نام خانوادگی را کامل وارد کنید.');
                return;
            }
            if (!faName.test(fn) || !faName.test(ln)) {
                alert('نام و نام خانوادگی باید به فارسی باشد (بدون عدد و حروف انگلیسی).');
                return;
            }
            fullName = fn + ' ' + ln;
        }

        let phone = '';
        if (inp) {
            const raw = inp.value.trim();
            phone = raw;
            if (window.melkinoNormalizePhone) {
                phone = window.melkinoNormalizePhone(raw);
            }
            if (!phone || (window.melkinoIsValidPhone && !window.melkinoIsValidPhone(phone))) {
                alert('شمارهٔ موبایل معتبر نیست. فرمت صحیح: ۰۹۱۲۳۴۵۶۷۸۹');
                inp.focus();
                return;
            }
        }

        const nameWrap = document.getElementById('nameRegSummaryWrap');
        const nameShow = document.getElementById('nameRegSummary');
        if (nameShow) { nameShow.textContent = fullName; }
        if (nameWrap) { nameWrap.style.display = fullName ? '' : 'none'; }

        const phoneWrap = document.getElementById('phoneRegSummaryWrap');
        const show = document.getElementById('phoneRegNumber');
        if (show) { show.textContent = phone; }
        if (phoneWrap) { phoneWrap.style.display = phone ? '' : 'none'; }

        phoneRegShowStep(2);
    }


    function phoneRegBack() {

        phoneRegShowStep(1);

        const inp = document.getElementById('phoneRegInput');
        if (inp) { inp.focus(); }
    }


    function submitProfileComplete() {

        const fi = document.getElementById('regFirstName');
        const li = document.getElementById('regLastNameP');
        const show = document.getElementById('phoneRegNumber');

        const payload = {
            action: 'set_profile',
            confirm: true
        };

        if (fi && li) {
            payload.first_name = fi.value.trim();
            payload.last_name = li.value.trim();
        }
        if (show && show.textContent.trim()) {
            payload.phone = show.textContent.trim();
        }

        if (!payload.first_name && !payload.phone) { return; }

        const btn = document.getElementById('phoneRegSubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'در حال ثبت…';
        }

        fetch(
            'profile-sync.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                cache: 'no-store',
                body: JSON.stringify(payload)
            }
        )
        .then(function (response) {
            return response.json().then(function (j) {
                return { ok: response.ok, j: j };
            });
        })
        .then(function (res) {
            if (!res.ok || !res.j || res.j.success !== true) {
                throw new Error((res.j && res.j.message) || 'ثبت انجام نشد.');
            }

            closePhoneRegister();
            if (window.showProfileToast) {
                showProfileToast('✓ اطلاعات حساب ثبت و تأیید شد.');
            }
            setTimeout(function () { location.reload(); }, 800);
        })
        .catch(function (e) {
            alert('❌ ' + (e && e.message ? e.message : 'ثبت انجام نشد.'));
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'تأیید و ثبت';
            }
        });
    }

    /* نام قدیمی برای سازگاری */
    function submitPhoneRegister() {
        submitProfileComplete();
    }

</script>


<?php
require_once __DIR__ . '/footer.php';
?>
