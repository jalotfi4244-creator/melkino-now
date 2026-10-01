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

/*
|--------------------------------------------------------------------------
| تشخیصِ پلتفرمِ مینی‌اپ
|--------------------------------------------------------------------------
| مستندات رسمی بله تأکید می‌کند که اسکریپتِ miniapp.js باید «پیش از هر
| اسکریپتِ دیگری در ابتدای <head>» باشد؛ چون تا وقتی Bale.WebApp.ready()
| فراخوانی نشود، کلاینتِ بله یک صفحه‌ی بارگذاریِ سفید نشان می‌دهد.
|
| دلیلِ اصلیِ صفحه‌ی سفید در بله همین بود: اسکریپت در انتهای صفحه و به‌صورت
| async لود می‌شد، یعنی پس از بارگیریِ کلِ صفحه؛ و اگر شبکه کند باشد یا
| کلاینت زودتر منتظر بماند، ready() هیچ‌وقت به‌موقع صدا زده نمی‌شود.
|
| برای تلگرام همچنان روشِ غیرِمسدودکننده را نگه می‌داریم، چون در ایران
| دسترسی به telegram.org معمولاً مسدود است و یک تگِ مسدودکننده باعث
| سفید ماندنِ صفحه می‌شود.
|--------------------------------------------------------------------------
*/
$melkinoUa = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
$melkinoIsBale = (strpos($melkinoUa, 'bale') !== false)
    || (strpos($melkinoUa, 'ble.ir') !== false);
$melkinoIsTelegram = (strpos($melkinoUa, 'telegram') !== false);

/*
|--------------------------------------------------------------------------
| آیا کاربر از قبل وارد شده است؟ (برای ورود خودکارِ مینی‌اپ)
|--------------------------------------------------------------------------
| این مقدار فقط به جاوااسکریپت اعلام می‌کند که نشستِ معتبری وجود دارد یا نه.
| اگر نباشد و داده‌ی هویتِ تلگرام/بله در دسترس باشد، ورود به‌صورت بی‌صدا
| انجام می‌شود تا کاربر اصلاً با صفحه‌ی ورود مواجه نشود.
|--------------------------------------------------------------------------
*/
$melkinoIsLoggedIn = !empty($_SESSION['reg_telegram_id']) || !empty($_SESSION['reg_bale_id']);

/*
|--------------------------------------------------------------------------
| دریافت لوگوی آپلودشده
|--------------------------------------------------------------------------
*/

// راند ۶۹: لوگوی نسخه‌دار مشترک — کش لوگوی کهنه در ایندکس رفع شد
require_once __DIR__ . '/melkino-logo.php';
$logoUrl = melkinoSiteLogoUrl() ?: null;
$mkFirstPage = function_exists('melkinoOnboardingFirstPage') ? melkinoOnboardingFirstPage() : [
    'title' => 'به ملکینو خوش آمدید',
    'text' => 'سامانه جامع جستجو و ثبت ملک.',
];
$mkFirstTitle = (string) ($mkFirstPage['title'] ?? 'به ملکینو خوش آمدید');
$mkFirstText = (string) ($mkFirstPage['text'] ?? '');
$mkFirstLogo = function_exists('melkinoOnboardingFirstLogoUrl') ? melkinoOnboardingFirstLogoUrl() : '';
if ($mkFirstLogo === '' && $logoUrl) {
    $mkFirstLogo = (string) $logoUrl;
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
<script src="https://tapi.bale.ai/miniapp.js?3"></script>
<script>
(function () {
    function go() {
        try {
            var w = window.Bale && window.Bale.WebApp;
            if (w) {
                try { if (typeof w.ready === 'function') w.ready(); } catch (e) {}
                try { if (typeof w.expand === 'function') w.expand(); } catch (e) {}
            }
        } catch (e) {}
        try {
            if (window.parent && window.parent !== window) {
                function send(t) {
                    window.parent.postMessage(JSON.stringify({ eventType: t, eventData: null }), '*');
                }
                send('iframe_ready');
                send('web_app_ready');
                send('web_app_expand');
            }
        } catch (e) {}
    }
    go();
    setTimeout(go, 0);
    setTimeout(go, 100);
    setTimeout(go, 400);
    setTimeout(go, 1200);
})();
</script>
<script>
/*
 * آمادگیِ مینی‌اپ (تلگرام و بله)
 * ========================================================
 * کلاینتِ تلگرام/بله تا وقتی پیامِ «آمادگی» را دریافت نکند، یک لایه‌ی
 * سفید روی صفحه نگه می‌دارد.
 *
 * سه اشکالِ پشتِ‌هم باعث می‌شد این پیام فرستاده نشود:
 *   ۱) تشخیص با User-Agent در سمتِ سرور؛
 *   ۲) وابستگی به ورودِ کاربر؛
 *   ۳) تشخیص در مرورگر بر اساسِ «بودن در قاب» یا User-Agent — که روی
 *      کلاینتِ بله در iOS هر دو شکست می‌خورند.
 *
 * عیب‌یابی روی دستگاهِ واقعی نشان داد User-Agentِ بله در iOS چیزی جز یک
 * Safari معمولی نیست و صفحه هم درونِ قاب نیست. بنابراین دیگر هیچ تشخیصی
 * انجام نمی‌شود: این بلوک همیشه اجرا می‌شود و اسکریپتِ بله همیشه
 * به‌صورت غیرِ مسدودکننده بارگیری می‌گردد.
 */
(function () {

    var sdkDone = { bale: false, telegram: false, eitaa: false };

    // ثبتِ رویدادها — فقط در صفحه‌ی عیب‌یاب استفاده می‌شود و در بقیه‌ی
    // صفحات بی‌اثر است.
    function note(text) {
        try {
            if (typeof window.__MK_MARK === 'function') { window.__MK_MARK(text); }
        } catch (e) {}
    }

    note('بلوکِ آمادگی اجرا شد (بدونِ نیاز به تشخیصِ پلتفرم)');

    // حالتِ عادی: ready() از طریقِ اسکریپتِ رسمی
    function announceSDK() {
        try {
            if (window.Bale && window.Bale.WebApp) {
                if (!sdkDone.bale) {
                    sdkDone.bale = true;
                    if (typeof window.Bale.WebApp.ready === 'function') window.Bale.WebApp.ready();
                    try { if (typeof window.Bale.WebApp.expand === 'function') window.Bale.WebApp.expand(); } catch (e) {}
                    note('Bale.WebApp پیدا شد و ready() فراخوانی شد ✅');
                }
                return true;
            }
        } catch (e) {}
        try {
            if (window.Telegram && window.Telegram.WebApp) {
                if (!sdkDone.telegram) {
                    sdkDone.telegram = true;
                    if (typeof window.Telegram.WebApp.ready === 'function') window.Telegram.WebApp.ready();
                    try { if (typeof window.Telegram.WebApp.expand === 'function') window.Telegram.WebApp.expand(); } catch (e) {}
                    note('Telegram.WebApp پیدا شد و ready() فراخوانی شد ✅');
                }
                return true;
            }
        } catch (e) {}
        try {
            if (window.Eitaa && window.Eitaa.WebApp) {
                if (!sdkDone.eitaa) {
                    sdkDone.eitaa = true;
                    if (typeof window.Eitaa.WebApp.ready === 'function') window.Eitaa.WebApp.ready();
                    try { if (typeof window.Eitaa.WebApp.expand === 'function') window.Eitaa.WebApp.expand(); } catch (e) {}
                    note('Eitaa.WebApp پیدا شد و ready() فراخوانی شد ✅');
                }
                return true;
            }
        } catch (e) {}
        return false;
    }

    function inFrame() {
        try { return (window.self !== window.top); } catch (e) { return true; }
    }

    // مسیرِ جایگزین: پروتکلِ خامِ مینی‌اپ (فقط وقتی درونِ قاب باشیم)
    function postReady() {
        if (!inFrame()) { return; }
        try {
            function send(eventType, eventData) {
                window.parent.postMessage(
                    JSON.stringify({ eventType: eventType, eventData: eventData }),
                    '*'
                );
            }
            send('iframe_ready', { reload_supported: true });
            send('web_app_ready', null);
            send('web_app_expand', null);
            note('مسیرِ جایگزین: پیام‌های آمادگی با postMessage فرستاده شد ✅');
        } catch (e) {}
    }

    // ۱) اگر شیء از پیش وجود داشت، همان لحظه اعلام کن
    if (announceSDK()) { note('SDK از پیش موجود بود'); }

    // ۲) بارگیریِ اسکریپتِ بله: همیشه و غیرِ مسدودکننده
    var ua = '';
    try { ua = navigator.userAgent || ''; } catch (e) {}

    var sources = ['https://tapi.bale.ai/miniapp.js?3'];
    var inBale = false;
    try { inBale = !!(window.Bale && window.Bale.WebApp) || /bale|ble\.ir/i.test(ua); } catch (e) {}
    if (!inBale && (inFrame() || /telegram/i.test(ua))) {
        sources.unshift('telegram-web-app.js');
    }

    for (var i = 0; i < sources.length; i++) {
        (function (src) {
            var s = document.createElement('script');
            s.src = src;
            s.async = true;                 // هرگز مسدودکننده نباشد
            s.onload = function () { note('اسکریپت لود شد: ' + src); announceSDK(); };
            s.onerror = function () { note('خطا در بارگیری: ' + src); };
            document.head.appendChild(s);
            note('درخواستِ بارگیری فرستاده شد: ' + src);
        })(sources[i]);
    }

    // ۳) پایشِ مداوم
    var tries = 0;
    var timer = setInterval(function () {
        tries++;
        announceSDK();
        if ((sdkDone.bale && sdkDone.telegram) || tries > 120) { clearInterval(timer); }
    }, 150);

    // ۴) مسیرِ جایگزین برای کلاینت‌هایی که صفحه را درونِ قاب نشان می‌دهند
    setTimeout(function () { postReady(); }, 400);
    setTimeout(function () { postReady(); }, 1500);
    setTimeout(function () { postReady(); }, 3000);
})();
</script>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover"
    >

    <meta name="theme-color" content="#031D1D">

    <title>ملکینو | انتخابی فراتر از یک ملک</title>

    <link
        href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css"
        rel="stylesheet" media="print" onload="this.media='all'"
    >

    <style>

        :root {
            --mk-teal: #052B2B;
            --mk-teal-dark: #031D1D;
            --mk-teal-deep: #021515;

            --mk-gold: #D4AF37;
            --mk-gold-light: #F0D36A;
            --mk-gold-soft: #F7E9A7;

            --mk-white: #FFFFFF;

            --mk-text: rgba(255,255,255,.92);
            --mk-muted: rgba(255,255,255,.58);
            --mk-muted-soft: rgba(255,255,255,.32);

            --mk-border: rgba(255,255,255,.075);

            --mk-ease: cubic-bezier(.22,.61,.36,1);
        }


        /* =========================================================
           RESET
        ========================================================== */

        * {
            box-sizing: border-box;
        }


        html,
        body {
            width: 100%;
            height: 100%;

            margin: 0;
            padding: 0;

            background: var(--mk-teal-deep);

            font-family: "Vazirmatn", sans-serif;
        }


        body {
            overflow: hidden;
        }


        button,
        a {
            font-family: inherit;
            -webkit-tap-highlight-color: transparent;
        }


        button {
            border: 0;
        }


        /* =========================================================
           PAGE
        ========================================================== */

        .mk-page {
            position: relative;

            width: 100%;
            height: 100svh;
            min-height: 100svh;

            overflow: hidden;

            color: var(--mk-text);

            background:

                radial-gradient(
                    circle at 78% 12%,
                    rgba(212,175,55,.095),
                    transparent 25%
                ),

                radial-gradient(
                    circle at 10% 88%,
                    rgba(255,255,255,.03),
                    transparent 24%
                ),

                linear-gradient(
                    145deg,
                    #073737 0%,
                    #052D2D 32%,
                    #031F1F 68%,
                    #021515 100%
                );
        }


        /* =========================================================
           BACKGROUND
        ========================================================== */

        .mk-grid {
            position: absolute;

            inset: 0;

            pointer-events: none;

            opacity: .22;

            background-image:

                linear-gradient(
                    rgba(255,255,255,.025) 1px,
                    transparent 1px
                ),

                linear-gradient(
                    90deg,
                    rgba(255,255,255,.025) 1px,
                    transparent 1px
                );

            background-size: 38px 38px;

            mask-image:
                linear-gradient(
                    to bottom,
                    transparent,
                    black 18%,
                    black 82%,
                    transparent
                );
        }


        .mk-glow {
            position: absolute;

            border-radius: 50%;

            pointer-events: none;
        }


        .mk-glow-one {
            width: 420px;
            height: 420px;

            top: -250px;
            right: -250px;

            background:
                radial-gradient(
                    circle,
                    rgba(212,175,55,.11),
                    transparent 68%
                );
        }


        .mk-glow-two {
            width: 330px;
            height: 330px;

            left: -240px;
            bottom: -240px;

            background:
                radial-gradient(
                    circle,
                    rgba(255,255,255,.035),
                    transparent 70%
                );
        }


        .mk-ring {
            position: absolute;

            width: min(580px, 95vw);
            height: min(580px, 95vw);

            right: -270px;
            top: 50%;

            transform: translateY(-50%);

            border-radius: 50%;

            border:
                1px solid rgba(212,175,55,.045);

            pointer-events: none;
        }


        .mk-ring::before {
            content: "";

            position: absolute;

            inset: 52px;

            border-radius: 50%;

            border:
                1px solid rgba(255,255,255,.022);
        }


        /* =========================================================
           TOP BAR
        ========================================================== */

        .mk-topbar {
            position: absolute;

            z-index: 60;

            top: 0;
            left: 0;
            right: 0;

            display: flex;

            align-items: center;
            justify-content: space-between;

            padding:
                max(16px, env(safe-area-inset-top))
                clamp(15px, 4vw, 32px)
                12px;
        }


        .mk-brand {
            display: inline-flex;

            align-items: center;

            gap: 9px;
        }


        .mk-logo-box {
            width: 43px;
            height: 43px;

            flex: 0 0 auto;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 13px;

            background:
                rgba(255,255,255,.035);

            border:
                1px solid rgba(212,175,55,.18);

            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.06);
        }


        .mk-logo-box img {
            width: 84%;
            height: 84%;

            display: block;

            object-fit: contain;
        }


        .mk-fallback-logo {
            width: 24px;
            height: 24px;

            color: var(--mk-gold-light);
        }


        .mk-brand-text {
            display: flex;

            flex-direction: column;

            gap: 2px;
        }


        .mk-brand-name {
            font-size: 14px;

            line-height: 1;

            color: var(--mk-white);

            font-weight: 900;
        }


        .mk-brand-sub {
            font-size: 8px;

            color: rgba(255,255,255,.31);
        }


        .mk-skip {
            cursor: pointer;

            padding: 8px 0;

            color:
                rgba(255,255,255,.34);

            background: transparent;

            font-size: 9px;

            transition: color .2s ease;
        }


        .mk-skip:hover {
            color:
                rgba(255,255,255,.62);
        }


        /* =========================================================
           SLIDER
        ========================================================== */

        .mk-slider {
            position: relative;

            z-index: 10;

            width: 100%;
            height: 100%;

            display: flex;

            direction: ltr;

            transition:
                transform .6s var(--mk-ease);

            touch-action: pan-y;
        }


        .mk-slide {
            width: 100%;
            height: 100%;

            flex: 0 0 100%;

            direction: rtl;

            display: flex;

            align-items: center;
            justify-content: center;

            padding:
                76px 18px 210px;
        }


        .mk-slide-inner {
            width: min(570px, 100%);

            display: flex;

            flex-direction: column;

            align-items: center;

            text-align: center;

            animation:
                mkReveal .7s var(--mk-ease) both;
        }


        @keyframes mkReveal {

            from {
                opacity: 0;

                transform:
                    translateY(20px);
            }

            to {
                opacity: 1;

                transform:
                    translateY(0);
            }
        }


        /* =========================================================
           VISUAL
        ========================================================== */

        .mk-visual {
            position: relative;

            width: min(300px, 72vw);
            aspect-ratio: 1;

            display: flex;

            align-items: center;
            justify-content: center;

            margin-bottom: 21px;
        }


        .mk-visual::before {
            content: "";

            position: absolute;

            inset: 2%;

            border-radius: 50%;

            border:
                1px solid rgba(212,175,55,.075);

            box-shadow:
                0 0 0 22px rgba(212,175,55,.012),
                0 0 0 44px rgba(212,175,55,.008);
        }


        .mk-visual::after {
            content: "";

            position: absolute;

            width: 72%;
            height: 72%;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(212,175,55,.09),
                    transparent 69%
                );
        }


        .mk-visual-card {
            position: relative;

            z-index: 3;

            width: 62%;

            aspect-ratio: 1;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 29px;

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.075),
                    rgba(255,255,255,.018)
                );

            border:
                1px solid rgba(255,255,255,.085);

            box-shadow:
                0 28px 70px rgba(0,0,0,.25),
                inset 0 1px 0 rgba(255,255,255,.065);

            backdrop-filter:
                blur(14px);

            -webkit-backdrop-filter:
                blur(14px);
        }


        .mk-visual-card img {
            width: 83%;
            height: 83%;

            object-fit: contain;

            filter:
                drop-shadow(
                    0 18px 27px rgba(0,0,0,.23)
                );
        }


        .mk-icon {
            width: 74px;
            height: 74px;

            color: var(--mk-gold-light);
        }


        /* =========================================================
           MINI DECOR
        ========================================================== */

        .mk-mini {
            position: absolute;

            z-index: 7;

            width: 44px;
            height: 44px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 50%;

            color: var(--mk-gold-light);

            background:
                rgba(3,29,29,.86);

            border:
                1px solid rgba(212,175,55,.14);

            box-shadow:
                0 12px 30px rgba(0,0,0,.17);

            animation:
                mkFloat 5s ease-in-out infinite;

            font-size: 15px;
        }


        .mk-mini-one {
            top: 8%;
            right: 7%;
        }


        .mk-mini-two {
            left: 7%;
            bottom: 11%;

            animation-delay: -2s;
        }


        .mk-mini-three {
            right: 14%;
            bottom: 4%;

            animation-delay: -3.5s;
        }


        @keyframes mkFloat {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-7px);
            }
        }


        /* =========================================================
           CONTENT
        ========================================================== */

        .mk-step {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-bottom: 10px;

            padding:
                7px 11px;

            border-radius: 999px;

            color:
                var(--mk-gold-light);

            background:
                rgba(212,175,55,.045);

            border:
                1px solid rgba(212,175,55,.14);

            font-size: 8px;

            font-weight: 850;
        }


        .mk-step-dot {
            width: 6px;
            height: 6px;

            border-radius: 50%;

            background:
                var(--mk-gold);

            box-shadow:
                0 0 10px rgba(212,175,55,.48);
        }


        .mk-title {
            margin: 0;

            color:
                var(--mk-white);

            font-size:
                clamp(28px, 8.6vw, 47px);

            line-height: 1.42;

            font-weight: 950;

            letter-spacing: -.8px;
        }


        .mk-title span {
            display: block;

            color:
                var(--mk-gold-light);
        }


        .mk-description {
            max-width: 480px;

            margin:
                10px auto 0;

            color:
                var(--mk-muted);

            font-size:
                clamp(10px, 2.9vw, 13px);

            line-height: 2;

        }

        .mk-slide:first-child .mk-title,
        .mk-slide:first-child .mk-title span {
            color: #f5c518;
        }
        .mk-slide:first-child .mk-description {
            color: #fff;
            font-size: clamp(14px, 3.8vw, 17px);
        }
        .mk-slide:first-child .mk-step,
        .mk-slide:first-child .mk-tagline {
            display: none;
        }
        .mk-slider.mk-single {
            transform: none !important;
            width: 100% !important;
        }
        .mk-slider.mk-single .mk-slide {
            min-width: 100%;
            width: 100%;
        }


        .mk-tagline {
            margin-top: 12px;

            color:
                var(--mk-muted-soft);

            font-size: 8px;
        }


        .mk-tagline strong {
            color:
                rgba(240,211,106,.68);

            font-weight: 850;
        }


        /* =========================================================
           FIXED NAVIGATION AREA
        ========================================================== */

        .mk-controls {
            position: absolute;

            z-index: 55;

            left: 0;
            right: 0;

            bottom:
                max(69px, env(safe-area-inset-bottom) + 60px);

            display: flex;

            justify-content: center;

            align-items: center;

            gap: 9px;

            padding:
                0 18px;
        }


        .mk-nav-btn {
            width: 105px;
            height: 42px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 7px;

            border-radius: 13px;

            border:
                1px solid rgba(255,255,255,.07);

            background:
                rgba(255,255,255,.035);

            color:
                rgba(255,255,255,.63);

            font-size: 9px;

            font-weight: 750;

            cursor: pointer;

            transition:
                transform .2s ease,
                background .2s ease,
                border-color .2s ease,
                opacity .2s ease;
        }


        .mk-nav-btn:hover {
            transform:
                translateY(-2px);

            background:
                rgba(255,255,255,.055);

            border-color:
                rgba(212,175,55,.16);

            color:
                rgba(255,255,255,.86);
        }


        .mk-nav-btn:active {
            transform:
                scale(.97);
        }


        .mk-nav-btn.primary {
            color:
                #183131;

            border:
                0;

            background:
                linear-gradient(
                    135deg,
                    var(--mk-gold-soft),
                    var(--mk-gold-light),
                    var(--mk-gold)
                );

            box-shadow:
                0 12px 27px rgba(212,175,55,.13);
        }


        .mk-nav-btn.primary:hover {
            background:
                linear-gradient(
                    135deg,
                    #fff0a5,
                    var(--mk-gold-light),
                    var(--mk-gold)
                );

            color:
                #122727;
        }


        .mk-nav-btn:disabled {
            cursor:
                default;

            opacity:
                .24;

            transform:
                none;

            background:
                rgba(255,255,255,.025);

            border-color:
                rgba(255,255,255,.05);

            color:
                rgba(255,255,255,.3);
        }


        .mk-nav-arrow {
            font-size: 13px;
            line-height: 1;
        }


        /* =========================================================
           BOTTOM PROGRESS
        ========================================================== */

        .mk-footer {
            position: absolute;

            z-index: 50;

            left: 0;
            right: 0;

            bottom: 0;

            padding:
                0 18px
                max(15px, env(safe-area-inset-bottom));
        }


        .mk-progress {
            width:
                min(200px, 52vw);

            height: 3px;

            margin:
                0 auto 8px;

            overflow: hidden;

            border-radius: 999px;

            background:
                rgba(255,255,255,.07);
        }


        .mk-progress-bar {
            width: 25%;
            height: 100%;

            border-radius: inherit;

            background:
                linear-gradient(
                    90deg,
                    var(--mk-gold),
                    var(--mk-gold-light)
                );

            box-shadow:
                0 0 10px rgba(212,175,55,.24);

            transition:
                width .55s var(--mk-ease);
        }


        .mk-footer-row {
            height: 21px;

            display: flex;

            align-items: center;
            justify-content: center;

            position: relative;
        }


        .mk-count {
            position: absolute;

            left: 0;

            color:
                rgba(255,255,255,.18);

            font-size: 7px;

            direction: ltr;
        }


        .mk-hint {
            color:
                rgba(255,255,255,.20);

            font-size: 7px;
        }


        /* =========================================================
           MOBILE
        ========================================================== */

        @media (max-width: 600px) {

            .mk-slide {
                padding:
                    76px 15px 202px;
            }


            .mk-visual {
                width:
                    min(270px, 69vw);

                margin-bottom: 17px;
            }


            .mk-visual-card {
                border-radius: 26px;
            }


            .mk-icon {
                width: 62px;
                height: 62px;
            }


            .mk-mini {
                width: 39px;
                height: 39px;

                font-size: 12px;
            }


            .mk-title {
                font-size:
                    clamp(26px, 8.6vw, 37px);
            }


            .mk-description {
                max-width: 340px;

                font-size: 10px;

                line-height: 1.95;
            }


            .mk-tagline {
                font-size: 7px;
            }


            .mk-controls {
                bottom:
                    max(62px, env(safe-area-inset-bottom) + 53px);

                gap: 7px;
            }


            .mk-nav-btn {
                width: 94px;
                height: 39px;

                border-radius: 12px;

                font-size: 8px;
            }


            .mk-progress {
                width: 175px;
            }


            .mk-hint {
                display: none;
            }


            .mk-brand-logo {
                width: 40px;
                height: 40px;

                border-radius: 12px;
            }


            .mk-brand-name {
                font-size: 12px;
            }


            .mk-brand-sub {
                font-size: 7px;
            }


            .mk-skip {
                font-size: 8px;
            }
        }


        /* =========================================================
           VERY SMALL PHONES
        ========================================================== */

        @media (
            max-width: 390px
        ) and (
            max-height: 720px
        ) {

            .mk-slide {
                padding-top: 66px;
                padding-bottom: 193px;
            }


            .mk-visual {
                width:
                    min(220px, 60vw);

                margin-bottom: 12px;
            }


            .mk-title {
                font-size: 25px;
            }


            .mk-description {
                font-size: 9px;
                line-height: 1.85;
            }


            .mk-tagline {
                display: none;
            }


            .mk-controls {
                bottom:
                    max(59px, env(safe-area-inset-bottom) + 50px);
            }


            .mk-nav-btn {
                width: 87px;
                height: 37px;
            }
        }


        /* =========================================================
           DESKTOP
        ========================================================== */

        @media (min-width: 800px) {

            .mk-slide {
                padding-bottom: 205px;
            }


            .mk-slide-inner {
                width: 690px;
            }


            .mk-visual {
                width: 350px;
            }


            .mk-title {
                font-size: 53px;
            }


            .mk-description {
                font-size: 13px;
            }


            .mk-nav-btn {
                width: 110px;
                height: 43px;
            }
        }


        /* =========================================================
           REDUCED MOTION
        ========================================================== */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration:
                    .01ms !important;

                animation-iteration-count:
                    1 !important;

                transition-duration:
                    .01ms !important;
            }
        }

    </style>

<!-- ورود خودکارِ مینی‌اپ تلگرام/بله -->
<script>
    window.MELKINO_LOGGED_IN = <?= $melkinoIsLoggedIn ? 'true' : 'false' ?>;
    window.MELKINO_PROFILE = <?= function_exists('melkinoJsJson') ? melkinoJsJson(function_exists('melkinoProfilePrefill') ? melkinoProfilePrefill() : ['logged_in' => false, 'name' => '', 'phone' => '']) : 'null' ?>;

    (function () {
        if (window.MELKINO_LOGGED_IN) return;

        function uaHas(p) { try { return new RegExp(p, 'i').test(navigator.userAgent || ''); } catch (e) { return false; } }

        function fromHash() {
            var hash = (location.hash || '').replace(/^#/, '');
            if (!hash) return { data: '', platform: '' };
            try {
                var params = new URLSearchParams(hash);
                var raw = params.get('tgWebAppData') || '';
                if (!raw) return { data: '', platform: '' };
                return { data: raw, platform: uaHas('bale') ? 'bale' : 'telegram' };
            } catch (e) { return { data: '', platform: '' }; }
        }

        function fromSdk() {
            var tg = '', bl = '';
            try { if (window.Telegram && window.Telegram.WebApp) tg = window.Telegram.WebApp.initData || ''; } catch (e) {}
            try { if (window.Bale && window.Bale.WebApp) bl = window.Bale.WebApp.initData || ''; } catch (e) {}
            return { telegram: tg, bale: bl };
        }

        function tryAutoLogin(platform, initData) {
            if (!initData) return;
            try {
                if (sessionStorage.getItem('melkino_autologin_done')) return;
                sessionStorage.setItem('melkino_autologin_done', '1');
            } catch (e) {}

            fetch(platform === 'bale' ? 'auth-bale.php' : 'auth-telegram.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ init_data: initData })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                var url = location.href;
                try {
                    var u = new URL(location.href);
                    if (data.login_token) u.searchParams.set('t', data.login_token);
                    url = u.toString();
                } catch (e) {
                    if (data.login_token) url += (location.search ? '&' : '?') + 't=' + encodeURIComponent(data.login_token);
                }
                location.replace(url);
            })
            .catch(function () {});
        }

        function attempt() {
            var sdk = fromSdk();
            if (sdk.telegram) return tryAutoLogin('telegram', sdk.telegram);
            if (sdk.bale) return tryAutoLogin('bale', sdk.bale);
            var h = fromHash();
            if (h.data) return tryAutoLogin(h.platform || 'telegram', h.data);
        }

        /*
        |------------------------------------------------------------------
        | نکته‌ی بسیار مهم (علتِ صفحه‌ی سفید در بله)
        |------------------------------------------------------------------
        | بارگیریِ اسکریپت‌های تلگرام/بله و فراخوانیِ ready() قبلاً همین‌جا
        | انجام می‌شد. اما این بلوک با عبارتِ
        |     if (window.MELKINO_LOGGED_IN) return;
        | شروع می‌شود؛ یعنی برای کاربری که از قبل وارد شده بود، هیچ‌وقت
        | اجرا نمی‌شد، اسکریپت‌ها لود نمی‌شدند و ready() صدا زده نمی‌شد.
        | چون کلاینتِ بله تا دریافتِ پیامِ آمادگی یک لایه‌ی سفید نشان
        | می‌دهد، نتیجه این بود: بار اول صفحه درست می‌آمد، ولی از دفعه‌ی
        | دوم به بعد فقط صفحه‌ی سفید دیده می‌شد.
        |
        | حالا آن کار در بلوکِ بالای <head> و برای همه انجام می‌شود.
        | اینجا فقط تا وقتی initData در دسترس قرار بگیرد، تلاشِ ورودِ
        | خودکار را چند بار تکرار می‌کنیم.
        |------------------------------------------------------------------
        */
        var attemptTries = 0;
        var attemptTimer = setInterval(function () {
            attemptTries++;
            try { attempt(); } catch (e) {}
            if (attemptTries > 40) { clearInterval(attemptTimer); }
        }, 250);

        setTimeout(attempt, 400);
        setTimeout(attempt, 1500);
    })();
</script>

    <!-- همگام‌سازی پروفایل: به‌محض ورود کاربر به مینی‌اپ، اطلاعات او
         در پایگاه داده ساخته/به‌روزرسانی می‌شود (حتی بدون ثبت آگهی) -->
    <script>
    (function () {
        try { if (sessionStorage.getItem('melkino_profile_synced')) return; } catch (e) {}

        function sdkData() {
            var tg = '', bl = '';
            try { if (window.Telegram && window.Telegram.WebApp) tg = window.Telegram.WebApp.initData || ''; } catch (e) {}
            try { if (window.Bale && window.Bale.WebApp) bl = window.Bale.WebApp.initData || ''; } catch (e) {}
            return { telegram: tg, bale: bl };
        }

        function hashData() {
            var h = (location.hash || '').replace(/^#/, '');
            if (!h) return '';
            try { var p = new URLSearchParams(h); return p.get('tgWebAppData') || ''; } catch (e) { return ''; }
        }

        function trySync() {
            var d = sdkData();
            var data = d.telegram || d.bale;
            var platform = d.bale ? 'bale' : 'telegram';
            if (!data) data = hashData();
            if (!data) return false;

            try { sessionStorage.setItem('melkino_profile_synced', '1'); } catch (e) {}

            fetch('profile-sync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ init_data: data, platform: platform })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success && window.MELKINO_PROFILE) {
                    window.MELKINO_PROFILE.logged_in = true;
                    if (res.name) window.MELKINO_PROFILE.name = res.name;
                    if (res.phone) window.MELKINO_PROFILE.phone = res.phone;
                }
            })
            .catch(function () {});
            return true;
        }

        if (!trySync()) {
            var tries = 0;
            var timer = setInterval(function () {
                tries++;
                if (trySync() || tries > 40) clearInterval(timer);
            }, 150);
        }
    })();
    </script>
</head>


<body>
<?php
if (is_file(__DIR__ . '/melkino-auth-gate.php')) {
    require_once __DIR__ . '/melkino-auth-gate.php';
    if (function_exists('melkinoAuthGateBoot')) {
        melkinoAuthGateBoot();
    }
}
?>

<div class="mk-page">

    <!-- Background -->
    <div class="mk-grid"></div>
    <div class="mk-glow mk-glow-one"></div>
    <div class="mk-glow mk-glow-two"></div>
    <div class="mk-ring"></div>


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <header class="mk-topbar">

        <div class="mk-brand">

            <div class="mk-logo-box">

                <?php if ($logoUrl): ?>

                    <img
                        src="<?= htmlspecialchars(
                            $logoUrl,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="لوگوی ملکینو"
                    >

                <?php else: ?>

                    <svg
                        class="mk-fallback-logo"
                        viewBox="0 0 24 24"
                        fill="none"
                    >

                        <path
                            d="M3 10L12 3L21 10V20C21 20.55 20.55 21 20 21H4C3.45 21 3 20.55 3 20V10Z"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linejoin="round"
                        />

                        <path
                            d="M9 21V13H15V21"
                            stroke="currentColor"
                            stroke-width="1.6"
                        />

                    </svg>

                <?php endif; ?>

            </div>


            <div class="mk-brand-text">

                <div class="mk-brand-name">
                    ملکینو
                </div>

                <div class="mk-brand-sub">
                    اولین پلتفرم ملکی شاهرود
                </div>

            </div>

        </div>


        <button
            type="button"
            class="mk-skip"
            id="skipBtn"
        >
            ورود به خانه
        </button>

    </header>



    <!-- صفحهٔ اول؛ بدون اسلایدر -->

    <main class="mk-slider mk-single" id="slider">

        <section class="mk-slide">

            <div class="mk-slide-inner">

                <div class="mk-visual">

                    <div class="mk-visual-card">

                        <?php if ($mkFirstLogo): ?>
                            <img src="<?= htmlspecialchars($mkFirstLogo, ENT_QUOTES, 'UTF-8') ?>" alt="ملکینو">
                        <?php else: ?>
                            <svg class="mk-icon" viewBox="0 0 24 24" fill="none">
                                <path d="M4 18H20" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                                <path d="M5 18L4 7L9 11L12 5L15 11L20 7L19 18" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                            </svg>
                        <?php endif; ?>

                    </div>

                </div>

                <h1 class="mk-title"><?= htmlspecialchars($mkFirstTitle, ENT_QUOTES, 'UTF-8') ?></h1>

                <p class="mk-description"><?= nl2br(htmlspecialchars($mkFirstText, ENT_QUOTES, 'UTF-8'), false) ?></p>

            </div>

        </section>

    </main>

    <div class="mk-controls">
        <button type="button" class="mk-nav-btn primary" id="goHomeBtn" style="flex:1;max-width:360px;margin:0 auto;">
            ورود به خانه
            <span class="mk-nav-arrow">←</span>
        </button>
    </div>

<script>
(function () {
    function goHome() { window.location.href = 'home.php'; }
    var a = document.getElementById('goHomeBtn');
    var b = document.getElementById('skipBtn');
    if (a) a.addEventListener('click', goHome);
    if (b) b.addEventListener('click', goHome);
})();
</script>

</body>
</html>