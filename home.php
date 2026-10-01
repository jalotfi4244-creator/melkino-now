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
    /*
     * IMPORTANT — Telegram Mini App / iOS:
     *
     * initData is available to JavaScript inside the Mini App and is NOT
     * available to PHP on the first HTTP request. The old code immediately
     * returned a 302 to login.php, which could lose the Mini App context and
     * leave login.php with an empty Telegram.WebApp.initData.
     *
     * Therefore, for an unauthenticated page we first render a tiny bootstrap
     * page. It lets Telegram/Bale/Eitaa provide initData to JavaScript, sends
     * it to the existing server-side auth endpoint, and only then redirects.
     * A normal browser that has no Mini App identity falls back to login.php.
     */
    $here = (string) ($_SERVER['REQUEST_URI'] ?? $_mkPage);
    $here = preg_replace('#^/+#', '', $here) ?? $_mkPage;
    if ($here === '' || strpos($here, 'login.php') === 0) {
        $here = 'home.php';
    }
    $bootstrapTarget = $here;
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ورود به ملکینو</title>
        <style>
            html,body{margin:0;min-height:100%;background:#0d1917;color:#e8eeec;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display",Tahoma,Arial,sans-serif}
            body{display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box}
            .mk-auth{width:min(440px,100%);text-align:center;background:#10201d;border:1px solid #29413b;border-radius:22px;padding:30px 22px;box-sizing:border-box;box-shadow:0 18px 60px rgba(0,0,0,.28)}
            .mk-logo{font-size:42px;margin-bottom:10px}.mk-title{font-size:20px;font-weight:700;margin:0 0 10px}.mk-msg{font-size:14px;line-height:2;color:#aebbb7}.mk-spin{width:24px;height:24px;border:3px solid rgba(255,255,255,.18);border-top-color:#fff;border-radius:50%;animation:mkspin .8s linear infinite;margin:0 auto 15px}@keyframes mkspin{to{transform:rotate(360deg)}}
            .mk-fallback{display:none;margin-top:18px}.mk-btn{display:block;text-decoration:none;border:0;border-radius:13px;padding:13px 16px;background:#2aabee;color:#fff;font-weight:700;font-size:15px}
        </style>
    </head>
    <body>
        <div class="mk-auth">
            <div class="mk-logo">🏠</div>
            <h1 class="mk-title">در حال ورود به ملکینو</h1>
            <div class="mk-spin"></div>
            <div id="mkMsg" class="mk-msg">در حال دریافت هویت امن از پیام‌رسان…</div>
            <div id="mkFallback" class="mk-fallback">
                <a class="mk-btn" href="login.php?redirect=<?= rawurlencode($bootstrapTarget) ?>">ادامه به صفحه ورود</a>
            </div>
        </div>
        <script>
        (function () {
            'use strict';
            var TARGET = <?= json_encode($bootstrapTarget, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            var msg = document.getElementById('mkMsg');
            var fallback = document.getElementById('mkFallback');
            var finished = false;

            function setMsg(t){ if(msg) msg.textContent=t; }
            function goLogin(){
                if(finished) return;
                finished = true;
                var u='login.php?redirect='+encodeURIComponent(TARGET);
                window.location.replace(u);
            }
            function getTg(){
                try { return window.Telegram && window.Telegram.WebApp ? String(window.Telegram.WebApp.initData || '') : ''; } catch(e){ return ''; }
            }
            function getBale(){
                try { return window.Bale && window.Bale.WebApp ? String(window.Bale.WebApp.initData || '') : ''; } catch(e){ return ''; }
            }
            function getEitaa(){
                try { return window.Eitaa && window.Eitaa.WebApp ? String(window.Eitaa.WebApp.initData || '') : ''; } catch(e){ return ''; }
            }
            function hashData(){
                try {
                    var h=(location.hash||'').replace(/^#/, '');
                    var p=h ? new URLSearchParams(h) : new URLSearchParams(location.search);
                    return p.get('tgWebAppData') || '';
                } catch(e){ return ''; }
            }
            function auth(platform,data){
                if(!data || finished) return;
                finished=true;
                setMsg('هویت دریافت شد؛ در حال ورود…');
                var endpoint = platform==='eitaa' ? 'auth-eitaa.php' : (platform==='bale' ? 'auth-bale.php' : 'auth-telegram.php');
                fetch(endpoint,{
                    method:'POST',
                    credentials:'same-origin',
                    headers:{'Content-Type':'application/json','Accept':'application/json'},
                    body:JSON.stringify({init_data:data})
                }).then(function(r){ return r.json(); }).then(function(d){
                    if(d && d.success){
                        var u=TARGET;
                        try{
                            var url=new URL(TARGET,location.href);
                            if(d.login_token) url.searchParams.set('t',d.login_token);
                            u=url.toString();
                        }catch(e){}
                        window.location.replace(u);
                        return;
                    }
                    finished=false;
                    setMsg((d&&d.message)?d.message:'ورود خودکار انجام نشد؛ در حال انتقال به صفحه ورود…');
                    setTimeout(goLogin,900);
                }).catch(function(){
                    finished=false;
                    setMsg('اتصال احراز هویت برقرار نشد؛ در حال انتقال به صفحه ورود…');
                    setTimeout(goLogin,900);
                });
            }
            function ready(){
                try{ if(window.Telegram&&window.Telegram.WebApp){ window.Telegram.WebApp.ready(); try{window.Telegram.WebApp.expand();}catch(e){} } }catch(e){}
                try{ if(window.Bale&&window.Bale.WebApp&&typeof window.Bale.WebApp.ready==='function') window.Bale.WebApp.ready(); }catch(e){}
                try{ if(window.Eitaa&&window.Eitaa.WebApp&&typeof window.Eitaa.WebApp.ready==='function') window.Eitaa.WebApp.ready(); }catch(e){}
            }
            function check(){
                if(finished) return;
                ready();
                var ei=getEitaa(), tg=getTg(), bl=getBale(), h=hashData();
                if(ei){auth('eitaa',ei);return;}
                if(tg){auth('telegram',tg);return;}
                if(bl){auth('bale',bl);return;}
                if(h){auth('telegram',h);return;}
            }

            // Telegram's official SDK. It is intentionally loaded directly;
            // there is no local telegram-web-app.js in this 3-file package.
            var s=document.createElement('script');
            s.src='https://telegram.org/js/telegram-web-app.js';
            s.async=true;
            s.onload=function(){ready();check();};
            document.head.appendChild(s);

            // Give the SDK/bridge time to initialize on iOS Telegram WebView.
            var started=Date.now();
            var timer=setInterval(function(){
                check();
                if(finished){clearInterval(timer);return;}
                if(Date.now()-started>9000){
                    clearInterval(timer);
                    setMsg('اطلاعات مینی‌اپ دریافت نشد.');
                    if(fallback) fallback.style.display='block';
                    setTimeout(goLogin,3500);
                }
            },150);
            check();
        })();
        </script>
    </body>
    </html>
    <?php
    exit;
}
unset($_mkPage, $_mkAllow);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/promotions.php';
require_once __DIR__ . '/card-display.php';
require_once __DIR__ . '/ad-cards-bootstrap.php';

// راند ۲۹: سیستم مدیریت فیلدهای نمایشی کارت (registry + تنظیمات + draft پیش‌نمایش پنل)
require_once __DIR__ . '/field-display.php';
$fdHome = melkinoFdPreviewSettings('home');


/* =====================================================
   Fallback: getFirstImage
   ===================================================== */

if (!function_exists('getFirstImage')) {

    function getFirstImage($selectedImages)
    {
        $images = [];

        $collect = static function ($value) use (&$images, &$collect) {

            if (is_string($value)) {

                $value = trim($value);

                if ($value === '') {
                    return;
                }

                $decoded = json_decode($value, true);

                if (is_array($decoded)) {
                    $collect($decoded);
                    return;
                }

                if (
                    strpos($value, ',') !== false ||
                    strpos($value, '|') !== false
                ) {

                    foreach (
                        preg_split('/[,|]+/', $value)
                        as $part
                    ) {
                        $collect($part);
                    }

                    return;
                }

                $images[] = $value;
                return;
            }


            if (is_array($value)) {

                foreach ($value as $item) {

                    if (is_array($item)) {

                        foreach (
                            ['url', 'path', 'src', 'image', 'file']
                            as $key
                        ) {

                            if (isset($item[$key])) {

                                $collect($item[$key]);
                                break;
                            }
                        }

                    } else {

                        $collect($item);
                    }
                }
            }
        };


        $collect($selectedImages);


        foreach ($images as $image) {

            $image = trim(
                str_replace('\\', '/', (string)$image)
            );

            if ($image === '') {
                continue;
            }


            /*
             * URL کامل
             */
            if (
                preg_match(
                    '#^https?://#i',
                    $image
                )
            ) {
                return $image;
            }


            $relative = ltrim(
                $image,
                '/'
            );

            $candidates = [
                $relative
            ];


            if (
                stripos($relative, 'melkino/') === 0
            ) {

                $candidates[] =
                    substr($relative, 8);
            }


            if (
                stripos($relative, './') === 0
            ) {

                $candidates[] =
                    ltrim(
                        substr($relative, 2),
                        '/'
                    );
            }


            foreach (
                array_unique($candidates)
                as $candidate
            ) {

                if (
                    file_exists(
                        __DIR__ . '/' . $candidate
                    )
                ) {
                    return $candidate;
                }
            }
        }


        return null;
    }
}


/* =====================================================
   دریافت آگهی‌های منتشر شده
   ===================================================== */

$publishedAds = [];


$isMockMode =
    defined('MOCK_MODE')
        ? MOCK_MODE
        : true;


if ($isMockMode) {

    /* =================================================
       MOCK MODE
       ================================================= */

    $jsonFile =
        __DIR__ . '/ads.json';

    $adsFromJson = [];


    if (file_exists($jsonFile)) {

        $jsonContent =
            file_get_contents($jsonFile);

        $decodedAds =
            json_decode(
                $jsonContent,
                true
            );

        if (is_array($decodedAds)) {
            $adsFromJson =
                $decodedAds;
        }
    }


    foreach ($adsFromJson as $ad) {

        if (
            ($ad['status'] ?? '') !==
            'published'
        ) {
            continue;
        }


        $selectedImages =
            $ad['selected_images']
            ?? ($ad['selectedImages'] ?? []);


        if (is_string($selectedImages)) {

            $selectedImages =
                json_decode(
                    $selectedImages,
                    true
                ) ?: [];
        }


        if (!is_array($selectedImages)) {
            $selectedImages = [];
        }


        foreach (
            [
                'images',
                'photos',
                'gallery',
                'gallery_images',
                'image'
            ] as $imageField
        ) {

            if (
                array_key_exists(
                    $imageField,
                    $ad
                ) &&
                !empty($ad[$imageField])
            ) {

                $source =
                    $ad[$imageField];


                if (is_array($source)) {

                    $selectedImages =
                        array_merge(
                            $selectedImages,
                            $source
                        );

                } elseif (is_string($source)) {

                    $decodedSource =
                        json_decode(
                            $source,
                            true
                        );

                    $selectedImages =
                        array_merge(
                            $selectedImages,
                            is_array($decodedSource)
                                ? $decodedSource
                                : [$source]
                        );
                }
            }
        }


        $details =
            $ad['property_details']
            ?? [];


        if (is_string($details)) {

            $details =
                json_decode(
                    $details,
                    true
                ) ?: [];
        }


        if (!is_array($details)) {
            $details = [];
        }


        $amenities =
            $ad['amenities']
            ?? [];


        if (is_string($amenities)) {

            $amenities =
                json_decode(
                    $amenities,
                    true
                ) ?: [];
        }


        if (!is_array($amenities)) {
            $amenities = [];
        }


        $deposit =
            $ad['deposit']
            ?? '';

        $rentMonthly =
            $ad['rent_monthly']
            ?? '';

        $priceSell =
            $ad['price_sell']
            ?? '';

        $totalPrice =
            $ad['total_price']
            ?? '';

        // راند ۷۳: قیمت از همهٔ ستون‌های قیمتی — شامل ستون مستقل price در اسکیمای لگاسی
        // (منطق مشترک melkinoAdDisplayPrice؛ مقادیر صفر مثل '0.00' نادیده می‌شوند)
        $displayPrice = melkinoAdDisplayPrice($ad);
        // خواندن کلید نخورده
        $isNotKeyed = 0;
        if (isset($ad['is_not_keyed'])) {
            $isNotKeyed = (int)$ad['is_not_keyed'];
        } elseif (isset($details['is_not_keyed'])) {
            $isNotKeyed = (int)$details['is_not_keyed'];
        } elseif (isset($ad['key_not_turned'])) {
            $isNotKeyed = (int)$ad['key_not_turned'];
        }


        $publishedAds[] = [

            'id' =>
                (string)(
                    $ad['id'] ?? ''
                ),

            'title' =>
                $ad['title']
                ?? 'ملک بدون عنوان',

            'transaction_type' =>
                $ad['transaction_type']
                ?? $ad['transactionType']
                ?? 'فروش',

            'property_type' =>
                $ad['property_type']
                ?? $ad['propertyType']
                ?? 'آپارتمان',

            'price' =>
                $displayPrice,

            'location' =>
                $ad['location']
                ?? '',

            'description' =>
                $ad['description']
                ?? '',

            'selected_images' =>
                $selectedImages,

            'details' =>
                $details,

            'amenities' =>
                $amenities,

            'created_at' =>
                $ad['created_at']
                ?? date('Y-m-d H:i:s'),

            'deposit' =>
                $deposit,

            'rent_monthly' =>
                $rentMonthly,

            'price_sell' =>
                $priceSell,

            'display_price' =>
                $displayPrice,

            'price_hidden' =>
                !empty($ad['price_hidden']),

            'total_price' =>
                $totalPrice,

            'full_rent' =>
                $ad['full_rent']
                ?? '',

            // فیلدهای وام (خط دوم قیمت + حباب «وام» روی کارت)
            'has_loan' =>
                $ad['has_loan']
                ?? 0,

            'loan_amount' =>
                $ad['loan_amount']
                ?? '',

            'exchange_interested' =>
                $ad['exchange_interested']
                ?? 0,

            'full_rent_enabled' =>
                $ad['full_rent_enabled']
                ?? '0',

            'is_not_keyed' =>
                $isNotKeyed,
            // فیلدهای خام برای رندر پویای کارت (راند ۲۱)
            'property_details' => $ad['property_details'] ?? null,
            'deed_type'        => $ad['deed_type'] ?? null,
            'exchange_types'   => $ad['exchange_types'] ?? null,
            'tags'             => $ad['tags'] ?? null,
            'melkino_visited'  => (int)($ad['melkino_visited'] ?? 0),
            'melkino_rating'   => $ad['melkino_rating'] ?? 0,
        ];
    }


    /*
     * جدیدترین آگهی ابتدا
     */
    usort(
        $publishedAds,
        static function ($a, $b) {

            return strcmp(
                (string)(
                    $b['created_at']
                    ?? ''
                ),

                (string)(
                    $a['created_at']
                    ?? ''
                )
            );
        }
    );

} else {

    /* =================================================
       DATABASE MODE
       ================================================= */

    try {

        if (
            !isset($pdo) ||
            !($pdo instanceof PDO)
        ) {
            throw new RuntimeException(
                'اتصال به دیتابیس برقرار نشد.'
            );
        }


        $stmt = $pdo->query(
            "SELECT *
             FROM ads
             WHERE status = 'published'
             ORDER BY created_at DESC"
        );


        $adsFromDB =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

            // راند ۲۹: حالت پیش‌نمایش پنل — فقط آگهی نمونهٔ انتخاب‌شده رندر شود
            if (
                isset($_GET['fd_preview']) && $_GET['fd_preview'] === '1'
                && !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true
                && isset($_GET['fd_ad']) && trim((string)$_GET['fd_ad']) !== ''
            ) {
                $__fdAd = trim((string)$_GET['fd_ad']);
                $adsFromDB = array_values(array_filter(
                    $adsFromDB,
                    static function ($r) use ($__fdAd) {
                        return (string)($r['id'] ?? '') === $__fdAd
                            || (string)($r['ad_id'] ?? '') === $__fdAd;
                    }
                ));
            }


        if ($adsFromDB) {

            $imageStmt =
                $pdo->prepare(
                    "SELECT
                        filename,
                        is_selected,
                        is_primary,
                        publish_publicly
                     FROM images
                     WHERE ad_id = ?
                     ORDER BY
                        is_primary DESC,
                        sort_order ASC,
                        id ASC"
                );


            $amenityStmt =
                $pdo->prepare(
                    "SELECT am.name
                     FROM ad_amenities aa
                     INNER JOIN amenities am
                         ON am.id = aa.amenity_id
                     WHERE
                        aa.ad_id = ?
                        AND am.is_active = 1
                     ORDER BY
                        am.sort_order ASC,
                        am.id ASC"
                );


            foreach ($adsFromDB as $ad) {

                $selectedImages = [];
                $allPublicImages = [];


                $imageStmt->execute([
                    (string)$ad['id']
                ]);


                $imageRows =
                    $imageStmt->fetchAll(
                        PDO::FETCH_ASSOC
                    );


                foreach ($imageRows as $imageRow) {

                    $filename =
                        trim(
                            (string)(
                                $imageRow['filename']
                                ?? ''
                            )
                        );


                    if ($filename === '') {
                        continue;
                    }


                    if (
                        !empty(
                            $imageRow['publish_publicly']
                        )
                    ) {

                        $allPublicImages[] =
                            $filename;
                    }


                    if (
                        !empty(
                            $imageRow['is_selected']
                        ) &&
                        !empty(
                            $imageRow['publish_publicly']
                        )
                    ) {

                        $selectedImages[] =
                            $filename;
                    }
                }


                /*
                 * اگر تصویر انتخاب‌شده‌ای وجود نداشت،
                 * تصاویر عمومی را نمایش بده.
                 */
                if (!$selectedImages) {

                    $selectedImages =
                        $allPublicImages;
                }


                $details =
                    $ad['property_details']
                    ?? [];


                if (is_string($details)) {

                    $decoded =
                        json_decode(
                            $details,
                            true
                        );

                    $details =
                        is_array($decoded)
                            ? $decoded
                            : [];
                }


                if (!is_array($details)) {
                    $details = [];
                }


                $amenities = [];


                try {

                    $amenityStmt->execute([
                        (string)$ad['id']
                    ]);


                    $amenities =
                        array_values(
                            array_filter(
                                array_map(
                                    static function ($row) {

                                        return trim(
                                            (string)(
                                                $row['name']
                                                ?? ''
                                            )
                                        );
                                    },

                                    $amenityStmt->fetchAll(
                                        PDO::FETCH_ASSOC
                                    )
                                )
                            )
                        );

                } catch (Throwable $amenityError) {

                    $amenities = [];
                }


                /*
                 * داده‌های قدیمی
                 */
                if (
                    !$amenities &&
                    !empty(
                        $ad['custom_fields']
                    )
                ) {

                    $custom =
                        is_string(
                            $ad['custom_fields']
                        )
                            ? json_decode(
                                $ad['custom_fields'],
                                true
                            )
                            : $ad['custom_fields'];


                    if (
                        is_array($custom) &&
                        !empty(
                            $custom['amenities']
                        ) &&
                        is_array(
                            $custom['amenities']
                        )
                    ) {

                        $amenities =
                            $custom['amenities'];
                    }
                }


                $deposit =
                    $ad['deposit']
                    ?? '';

                $rentMonthly =
                    $ad['rent_monthly']
                    ?? '';

                $priceSell =
                    $ad['price_sell']
                    ?? '';

                $totalPrice =
                    $ad['total_price']
                    ?? '';

                // راند ۷۳: قیمت از همهٔ ستون‌ها (از جمله ستون لگاسی price) با نادیده‌گرفتن 0.00
                $displayPrice = melkinoAdDisplayPrice($ad);

                // خواندن کلید نخورده از ستون is_not_keyed
                $isNotKeyed = isset($ad['is_not_keyed']) ? (int)$ad['is_not_keyed'] : 0;


                $publishedAds[] = [

                    'id' =>
                        (string)(
                            $ad['id'] ?? ''
                        ),

                    'title' =>
                        $ad['title']
                        ?? 'ملک بدون عنوان',

                    'transaction_type' =>
                        $ad['transaction_type']
                        ?? 'فروش',

                    'property_type' =>
                        $ad['property_type']
                        ?? 'آپارتمان',

                    'price' =>
                        $displayPrice,

                    'location' =>
                        $ad['location']
                        ?? '',

                    'description' =>
                        $ad['description']
                        ?? '',

                    'selected_images' =>
                        $selectedImages,

                    'details' =>
                        $details,

                    'amenities' =>
                        $amenities,

                    'created_at' =>
                        $ad['created_at']
                        ?? date('Y-m-d H:i:s'),

                    'deposit' =>
                        $deposit,

                    'rent_monthly' =>
                        $rentMonthly,

                    'price_sell' =>
                        $priceSell,

                    'display_price' =>
                        $displayPrice,

                    'price_hidden' =>
                        !empty(
                            $ad['price_hidden']
                        ),

                    'total_price' =>
                        $totalPrice,

                    'full_rent' =>
                        $ad['full_rent']
                        ?? '',

                    // فیلدهای وام (خط دوم قیمت + حباب «وام» روی کارت)
                    'has_loan' =>
                        $ad['has_loan']
                        ?? 0,

                    'loan_amount' =>
                        $ad['loan_amount']
                        ?? '',

                    'exchange_interested' =>
                        $ad['exchange_interested']
                        ?? 0,

                    'full_rent_enabled' =>
                        $ad['full_rent_enabled']
                        ?? 0,

                    'is_not_keyed' =>
                        $isNotKeyed,
                    // فیلدهای خام برای رندر پویای کارت (راند ۲۱)
                    'property_details' => $ad['property_details'] ?? null,
                    'deed_type'        => $ad['deed_type'] ?? null,
                    'exchange_types'   => $ad['exchange_types'] ?? null,
                    'tags'             => $ad['tags'] ?? null,
                    'melkino_visited'  => (int)($ad['melkino_visited'] ?? 0),
                    'melkino_rating'   => $ad['melkino_rating'] ?? 0,
                ];
            }
        }

    } catch (Throwable $e) {

        error_log(
            'Melkino home database error: ' .
            $e->getMessage()
        );

        $publishedAds = [];
    }
}


/* =====================================================
   Display Price
   ===================================================== */

function normalizeMoneyValue($value): float
{
    if (
        is_array($value) ||
        is_object($value)
    ) {
        return 0;
    }


    $value =
        trim(
            (string)$value
        );


    if (
        $value === '' ||
        $value === '0'
    ) {
        return 0;
    }


    $value =
        strtr(
            $value,
            [
                '۰' => '0',
                '۱' => '1',
                '۲' => '2',
                '۳' => '3',
                '۴' => '4',
                '۵' => '5',
                '۶' => '6',
                '۷' => '7',
                '۸' => '8',
                '۹' => '9',
                '٬' => ',',
                '،' => ',',
                ' ' => '',
                'تومان' => '',
            ]
        );


    $value =
        preg_replace(
            '/[^0-9.\-]/',
            '',
            $value
        );


    if (
        $value === '' ||
        !is_numeric($value)
    ) {
        return 0;
    }


    return (float)$value;
}


function toPersianDigits(
    string $value
): string
{
    return strtr(
        $value,
        [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]
    );
}


function formatMoneyFa($value): string
{
    $number =
        normalizeMoneyValue(
            $value
        );


    if ($number <= 0) {
        return '';
    }


    $formatted =
        number_format(
            $number,
            0,
            '.',
            ','
        );


    return toPersianDigits(
        $formatted
    );
}


/**
 * خطِ دومِ قیمت برای ملک‌های وام‌دار:
 * «(قیمت منهای وام) تومان + (وام) تومان وام»
 * خروجی HTML است (فقط در کارت‌ها استفاده می‌شود).
 */
function loanPriceLine($ad): string
{
    if (!function_exists('melkinoLoanInfo')) {
        return '';
    }
    if (function_exists('shouldHidePublicPrice') && shouldHidePublicPrice((array)$ad)) {
        return '';
    }
    $loan = melkinoLoanInfo((array)$ad);
    if (empty($loan['has']) || $loan['price'] <= 0 || $loan['net'] <= 0) {
        return '';
    }
    return '<div class="ad-card-price-loan">' . melkinoSvgIcon('bank') . ' '
        . toPersianDigits(number_format($loan['net'], 0, '.', ','))
        . ' تومان + '
        . toPersianDigits(number_format($loan['amount'], 0, '.', ','))
        . ' تومان وام</div>';
}

function displayPrice($ad): string
{
    if (
        function_exists('shouldHidePublicPrice') &&
        shouldHidePublicPrice((array)$ad)
    ) {
        return 'برای استعلام قیمت تماس بگیرید';
    }

    $raw = function_exists('melkinoAdDisplayPrice')
        ? melkinoAdDisplayPrice((array)$ad)
        : '';

    $raw = trim((string)$raw);
    if ($raw === '') {
        return 'تماس بگیرید';
    }

    $toNumber = static function ($value): float {
        if ($value === null || $value === '') {
            return 0;
        }
        $value = strtr((string)$value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
        $value = str_replace([',', '٬', '،', ' '], '', $value);
        return is_numeric($value) ? (float)$value : 0;
    };

    if (strpos($raw, '|') !== false) {
        $parts = array_map('trim', explode('|', $raw, 2));
        $dep = $toNumber($parts[0] ?? '');
        $rent = $toNumber($parts[1] ?? '');
        $out = [];
        if ($dep > 0) {
            $out[] = 'ودیعه: ' . toPersianDigits(number_format($dep, 0, '.', ',')) . ' تومان';
        }
        if ($rent > 0) {
            $out[] = 'اجاره: ' . toPersianDigits(number_format($rent, 0, '.', ',')) . ' تومان';
        }
        return $out ? implode(' | ', $out) : 'تماس بگیرید';
    }

    $n = $toNumber($raw);
    if ($n > 0) {
        return toPersianDigits(number_format($n, 0, '.', ',')) . ' تومان';
    }
    return 'تماس بگیرید';
}


/* =====================================================
   Transaction Label
   ===================================================== */

function getTransactionLabel($type)
{
    $labels = [
        'فروش' =>
            'فروش',

        'پیش فروش' =>
            'پیش فروش',

        'اجاره' =>
            'اجاره'
    ];


    return
        $labels[$type]
        ?? $type;
}


/* =====================================================
   Onboarding Logo
   ===================================================== */

// راند ۶۹: لوگوی نسخه‌دار مشترک
require_once __DIR__ . '/melkino-logo.php';
$onboardingLogoUrl = melkinoSiteLogoUrl();

?>


<?php
require_once __DIR__ . '/header.php';
if (is_file(__DIR__ . '/melkino-auth-gate.php')) {
    require_once __DIR__ . '/melkino-auth-gate.php';
    if (function_exists('melkinoAuthGateBoot')) {
        melkinoAuthGateBoot();
    }
}
?>


<style>

/* =====================================================
   HOME PAGE
   ===================================================== */

.home-content {
    flex: 1 1 auto;

    min-height: 0;

    width: 100%;

    overflow-y: auto;
    overflow-x: hidden;

    box-sizing: border-box;

    padding:
        14px
        14px
        104px;

    background:
        radial-gradient(
            circle at 100% 0%,
            rgba(212,175,55,.08),
            transparent 24%
        ),
        linear-gradient(
            180deg,
            var(--bg) 0%,
            color-mix(
                in srgb,
                var(--bg) 94%,
                var(--primary) 6%
            ) 100%
        );

    -webkit-overflow-scrolling: touch;
}


/* =====================================================
   Shortcut Menu
   ===================================================== */

.home-shortcuts {

    display: grid;

    grid-template-columns:
        repeat(6, minmax(0, 1fr));

    gap: 10px;

    padding: 14px;

    margin:
        8px
        0
        2px;

    background:
        rgba(255,255,255,.62);

    border:
        1px solid
        rgba(6,78,78,.08);

    border-radius: 24px;

    box-shadow:
        0 12px 34px
        rgba(0,0,0,.055);

    backdrop-filter:
        blur(12px);

    -webkit-backdrop-filter:
        blur(12px);
}


.home-shortcut {

    display: flex;

    flex-direction: column;

    align-items: center;

    gap: 7px;

    width: auto;

    min-width: 0;

    padding:
        6px
        2px
        4px;

    border-radius: 18px;

    text-decoration: none;

    color: inherit;

    transition:
        transform .22s ease,
        background .22s ease;
}


.home-shortcut:hover {

    transform:
        translateY(-2px);

    background:
        rgba(255,255,255,.72);
}


.home-shortcut:active {

    transform:
        scale(.97);
}


.home-shortcut-icon {

    width: 58px;
    height: 58px;

    border-radius: 19px;

    display: flex;

    justify-content: center;
    align-items: center;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            #f2f5f3
        );

    color:
        var(--primary);

    box-shadow:
        0 9px 20px
        rgba(0,0,0,.06),
        inset
        0 0 0 1px
        rgba(6,78,78,.06);
}


.home-shortcut.primary
.home-shortcut-icon {

    background:
        linear-gradient(
            145deg,
            var(--gold),
            #f0d673
        );

    color:
        #172121;

    box-shadow:
        0 10px 22px
        rgba(212,175,55,.25);
}


.home-shortcut-label {

    font-size: 10px;

    line-height: 1.5;

    font-weight: 700;

    color:
        var(--text-secondary);

    text-align: center;
}


.home-shortcut.primary
.home-shortcut-label {

    color:
        var(--primary);
}


/* =====================================================
   ADS SECTION
   ===================================================== */

.ads-section {

    width: 100%;

    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap: 16px;

    padding:
        16px
        2px
        0;

    align-items: start;
}

/* راند ۷۵: لیست خانه تمام عرض گرید است تا کارت و تبلیغ هم‌اندازه باشند */
#mkHomeAdsList.properties-list,
.ads-section .properties-list {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    padding: 0;
    align-items: stretch;
}
#mkHomeAdsList .property-card,
#mkHomeAdsList .promo-card {
    height: auto;
    min-width: 0;
}
#mkHomeAdsList .property-card.mk-l-photo-left,
#mkHomeAdsList .property-card.mk-l-photo-right,
#mkHomeAdsList .property-card.mk-l-photo-editorial,
#mkHomeAdsList .property-card.mk-l-horizontal,
#mkHomeAdsList .property-card.mk-l-split,
#mkHomeAdsList .property-card.mk-l-wide,
#mkHomeAdsList .property-card.mk-l-magazine {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    height: auto !important;
}
#mkHomeAdsList .property-card.mk-l-photo-right {
    flex-direction: row-reverse !important;
}
@media (max-width: 720px) {
    #mkHomeAdsList .property-card.mk-l-photo-left,
    #mkHomeAdsList .property-card.mk-l-photo-right,
    #mkHomeAdsList .property-card.mk-l-photo-editorial,
    #mkHomeAdsList .property-card.mk-l-horizontal,
    #mkHomeAdsList .property-card.mk-l-split,
    #mkHomeAdsList .property-card.mk-l-wide,
    #mkHomeAdsList .property-card.mk-l-magazine {
        flex-direction: column !important;
    }
}
#mkHomeAdsList .property-content {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}
#mkHomeAdsList .property-footer {
    margin-top: auto;
}


/* =====================================================
   ADS HEADER
   ===================================================== */

.ads-header {

    grid-column:
        1 / -1;

    display: flex;

    justify-content:
        space-between;

    align-items:
        center;

    width: 100%;

    margin:
        3px
        2px
        0;

    padding:
        0
        2px;

    box-sizing:
        border-box;
}


.ads-title {

    position: relative;

    padding-right: 12px;

    font-size: 20px;

    font-weight: 800;

    letter-spacing:
        -.2px;

    color:
        var(--text-primary);
}


.ads-title::before {

    content: "";

    position: absolute;

    right: 0;

    top: 50%;

    width: 4px;

    height: 22px;

    transform:
        translateY(-50%);

    border-radius:
        999px;

    background:
        linear-gradient(
            180deg,
            var(--gold),
            #f0d673
        );
}


.ads-count {

    padding:
        7px
        11px;

    border-radius:
        999px;

    background:
        rgba(212,175,55,.10);

    border:
        1px solid
        rgba(212,175,55,.18);

    color:
        var(--primary);

    font-size: 12px;

    font-weight:
        700;

    white-space:
        nowrap;
}


/* =====================================================
   AD CARD LINK
   ===================================================== */

.ads-section > a {

    display:
        block;

    width:
        100%;

    min-width:
        0;

    color:
        inherit;

    text-decoration:
        none;
}


/* =====================================================
   AD CARD
   ===================================================== */

<?php echo melkinoFdHomeCss(); ?>

.ad-card {

    width:
        100%;

    height:
        100%;

    margin:
        0;

    overflow:
        hidden;

    background:
        rgba(255,255,255,.78);

    border:
        1px solid
        rgba(6,78,78,.09);

    border-radius:
        24px;

    box-shadow:
        0 14px 36px
        rgba(0,0,0,.07);

    cursor:
        pointer;

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}


.ad-card:hover {

    transform:
        translateY(-4px);

    box-shadow:
        0 20px 44px
        rgba(0,0,0,.10);
}


.ad-card:active {

    transform:
        scale(.985);
}


/* =====================================================
   IMAGE
   ===================================================== */

.ad-card-image {
    position: relative;
.mk-decor-badge{position:absolute;bottom:10px;inset-inline-start:10px;background:rgba(15,23,42,.62);color:#fff;font-size:11px;line-height:1.2;padding:5px 10px;border-radius:999px;z-index:3;backdrop-filter:blur(4px);}


    width:
        100%;

    height:
        220px;

    overflow:
        hidden;

    position:
        relative;

    background:
        linear-gradient(
            135deg,
            rgba(212,175,55,.15),
            rgba(6,78,78,.06)
        ),
        var(--gold-bg);
}


.ad-card-image img {

    width:
        100%;

    height:
        100%;

    object-fit:
        cover;

    display:
        block;

    transition:
        transform .55s ease,
        filter .35s ease;
}


.ad-card:hover
.ad-card-image img {

    transform:
        scale(1.045);

    filter:
        saturate(1.04);
}


.ad-card-image .no-image {

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    height:
        100%;

    color:
        var(--text-secondary);

    font-size:
        14px;
}


/* =====================================================
   BADGES (نوع معامله و کلید نخورده)
   ===================================================== */

.ad-card-badges {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    z-index: 2;
}


.ad-card-badge {
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    backdrop-filter: blur(8px);
    box-shadow: 0 7px 18px rgba(0,0,0,.15);
    white-space: nowrap;
    display: inline-block;
}


.ad-card-badge.transaction {
    background: rgba(6,78,78,.88);
    color: #fff;
}


.ad-card-badge.key-not-turned {
    background: var(--gold);
    color: #172121;
    border: 1px solid rgba(255,255,255,.2);
}


.ad-card-badge.loan {
    background: rgba(6,95,70,.92);
    color: #fff;
}


.ad-card-badge.exchange {
    background: rgba(23,58,138,.88);
    color: #fff;
}

.ad-card-badge.spec {
    background: rgba(100, 116, 139, .14);
    color: #64748b;
    border: 1px solid rgba(100, 116, 139, .35);
}


.ad-card-price-loan {
    margin-top: 2px;
    color: #0a7a55;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.9;
}


/* =====================================================
   CARD BODY
   ===================================================== */

.ad-card-body {

    padding:
        15px
        16px
        13px;
}


.ad-card-title {

    margin-bottom:
        8px;

    color:
        var(--text-primary);

    font-size:
        17px;

    font-weight:
        800;

    line-height:
        1.7;

    overflow-wrap:
        anywhere;
}


.ad-card-location {

    display:
        flex;

    align-items:
        center;

    gap:
        4px;

    margin-bottom:
        9px;

    color:
        var(--text-secondary);

    font-size:
        12px;

    line-height:
        1.7;
}


.ad-card-details {

    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        6px;

    margin-bottom:
        10px;

    font-size:
        11px;
}


.ad-card-details span {

    padding:
        5px
        9px;

    border-radius:
        999px;

    background:
        #f7f8f6;

    border:
        1px solid
        rgba(6,78,78,.07);

    color:
        var(--text-secondary);

    font-size:
        11px;

    font-weight:
        600;
}


.ad-card-price {

    margin-top:
        4px;

    color:
        var(--primary);

    font-size:
        19px;

    font-weight:
        900;

    line-height:
        1.9;

    white-space:
        pre-line;

    overflow-wrap:
        anywhere;
}


/* =====================================================
   CARD FOOTER
   ===================================================== */

.ad-card-footer {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        8px;

    padding:
        11px
        16px;

    border-top:
        1px solid
        rgba(6,78,78,.07);

    background:
        rgba(6,78,78,.025);

    color:
        var(--text-secondary);

    font-size:
        10px;
}


/* =====================================================
   EMPTY
   ===================================================== */

.ads-empty {

    grid-column:
        1 / -1;

    padding:
        50px
        20px;

    text-align:
        center;

    color:
        var(--text-secondary);
}


/* =====================================================
   TABLET
   ===================================================== */

@media (min-width: 601px)
and (max-width: 1000px) {

    .home-content {

        padding:
            12px
            12px
            104px;
    }


    .home-shortcuts {

        grid-template-columns:
            repeat(
                3,
                minmax(
                    0,
                    1fr
                )
            );
    }


    .ads-section {

        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );

        gap:
            14px;

        padding:
            14px
            0
            0;
    }

    #mkHomeAdsList.properties-list,
    .ads-section .properties-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }


    .ad-card-image {

        height:
            210px;
    }
}


/* =====================================================
   MOBILE
   ===================================================== */

@media (max-width: 600px) {

    .home-content {

        padding:
            9px
            9px
            104px;
    }


    .home-shortcuts {

        grid-template-columns:
            repeat(
                3,
                minmax(
                    0,
                    1fr
                )
            );

        gap:
            8px;

        padding:
            12px
            9px;

        border-radius:
            20px;
    }


    .home-shortcut-icon {

        width:
            54px;

        height:
            54px;

        border-radius:
            17px;
    }


    .home-shortcut-label {

        font-size:
            9px;
    }


    .ads-section {

        grid-template-columns:
            1fr;

        gap:
            12px;

        padding:
            14px
            0
            0;
    }

    #mkHomeAdsList.properties-list,
    .ads-section .properties-list {
        grid-template-columns: 1fr;
        gap: 12px;
    }


    .ads-header {

        margin:
            0
            2px
            0;
    }


    .ads-title {

        font-size:
            18px;
    }


    .ads-count {

        font-size:
            10px;

        padding:
            6px
            9px;
    }


    .ad-card {

        border-radius:
            20px;
    }


    .ad-card-image {

        height:
            205px;
    }


    .ad-card-body {

        padding:
            14px
            14px
            12px;
    }


    .ad-card-title {

        font-size:
            16px;
    }


    .ad-card-price {

        font-size:
            18px;
    }


    .ad-card-footer {

        padding:
            10px
            14px;
    }
}


/* =====================================================
   VERY SMALL MOBILE
   ===================================================== */

@media (max-width: 430px) {

    .home-shortcuts {

        gap:
            6px;

        padding:
            11px
            7px;
    }


    .home-shortcut-icon {

        width:
            50px;

        height:
            50px;

        border-radius:
            15px;
    }


    .home-shortcut-label {

        font-size:
            8.5px;
    }


    .ads-section {

        gap:
            10px;
    }


    .ad-card-image {

        height:
            195px;
    }
}


/* =====================================================
   DARK MODE
   ===================================================== */

[data-theme="dark"]
.home-content {

    background:
        radial-gradient(
            circle at 100% 0%,
            rgba(229,184,66,.07),
            transparent 24%
        ),
        linear-gradient(
            180deg,
            #0B1616 0%,
            #0F1D1D 100%
        );
}


[data-theme="dark"]
.home-shortcuts {

    background:
        #152727;

    border-color:
        #294646;

    box-shadow:
        0 12px 34px
        rgba(0,0,0,.28);
}


[data-theme="dark"]
.home-shortcut {

    background:
        transparent;
}


[data-theme="dark"]
.home-shortcut:hover {

    background:
        rgba(255,255,255,.045);
}


[data-theme="dark"]
.home-shortcut-icon {

    background:
        linear-gradient(
            145deg,
            #203737,
            #182D2D
        );

    color:
        #72D2CC;

    box-shadow:
        0 8px 18px
        rgba(0,0,0,.28),

        inset
        0 0 0 1px
        rgba(114,210,204,.09);
}


[data-theme="dark"]
.home-shortcut.primary
.home-shortcut-icon {

    background:
        linear-gradient(
            145deg,
            #E7C65A,
            #CBA83B
        );

    color:
        #142020;

    box-shadow:
        0 10px 22px
        rgba(212,175,55,.22);
}


[data-theme="dark"]
.home-shortcut-label {

    color:
        #E3EFED;

    text-shadow:
        0 1px 2px
        rgba(0,0,0,.28);
}


[data-theme="dark"]
.home-shortcut.primary
.home-shortcut-label {

    color:
        #E7C65A;
}


[data-theme="dark"]
.ads-title {

    color:
        #F4F8F7;
}


[data-theme="dark"]
.ads-count {

    color:
        #E7C65A;

    background:
        rgba(231,198,90,.10);

    border-color:
        rgba(231,198,90,.22);
}


[data-theme="dark"]
.ad-card {

    background:
        #142525;

    border-color:
        #294646;

    box-shadow:
        0 14px 36px
        rgba(0,0,0,.28);
}


[data-theme="dark"]
.ad-card:hover {

    box-shadow:
        0 20px 44px
        rgba(0,0,0,.38);
}


[data-theme="dark"]
.ad-card-details span {

    background:
        #1B3232;

    border-color:
        #2E4A4A;

    color:
        #CFE0DD;
}


[data-theme="dark"]
.ad-card-price {

    color:
        #E7C65A;
}


[data-theme="dark"]
.ad-card-footer {

    background:
        #102020;

    border-top-color:
        #294646;
}


/* =====================================================
   LAYOUT FIX
   ===================================================== */

html,
body {

    min-height:
        100%;
}


body {

    margin:
        0;
}


.app-container {

    min-height:
        100dvh;

    display:
        flex;

    flex-direction:
        column;

    overflow:
        hidden;
}


.home-content {

    flex:
        1 1 auto;

    min-height:
        0;

    width:
        100%;

    box-sizing:
        border-box;

    overflow-y:
        auto;

    overflow-x:
        hidden;

    -webkit-overflow-scrolling:
        touch;
}


.home-content + .bottom-nav,
.home-content + footer {

    flex:
        0 0 auto;
}

</style>


<div class="app-container">


    <main class="home-content">


        <!-- =================================================
             Shortcut Menu
             ================================================= -->

        <section class="home-shortcuts">


            <!-- ثبت ملک -->

            <a
                href="register-step1.php"
                class="home-shortcut primary"
                data-tour="home-register"
            >

                <div class="home-shortcut-icon">

                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path d="M12 5v14"></path>

                        <path d="M5 12h14"></path>

                    </svg>

                </div>

                <span class="home-shortcut-label">
                    ثبت ملک
                </span>

            </a>


            <!-- ثبت درخواست -->

            <a
                href="property-request.php"
                class="home-shortcut"
                data-tour="home-request"
            >

                <div class="home-shortcut-icon">

                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path d="M12 2v4"></path>

                        <path d="M12 22v-4"></path>

                        <path d="M4 12H2"></path>

                        <path d="M22 12h-2"></path>

                        <path d="M19.07 4.93l-1.41 1.41"></path>

                        <path d="M4.93 19.07l1.41-1.41"></path>

                        <path d="M19.07 19.07l-1.41-1.41"></path>

                        <path d="M4.93 4.93l1.41 1.41"></path>

                        <circle
                            cx="12"
                            cy="12"
                            r="4"
                        ></circle>

                    </svg>

                </div>

                <span class="home-shortcut-label">
                    ثبت درخواست
                </span>

            </a>


            <!-- نقشه املاک -->

            <a
                href="map.php"
                class="home-shortcut"
                data-tour="home-map"
            >

                <div class="home-shortcut-icon">

                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"></path>
                        <circle cx="12" cy="10" r="2.5"></circle>

                    </svg>

                </div>

                <span class="home-shortcut-label">
                    نقشه
                </span>

            </a>


            <!-- جستجو -->

            <a
                href="search.php"
                class="home-shortcut"
                data-tour="home-search"
            >

                <div class="home-shortcut-icon">

                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <circle
                            cx="11"
                            cy="11"
                            r="8"
                        ></circle>

                        <line
                            x1="21"
                            y1="21"
                            x2="16.65"
                            y2="16.65"
                        ></line>

                    </svg>

                </div>

                <span class="home-shortcut-label">
                    جستجو
                </span>

            </a>


            <?php if (function_exists('melkinoCalcEnabled') && melkinoCalcEnabled()): ?>
            <!-- ماشین‌حساب قیمت -->

            <a
                href="property-calculator.php"
                class="home-shortcut"
            >

                <div class="home-shortcut-icon">

                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <rect x="5" y="3" width="14" height="18" rx="2"></rect>
                        <rect x="8" y="6" width="8" height="3" rx="0.5"></rect>
                        <circle cx="9" cy="13" r="1" fill="currentColor" stroke="none"></circle>
                        <circle cx="12" cy="13" r="1" fill="currentColor" stroke="none"></circle>
                        <circle cx="15" cy="13" r="1" fill="currentColor" stroke="none"></circle>
                        <circle cx="9" cy="16.5" r="1" fill="currentColor" stroke="none"></circle>
                        <circle cx="12" cy="16.5" r="1" fill="currentColor" stroke="none"></circle>
                        <circle cx="15" cy="16.5" r="1" fill="currentColor" stroke="none"></circle>

                    </svg>

                </div>

                <span class="home-shortcut-label">
                    ماشین‌حساب
                </span>

            </a>
            <?php endif; ?>


            <!-- همه آگهی‌ها -->

            <a
                href="properties.php"
                class="home-shortcut"
            >

                <div class="home-shortcut-icon">

                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <rect
                            x="3"
                            y="3"
                            width="18"
                            height="18"
                            rx="2"
                            ry="2"
                        ></rect>

                        <line
                            x1="8"
                            y1="8"
                            x2="16"
                            y2="8"
                        ></line>

                        <line
                            x1="8"
                            y1="12"
                            x2="16"
                            y2="12"
                        ></line>

                        <line
                            x1="8"
                            y1="16"
                            x2="13"
                            y2="16"
                        ></line>

                    </svg>

                </div>

                <span class="home-shortcut-label">
                    همه آگهی‌ها
                </span>

            </a>


            <!-- VIP -->

            <a
                href="vip.php"
                class="home-shortcut"
                data-tour="home-vip"
            >

                <div class="home-shortcut-icon">

                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <polygon
                            points="
                                12 2
                                15.09 8.26
                                22 9.27
                                17 14.14
                                18.18 21.02
                                12 17.77
                                5.82 21.02
                                7 14.14
                                2 9.27
                                8.91 8.26
                            "
                        ></polygon>

                    </svg>

                </div>

                <span class="home-shortcut-label">
                    فایل‌های VIP
                </span>

            </a>


            <!-- ارتباط با ما -->

            <a
                href="contact.php"
                class="home-shortcut"
                data-tour="home-contact"
            >

                <div class="home-shortcut-icon">

                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path
                            d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"
                        ></path>

                    </svg>

                </div>

                <span class="home-shortcut-label">
                    ارتباط با ما
                </span>

            </a>

        </section>


        <!-- =================================================
             Published Ads
             ================================================= -->

        <section class="ads-section">


            <div class="ads-header">

                <span class="ads-title">
                    آگهی‌های منتشر شده
                </span>

                <span class="ads-count">
                    <?= count($publishedAds) ?> آگهی
                </span>

            </div>


            <?php if (empty($publishedAds)): ?>


                <div class="ads-empty">

                    <svg
                        width="64"
                        height="64"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        style="margin-bottom:12px;"
                    >

                        <rect
                            x="2"
                            y="7"
                            width="20"
                            height="14"
                            rx="2"
                            ry="2"
                        ></rect>

                        <path
                            d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"
                        ></path>

                    </svg>


                    <p
                        style="
                            font-size:16px;
                            font-weight:600;
                            margin:0 0 8px;
                        "
                    >
                        هیچ آگهی منتشر شده‌ای وجود ندارد
                    </p>


                    <p
                        style="
                            font-size:14px;
                            margin:0;
                        "
                    >
                        آگهی‌ها پس از تأیید ادمین در اینجا نمایش داده می‌شوند.
                    </p>

                </div>


            <?php else: ?>


                <?= melkinoAdCardsHead('home') ?>
                <div class="properties-list" id="mkHomeAdsList" data-tour="property-cards">
                <?php foreach (array_values($publishedAds) as $__ssrAd): ?>
                    <article class="property-card mk-home-ssr" data-id="<?= htmlspecialchars((string)($__ssrAd['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="ad-card-body">
                            <h3 class="ad-card-title"><?= htmlspecialchars((string)($__ssrAd['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="ad-card-price property-price"><?= htmlspecialchars(displayPrice($__ssrAd), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
                <script>window.MELKINO_HOME_ADS = <?= json_encode(array_values($publishedAds), JSON_UNESCAPED_UNICODE) ?>;</script>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var host = document.getElementById('mkHomeAdsList');
                    var ads = window.MELKINO_HOME_ADS || [];
                    if (host && window.mkRenderAdCards) {
                        window.mkRenderAdCards(host, ads);
                        return;
                    }
                    /* راند ۷۴: اگر ad-cards.js روی سرور نبود، حداقل قیمت فروش/پیش‌فروش از HTML سرور می‌ماند */
                });
                </script>


            <?php endif; ?>


        </section>


    </main>


    <?php
    require_once __DIR__ . '/footer.php';
    ?>

</div>