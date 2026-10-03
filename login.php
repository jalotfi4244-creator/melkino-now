<?php
/**
|--------------------------------------------------------------------------
| ورود به ملکینو — روش‌های فعال از پنل ادمین انتخاب می‌شوند
|--------------------------------------------------------------------------
| ادمین در تب «ربات و کانال» مشخص می‌کند کدام روش‌ها فعال باشند:
| تلگرام، بله، ایتا و/یا شماره موبایل (کد یک‌بارمصرف). روش غیرفعال،
| دکمه‌اش رندر نمی‌شود و اندپوینتِ سمت سرور هم ۴۰۳ برمی‌گرداند.
|
| نکته‌ی مهم: اگر کاربر واقعاً داخل مینی‌اپ باشد (و آن روش فعال
| باشد)، ورود به‌صورت خودکار (بدون نیاز به کلیک) انجام می‌شود.
| داده‌ی هویت از سه مسیر خوانده می‌شود تا در صورت کندی/فیلتر بودنِ
| اسکریپت تلگرام هم ورود انجام شود:
|   ۱. window.Telegram.WebApp.initData  (روش استاندارد)
|   ۲. بخش هشِ آدرس (#tgWebAppData=...) در لینک مستقیم مینی‌اپ
|   ۳. window.Bale.WebApp.initData      (پیام‌رسان بله)
|   ۴. window.Eitaa.WebApp.initData     (برنامک ایتا)
|--------------------------------------------------------------------------
*/

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/bot-settings.php';

/** فقط مسیرهای داخلی برای بازگشت مجازند (جلوگیری از Open Redirect) */
function melkinoSafeRedirectTarget(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'profile.php';
    }
    if (preg_match('#^https?://#i', $value)) {
        return 'profile.php';
    }
    if (preg_match('#^[a-zA-Z0-9_./?=&%\-]+\.php#', $value) === 1) {
        return $value;
    }
    return 'profile.php';
}

// اگر از قبل هویت معتبر دارد، مستقیم به مقصد برود
$identity = melkinoCurrentIdentity();
if (!empty($identity['user_id'])) {
    header('Location: ' . melkinoSafeRedirectTarget($_GET['redirect'] ?? null));
    exit;
}

$redirectTarget = melkinoSafeRedirectTarget($_GET['redirect'] ?? null);
$botSettings = function_exists('melkinoBotSettings') ? melkinoBotSettings() : [];
$tgBot = ltrim((string)($botSettings['telegram_bot_username'] ?? ''), '@');
$baleBot = ltrim((string)($botSettings['bale_bot_username'] ?? ''), '@');
$eitaaBot = ltrim((string)($botSettings['eitaa_bot_username'] ?? ''), '@');

// روش‌های ورودِ فعال — ادمین از تب «ربات و کانال» پنل انتخاب می‌کند.
// روش غیرفعال: دکمه‌اش اصلاً رندر نمی‌شود و اندپوینتِ سمت سرور هم ۴۰۳ می‌دهد.
$loginTg    = melkinoLoginMethodEnabled('telegram');
$loginBale  = melkinoLoginMethodEnabled('bale');
$loginEitaa = melkinoLoginMethodEnabled('eitaa');
$loginSms   = melkinoLoginMethodEnabled('sms');
$loginMethods = [];
if ($loginTg)    $loginMethods[] = 'تلگرام';
if ($loginBale)  $loginMethods[] = 'بله';
if ($loginEitaa) $loginMethods[] = 'ایتا';
if ($loginSms)   $loginMethods[] = 'شماره موبایل (کد یک‌بارمصرف)';
$loginMethodsText = $loginMethods === [] ? '—' : implode('، ', $loginMethods);

// تشخیص وضعیت تنظیمات برای نمایش راهنمای دقیق‌تر
$tokenState = 'unknown';
if (function_exists('melkinoTelegramToken')) {
    $tg = (string)melkinoTelegramToken();
    $tokenState = ($tg === '' || $tg === 'توکن_ربات_تلگرام') ? 'missing' : 'ok';
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
<script>try{if(location.hash){sessionStorage.setItem('melkino_tg_hash',location.hash);}}catch(e){}</script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود به ملکینو</title>

<!-- اسکریپت‌های مینی‌اپ؛ به‌صورت async لود می‌شوند تا در صورت کندی
     یا در دسترس نبودن، صفحه معطل نماند (ورود از طریق هشِ آدرس هم
     به‌عنوان مسیر پشتیبان انجام می‌شود). -->
<script>
    window.__melkinoSdk = { telegram: false, bale: false, eitaa: false, telegramError: '', baleError: '', eitaaError: '' };

    function melkinoLoadSdk(src, onOk, onErr) {
        var s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.onload = onOk;
        s.onerror = onErr;
        document.head.appendChild(s);
    }

    // منبع اصلی و دو منبع جایگزین برای هر پیام‌رسان
    // راند ۶۲: نسخهٔ میزبانی‌شدهٔ محلی اول — حتی اگر CDN تلگرام فیلتر/کند
    // باشد، بار اولِ مینی‌اپ هم SDK آماده است و initData همان لحظه می‌رسد.
    window.__melkinoTelegramSources = [
        'https://telegram.org/js/telegram-web-app.js',
        'https://telegram.org/js/telegram-web-app.js?1'
    ];
    window.__melkinoBaleSources = [
        'https://tapi.bale.ai/miniapp.js?3',
        'https://tapi.bale.ai/miniapp.js?4'
    ];
    window.__melkinoEitaaSources = [
        'https://developer.eitaa.com/eitaa-web-app.js'
    ];

    window.__melkinoTryTelegram = function (i) {
        if (i >= window.__melkinoTelegramSources.length) return;
        melkinoLoadSdk(
            window.__melkinoTelegramSources[i],
            function () {
                window.__melkinoSdk.telegram = true;
                // بله/تلگرام تا وقتی ready() صدا زده نشود صفحه را نشان نمی‌دهند
                try { if (window.Telegram && window.Telegram.WebApp) window.Telegram.WebApp.ready(); } catch (e) {}
            },
            function () {
                window.__melkinoSdk.telegramError = window.__melkinoTelegramSources[i];
                window.__melkinoTryTelegram(i + 1);
            }
        );
    };

    window.__melkinoTryBale = function (i) {
        if (i >= window.__melkinoBaleSources.length) return;
        melkinoLoadSdk(
            window.__melkinoBaleSources[i],
            function () {
                window.__melkinoSdk.bale = true;
                try { if (window.Bale && window.Bale.WebApp && typeof window.Bale.WebApp.ready === 'function') window.Bale.WebApp.ready(); } catch (e) {}
            },
            function () {
                window.__melkinoSdk.baleError = window.__melkinoBaleSources[i];
                window.__melkinoTryBale(i + 1);
            }
        );
    };

    window.__melkinoTryEitaa = function (i) {
        if (i >= window.__melkinoEitaaSources.length) return;
        melkinoLoadSdk(
            window.__melkinoEitaaSources[i],
            function () {
                window.__melkinoSdk.eitaa = true;
                try {
                    if (window.Eitaa && window.Eitaa.WebApp && typeof window.Eitaa.WebApp.ready === 'function') {
                        window.Eitaa.WebApp.ready();
                        try { window.Eitaa.WebApp.expand(); } catch (e2) {}
                    }
                } catch (e) {}
            },
            function () {
                window.__melkinoSdk.eitaaError = window.__melkinoEitaaSources[i];
                window.__melkinoTryEitaa(i + 1);
            }
        );
    };
</script>

<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" media="print" onload="this.media='all'" />
<style>
    * { box-sizing: border-box; font-family: 'Vazirmatn', Tahoma, sans-serif; }
    body {
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(160deg, #0D1413 0%, #122320 100%); color: #F3F4F6; padding: 20px;
    }
    .login-card {
        background: #16211F; border: 1px solid #223330; border-radius: 20px;
        padding: 32px 26px; max-width: 420px; width: 100%; text-align: center;
    }
    .login-card h1 { font-size: 20px; margin: 0 0 8px; }
    .login-card p { color: #A8B1AE; font-size: 14px; margin: 0 0 22px; line-height: 1.9; }
    .login-btn {
        width: 100%; padding: 14px; border-radius: 12px; border: none;
        font-size: 15px; font-weight: 700; cursor: pointer; margin-bottom: 12px;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        text-decoration: none;
    }
    .login-btn.telegram { background: #2AABEE; color: #fff; }
    .login-btn.bale { background: #2C4A7C; color: #fff; }
    .login-btn.eitaa { background: #E67E22; color: #fff; }
    .login-btn.sms { background: #1F8A70; color: #fff; }
    .login-btn.ghost { background: transparent; color: #A8B1AE; border: 1px solid #2E423E; }
    .login-btn:disabled { opacity: .5; cursor: not-allowed; }
    .sms-box { text-align: right; }
    .sms-divider {
        display: flex; align-items: center; gap: 10px;
        color: #6B7A76; font-size: 12px; margin: 16px 0 14px;
    }
    .sms-divider::before, .sms-divider::after { content: ''; flex: 1; border-top: 1px solid #223330; }
    .sms-input {
        width: 100%; box-sizing: border-box; padding: 13px 14px; border-radius: 12px;
        border: 1px solid #2E423E; background: #101A18; color: #F3F4F6;
        font-size: 15px; margin-bottom: 10px; text-align: center; letter-spacing: 1px;
    }
    .sms-input:focus { outline: none; border-color: #1F8A70; }
    .login-msg { font-size: 13px; margin-top: 10px; min-height: 18px; line-height: 1.8; }
    .login-msg.error { color: #F87171; }
    .login-msg.success { color: #4ADE80; }
    .login-msg.info { color: #A8B1AE; }
    .login-hint {
        font-size: 12px; color: #6B7A76; line-height: 1.9; margin-top: 18px;
        border-top: 1px solid #223330; padding-top: 16px;
    }
    .box-note {
        background: #101A18; border: 1px solid #2E423E; border-radius: 14px;
        padding: 14px 16px; font-size: 13px; color: #C6CFCC; line-height: 1.9;
        text-align: right; margin-bottom: 14px;
    }
    .box-note b { color: #F3F4F6; }
    .spinner {
        width: 22px; height: 22px; border-radius: 50%;
        border: 3px solid rgba(255,255,255,.2); border-top-color: #fff;
        animation: melkino-spin .8s linear infinite; margin: 0 auto 12px;
    }
    @keyframes melkino-spin { to { transform: rotate(360deg); } }
    details.diag {
        margin-top: 18px; text-align: right; font-size: 12px; color: #6B7A76;
        border-top: 1px solid #223330; padding-top: 12px;
    }
    details.diag summary { cursor: pointer; outline: none; }
    details.diag pre {
        background: #0D1413; border: 1px solid #223330; border-radius: 10px;
        padding: 10px; overflow-x: auto; color: #9FB3AF; font-size: 11px;
        line-height: 1.7; white-space: pre-wrap; word-break: break-word;
    }
</style>
</head>
<body>
    <div class="login-card">
        <div style="font-size:40px;margin-bottom:6px;">🏠</div>
        <h1>ورود به ملکینو</h1>

        <!-- وضعیت اولیه: در حال بررسی محیط -->
        <div id="checkingBox">
            <div class="spinner"></div>
            <p style="margin:0;">در حال بررسی ورود از طریق تلگرام/بله/ایتا...</p>
        </div>

        <!-- دکمه‌های دستی (فقط روش‌های فعالِ انتخاب‌شده توسط ادمین رندر می‌شوند) -->
        <div id="manualBox" style="display:none;">
            <p>برای ورود، یکی از روش‌های زیر را انتخاب کن.</p>

            <?php if ($loginTg): ?>
            <button type="button" class="login-btn telegram" id="btnTelegram" style="display:none;" onclick="loginWith('telegram')">
                📨 ورود با تلگرام
            </button>
            <?php endif; ?>

            <?php if ($loginBale): ?>
            <button type="button" class="login-btn bale" id="btnBale" style="display:none;" onclick="loginWith('bale')">
                💬 ورود با بله
            </button>
            <?php endif; ?>

            <?php if ($loginEitaa): ?>
            <button type="button" class="login-btn eitaa" id="btnEitaa" style="display:none;" onclick="loginWith('eitaa')">
                🟠 ورود با ایتا
            </button>
            <?php endif; ?>
        </div>

        <!-- راهنمای بیرون از مینی‌اپ -->
        <div id="outsideBox" style="display:none;">
            <div class="box-note" id="outsideNote"></div>

            <?php if ($loginTg && $tgBot !== ''): ?>
                <a class="login-btn telegram" id="openTgBot" href="https://t.me/<?= htmlspecialchars($tgBot, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    📨 باز کردن ربات در تلگرام
                </a>
            <?php endif; ?>
            <?php if ($loginBale && $baleBot !== ''): ?>
                <a class="login-btn bale" href="https://ble.ir/<?= htmlspecialchars($baleBot, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    💬 باز کردن ربات در بله
                </a>
            <?php endif; ?>
            <?php if ($loginEitaa && $eitaaBot !== ''): ?>
                <a class="login-btn eitaa" href="https://eitaa.com/<?= htmlspecialchars($eitaaBot, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    🟠 باز کردن برنامه در ایتا
                </a>
            <?php endif; ?>
            <?php if ((!$loginTg || $tgBot === '') && (!$loginBale || $baleBot === '') && (!$loginEitaa || $eitaaBot === '')): ?>
                <p style="color:#F87171;font-size:13px;line-height:1.9;<?= $loginSms ? 'display:none;' : '' ?>">
                    لطفاً این صفحه را از داخل ربات تلگرام یا بله‌ی ملکینو باز کن.
                </p>
            <?php endif; ?>

            <?php if ($loginTg || $loginBale): ?>
            <button type="button" class="login-btn ghost" onclick="location.reload()">
                🔄 تلاش دوباره
            </button>
            <?php endif; ?>
        </div>

        <?php if ($loginSms): ?>
        <!-- ورود با شماره موبایل + کد یک‌بارمصرف (فقط وقتی ادمین فعال کرده باشد) -->
        <div class="sms-box" id="smsBox">
            <div class="sms-divider"><?= melkinoSvgIcon('phone') ?> ورود با شماره موبایل</div>

            <input
                type="tel"
                inputmode="numeric"
                autocomplete="tel"
                id="smsPhone"
                class="sms-input"
                dir="ltr"
                placeholder="09123456789"
                maxlength="11"
            >

            <button type="button" class="login-btn sms" id="smsSendBtn" onclick="smsRequestCode()">
                📩 دریافت کد یک‌بارمصرف
            </button>

            <div id="smsOtpRow" style="display:none;">
                <input
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    id="smsCode"
                    class="sms-input"
                    dir="ltr"
                    placeholder="کد ۶ رقمی"
                    maxlength="6"
                >
                <button type="button" class="login-btn sms" id="smsVerifyBtn" onclick="smsVerifyCode()">
                    ✅ ورود
                </button>
            </div>

            <div class="login-msg" id="smsMsg"></div>
        </div>
        <?php endif; ?>

        <div class="login-msg" id="loginMsg"></div>

        <div class="login-hint">
            ورود از طریق <?= htmlspecialchars($loginMethodsText, ENT_QUOTES, 'UTF-8') ?> امکان‌پذیر است؛<br>
            هویت شما با امضای امنِ پیام‌رسان یا کد یک‌بارمصرف تأیید می‌شود.
        </div>

        <details class="diag">
            <summary>جزئیات فنی (برای رفع اشکال)</summary>
            <pre id="diagBox">در حال جمع‌آوری اطلاعات...</pre>
        </details>
    </div>

<script>
    const REDIRECT_TARGET = <?= melkinoJsJson($redirectTarget) ?>;
    const TG_BOT    = <?= melkinoJsJson($tgBot) ?>;
    const BALE_BOT  = <?= melkinoJsJson($baleBot) ?>;
    const EITAA_BOT = <?= melkinoJsJson($eitaaBot) ?>;
    /* روش‌های ورودِ فعال (سمت سرور از تنظیمات پنل ادمین خوانده شده) */
    const LOGIN_FLAGS = <?= melkinoJsJson([
        'telegram' => (bool)$loginTg,
        'bale'     => (bool)$loginBale,
        'eitaa'    => (bool)$loginEitaa,
        'sms'      => (bool)$loginSms,
    ]) ?>;

    const el = function (id) { return document.getElementById(id); };
    let lastInitData = { telegram: '', bale: '', eitaa: '' };
    let authFinished = false;

    /* ---------------------------------------------------------
       ابزارها
       --------------------------------------------------------- */
    function userAgent() { return navigator.userAgent || ''; }
    function uaHas(pattern) { try { return new RegExp(pattern, 'i').test(userAgent()); } catch (e) { return false; } }

    /**
     * بعضی نسخه‌های تلگرام/بله در عامل کاربر (UA) اسمی از خودشان نمی‌گذارند؛
     * بنابراین علاوه بر UA، پلتفرمی که خودِ اسکریپت اعلام می‌کند هم بررسی
     * می‌شود (مقدار unknown یعنی خارج از برنامه).
     */
    function sdkPlatform() {
        let tg = '', bl = '', ei = '';
        try { if (window.Telegram && window.Telegram.WebApp) tg = String(window.Telegram.WebApp.platform || ''); } catch (e) {}
        try { if (window.Bale && window.Bale.WebApp) bl = String(window.Bale.WebApp.platform || ''); } catch (e) {}
        try { if (window.Eitaa && window.Eitaa.WebApp) ei = String(window.Eitaa.WebApp.platform || ''); } catch (e) {}
        return { telegram: tg, bale: bl, eitaa: ei };
    }

    // راند ۶۳: پل بومی تلگرام در وب‌ویو مینی‌اپ (iOS: TelegramWebviewProxy،
    // اندروید: TelegramGameProxy) — حتی وقتی UA یا پلتفرم SDK چیزی نمی‌گوید.
    function nativeTgBridge() {
        try {
            return !!(window.TelegramWebviewProxy || window.TelegramGameProxy || window.TelegramGameProxy_receiveEvent);
        } catch (e) { return false; }
    }

    function insideTelegramApp() {
        // iOS Telegram WebView may expose neither a Telegram UA token nor a
        // useful platform value. A non-empty signed initData is definitive.
        if (uaHas('telegram')) return true;
        if (nativeTgBridge()) return true;
        try {
            if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.initData) return true;
        } catch (e) {}
        const p = sdkPlatform().telegram;
        return p !== '' && p !== 'unknown';
    }

    function insideBaleApp() {
        if (uaHas('bale')) return true;
        const p = sdkPlatform().bale;
        return p !== '' && p !== 'unknown';
    }

    function insideEitaaApp() {
        if (uaHas('eitaa')) return true;
        try { if (window.Eitaa && window.Eitaa.WebApp && window.Eitaa.WebApp.initData) return true; } catch (e) {}
        const p = sdkPlatform().eitaa;
        return p !== '' && p !== 'unknown';
    }

    /**
     * تلگرام در «لینک مستقیم مینی‌اپ» داده‌های هویت را در بخش هشِ آدرس
     * قرار می‌دهد (#tgWebAppData=...). اگر اسکریپت تلگرام لود نشود
     * (مثلاً به‌دلیل فیلتر یا کندی شبکه) باز هم می‌توان از همین مسیر
     * هویت کاربر را به‌دست آورد.
     */
    function initDataFromHash() {
        const hash = (location.hash || '').replace(/^#/, '');
        const params = hash ? new URLSearchParams(hash) : new URLSearchParams(location.search);
        let raw = params.get('tgWebAppData') || '';
        if (!raw) {
            try { raw = params.get('tgWebAppData'.toLowerCase()) || ''; } catch (e) {}
        }
        if (!raw) {
            const q = new URLSearchParams(location.search);
            raw = q.get('tgWebAppData') || '';
        }
        // راند ۶۳: اگر چیزی هش را از بین برد (ریدایرکت هاست)، نسخهٔ ذخیره‌شدهٔ
        // اولیه در sessionStorage بازیابی می‌شود.
        if (!raw) {
            try {
                const saved = (sessionStorage.getItem('melkino_tg_hash') || '').replace(/^#/, '');
                if (saved) raw = new URLSearchParams(saved).get('tgWebAppData') || '';
            } catch (e) {}
        }
        if (!raw) return { data: '', platform: '' };
        const platform = uaHas('eitaa') ? 'eitaa' : (uaHas('bale') ? 'bale' : 'telegram');
        return { data: raw, platform: platform };
    }

    function sdkInitData() {
        let tg = '', bl = '', ei = '';
        try { if (window.Eitaa && window.Eitaa.WebApp) ei = window.Eitaa.WebApp.initData || ''; } catch (e) {}
        try { if (window.Telegram && window.Telegram.WebApp) tg = window.Telegram.WebApp.initData || ''; } catch (e) {}
        try { if (window.Bale && window.Bale.WebApp) bl = window.Bale.WebApp.initData || ''; } catch (e) {}
        // اگر کیت ایتا همان Telegram.WebApp را هم ساخته، هویت ایتا اولویت دارد
        if (ei && tg && ei === tg) tg = '';
        return { telegram: tg, bale: bl, eitaa: ei };
    }

    function readyWebApp() {
        try {
            if (window.Telegram && window.Telegram.WebApp) {
                window.Telegram.WebApp.ready();
                try { window.Telegram.WebApp.expand(); } catch (e) {}
            }
        } catch (e) {}
        try { if (window.Bale && window.Bale.WebApp) window.Bale.WebApp.ready(); } catch (e) {}
        try {
            if (window.Eitaa && window.Eitaa.WebApp) {
                window.Eitaa.WebApp.ready();
                try { window.Eitaa.WebApp.expand(); } catch (e2) {}
            }
        } catch (e) {}
    }

    /* ---------------------------------------------------------
       انتظار برای آماده شدن داده‌ی هویت (حداکثر ۵ ثانیه)
       --------------------------------------------------------- */
    function waitForInitData(timeoutMs) {
        return new Promise(function (resolve) {
            // اگر هیچ روشِ پیام‌رسانی فعال نباشد، معطلِ initData نمی‌مانیم
            if (!LOGIN_FLAGS.telegram && !LOGIN_FLAGS.bale && !LOGIN_FLAGS.eitaa) {
                return resolve({ platform: '', data: '', source: 'none' });
            }
            const start = Date.now();
            (function tick() {
                const sdk = sdkInitData();
                if (LOGIN_FLAGS.eitaa && sdk.eitaa) { lastInitData = sdk; return resolve({ platform: 'eitaa', data: sdk.eitaa, source: 'sdk' }); }
                if (LOGIN_FLAGS.telegram && sdk.telegram) { lastInitData = sdk; return resolve({ platform: 'telegram', data: sdk.telegram, source: 'sdk' }); }
                if (LOGIN_FLAGS.bale && sdk.bale)     { lastInitData = sdk; return resolve({ platform: 'bale',     data: sdk.bale,     source: 'sdk' }); }

                const fromHash = initDataFromHash();
                if (fromHash.data) {
                    const platform = fromHash.platform || 'telegram';
                    if (LOGIN_FLAGS[platform]) {
                        return resolve({ platform: platform, data: fromHash.data, source: 'hash' });
                    }
                }

                const elapsed = Date.now() - start;
                if (elapsed >= timeoutMs) {
                    return resolve({ platform: '', data: '', source: 'none' });
                }
                // راند ۶۲: داخل اپ پیام‌رسان منتظر می‌مانیم و وضعیت زنده نشان می‌دهیم
                if (elapsed > 4000 && (insideTelegramApp() || insideBaleApp() || insideEitaaApp())) {
                    const m = el('loginMsg');
                    if (m) { m.className = 'login-msg info'; m.textContent = 'در حال دریافت هویت از پیام‌رسان… چند لحظه دیگر صبر کن.'; }
                }
                setTimeout(tick, 120);
            })();
        });
    }

    /* ---------------------------------------------------------
       ارسال داده‌ی هویت به سرور
       --------------------------------------------------------- */
    function sendInitData(platform, initData) {
        const endpoint = platform === 'eitaa' ? 'auth-eitaa.php' : (platform === 'bale' ? 'auth-bale.php' : 'auth-telegram.php');
        const msg = el('loginMsg');
        msg.className = 'login-msg info';
        msg.textContent = 'در حال ورود...';

        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ init_data: initData })
        })
        .then(function (r) { return r.text().then(function (t) { return { r: r, t: t }; }); })
        .then(function (x) {
            var data = null;
            try { data = JSON.parse(x.t); } catch (eJ) { data = null; }
            if (data && data.success) {
                authFinished = true;
                try { sessionStorage.removeItem('melkino_auth_reload'); } catch (eC) {}
                msg.className = 'login-msg success';
                msg.textContent = 'ورود موفق — در حال انتقال...';
                goToTarget(data.login_token || '');
                return true;
            }

            /* پاسخ غیر-JSON (چالش امنیتی هاست) یا ۴۰۳/۵۰۳ → یک‌بار رفرش
               کامل خودکار؛ چالش فقط با ناوبری کامل تکمیل می‌شود. */
            if (!data || x.r.status === 403 || x.r.status === 503 || x.r.status === 502) {
                var n = 0;
                try { n = parseInt(sessionStorage.getItem('melkino_auth_reload') || '0', 10) || 0; } catch (eN) {}
                if (n < 1) {
                    try { sessionStorage.setItem('melkino_auth_reload', String(n + 1)); } catch (eS) {}
                    msg.className = 'login-msg info';
                    msg.textContent = 'در حال تازه‌سازی امن اتصال…';
                    setTimeout(function () { location.reload(); }, 600);
                    return false;
                }
            }

            // ورود ناموفق: پیام سرور + امکان تلاش دوباره
            authFinished = false;
            msg.className = 'login-msg error';
            let text = (data && data.message) ? data.message : ('ورود ناموفق بود. (کد ' + x.r.status + ')');
            if (text.indexOf('منقضی') !== -1 || text.indexOf('معتبر نیست') !== -1) {
                text += ' — مینی‌اپ را کامل ببند و دوباره باز کن تا اعتبارنامه‌ی تازه بگیری.';
            }
            msg.innerHTML = text.replace(/</g, '&lt;')
                + '<br><a href="#" onclick="try{sessionStorage.removeItem(\'melkino_auth_reload\');}catch(e){} location.reload();return false;" style="color:#7FD1BE;">🔄 تلاش دوباره</a>';
            return false;
        })
        .catch(function () {
            msg.className = 'login-msg error';
            msg.innerHTML = 'خطا در ارتباط با سرور. اتصال اینترنت را بررسی کن.'
                + '<br><a href="#" onclick="try{sessionStorage.removeItem(\'melkino_auth_reload\');}catch(e){} location.reload();return false;" style="color:#7FD1BE;">🔄 تلاش دوباره</a>';
            return false;
        });
    }

    function goToTarget(token) {
        let url = REDIRECT_TARGET;
        try {
            const u = new URL(REDIRECT_TARGET, location.href);
            if (token) {
                u.searchParams.set('t', token);
                try { localStorage.setItem('melkino_login_token', token); } catch (e) {}
            }
            url = u.toString();
        } catch (e) {
            if (token) url = REDIRECT_TARGET + (REDIRECT_TARGET.indexOf('?') === -1 ? '?' : '&') + 't=' + encodeURIComponent(token);
        }
        setTimeout(function () { location.replace(url); }, 350);
    }

    function loginWith(platform) {
        const sdk = sdkInitData();
        const fromHash = initDataFromHash();
        const data = (platform === 'eitaa')
            ? (sdk.eitaa || (fromHash.platform === 'eitaa' ? fromHash.data : ''))
            : (platform === 'bale')
            ? (sdk.bale || (fromHash.platform === 'bale' ? fromHash.data : ''))
            : (sdk.telegram || (fromHash.platform === 'telegram' ? fromHash.data : ''));

        if (!data) {
            const msg = el('loginMsg');
            msg.className = 'login-msg error';
            const names = { eitaa: 'ایتا', bale: 'بله', telegram: 'تلگرام' };
            msg.textContent = 'داده‌ی هویت از ' + (names[platform] || platform) + ' دریافت نشد.';
            return;
        }
        sendInitData(platform, data);
    }

    /* ---------------------------------------------------------
       نمایش راهنما وقتی داده‌ی هویت در دسترس نیست
       --------------------------------------------------------- */
    function showOutsideGuide() {
        el('checkingBox').style.display = 'none';
        el('outsideBox').style.display = 'block';

        const note = el('outsideNote');
        const msg = el('loginMsg');

        if (!LOGIN_FLAGS.telegram && !LOGIN_FLAGS.bale && !LOGIN_FLAGS.eitaa) {
            note.innerHTML = 'ورود با پیام‌رسان‌ها توسط مدیر سایت غیرفعال شده است.'
                + (LOGIN_FLAGS.sms ? '<br>می‌توانی با <b>شماره موبایل و کد یک‌بارمصرف</b> وارد شوی (فرم پایین).' : '');
            msg.className = 'login-msg info';
            msg.textContent = '';
        } else if (insideTelegramApp()) {
            const inMini = nativeTgBridge();
            if (inMini) {
                note.innerHTML = '<b>داخل مینی‌اپ تلگرام هستی؛ ورود خودکار در حال تلاش است.</b><br>'
                    + 'اگر تا چند لحظهٔ دیگر خودکار وارد نشد، <b>بدون بستن صفحه</b> '
                    + (LOGIN_FLAGS.sms
                        ? 'از فرم <b>شماره موبایل و کد یک‌بارمصرف</b> (پایین همین صفحه) وارد شو.'
                        : 'روی «تلاش دوباره» بزن.')
                    + '<br><span style="color:#9ad">به‌محض رسیدن هویت تلگرام، ورود به‌صورت خودکار انجام می‌شود.</span>';
                msg.className = 'login-msg info';
                msg.textContent = 'در حال ورود خودکار از مینی‌اپ…';
                if (LOGIN_FLAGS.sms) {
                    const sb = el('smsBox');
                    if (sb) { sb.style.display = 'block'; setTimeout(function () { sb.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 400); }
                }
            } else {
                note.innerHTML = '<b>شما داخل تلگرام هستید، ولی این صفحه به‌عنوان «مینی‌اپ» باز نشده است.</b><br>'
                    + 'تلگرام فقط زمانی هویت شما را به سایت می‌دهد که برنامه را از '
                    + '<b>دکمه‌ی منوی ربات</b> (یا دکمه‌ی شیشه‌ای کنار پیام‌ها) باز کنید؛ '
                    + 'باز کردنِ لینک در مرورگر داخلی تلگرام کافی نیست.<br>'
                    + '<span style="color:#7FD1BE;">راه حل: ربات را باز کن و از دکمه‌ی «ورود به ملکینو» وارد شو.</span>'
                    + '<br><span style="color:#9ad">لازم نیست صفحه را ببندی: دریافت هویت در پس‌زمینه ادامه دارد و به‌محض آماده شدن، ورود خودکار انجام می‌شود.</span>';
                msg.className = 'login-msg info';
                msg.textContent = 'هویت تلگرام دریافت نشد (مرورگر داخلی بدون مجوز مینی‌اپ).';
            }
        } else if (insideBaleApp()) {
            note.innerHTML = '<b>شما داخل بله هستید، ولی این صفحه به‌عنوان «مینی‌اپ» باز نشده است.</b><br>'
                + 'برای ورود، برنامه را از <b>دکمه‌ی منوی ربات بله</b> باز کنید.';
            msg.className = 'login-msg info';
            msg.textContent = 'هویت بله دریافت نشد.';
        } else if (insideEitaaApp()) {
            note.innerHTML = '<b>شما داخل ایتا هستید، ولی این صفحه به‌عنوان «برنامک» باز نشده است.</b><br>'
                + 'برای ورود، ملکینو را از داخل <b>برنامه/ربات ایتا</b> باز کنید.';
            msg.className = 'login-msg info';
            msg.textContent = 'هویت ایتا دریافت نشد.';
        } else {
            note.innerHTML = 'این صفحه در یک مرورگر معمولی باز شده است.<br>'
                + 'برای ورود باید برنامه را از داخل <b>ربات تلگرام، بله یا ایتا</b> باز کنید.';
            msg.className = 'login-msg info';
            msg.textContent = 'خارج از تلگرام/بله/ایتا هستید.';
        }

        // راند ۶۲: حتی پس از نمایش راهنما، دریافت هویت در پس‌زمینه ادامه دارد؛
        // به‌محض رسیدن initData (SDK دیر لود شود)، خودکار ورود انجام می‌شود
        // و نیازی به بستن و باز کردن مجدد مینی‌اپ نیست.
        if (!window.__melkinoAutoRetry) {
            window.__melkinoAutoRetry = true;
            const t0 = Date.now();
            const iv = setInterval(function () {
                if (authFinished || Date.now() - t0 > 90000) { clearInterval(iv); return; }
                const sdk = sdkInitData();
                const h = initDataFromHash();
                const plat = sdk.eitaa ? 'eitaa' : (sdk.telegram ? 'telegram' : (sdk.bale ? 'bale' : (h.data ? (h.platform || 'telegram') : '')));
                const data = sdk.eitaa || sdk.telegram || sdk.bale || h.data;
                if (plat && data && LOGIN_FLAGS[plat]) {
                    clearInterval(iv);
                    el('checkingBox').style.display = 'none';
                    const ob = el('outsideBox'); if (ob) ob.style.display = 'none';
                    const mb = el('manualBox'); if (mb) mb.style.display = 'none';
                    sendInitData(plat, data);
                }
            }, 1000);
        }

        // اگر رباتی تنظیم شده باشد، لینک باز کردنِ مینی‌اپ را مستقیم می‌کنیم
        const tgLink = el('openTgBot');
        if (tgLink && TG_BOT) {
            tgLink.href = 'https://t.me/' + TG_BOT;
            tgLink.target = '_top';
        }
    }

    function showManualButtons(platform) {
        el('checkingBox').style.display = 'none';
        const box = el('manualBox');
        if (box) box.style.display = 'block';
        // دکمه‌ی روشِ غیرفعال اصلاً رندر نشده، پس null-safe
        const tgBtn = el('btnTelegram');
        const blBtn = el('btnBale');
        const eiBtn = el('btnEitaa');
        if (platform === 'telegram' && tgBtn) tgBtn.style.display = 'flex';
        if (platform === 'bale' && blBtn) blBtn.style.display = 'flex';
        if (platform === 'eitaa' && eiBtn) eiBtn.style.display = 'flex';
    }

    /* ---------------------------------------------------------
       نمایش جزئیات فنی
       --------------------------------------------------------- */
    function renderDiag(extra) {
        const info = {
            'آدرس صفحه': location.href.replace(/#.*$/, '(هش حذف شد)'),
            'بخش هش دارد': (location.hash || '').length > 0 ? 'بله (' + location.hash.length + ' کاراکتر)' : 'خیر',
            'اسکریپت تلگرام': window.__melkinoSdk.telegram ? 'لود شد' : (window.__melkinoSdk.telegramError ? 'خطا در لود' : 'لود نشد (بی‌نیاز اگر هش موجود باشد)'),
            'اسکریپت بله': window.__melkinoSdk.bale ? 'لود شد' : (window.__melkinoSdk.baleError ? 'خطا در لود' : 'لود نشد'),
            'Telegram.WebApp': (function () { try { return !!(window.Telegram && window.Telegram.WebApp); } catch (e) { return 'خطا'; } })(),
            'پلتفرم (اسکریپت)': (function () { const p = sdkPlatform(); return ('تلگرام: ' + (p.telegram || '—') + ' | بله: ' + (p.bale || '—')); })(),
            'داخل تلگرام؟': insideTelegramApp() ? 'بله' : 'خیر',
            'داخل بله؟': insideBaleApp() ? 'بله' : 'خیر',
            'initData (تلگرام)': lastInitData.telegram ? 'موجود (' + lastInitData.telegram.length + ' کاراکتر)' : 'خالی',
            'initData (بله)': lastInitData.bale ? 'موجود (' + lastInitData.bale.length + ' کاراکتر)' : 'خالی',
            'initData از هش': (function () { const h = initDataFromHash(); return h.data ? 'موجود (' + h.data.length + ')' : 'خالی'; })(),
            'عامل کاربر (UA)': userAgent(),
            'پروتکل': location.protocol,
            'توکن ربات (سرور)': <?= json_encode($tokenState === 'ok' ? 'تنظیم شده' : ($tokenState === 'missing' ? 'تنظیم نشده ⚠️' : 'نامشخص'), JSON_UNESCAPED_UNICODE) ?>
        };
        if (extra) { for (const k in extra) { info[k] = extra[k]; } }

        let text = '';
        for (const k in info) { text += k + ': ' + info[k] + '\n'; }
        el('diagBox').textContent = text;
    }

    /* ---------------------------------------------------------
       اجرا
       --------------------------------------------------------- */
    /* ---------------------------------------------------------
       ورود با شماره موبایل + کد یک‌بارمصرف (فقط اگر ادمین فعال کرده باشد)
       --------------------------------------------------------- */
    function smsSetMsg(text, cls) {
        const box = el('smsMsg');
        if (!box) return;
        box.className = 'login-msg' + (cls ? ' ' + cls : '');
        box.textContent = text || '';
    }

    function normalizePhone(raw) {
        const fa = { '۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9',
                     '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9' };
        let p = String(raw || '').replace(/[۰-۹٠-٩]/g, function (d) { return fa[d] || d; });
        p = p.replace(/\D/g, '');
        if (p.indexOf('98') === 0 && p.length === 12) p = '0' + p.slice(2);
        return p;
    }

    function smsRequestCode() {
        const phoneEl = el('smsPhone');
        const btn = el('smsSendBtn');
        if (!phoneEl) return;
        const phone = normalizePhone(phoneEl.value);
        if (!/^09\d{9}$/.test(phone)) {
            smsSetMsg('شماره موبایل معتبر نیست (مثال: 09123456789).', 'error');
            return;
        }
        phoneEl.value = phone;
        if (btn) { btn.disabled = true; btn.textContent = '⏳ در حال ارسال کد...'; }
        smsSetMsg('', '');

        fetch('request-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                const row = el('smsOtpRow');
                if (row) row.style.display = 'block';
                let text = data.message || 'کد ارسال شد.';
                if (data.code) text += ' کد شما: ' + data.code;
                smsSetMsg(text, 'info');
                const codeEl = el('smsCode');
                if (codeEl) codeEl.focus();
            } else {
                smsSetMsg((data && data.message) || 'ارسال کد ناموفق بود.', 'error');
            }
        })
        .catch(function () { smsSetMsg('خطا در ارتباط با سرور.', 'error'); })
        .finally(function () {
            if (btn) { btn.disabled = false; btn.textContent = '📩 دریافت کد یک‌بارمصرف'; }
        });
    }

    function smsVerifyCode() {
        const phoneEl = el('smsPhone');
        const codeEl = el('smsCode');
        const btn = el('smsVerifyBtn');
        if (!phoneEl || !codeEl) return;
        const phone = normalizePhone(phoneEl.value);
        const code = codeEl.value.replace(/\D/g, '');
        if (!/^09\d{9}$/.test(phone)) { smsSetMsg('شماره موبایل معتبر نیست.', 'error'); return; }
        if (!/^\d{6}$/.test(code)) { smsSetMsg('کد باید ۶ رقم باشد.', 'error'); return; }
        if (btn) { btn.disabled = true; btn.textContent = '⏳ در حال بررسی...'; }
        smsSetMsg('', '');

        fetch('verify-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: phone, code: code })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                smsSetMsg('ورود موفق — در حال انتقال...', 'success');
                goToTarget('');
                return;
            }
            smsSetMsg((data && data.message) || 'کد نامعتبر بود.', 'error');
        })
        .catch(function () { smsSetMsg('خطا در ارتباط با سرور.', 'error'); })
        .finally(function () {
            if (btn) { btn.disabled = false; btn.textContent = '✅ ورود'; }
        });
    }

    (function start() {
        // فقط اسکریپتِ روش‌های فعال لود می‌شود (غیرِ مسدودکننده)
        if (LOGIN_FLAGS.telegram) { try { window.__melkinoTryTelegram(0); } catch (e) {} }
        if (LOGIN_FLAGS.bale)     { try { window.__melkinoTryBale(0); } catch (e) {} }
        if (LOGIN_FLAGS.eitaa)    { try { window.__melkinoTryEitaa(0); } catch (e) {} }

        readyWebApp();

        // iOS Telegram: capture initData immediately after ready(). Do not
        // wait for the UA/platform heuristic; initData itself is authoritative.
        try {
            const immediate = sdkInitData();
            if (immediate.telegram && LOGIN_FLAGS.telegram) {
                lastInitData = immediate;
                sendInitData('telegram', immediate.telegram);
                return;
            }
        } catch (e) {}

        renderDiag();

        // اگر هیچ روشِ پیام‌رسانی فعال نباشد، مستقیم راهنما را نشان بده
        if (!LOGIN_FLAGS.telegram && !LOGIN_FLAGS.bale && !LOGIN_FLAGS.eitaa) {
            showOutsideGuide();
            return;
        }

        waitForInitData((insideTelegramApp() || insideBaleApp() || insideEitaaApp()) ? 20000 : 5000).then(function (found) {
            renderDiag({ 'نتیجه‌ی جستجو': found.source === 'none' ? 'هیچ هویتی یافت نشد' : ('یافت شد از طریق ' + found.source) });

            if (found.data) {
                lastInitData[found.platform] = found.data;
                el('checkingBox').style.display = 'block';
                const msg = el('loginMsg');
                msg.className = 'login-msg info';
                msg.textContent = 'در حال ورود خودکار...';
                sendInitData(found.platform, found.data);
                return;
            }

            showOutsideGuide();
        });
    })();
</script>
</body>
</html>
