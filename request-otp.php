<?php
/*
|--------------------------------------------------------------------------
| درخواست کد یک‌بارمصرف (OTP)
|--------------------------------------------------------------------------
| اولویت ارسال: تلگرام (اگر این شماره قبلاً از تلگرام وارد شده و
| chat_id شناخته‌شده دارد) → بله (همین حالت) → پیامک (در صورت تنظیم
| سرویس) → نمایش مستقیم روی صفحه (فقط وقتی هیچ‌کدام از سه مورد بالا
| در دسترس نیست؛ برای این‌که کاربر هیچ‌وقت کاملاً گیر نکند).
|--------------------------------------------------------------------------
*/

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bot-settings.php';
require_once __DIR__ . '/security-lib.php';

// =========================================================
// ورود با شماره موبایل / کد یک‌بارمصرف
// قبلاً اینجا یک بلوک ۴۰۳ سخت‌کد بود؛ حالا ادمین از پنل
// (تب «ربات و کانال» → «روش‌های ورود») فعال/غیرفعالش می‌کند.
// =========================================================
if (!melkinoLoginMethodEnabled('sms')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ورود با شماره موبایل توسط مدیر سایت غیرفعال شده است.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/bale.php';
require_once __DIR__ . '/sms.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'روش مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

$rawPhone = trim((string)($body['phone'] ?? ''));

// نرمال‌سازی: تبدیل ارقام فارسی/عربی به انگلیسی
$phone = strtr($rawPhone, [
    '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
    '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
]);
$phone = preg_replace('/\D/', '', $phone);
if (strpos($phone, '98') === 0 && strlen($phone) === 12) {
    $phone = '0' . substr($phone, 2);
}

if (!preg_match('/^09\d{9}$/', $phone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'شماره موبایل معتبر نیست (فرمت درست: 09123456789).'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // محدودیت نرخ: حداکثر ۳ درخواست کد برای یک شماره در ۱۰ دقیقه‌ی اخیر،
    // تا از سوءاستفاده (اسپم کردن پیامک/پیام دیگران) جلوگیری شود.
    $rateCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM otp_codes WHERE phone = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)"
    );
    $rateCheck->execute([$phone]);
    if ((int)$rateCheck->fetchColumn() >= 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'تعداد درخواست کد برای این شماره زیاد بوده؛ چند دقیقه‌ی دیگر دوباره امتحان کن.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // اصلاح امنیتی: محدودیت قبلی فقط «به ازای شماره» بود، بنابراین یک
    // مهاجم می‌توانست با چرخاندن شماره‌ها، هزاران پیامک/پیام بفرستد و
    // شماره‌ها را enumerate کند. حالا یک سقف مستقل روی IP هم هست.
    if (!melkinoRateLimitHit('otp:ip:' . melkinoClientIp(), 15, 3600, true)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'درخواست‌های زیادی از این دستگاه ثبت شده؛ کمی بعد دوباره تلاش کن.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // اصلاح امنیتی: کدهای قبلیِ همین شماره باطل می‌شوند تا هم‌زمان چند
    // کد معتبر وجود نداشته باشد (کاهش سطح حملهٔ حدس زدن کد).
    $pdo->prepare('UPDATE otp_codes SET is_used = 1 WHERE phone = ? AND is_used = 0')
        ->execute([$phone]);

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 120); // اعتبار ۲ دقیقه

    // پیدا کردن کانال ارسال: آیا این شماره قبلاً با تلگرام یا بله وارد شده؟
    $known = $pdo->prepare('SELECT telegram_id, bale_id FROM users WHERE phone = ? LIMIT 1');
    $known->execute([$phone]);
    $knownRow = $known->fetch(PDO::FETCH_ASSOC) ?: [];

    $channel = 'screen';
    $telegramChatId = null;
    $baleChatId = null;
    $showOnScreen = false;
    $sendResult = null;

    if (!empty($knownRow['telegram_id'])) {
        $sendResult = telegramSendMessage($knownRow['telegram_id'], "کد ورود ملکینو: <b>$code</b>\nاین کد تا ۲ دقیقه معتبر است.");
        if ($sendResult['success']) {
            $channel = 'telegram';
            $telegramChatId = $knownRow['telegram_id'];
        }
    }

    if ($channel === 'screen' && !empty($knownRow['bale_id'])) {
        $sendResult = baleSendMessage($knownRow['bale_id'], "کد ورود ملکینو: $code\nاین کد تا ۲ دقیقه معتبر است.");
        if ($sendResult['success']) {
            $channel = 'bale';
            $baleChatId = $knownRow['bale_id'];
        }
    }

    if ($channel === 'screen') {
        $smsResult = smsSendCode($phone, $code);
        if ($smsResult['success']) {
            $channel = 'sms';
        }
    }

    if ($channel === 'screen') {
        /*
         * اصلاح امنیتی مهم (account takeover):
         * پیش‌تر وقتی هیچ کانالی پیکربندی نشده بود، کدِ ورود مستقیماً در
         * پاسخ HTTP برگردانده می‌شد. یعنی هر کسی می‌توانست برای «شمارهٔ
         * هر کاربر دیگری» کد بگیرد، کد را در همان پاسخ ببیند و وارد
         * حساب او شود.
         *
         * حالا این حالت پیش‌فرض خاموش است و فقط اگر مدیر صریحاً
         * settings → security.otp_screen_fallback را روشن کند فعال
         * می‌شود (برای محیط تست/راه‌اندازی اولیه).
         */
        /*
         * دو قفل مستقل لازم است تا کد روی صفحه نشان داده شود:
         *   ۱) کلید سمت سرور  MELKINO_ALLOW_DEBUG_OTP=1  (env یا ثابت)
         *   ۲) تنظیم پنل      security.otp_screen_fallback
         * در production کلید اول وجود ندارد، پس حتی اگر تنظیم پنل اشتباهاً
         * روشن شود، کد هرگز در پاسخ برنمی‌گردد.
         */
        $envAllowsDebugOtp = defined('MELKINO_ALLOW_DEBUG_OTP')
            ? ((string) MELKINO_ALLOW_DEBUG_OTP === '1')
            : (function_exists('melkinoEnv') && melkinoEnv('MELKINO_ALLOW_DEBUG_OTP', '0') === '1');

        $screenFallback = false;
        if ($envAllowsDebugOtp) {
            try {
                if (function_exists('dbSettingGet')) {
                    $screenFallback = (bool) dbSettingGet($pdo, 'security', 'otp_screen_fallback', false);
                }
            } catch (Throwable $eSetting) {
                $screenFallback = false;
            }
        }
        $showOnScreen = $screenFallback;
    }

    $insert = $pdo->prepare(
        melkinoOtpHasHashColumn()
            ? "INSERT INTO otp_codes (phone, code, code_hash, channel, telegram_chat_id, bale_chat_id, attempts, is_used, expires_at)
               VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)"
            : "INSERT INTO otp_codes (phone, code, channel, telegram_chat_id, bale_chat_id, attempts, is_used, expires_at)
               VALUES (?, ?, ?, ?, ?, 0, 0, ?)"
    );
    // اگر ستون code_hash روی دیتابیس وجود داشته باشد، هش هم ذخیره می‌شود.
    // تا زمانی که ستون ساخته نشده، دقیقاً مثل قبل رفتار می‌کند.
    if (melkinoOtpHasHashColumn()) {
        $insert->execute([$phone, $code, melkinoOtpHash($code), $channel, $telegramChatId, $baleChatId, $expiresAt]);
    } else {
        $insert->execute([$phone, $code, $channel, $telegramChatId, $baleChatId, $expiresAt]);
    }

    $response = [
        'success' => true,
        'channel' => $channel,
        'message' => match ($channel) {
            'telegram' => 'کد به تلگرام شما ارسال شد.',
            'bale' => 'کد به بله شما ارسال شد.',
            'sms' => 'کد با پیامک ارسال شد.',
            default => $showOnScreen
                ? 'کانال ارسال پیامی در دسترس نیست؛ کد به‌صورت موقت روی همین صفحه نمایش داده می‌شود.'
                : 'در حال حاضر امکان ارسال کد وجود ندارد. لطفاً با پشتیبانی تماس بگیرید.',
        },
    ];
    if ($showOnScreen) {
        $response['code'] = $code;
    }
    if ($channel === 'screen' && !$showOnScreen) {
        $response['success'] = false;
        http_response_code(503);
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => melkinoSafeError($e, 'request-otp', 'خطا در ارسال کد.'),
    ], JSON_UNESCAPED_UNICODE);
}
