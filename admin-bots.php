<?php
/**
|--------------------------------------------------------------------------
| تنظیمات ربات‌ها و کانال (پنل ادمین)
|--------------------------------------------------------------------------
| هم UI تب را می‌سازد و هم endpointهای AJAX را سرو می‌دهد:
|   ?action=get              دریافت تنظیمات
|   ?action=save             ذخیره تنظیمات
|   ?action=test_telegram    تست اتصال تلگرام
|   ?action=test_bale        تست اتصال بله
|   ?action=test_channel     تست دسترسی به کانال
|   ?action=test_sms         تست ارسال پیامک
|   ?action=publish_get      دریافت تنظیمات انتشار (فیلدها + متن ثابت)
|   ?action=publish_save     ذخیره تنظیمات انتشار
|   ?action=publish_preview  پیش‌نمایش متن انتشار
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/admin-guard.php';

$melkinoBotAction = (string)($_GET['action'] ?? $_POST['action'] ?? '');

if ($melkinoBotAction !== '') {
    melkinoRequireAdminJson();
    require_once __DIR__ . '/bot-settings.php';
    require_once __DIR__ . '/telegram.php';

    switch ($melkinoBotAction) {
        case 'get':
            $settings = melkinoBotSettings();
            // توکن‌ها و کلیدها فقط به‌صورت ماسک نمایش داده می‌شوند
            foreach (['telegram_token', 'bale_token', 'eitaa_token', 'sms_api_key'] as $k) {
                if (!empty($settings[$k])) {
                    $settings[$k . '_masked'] = substr($settings[$k], 0, 6) . '••••••' . substr($settings[$k], -4);
                } else {
                    $settings[$k . '_masked'] = '';
                }
                unset($settings[$k]);
            }
            melkinoAdminJson(['success' => true, 'settings' => $settings]);

        case 'save':
            $data = melkinoAdminJsonBody();
            try {
                $ok = melkinoSaveBotSettings($data);
                melkinoAdminJson([
                    'success' => $ok,
                    'message' => $ok ? 'تنظیمات ربات‌ها ذخیره شد.' : 'ذخیره‌سازی ناموفق بود.',
                ], $ok ? 200 : 500);
            } catch (InvalidArgumentException $e) {
                melkinoAdminJson(['success' => false, 'message' => melkinoSafeError($e, 'admin-bots', 'عملیات انجام نشد.')], 422);
            }

        case 'test_telegram':
            $data = melkinoAdminJsonBody();
            $result = melkinoTestTelegramConnection($data['token'] ?? null);
            melkinoAdminJson($result, $result['success'] ? 200 : 400);

        case 'test_bale':
            $data = melkinoAdminJsonBody();
            $result = melkinoTestBaleConnection($data['token'] ?? null);
            melkinoAdminJson($result, $result['success'] ? 200 : 400);

        case 'test_eitaa':
            $data = melkinoAdminJsonBody();
            require_once __DIR__ . '/eitaa.php';
            $result = melkinoTestEitaaConnection($data['token'] ?? null);
            melkinoAdminJson($result, $result['success'] ? 200 : 400);

        case 'test_channel':
            $data = melkinoAdminJsonBody();
            $channel = trim((string)($data['channel'] ?? ''));
            if ($channel === '') {
                melkinoAdminJson(['success' => false, 'message' => 'شناسه کانال وارد نشده است.'], 422);
            }
            $token = melkinoTelegramToken();
            if ($token === '') {
                melkinoAdminJson(['success' => false, 'message' => 'ابتدا توکن تلگرام را ذخیره کن.'], 422);
            }
            $url = 'https://api.telegram.org/bot' . $token . '/getChat?chat_id=' . urlencode($channel);
            $response = function_exists('melkinoHttpPost') ? melkinoHttpPost($url, '') : @file_get_contents($url);
            $decoded = json_decode((string)$response, true);
            if (!is_array($decoded) || empty($decoded['ok'])) {
                $desc = is_array($decoded) ? ($decoded['description'] ?? 'پاسخ نامعتبر') : 'ارتباط برقرار نشد';
                melkinoAdminJson(['success' => false, 'message' => 'کانال: ' . $desc], 400);
            }
            melkinoAdminJson([
                'success' => true,
                'message' => 'کانال در دسترس است: ' . ($decoded['result']['title'] ?? $channel),
            ]);

        /* --------------------------------------------------------------
           تست دسترسی به کانالِ بله
           --------------------------------------------------------------
           در اینجا شناسه ابتدا پاک‌سازی می‌شود؛ چون اگر مقدارِ خالی یا
           پیش‌فرض به سرورِ بله فرستاده شود، پاسخِ «chat_id: must be a
           valid value» برمی‌گردد که پیامِ گمراه‌کننده‌ای است.
        -------------------------------------------------------------- */
        case 'test_bale_channel':
            $data = melkinoAdminJsonBody();
            $channel = trim((string)($data['channel'] ?? ''));

            $placeholders = ['', '@آیدی_کانال', 'آیدی_کانال', '@', '-', '0'];
            if (in_array($channel, $placeholders, true)) {
                melkinoAdminJson([
                    'success' => false,
                    'message' => 'شناسه کانال بله وارد نشده است. شناسه را وارد و ذخیره کن.',
                ], 422);
            }

            $token = function_exists('melkinoBaleToken') ? (string)melkinoBaleToken() : '';
            if ($token === '' || in_array($token, ['توکن_ربات_بله'], true)) {
                melkinoAdminJson([
                    'success' => false,
                    'message' => 'ابتدا توکن ربات بله را وارد و ذخیره کن.',
                ], 422);
            }

            $url = 'https://tapi.bale.ai/bot' . $token . '/getChat?chat_id=' . urlencode($channel);
            $response = function_exists('melkinoHttpPost') ? melkinoHttpPost($url, '') : @file_get_contents($url);
            $decoded = json_decode((string)$response, true);

            if (!is_array($decoded) || empty($decoded['ok'])) {
                $desc = is_array($decoded) ? ($decoded['description'] ?? 'پاسخ نامعتبر') : 'ارتباط با بله برقرار نشد';

                // پرتکرارترین خطا هنگامِ انتشار همین است؛ علتش را روشن می‌گوییم
                // تا ادمین به‌جای حدس زدن، بداند دقیقاً چه باید بکند.
                $hint = '';
                $d = strtolower($desc);
                if (strpos($d, 'no such group or user') !== false
                    || strpos($d, 'chat not found') !== false
                    || strpos($d, 'group not found') !== false) {
                    $hint = ' — یعنی ربات این شناسه را نمی‌شناسد: یا آیدی اشتباه است، یا ربات هنوز عضو/ادمینِ '
                          . 'این کانال نیست. از دکمه‌ی «یافتن شناسه‌ی کانال» در پایین استفاده کن تا شناسه‌ی '
                          . 'عددیِ درست را پیدا کنی.';
                }

                melkinoAdminJson(['success' => false, 'message' => 'کانال بله: ' . $desc . $hint], 400);
            }

            melkinoAdminJson([
                'success' => true,
                'message' => 'کانال بله در دسترس است: ' . ($decoded['result']['title'] ?? $channel),
            ]);

        /* ---------------------------------------------------------------
           یافتنِ شناسه‌ی کانال از روی پیام‌های اخیر ربات
           ---------------------------------------------------------------
           چرا لازم است؟ پرتکرارترین خطا هنگامِ انتشار، «no such group or
           user» است؛ یعنی شناسه‌ای که ادمین وارد کرده برای ربات قابلِ
           شناسایی نیست. مطمئن‌ترین راه این است که از خودِ بله بپرسیم ربات
           اخیراً چه گفتگوهایی را دیده است و شناسه‌ی عددیِ همان‌ها را
           نشان بدهیم تا ادمین مستقیماً انتخاب کند.
        --------------------------------------------------------------- */
        case 'bale_find_chats':

            $token = function_exists('melkinoBaleToken') ? (string)melkinoBaleToken() : '';
            if ($token === '' || in_array($token, ['توکن_ربات_بله'], true)) {
                melkinoAdminJson([
                    'success' => false,
                    'message' => 'ابتدا توکن ربات بله را وارد و ذخیره کن.',
                ], 422);
            }

            $url      = 'https://tapi.bale.ai/bot' . $token . '/getUpdates?limit=100';
            $response = function_exists('melkinoHttpPost') ? melkinoHttpPost($url, '') : @file_get_contents($url);
            $decoded  = json_decode((string)$response, true);

            if (!is_array($decoded) || empty($decoded['ok'])) {
                $desc = is_array($decoded) ? ($decoded['description'] ?? 'پاسخ نامعتبر') : 'ارتباط با بله برقرار نشد';
                melkinoAdminJson([
                    'success' => false,
                    'message' => 'دریافتِ به‌روزرسانی‌ها ناموفق بود: ' . $desc,
                ], 400);
            }

            $chats = [];
            foreach ((array)($decoded['result'] ?? []) as $upd) {
                $candidates = [];
                if (!empty($upd['message']['chat']))             { $candidates[] = $upd['message']['chat']; }
                if (!empty($upd['channel_post']['chat']))        { $candidates[] = $upd['channel_post']['chat']; }
                if (!empty($upd['my_chat_member']['chat']))      { $candidates[] = $upd['my_chat_member']['chat']; }
                if (!empty($upd['edited_message']['chat']))      { $candidates[] = $upd['edited_message']['chat']; }
                if (!empty($upd['edited_channel_post']['chat'])) { $candidates[] = $upd['edited_channel_post']['chat']; }

                foreach ($candidates as $c) {
                    $id = (string)($c['id'] ?? '');
                    if ($id === '' || isset($chats[$id])) { continue; }
                    $title = (string)($c['title'] ?? '');
                    if ($title === '') {
                        $title = trim(((string)($c['first_name'] ?? '')) . ' ' . ((string)($c['last_name'] ?? '')));
                    }
                    $chats[$id] = [
                        'id'       => $id,
                        'type'     => (string)($c['type'] ?? ''),
                        'title'    => $title,
                        'username' => (string)($c['username'] ?? ''),
                    ];
                }
            }

            if (!$chats) {
                melkinoAdminJson([
                    'success' => true,
                    'chats'   => [],
                    'message' => 'هیچ گفتگویی در به‌روزرسانی‌های اخیر پیدا نشد. برای این‌که ربات کانال را '
                               . 'بشناسد: ربات را به کانال اضافه کن و آن را ادمین (با اجازه‌ی ارسال پیام) '
                               . 'کن؛ سپس یک پیام در کانال بفرست و دوباره این دکمه را بزن.',
                ]);
            }

            melkinoAdminJson([
                'success' => true,
                'chats'   => array_values($chats),
                'message' => count($chats) . ' گفتگو پیدا شد. روی شناسه‌ی عددیِ کانال بزن تا در فیلد قرار بگیرد.',
            ]);

        /* ---------------------------------------------------------------
           بررسیِ توکنِ بله: آیا بله این توکن را قبول دارد؟
           ---------------------------------------------------------------
           خطای «Unauthorized» یعنی خودِ توکن پذیرفته نشده است (برخلافِ
           «no such group or user» که مربوط به شناسه‌ی کانال است). این
           action با فراخوانیِ getMe هویتِ ربات را می‌گیرد تا ادمین مطمئن
           شود توکنِ درستی ذخیره شده است. توکن هرگز به‌طور کامل نمایش
           داده نمی‌شود.
        --------------------------------------------------------------- */
        case 'bale_whoami':

            $token = function_exists('melkinoBaleToken') ? (string)melkinoBaleToken() : '';
            if ($token === '' || in_array($token, ['توکن_ربات_بله'], true)) {
                melkinoAdminJson([
                    'success' => false,
                    'message' => 'توکن ربات بله تنظیم نشده است. ابتدا توکن را وارد و ذخیره کن.',
                ], 422);
            }

            $masked = strlen($token) > 12
                ? substr($token, 0, 6) . '…' . substr($token, -4) . ' (طول: ' . strlen($token) . ')'
                : '(کوتاه)';

            $url      = 'https://tapi.bale.ai/bot' . $token . '/getMe';
            $response = function_exists('melkinoHttpPost') ? melkinoHttpPost($url, '') : @file_get_contents($url);
            $decoded  = json_decode((string)$response, true);

            if (!is_array($decoded) || empty($decoded['ok'])) {
                $desc = is_array($decoded) ? ($decoded['description'] ?? 'پاسخ نامعتبر') : 'ارتباط با بله برقرار نشد';
                $hint = '';
                if (stripos($desc, 'unauthorized') !== false) {
                    $hint = ' یعنی بله این توکن را به‌رسمیت نمی‌شناسد. توکن را دوباره از پنل '
                          . 'توسعه‌دهندگانِ بله (یا @botfather_bale) بگیر و اینجا ذخیره کن؛ '
                          . 'توجه کن که توکنِ تلگرام و توکنِ بله دو چیز کاملاً متفاوت‌اند و '
                          . 'جایگزینِ هم نیستند.';
                }
                melkinoAdminJson([
                    'success' => false,
                    'masked'  => $masked,
                    'message' => 'بله توکن را نپذیرفت: ' . $desc . $hint,
                ], 400);
            }

            $r = $decoded['result'] ?? [];
            melkinoAdminJson([
                'success' => true,
                'masked'  => $masked,
                'message' => 'توکن معتبر است. ربات: '
                           . (isset($r['username']) ? '@' . $r['username'] : '(بدون نام کاربری)')
                           . (isset($r['first_name']) ? ' — ' . $r['first_name'] : '')
                           . (isset($r['id']) ? ' — شناسه: ' . $r['id'] : ''),
            ]);

        /* ---------------------------------------------------------------
           تست ارسال پیامک
        --------------------------------------------------------------- */
        case 'test_sms':
            require_once __DIR__ . '/sms.php';
            $data = melkinoAdminJsonBody();
            $phone = (string)($data['phone'] ?? '');
            $phone = strtr($phone, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]);
            $phone = preg_replace('/\\D/', '', $phone);
            if (!preg_match('/^09\\d{9}$/', $phone)) {
                melkinoAdminJson(['success' => false, 'message' => 'شماره موبایل معتبر نیست (فرمت درست: 09123456789).'], 422);
            }
            $result = smsSendText($phone, 'تست پنل پیامک ملکینو ✅');
            melkinoAdminJson($result, $result['success'] ? 200 : 400);

        /* ---------------------------------------------------------------
           تنظیمات انتشار آگهی در کانال (فیلدها + متن بالا/پایین)
        --------------------------------------------------------------- */
        case 'publish_get':
            $data = melkinoAdminJsonBody();
            $platform = melkinoPublishPlatform((string)($data['platform'] ?? $_GET['platform'] ?? 'telegram'));
            $ptype = trim((string)($data['property_type'] ?? ''));
            $trans = trim((string)($data['transaction_type'] ?? ''));
            $settings = melkinoPublishSettings($platform, $ptype, $trans);
            $matrix = melkinoPublishMatrix($platform);
            melkinoAdminJson([
                'success'  => true,
                'platform' => $platform,
                'defs'     => melkinoPublishFieldDefs($ptype !== '' ? $ptype : null, $trans !== '' ? $trans : null),
                'settings' => $settings,
                'combos'   => melkinoPublishCombos(),
                // کلیدهایی که ادمین برایشان تنظیم اختصاصی ذخیره کرده است
                'override_keys' => array_keys($matrix),
            ]);

        case 'publish_save':
            $data = melkinoAdminJsonBody();
            $platform = melkinoPublishPlatform((string)($data['platform'] ?? 'telegram'));
            $ok = melkinoSavePublishSettings($platform, $data);
            $isCombo = trim((string)($data['property_type'] ?? '')) !== '' || trim((string)($data['transaction_type'] ?? '')) !== '';
            if (!empty($data['reset_combo'])) {
                $msg = 'تنظیم اختصاصی این ترکیب حذف شد؛ از این پس تنظیمات عمومی اعمال می‌شود.';
            } elseif ($isCombo) {
                $msg = 'تنظیم انتشار برای این ترکیب (نوع ملک/معامله) ذخیره شد.';
            } else {
                $msg = 'تنظیمات انتشار ذخیره شد.';
            }
            melkinoAdminJson([
                'success' => $ok,
                'message' => $ok ? $msg : 'ذخیره‌سازی ناموفق بود.',
            ], $ok ? 200 : 500);

        /* ---------------------------------------------------------------
           پیش‌نمایش متن انتشار با تنظیمات فعلی (روی جدیدترین آگهی منتشرشده،
           یا یک آگهی نمونه اگر هنوز آگهی‌ای وجود ندارد)
        --------------------------------------------------------------- */
        case 'publish_preview':
            if (is_file(__DIR__ . '/consultant_helper.php')) {
                require_once __DIR__ . '/consultant_helper.php';
            }
            $data = melkinoAdminJsonBody();
            $platform = melkinoPublishPlatform((string)($data['platform'] ?? $_GET['platform'] ?? 'telegram'));
            $pType = trim((string)($data['property_type'] ?? ''));
            $pTrans = trim((string)($data['transaction_type'] ?? ''));
            global $pdo;
            $ad = null;
            if ($pdo instanceof PDO) {
                try {
                    // اگر ترکیب خاصی انتخاب شده، اول سعی کن آگهی واقعیِ همان
                    // ترکیب را برای پیش‌نمایش پیدا کنی
                    if ($pType !== '' || $pTrans !== '') {
                        $where = ["status = 'published'"];
                        $params = [];
                        if ($pType !== '') { $where[] = 'property_type = ?'; $params[] = $pType; }
                        if ($pTrans !== '') { $where[] = 'transaction_type = ?'; $params[] = $pTrans; }
                        $st = $pdo->prepare('SELECT * FROM ads WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT 1');
                        $st->execute($params);
                        $ad = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                    }
                    // راند ۱۷: اگر «نوع ملک» انتخاب شده ولی آگهی واقعیِ همان نوع
                    // پیدا نشد، دیگر آگهیِ بی‌ربطِ نوعِ دیگر نشان داده نمی‌شود —
                    // نمونهٔ مخصوصِ همان نوع ساخته می‌شود (پایین‌تر) تا فیلدهای
                    // اختصاصی (pd.*) با مقدار نمونه در پیش‌نمایش دیده شوند.
                    if (!$ad && $pType === '') {
                        $st = $pdo->query("SELECT * FROM ads WHERE status = 'published' ORDER BY created_at DESC, id DESC LIMIT 1");
                        $ad = $st ? $st->fetch(PDO::FETCH_ASSOC) : null;
                    }
                } catch (Throwable $e) {
                    $ad = null;
                }
            }
            $isSample = false;
            if (!$ad) {
                $isSample = true;
                $ad = [
                    'id' => 'AD-0000-0000',
                    'title' => 'آپارتمان ۱۲۰ متری در مرکز شهر',
                    'transaction_type' => $pTrans !== '' ? $pTrans : 'فروش',
                    'property_type' => $pType !== '' ? $pType : 'آپارتمان',
                    'location' => 'خیابان امام',
                    'address' => 'خیابان امام، کوچه ۵',
                    'area' => '120',
                    'rooms' => '3',
                    'floor' => '2',
                    'year' => '1398',
                    'price_sell' => '2800000000',
                    'description' => 'آپارتمانی نورگیر با دسترسی عالی.',
                    'last_name' => 'نام نمونه',
                    'phone' => '09123456789',
                ];
                // نمونهٔ سند و معاوضه (راند ۱۷) تا خطوط 📜/🔄 در پیش‌نمایش دیده شوند
                $ad['deed_type'] = 'طلق';
                $ad['deed_notes'] = '';
                $ad['exchange_interested'] = 1;
                $ad['exchange_types'] = 'آپارتمان,خودرو';
                $ad['exchange_with'] = '';
                // نمونهٔ property_details به تفکیک نوع ملک (راند ۱۷) — تا فیلدهای
                // اختصاصی (pd.*) هم در پیش‌نمایش با مقدار نمونه دیده شوند
                $__pdSamples = [
                    'آپارتمان' => ['area' => '120', 'floor' => '2', 'rooms' => '3', 'year' => '1398', 'flooring' => 'سرامیک', 'cabinet' => 'MDF', 'cooling' => 'اسپیلیت', 'heating' => 'پکیج', 'total_units' => '5'],
                    'ویلا'     => ['land_area' => '250', 'area' => '180', 'rooms' => '3', 'year' => '1399', 'flooring' => 'سرامیک', 'cabinet' => 'MDF', 'cooling' => 'اسپیلیت', 'heating' => 'پکیج'],
                    'زمین'     => ['land_area' => '212', 'land_type' => 'مسکونی', 'land_width' => '11.5', 'land_length' => '18.5', 'land_front_width' => '11.5', 'land_blocks' => '1', 'land_direction' => 'شمالی', 'land_shape' => 'مستطیل', 'land_deed_status' => 'دارد', 'land_deed_type' => 'تک‌برگ', 'land_setback_status' => 'ندارد', 'land_ownership' => 'شش‌دانگ'],
                    'باغ'      => ['garden_area' => '1000', 'tree_types' => 'گردو، بادام', 'tree_age' => '۸ سال', 'irrigation_type' => 'قطره‌ای', 'has_well' => '1', 'has_pond' => '0', 'has_building' => '1', 'building_area' => '60', 'document_type' => 'قولنامه'],
                    'اداری'    => ['office_area' => '90', 'office_floor' => '3', 'office_units_per_floor' => '4', 'office_rooms' => '2', 'office_year' => '1395', 'office_condition' => 'بازسازی‌شده', 'office_orientation' => 'جنوبی', 'office_usage' => 'دفتر کار'],
                    'تجاری'    => ['area' => '45', 'front' => '6', 'flooring' => 'سرامیک', 'wall' => 'رنگ روغن', 'cabinet' => 'ندارد', 'cooling' => 'اسپیلیت', 'heating' => 'برقی', 'location_type' => 'دوبر', 'location_features' => 'بر خیابان اصلی', 'jobs' => 'رستوران، کافه'],
                ];
                $__pdKey = (string)($ad['property_type'] ?? '');
                if (isset($__pdSamples[$__pdKey])) {
                    $ad['property_details'] = json_encode($__pdSamples[$__pdKey], JSON_UNESCAPED_UNICODE);
                }
                // عنوان و فیلدهای پایهٔ نمونه هم با نوع ملک هماهنگ شود
                $__sampleBase = [
                    'زمین'  => ['title' => 'زمین ۲۱۲ متری مسکونی', 'area' => null, 'rooms' => null, 'floor' => null, 'year' => null],
                    'باغ'   => ['title' => 'باغ ۱۰۰۰ متری با خانه باغ', 'area' => null, 'rooms' => null, 'floor' => null, 'year' => null],
                    'اداری' => ['title' => 'واحد اداری ۹۰ متری', 'area' => null, 'rooms' => null, 'floor' => null, 'year' => null],
                    'تجاری' => ['title' => 'مغازه ۴۵ متری دوبر', 'area' => '45', 'rooms' => null, 'floor' => null, 'year' => null],
                    'ویلا'  => ['title' => 'ویلای ۲۵۰ متری', 'area' => '180', 'rooms' => '3', 'floor' => null, 'year' => '1399'],
                ];
                if (isset($__sampleBase[$__pdKey])) {
                    $ad = array_merge($ad, $__sampleBase[$__pdKey]);
                }
                // نمونهٔ رهن/اجاره (راند ۱۸) تا خطوط 💵/🗓️/🔑 در پیش‌نمایش دیده شوند
                if (in_array($ad['transaction_type'], ['رهن کامل'], true)) {
                    $ad['price_sell'] = null;
                    $ad['full_rent_enabled'] = 1;
                    $ad['full_rent'] = '300000000';
                } elseif (in_array($ad['transaction_type'], ['اجاره'], true)) {
                    $ad['price_sell'] = null;
                    $ad['deposit'] = '150000000';
                    $ad['rent_monthly'] = '12000000';
                } elseif (in_array($ad['transaction_type'], ['رهن و اجاره'], true)) {
                    $ad['price_sell'] = null;
                    $ad['deposit'] = '100000000';
                    $ad['rent_monthly'] = '20000000';
                }
                // نمونهٔ وام (فقط برای فروش/پیش‌فروش) تا ادمین شکل پیام را ببیند
                if (in_array($ad['transaction_type'], ['فروش', 'پیش فروش', ''], true)) {
                    $ad['total_price'] = '2800000000';
                    $ad['has_loan'] = 1;
                    $ad['loan_amount'] = '300000000';
                    $ad['loan_type'] = 'وام مسکن';
                    $ad['loan_duration'] = '۱۲ سال';
                    $ad['loan_bank'] = 'بانک مسکن';
                    $ad['loan_installment'] = '5000000';
                    $ad['loan_installments_paid'] = '۲۴';
                    $ad['loan_notes'] = 'وام قابل انتقال به خریدار است.';
                }
            } elseif ($pType !== '' || $pTrans !== '') {
                // آگهی واقعیِ ترکیبِ دیگر پیدا نشد؛ نوع‌ها را با ترکیبِ انتخابی
                // تنظیم کن تا حداقل «تنظیمات فیلدهای» همان ترکیب در پیش‌نمایش
                // اعمال شود (سازندهٔ پیام بر اساس نوعِ خودِ آگهی تصمیم می‌گیرد)
                if ($pType !== '') { $ad['property_type'] = $pType; }
                if ($pTrans !== '') { $ad['transaction_type'] = $pTrans; }
            }
            $text = function_exists('melkinoAdMessageText')
                ? melkinoAdMessageText($ad, $platform !== 'bale', $platform)
                : (string)($ad['title'] ?? '');
            melkinoAdminJson([
                'success'   => true,
                'platform'  => $platform,
                'text'      => $text,
                'is_sample' => $isSample,
                'ad_title'  => (string)($ad['title'] ?? ''),
            ]);

        default:
            melkinoAdminJson(['success' => false, 'message' => 'عمل نامعتبر'], 400);
    }
}

// ---------------------------------------------------------------
// خروجی HTML تب
// ---------------------------------------------------------------
?>
<!-- =========================================================
     روش‌های ورود کاربران
     =========================================================
     ادمین انتخاب می‌کند کاربران با کدام روش‌ها بتوانند وارد شوند.
     روشِ غیرفعال، هم از صفحه‌ی ورود حذف می‌شود و هم اندپوینتِ
     احراز هویتِ مربوطه در سمت سرور ۴۰۳ برمی‌گرداند. -->
<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('lock') ?> روش‌های ورود کاربران</span>
    </div>

    <div style="padding:0 16px 16px;">
        <div class="admin-field-help" style="margin-bottom:10px;">
            روش‌های غیرفعال، دکمه‌شان در صفحهٔ ورود (<span dir="ltr">login.php</span>) نمایش داده نمی‌شود
            و تلاش برای ورود از آن مسیر هم در سمت سرور رد می‌شود.
            <b>حداقل یکی از روش‌ها باید فعال بماند.</b>
        </div>

        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e2e2e2;border-radius:10px;margin-bottom:8px;cursor:pointer;">
            <input type="checkbox" id="loginTelegramEnabled" style="width:18px;height:18px;accent-color:var(--primary);">
            <span style="font-size:14px;"><?= melkinoSvgIcon('send') ?> ورود با تلگرام <span style="color:var(--text-muted);font-size:12px;">(مینی‌اپ ربات تلگرام)</span></span>
        </label>

        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e2e2e2;border-radius:10px;margin-bottom:8px;cursor:pointer;">
            <input type="checkbox" id="loginBaleEnabled" style="width:18px;height:18px;accent-color:var(--primary);">
            <span style="font-size:14px;"><?= melkinoSvgIcon('chat') ?> ورود با بله <span style="color:var(--text-muted);font-size:12px;">(مینی‌اپ ربات بله)</span></span>
        </label>

        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e2e2e2;border-radius:10px;cursor:pointer;">
            <input type="checkbox" id="loginSmsEnabled" style="width:18px;height:18px;accent-color:var(--primary);">
            <span style="font-size:14px;"><?= melkinoSvgIcon('phone') ?> ورود با پیامک <span style="color:var(--text-muted);font-size:12px;">(شماره موبایل + کد یک‌بارمصرف)</span></span>
        </label>

        <div class="admin-field-help" style="margin-top:8px;">
            برای ورود پیامکی، کد ابتدا از طریق ربات تلگرام/بله (اگر شماره قبلاً شناخته شود)،
            بعد از پنل پیامک (کارت پایین) و در غیر این صورت موقتاً روی خود صفحه نمایش داده می‌شود.
            تغییرات با دکمهٔ «ذخیره تنظیمات ربات‌ها» در پایین همین تب اعمال می‌شود.
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('bot') ?> ربات تلگرام</span>
        <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:12px;" onclick="testBotConnection('telegram')">
            <?= melkinoSvgIcon('plug') ?> تست اتصال
        </button>
    </div>

    <div style="padding:0 16px 16px;">
        <label class="admin-field-label">توکن ربات تلگرام</label>
        <input type="text" id="botTelegramToken" class="admin-input" dir="ltr" placeholder="123456789:AAE..." autocomplete="off">
        <div class="admin-field-help">از @BotFather دریافت می‌شود. پس از ذخیره، امضای ورود کاربران با این توکن بررسی می‌شود.</div>

        <label class="admin-field-label">شناسه کانال</label>
        <input type="text" id="botTelegramChannel" class="admin-input" dir="ltr" placeholder="@melkino_shahrood">
        <div class="admin-field-help">آگهی‌های منتشرشده می‌توانند به این کانال ارسال شوند.</div>

        <label class="admin-field-label">نام کاربری ربات (بدون @)</label>
        <input type="text" id="botTelegramUsername" class="admin-input" dir="ltr" placeholder="melkino_bot">
        <div class="admin-field-help">برای ساخت دکمه‌ی «ورود از طریق تلگرام» در مرورگر معمولی استفاده می‌شود.</div>

        <button type="button" class="btn-secondary" style="padding:8px 16px;font-size:13px;margin-top:8px;" onclick="testChannelConnection()">
            <?= melkinoSvgIcon('megaphone') ?> تست دسترسی به کانال
        </button>
    </div>
</div>

<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('chat') ?> ربات بله</span>
        <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:12px;" onclick="testBotConnection('bale')">
            <?= melkinoSvgIcon('plug') ?> تست اتصال
        </button>
    </div>

    <div style="padding:0 16px 16px;">
        <label class="admin-field-label">توکن ربات بله</label>
        <input type="text" id="botBaleToken" class="admin-input" dir="ltr" placeholder="387417012:..." autocomplete="off">
        <div class="admin-field-help">از پنل توسعه‌دهندگان بله (یا @botfather_bale) دریافت می‌شود. خالی بگذار تا مقدار قبلی حفظ شود؛ برای پاک‌کردن «-» وارد کن.</div>

        <label class="admin-field-label">شناسه کانال بله</label>
        <input type="text" id="botBaleChannel" class="admin-input" dir="ltr" placeholder="@melkino">
        <div class="admin-field-help">
            می‌تواند با @ (مانند <span dir="ltr">@melkino</span>) یا شناسه‌ی عددی کانال باشد.
            ربات باید در کانال، ادمین با اجازه‌ی ارسال باشد.
        </div>

        <label class="admin-field-label">نام کاربری ربات (بدون @)</label>
        <input type="text" id="botBaleUsername" class="admin-input" dir="ltr" placeholder="melkino_bot">

        <button type="button" class="btn-secondary" style="padding:8px 16px;font-size:13px;margin-top:8px;" onclick="baleWhoAmI()">
            <?= melkinoSvgIcon('receipt') ?> بررسیِ توکن (نمایشِ هویتِ ربات)
        </button>
        <div class="admin-field-help" style="margin-top:6px">
            اگر هنگامِ انتشار خطای
            <span dir="ltr">Unauthorized</span>
            می‌بینی، یعنی خودِ توکن پذیرفته نشده است (نه شناسه‌ی کانال). با
            این دکمه مشخص می‌شود بله این توکن را قبول دارد یا نه، و کدام
            ربات به آن وصل است.
        </div>

        <button type="button" class="btn-secondary" style="padding:8px 16px;font-size:13px;margin-top:8px;" onclick="findBaleChats()">
            <?= melkinoSvgIcon('search') ?> یافتن شناسه‌ی کانال (از پیام‌های اخیرِ ربات)
        </button>
        <div class="admin-field-help" style="margin-top:6px">
            اگر هنگامِ انتشار خطای
            <span dir="ltr">no such group or user</span>
            می‌بینی، یعنی ربات این شناسه را نمی‌شناسد. با این دکمه فهرستِ
            گفتگوهایی که ربات اخیراً دیده را ببین و شناسه‌ی <b>عددیِ</b> کانال را
            انتخاب کن؛ شناسه‌ی عددی از آیدیِ @ بسیار مطمئن‌تر است.
        </div>
        <div id="baleChatsBox" style="display:none;margin-top:10px;padding:10px;border:1px solid #e2e2e2;border-radius:10px;background:#fafafa"></div>

        <button type="button" class="btn-secondary" style="padding:8px 16px;font-size:13px;margin-top:8px;" onclick="testBaleChannelConnection()">
            <?= melkinoSvgIcon('megaphone') ?> تست دسترسی به کانال بله
        </button>
    </div>
</div>

<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('bot') ?> برنامه / برنامک ایتا</span>
        <button type="button" class="btn-secondary" style="padding:6px 14px;font-size:12px;" onclick="testBotConnection('eitaa')">
            <?= melkinoSvgIcon('plug') ?> تست اتصال
        </button>
    </div>

    <div style="padding:0 16px 16px;">
        <label class="admin-field-label">توکن برنامه ایتا</label>
        <input type="text" id="botEitaaToken" class="admin-input" dir="ltr" placeholder="123456789:AAE..." autocomplete="off">
        <div class="admin-field-help">
            از پنل <b>ایتایار</b> برای همان برنامه‌ای که برنامک ملکینو را باز می‌کند بگیر.
            این توکن برای اعتبارسنجی <span dir="ltr">initData</span> سمت سرور لازم است.
            خالی بگذار تا مقدار قبلی حفظ شود؛ برای پاک‌کردن «-» وارد کن.
        </div>

        <label class="admin-field-label">نام کاربری ربات / برنامه (بدون @)</label>
        <input type="text" id="botEitaaUsername" class="admin-input" dir="ltr" placeholder="melkino_bot">
        <div class="admin-field-help">
            برای لینک «باز کردن در ایتا» وقتی کاربر صفحهٔ ورود را در مرورگر معمولی باز کند.
            آدرس برنامک را در ایتایار روی همین سایت تنظیم کن و از داخل برنامه بازش کن تا ورود خودکار شود.
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('globe') ?> پروکسی ارتباط با پیام‌رسان‌ها</span>
    </div>

    <div style="padding:0 16px 16px;">
        <label class="admin-field-label">نشانی پروکسی (اختیاری)</label>
        <input type="text" id="botProxy" class="admin-input" dir="ltr" placeholder="http://user:pass@1.2.3.4:8080" autocomplete="off">
        <div class="admin-field-help">
            اگر هاست شما به <span dir="ltr">api.telegram.org</span> دسترسی ندارد
            (برای هاست‌های داخل ایران معمول است)، نشانی یک پروکسی را اینجا وارد کنید تا
            تستِ اتصال و انتشارِ آگهی از طریق آن انجام شود. از
            <span dir="ltr">http://</span> و <span dir="ltr">socks5://</span>
            پشتیبانی می‌شود. در غیر این صورت این فیلد را خالی بگذارید.
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('phone') ?> پنل پیامک</span>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-secondary);cursor:pointer;">
            <input type="checkbox" id="smsEnabled" style="width:18px;height:18px;accent-color:var(--primary);">
            فعال باشد
        </label>
    </div>

    <div style="padding:0 16px 16px;">
        <div class="admin-field-help" style="margin-bottom:10px;">
            اگر پنل پیامک غیرفعال باشد یا تنظیم نشده باشد، کد ورود به‌جای پیامک، مستقیم روی صفحه نمایش داده می‌شود.
        </div>

        <label class="admin-field-label">نشانی API سرویس پیامک</label>
        <input type="text" id="smsApiUrl" class="admin-input" dir="ltr" placeholder="https://..." autocomplete="off">
        <div class="admin-field-help">نشانی وب‌سرویس ارسال پیامک.</div>

        <label class="admin-field-label">کلید API</label>
        <input type="text" id="smsApiKey" class="admin-input" dir="ltr" placeholder="..." autocomplete="off">
        <div class="admin-field-help">خالی بگذار تا مقدار قبلی حفظ شود؛ برای پاک‌کردن «-» وارد کن.</div>

        <label class="admin-field-label">شماره خط ارسال‌کننده</label>
        <input type="text" id="smsSenderLine" class="admin-input" dir="ltr" placeholder="3000...">
        <div class="admin-field-help">شماره خطی که پیامک از طرف آن ارسال می‌شود.</div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:center;">
            <input type="text" id="smsTestPhone" class="admin-input" dir="ltr" placeholder="09123456789" style="max-width:170px;">
            <button type="button" class="btn-secondary" style="padding:8px 16px;font-size:13px;" onclick="testSmsSend()">
                <?= melkinoSvgIcon('send') ?> ارسال پیامک تست
            </button>
        </div>
        <div class="admin-field-help" style="margin-top:6px">ابتدا تنظیمات را با دکمه‌ی پایین صفحه ذخیره کن، بعد تست بگیر.</div>
    </div>
</div>

<div class="admin-card">
    <div class="card-actions" style="padding:16px;">
        <button type="button" class="btn-primary" onclick="saveBotSettings()"><?= melkinoSvgIcon('save') ?> ذخیره تنظیمات ربات‌ها</button>
        <span id="botSettingsStatus" class="admin-status-msg"></span>
    </div>
    <div style="padding:0 16px 16px;color:var(--text-muted);font-size:12px;line-height:1.9;">
        توکن‌ها در دیتابیس ذخیره می‌شوند و در خروجی‌های این صفحه هیچ‌وقت به‌صورت کامل نمایش داده نمی‌شوند.
    </div>
</div>

<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('edit') ?> محتوای انتشار در کانال تلگرام</span>
    </div>

    <div style="padding:0 16px 16px;">
        <div class="admin-field-help" style="margin-bottom:8px;">
            انتخاب کن کدام فیلدهای آگهی در پیام کانال تلگرام بیاید.
            می‌توانی برای هر <b>نوع ملک</b> و <b>نوع معامله</b> تنظیمِ جداگانه ذخیره کنی؛
            با انتخاب هر نوع ملک، <b>فقط فیلدهای مرتبط با همان نوع</b> فهرست می‌شوند
            (مثلاً «تعداد اتاق» و «طبقه» برای زمین نمایش داده نمی‌شوند).
            ترکیبی که تنظیم اختصاصی ندارد از «تنظیمات عمومی» استفاده می‌کند:
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
            <label style="font-size:13px;"><?= melkinoSvgIcon('tag', 'mk-icon mk-icon--sm') ?> نوع ملک:
                <select id="publishTypeTelegram" class="admin-input" onchange="melkinoReloadPublish('telegram')" style="padding:6px 10px;font-size:13px;min-width:140px;"></select>
            </label>
            <label style="font-size:13px;"><?= melkinoSvgIcon('pin', 'mk-icon mk-icon--sm') ?> نوع معامله:
                <select id="publishTransTelegram" class="admin-input" onchange="melkinoReloadPublish('telegram')" style="padding:6px 10px;font-size:13px;min-width:140px;"></select>
            </label>
            <button type="button" class="btn-secondary" id="publishResetTelegram" onclick="resetPublishCombo('telegram')" style="display:none;padding:6px 12px;font-size:12px;" title="تنظیم اختصاصی این ترکیب را حذف می‌کند و به تنظیمات عمومی برمی‌گرداند"><?= melkinoSvgIcon('trash') ?> حذف تنظیم این ترکیب</button>
            <span id="publishComboStatusTelegram" style="font-size:12px;color:var(--text-muted);"></span>
        </div>
        <div id="publishFieldsTelegram" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-bottom:12px;">
            <span style="font-size:12px;color:var(--text-muted);">در حال بارگذاری…</span>
        </div>

        <label class="admin-field-label">متن ثابت بالای آگهی‌ها (اختیاری)</label>
        <textarea id="publishHeaderTelegram" rows="2" class="admin-input" style="width:100%;box-sizing:border-box;padding:10px;font-family:inherit;font-size:13px;resize:vertical;" placeholder="این متن بالای همه‌ی آگهی‌های کانال نمایش داده می‌شود"></textarea>

        <label class="admin-field-label" style="margin-top:10px;display:block;">متن ثابت پایین آگهی‌ها (اختیاری)</label>
        <textarea id="publishFooterTelegram" rows="2" class="admin-input" style="width:100%;box-sizing:border-box;padding:10px;font-family:inherit;font-size:13px;resize:vertical;" placeholder="این متن پایین همه‌ی آگهی‌های کانال نمایش داده می‌شود"></textarea>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:center;">
            <button type="button" class="btn-primary" style="padding:8px 16px;font-size:13px;" onclick="savePublishSettings('telegram')"><?= melkinoSvgIcon('save') ?> ذخیره</button>
            <button type="button" class="btn-secondary" style="padding:8px 16px;font-size:13px;" onclick="previewPublish('telegram')"><?= melkinoSvgIcon('eye') ?> پیش‌نمایش</button>
            <span id="publishStatusTelegram" class="admin-status-msg"></span>
        </div>
        <pre id="publishPreviewTelegram" dir="auto" style="display:none;white-space:pre-wrap;word-break:break-word;font-family:inherit;font-size:12px;line-height:2;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:10px;max-height:320px;overflow:auto;"></pre>
    </div>
</div>

<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('edit') ?> محتوای انتشار در کانال بله</span>
    </div>

    <div style="padding:0 16px 16px;">
        <div class="admin-field-help" style="margin-bottom:8px;">
            انتخاب کن کدام فیلدهای آگهی در پیام کانال بله بیاید.
            می‌توانی برای هر <b>نوع ملک</b> و <b>نوع معامله</b> تنظیمِ جداگانه ذخیره کنی؛
            با انتخاب هر نوع ملک، <b>فقط فیلدهای مرتبط با همان نوع</b> فهرست می‌شوند
            (مثلاً «تعداد اتاق» و «طبقه» برای زمین نمایش داده نمی‌شوند).
            ترکیبی که تنظیم اختصاصی ندارد از «تنظیمات عمومی» استفاده می‌کند:
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px;">
            <label style="font-size:13px;"><?= melkinoSvgIcon('tag', 'mk-icon mk-icon--sm') ?> نوع ملک:
                <select id="publishTypeBale" class="admin-input" onchange="melkinoReloadPublish('bale')" style="padding:6px 10px;font-size:13px;min-width:140px;"></select>
            </label>
            <label style="font-size:13px;"><?= melkinoSvgIcon('pin', 'mk-icon mk-icon--sm') ?> نوع معامله:
                <select id="publishTransBale" class="admin-input" onchange="melkinoReloadPublish('bale')" style="padding:6px 10px;font-size:13px;min-width:140px;"></select>
            </label>
            <button type="button" class="btn-secondary" id="publishResetBale" onclick="resetPublishCombo('bale')" style="display:none;padding:6px 12px;font-size:12px;" title="تنظیم اختصاصی این ترکیب را حذف می‌کند و به تنظیمات عمومی برمی‌گرداند"><?= melkinoSvgIcon('trash') ?> حذف تنظیم این ترکیب</button>
            <span id="publishComboStatusBale" style="font-size:12px;color:var(--text-muted);"></span>
        </div>
        <div id="publishFieldsBale" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-bottom:12px;">
            <span style="font-size:12px;color:var(--text-muted);">در حال بارگذاری…</span>
        </div>

        <label class="admin-field-label">متن ثابت بالای آگهی‌ها (اختیاری)</label>
        <textarea id="publishHeaderBale" rows="2" class="admin-input" style="width:100%;box-sizing:border-box;padding:10px;font-family:inherit;font-size:13px;resize:vertical;" placeholder="این متن بالای همه‌ی آگهی‌های کانال نمایش داده می‌شود"></textarea>

        <label class="admin-field-label" style="margin-top:10px;display:block;">متن ثابت پایین آگهی‌ها (اختیاری)</label>
        <textarea id="publishFooterBale" rows="2" class="admin-input" style="width:100%;box-sizing:border-box;padding:10px;font-family:inherit;font-size:13px;resize:vertical;" placeholder="این متن پایین همه‌ی آگهی‌های کانال نمایش داده می‌شود"></textarea>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;align-items:center;">
            <button type="button" class="btn-primary" style="padding:8px 16px;font-size:13px;" onclick="savePublishSettings('bale')"><?= melkinoSvgIcon('save') ?> ذخیره</button>
            <button type="button" class="btn-secondary" style="padding:8px 16px;font-size:13px;" onclick="previewPublish('bale')"><?= melkinoSvgIcon('eye') ?> پیش‌نمایش</button>
            <span id="publishStatusBale" class="admin-status-msg"></span>
        </div>
        <pre id="publishPreviewBale" dir="auto" style="display:none;white-space:pre-wrap;word-break:break-word;font-family:inherit;font-size:12px;line-height:2;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px;margin-top:10px;max-height:320px;overflow:auto;"></pre>
    </div>
</div>

<div class="admin-card">
    <div class="card-header">
        <span class="card-title"><?= melkinoSvgIcon('compass') ?> راهنمای هاست‌هایی که دسترسیِ خروجی ندارند</span>
    </div>
    <div style="padding:14px 16px;color:var(--text-muted);font-size:13px;line-height:2;">
        <p style="margin:0 0 10px">
            بعضی هاست‌ها — از جمله <b>InfinityFree</b> — ارتباطِ خروجیِ سرور با
            <span dir="ltr">api.telegram.org</span> را به‌طور کامل مسدود کرده‌اند.
            در این حالت هر کاری که «سرور» انجام دهد با خطا مواجه می‌شود،
            <b>حتی وقتی توکن کاملاً سالم است</b>.
        </p>
        <p style="margin:0 0 10px">
            برای حل این مشکل، سامانه به‌صورت خودکار ابتدا از
            <b>مرورگرِ خودِ شما</b> با تلگرام/بله ارتباط برقرار می‌کند و فقط اگر
            مرورگر هم موفق نشد، از سرور امتحان می‌کند. بنابراین:
        </p>
        <ul style="margin:0 18px 10px;padding:0">
            <li>اگر مرورگر شما به تلگرام دسترسی دارد (مثلاً فیلترشکن روشن است)
                → <b>همه چیز کار می‌کند</b>: تست اتصال و انتشار آگهی.</li>
            <li>اگر مرورگر شما هم به تلگرام دسترسی ندارد
                → یک پروکسی در کارتِ بالا ثبت کنید، یا سایت را به هاستی منتقل کنید
                که ارتباطِ خروجیِ آزاد داشته باشد.</li>
        </ul>
        <p style="margin:0">
            برای این‌که بفهمید دقیقاً کدام حالت برقرار است، به تب
            <b>«عیب‌یاب»</b> بروید و دکمهٔ <b>«شروع تست»</b> را بزنید. در گروه
            «ربات و کانال»، موردِ <b>«ارتباط مستقیم از مرورگر»</b> تعیین‌کننده است.
        </p>
    </div>
</div>
