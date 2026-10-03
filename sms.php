<?php
/*
|--------------------------------------------------------------------------
| ارسال پیامک — سرویس‌دهنده: ملی‌پیامک (melipayamak.com)
|--------------------------------------------------------------------------
| تنظیمات از پنل ادمین (تب «ربات و کانال» → کارت پنل پیامک) خوانده می‌شود و
| در صورت نبودن، از ثابت‌های config.php (SMS_API_KEY و...).
|
| قرارداد REST ملی‌پیامک:
|   POST https://rest.payamak-panel.com/api/SendSMS/SendSMS
|        username, password, to, from, text, isFlash
|   پاسخ: {"Value": "recId", "RetStatus": 1, "StrRetStatus": "Ok"}
|   ⚠️ موفقیت فقط RetStatus=1 است؛ پاسخ HTTP 200 به‌تنهایی معیار نیست.
|   خط خدماتی/الگو: POST .../BaseServiceNumber  (username,password,to,text,bodyId)
|
| اگر پنل غیرفعال باشد، فراخوان (request-otp.php) کد را مستقیم روی صفحه
| نشان می‌دهد — رفتار قبلی دست‌نخورده.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/telegram.php'; // برای melkinoHttpPost مشترک
require_once __DIR__ . '/bot-settings.php'; // برای melkinoSmsSettings

/** نقطهٔ پایانی پیش‌فرض ارسال ملی‌پیامک */
if (!defined('MELIPAYAMAK_SEND_URL')) {
    define('MELIPAYAMAK_SEND_URL', 'https://rest.payamak-panel.com/api/SendSMS/SendSMS');
}
/** نقطهٔ پایانی ارسال با الگو (خط خدماتی) ملی‌پیامک */
if (!defined('MELIPAYAMAK_BASE_URL')) {
    define('MELIPAYAMAK_BASE_URL', 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber');
}

/**
 * ترجمهٔ کدهای بازگشتی ملی‌پیامک به پیام فارسی.
 * (بر اساس جدول رسمی ReturnValue مستندات ملی‌پیامک)
 */
if (!function_exists('melipayamakErrorText')) {
    function melipayamakErrorText($ret): string
    {
        $map = [
            '110' => 'الزام استفاده از ApiKey به‌جای رمز عبور (با پشتیبانی ملی‌پیامک تماس بگیرید).',
            '109' => 'الزام تنظیم IP مجاز برای استفاده از API (در پنل ملی‌پیامک IP سرور را مجاز کنید).',
            '108' => 'IP به دلیل تلاش ناموفق مسدود شده است.',
            '-10' => 'در متغیرهای ارسالی لینک وجود دارد (مقررات الگو).',
            '-9'  => 'زمان ارسال گذشته است.',
            '-8'  => 'زمان ارسال مناسب نیست (بازهٔ مجاز ۷ صبح تا ۲۲).',
            '-7'  => 'خطای شماره فرستنده — با پشتیبانی سامانه تماس بگیرید.',
            '-6'  => 'خطای داخلی سامانه — با پشتیبانی تماس بگیرید.',
            '-5'  => 'متن ارسالی با متغیرهای الگو (پترن) همخوانی ندارد.',
            '-4'  => 'کد الگو صحیح نیست یا توسط مدیر سامانه تأیید نشده است.',
            '-3'  => 'خط ارسالی در سامانه تعریف نشده است.',
            '-2'  => 'محدودیت تعداد شماره: در هر بار ارسال فقط یک شماره مجاز است.',
            '-1'  => 'دسترسی وب‌سرویس غیرفعال است — با پشتیبانی تماس بگیرید.',
            '0'   => 'نام کاربری یا رمز عبور سامانه پیامک صحیح نیست.',
            '2'   => 'اعتبار پنل پیامک کافی نیست — حساب را شارژ کنید.',
            '6'   => 'سامانه پیامک در حال بروزرسانی است — بعداً تلاش کنید.',
            '7'   => 'متن حاوی کلمهٔ فیلترشده است — با واحد اداری سامانه تماس بگیرید.',
            '10'  => 'حساب کاربری سامانه پیامک فعال نیست.',
            '11'  => 'ارسال انجام نشد.',
            '12'  => 'مدارک کاربر در سامانه پیامک کامل نیست.',
            '19'  => 'از محدودیت ساعتی ارسال فراتر رفته‌اید.',
            '35'  => 'شمارهٔ گیرنده در لیست سیاه مخابرات است (دریافت تبلیغات بسته).',
        ];
        $key = (string)$ret;
        return $map[$key] ?? ('خطای نامشخص سامانه پیامک (کد ' . $key . ').');
    }
}

/**
 * خواندن تنظیمات موثر پیامک (پنل ادمین با fallback به ثابت‌ها).
 *
 * @return array ['enabled','api_key'(نام کاربری),'api_url','sender_line',
 *               'provider','password','otp_line','promo_line','otp_body_id']
 */
function smsEffectiveSettings(): array
{
    if (function_exists('melkinoSmsSettings')) {
        $s = melkinoSmsSettings();
        return array_merge([
            'provider'    => 'melipayamak',
            'password'    => defined('SMS_API_PASSWORD') ? (string)SMS_API_PASSWORD : '',
            'otp_line'    => '',
            'promo_line'  => '',
            'otp_body_id' => '',
        ], $s);
    }
    return [
        'enabled'     => defined('SMS_API_KEY') && (string)SMS_API_KEY !== ''
                      && defined('SMS_API_URL') && (string)SMS_API_URL !== '',
        'api_key'     => defined('SMS_API_KEY') ? (string)SMS_API_KEY : '',
        'api_url'     => defined('SMS_API_URL') ? (string)SMS_API_URL : '',
        'sender_line' => defined('SMS_SENDER_LINE') ? (string)SMS_SENDER_LINE : '',
        'provider'    => 'melipayamak',
        'password'    => defined('SMS_API_PASSWORD') ? (string)SMS_API_PASSWORD : '',
        'otp_line'    => '',
        'promo_line'  => '',
        'otp_body_id' => '',
    ];
}

/**
 * پارس پاسخ JSON ملی‌پیامک.
 * @return array ['ok'=>bool,'ret'=>string,'value'=>string,'message'=>string]
 */
if (!function_exists('melipayamakParseResponse')) {
    function melipayamakParseResponse(?string $body): array
    {
        if ($body === null || trim($body) === '') {
            return ['ok' => false, 'ret' => '', 'value' => '', 'message' => 'اتصال به سرویس پیامک برقرار نشد.'];
        }
        $j = json_decode($body, true);
        if (!is_array($j) || !array_key_exists('RetStatus', $j)) {
            // پاسخ غیرمنتظره — هیچ‌وقت «موفق» فرض نمی‌شود
            return ['ok' => false, 'ret' => '', 'value' => '', 'message' => 'پاسخ نامعتبر از سرویس پیامک.'];
        }
        $ret = (string)$j['RetStatus'];
        $value = (string)($j['Value'] ?? '');
        if ($ret === '1' && $value !== '' && $value !== '0') {
            // recId یک عدد یکتا؛ موفقیت
            return ['ok' => true, 'ret' => $ret, 'value' => $value, 'message' => 'پیامک ارسال شد.'];
        }
        return ['ok' => false, 'ret' => $ret, 'value' => $value, 'message' => melipayamakErrorText($ret)];
    }
}

/**
 * ارسال یک متن دلخواه با پنل پیامک (ملی‌پیامک).
 *
 * @param string|null $fromLine جایگذاری خط فرستنده (مثلاً خط تبلیغاتی برای کمپین)
 * @return array ['success'=>bool, 'message'=>string, 'rec_id'=>string]
 */
function smsSendText(string $phone, string $text, ?string $fromLine = null): array
{
    $settings = smsEffectiveSettings();

    if (!$settings['enabled']) {
        return ['success' => false, 'message' => 'پنل پیامک غیرفعال است. از تب «ربات و کانال» آن را فعال کن.', 'rec_id' => ''];
    }
    if ((string)$settings['api_key'] === '') {
        return ['success' => false, 'message' => 'نام کاربری سامانه پیامک تنظیم نشده است.', 'rec_id' => ''];
    }

    $provider = (string)($settings['provider'] ?? 'melipayamak');
    $from = $fromLine !== null && trim($fromLine) !== '' ? trim($fromLine) : (string)($settings['sender_line'] ?? '');

    if ($provider === 'melipayamak') {
        if ($from === '') {
            return ['success' => false, 'message' => 'شماره خط ارسال‌کننده تنظیم نشده است.', 'rec_id' => ''];
        }
        $fields = [
            'username' => (string)$settings['api_key'],
            'password' => (string)($settings['password'] ?? ''),
            'to'       => $phone,
            'from'     => $from,
            'text'     => $text,
            'isFlash'  => 'false',
        ];
        $url = trim((string)($settings['api_url'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            $url = MELIPAYAMAK_SEND_URL;
        }
        $response = melkinoHttpPost($url, http_build_query($fields));
        $parsed = melipayamakParseResponse($response);
        return ['success' => $parsed['ok'], 'message' => $parsed['message'], 'rec_id' => $parsed['ok'] ? $parsed['value'] : ''];
    }

    // حالت سازگاری: سرویس‌دهندهٔ عمومی قبلی (api_url + apikey)
    if ((string)$settings['api_url'] === '') {
        return ['success' => false, 'message' => 'سرویس پیامک تنظیم نشده است.', 'rec_id' => ''];
    }
    $postFields = http_build_query([
        'apikey'   => $settings['api_key'],
        'sender'   => $from,
        'receptor' => $phone,
        'message'  => $text,
    ]);
    $response = melkinoHttpPost($settings['api_url'], $postFields);
    if ($response === null) {
        return ['success' => false, 'message' => 'اتصال به سرویس پیامک برقرار نشد.', 'rec_id' => ''];
    }
    return ['success' => true, 'message' => 'پیامک ارسال شد.', 'rec_id' => ''];
}

/**
 * ارسال کد ورود (OTP) — طبق مقررات، از خط خدماتی/تراکنشی ارسال می‌شود.
 * اگر «کد الگو (bodyId)» تنظیم شده باشد از BaseServiceNumber استفاده می‌شود
 * (متنِ آزاد ندارد و متن پترن توسط سامانه تأیید شده است).
 *
 * @return array ['success'=>bool, 'message'=>string]
 */
function smsSendCode(string $phone, string $code): array
{
    $settings = smsEffectiveSettings();
    $bodyId = trim((string)($settings['otp_body_id'] ?? ''));
    $otpLine = trim((string)($settings['otp_line'] ?? ''));

    if ($bodyId !== '' && (string)($settings['provider'] ?? 'melipayamak') === 'melipayamak') {
        if (!$settings['enabled']) {
            return ['success' => false, 'message' => 'پنل پیامک غیرفعال است.'];
        }
        $fields = [
            'username' => (string)$settings['api_key'],
            'password' => (string)($settings['password'] ?? ''),
            'to'       => $phone,
            'text'     => $code, // مقدار متغیر الگو
            'bodyId'   => $bodyId,
        ];
        $response = melkinoHttpPost(MELIPAYAMAK_BASE_URL, http_build_query($fields));
        $parsed = melipayamakParseResponse($response);
        return ['success' => $parsed['ok'], 'message' => $parsed['message']];
    }

    // متن کد ورود قابل تغییر از پنل (تب ربات و کانال) — متغیر {code}
    $tpl = trim((string)melkinoBotSetting('sms_otp_template', ''));
    if ($tpl === '') {
        $tpl = 'کد ورود ملکینو: {code} (اعتبار ۲ دقیقه)';
    }
    $text = str_replace('{code}', $code, $tpl);
    return smsSendText($phone, $text, $otpLine !== '' ? $otpLine : null);
}
