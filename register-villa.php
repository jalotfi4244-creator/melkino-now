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
// ==============================================
// روی سرور واقعی، خطاها فقط لاگ می‌شوند نه چاپ در صفحه
// (قبلاً display_errors روشن بود؛ همین باعث می‌شد یک Warning یا
// Notice ساده، خروجی JSON آپلود تصویر یا فرآیند ثبت را خراب کند و
// به‌عنوان «خطای دیتابیس» به کاربر نمایش داده شود)
// ==============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/security-lib.php';
require_once __DIR__ . '/jalali-lib.php';
require_once __DIR__ . '/form-options.php';
require_once __DIR__ . '/auth.php';

// ثبت ملک فقط برای کاربرانی مجاز است که با تلگرام یا بله وارد شده باشند.
melkinoRequireLogin();

// درخواست‌های تغییردهنده (آپلود تصویر / ثبت ملک) باید توکن CSRF داشته باشند
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { melkinoCsrfCheck(); }



// ==============================================
// پردازش آپلود تصاویر از طریق AJAX
// ==============================================
if (isset($_POST['action']) && $_POST['action'] === 'upload_images') {
    header('Content-Type: application/json');
    
    $uploadedImages = [];
    $uploadErrors = [];
    $targetDir = __DIR__ . "/uploads/";
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024;

    // قبلاً این بخش هر خطایی (پوشه‌ی غیرقابل‌نوشتن، فرمت نامعتبر، حجم زیاد)
    // را بی‌صدا نادیده می‌گرفت و همیشه success:true با images خالی برمی‌گرداند؛
    // یعنی کاربر می‌دید «عکس آپلود نشد» بدون اینکه هیچ دلیلی نشان داده شود.
    // حالا دلیل دقیق در پاسخ JSON برگردانده می‌شود.

    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    if (is_dir($targetDir)) {
        // .htaccess ضد اجرای اسکریپت در پوشهٔ آپلود
        melkinoProtectUploadDir($targetDir);
    }

    if (!is_dir($targetDir) || !is_writable($targetDir)) {
        echo json_encode([
            'success' => false,
            'message' => 'پوشه‌ی uploads روی سرور وجود ندارد یا قابل نوشتن نیست. لطفاً از طریق File Manager هاست، به پوشه‌ی uploads دسترسی نوشتن (chmod 755) بده.',
            'images' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $adId = 'AD-' . date('Ymd') . '-' . rand(1000, 9999);

    if (isset($_FILES['images']) && is_array($_FILES['images']['name']) && count($_FILES['images']['name']) > 0) {
        for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
            if (empty($_FILES['images']['name'][$i])) continue;

            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                $uploadErrors[] = $_FILES['images']['name'][$i] . ': کد خطای آپلود ' . $_FILES['images']['error'][$i];
                continue;
            }

            if ($_FILES['images']['size'][$i] > $maxFileSize) {
                $uploadErrors[] = $_FILES['images']['name'][$i] . ': حجم فایل بیش از ۵ مگابایت است.';
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['images']['tmp_name'][$i]);
            finfo_close($finfo);

            $fileExt = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));

            if (!in_array($mimeType, $allowedMimes) || !in_array($fileExt, $allowedExtensions)) {
                $uploadErrors[] = $_FILES['images']['name'][$i] . ': فرمت تصویر مجاز نیست.';
                continue;
            }

            // اعتبارسنجی واقعی تصویر (نه فقط MIME/پسوند) تا فایل‌های
            // جعلی با هدر تصویر ولی محتوای اسکریپت رد شوند.
            // تشخیص نوع واقعی تصویر؛ روی هاست‌هایی که getimagesize ندارند
            // از امضای بایت‌های فایل استفاده می‌شود (به‌جای خطای کشنده).
            $imgProbe = melkinoImageTypeOf($_FILES['images']['tmp_name'][$i]);
            $imgInfo  = [$imgProbe['width'], $imgProbe['height'], $imgProbe['type']];
            if (empty($imgProbe['type']) || !in_array((int) $imgProbe['type'], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
                $uploadErrors[] = $_FILES['images']['name'][$i] . ': فایل یک تصویر معتبر نیست.';
                continue;
            }

            $uniqueId = bin2hex(random_bytes(10));
            $fileName = $adId . '_' . date('Ymd_His') . '_' . $uniqueId . '.' . $fileExt;
            $fileName = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $fileName);
            $targetFile = $targetDir . $fileName;

            // ذخیره با رمزگذاری مجدد؛ در نبود GD به انتقال ساده برمی‌گردیم.
            $storedOk = melkinoReencodeImage($_FILES['images']['tmp_name'][$i], $targetFile, (int) $imgInfo[2]);
            if (!$storedOk) {
                $storedOk = move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetFile);
            }

            if ($storedOk) {
                $uploadedImages[] = 'uploads/' . $fileName;
            } else {
                $uploadErrors[] = $_FILES['images']['name'][$i] . ': ذخیره‌ی فایل روی سرور ناموفق بود.';
            }
        }
    } else {
        $uploadErrors[] = 'هیچ فایلی در درخواست ارسال دریافت نشد.';
    }

    echo json_encode([
        'success' => count($uploadedImages) > 0,
        'images' => $uploadedImages,
        'adId' => $adId,
        'errors' => $uploadErrors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==============================================
// فرم ثبت ملک - مخصوص ویلا
// ==============================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_property'])) {
    
    // دریافت اطلاعات از فرم (از فیلدهای مخفی)
    // قبلاً این مقدار از $_SESSION['reg_telegram_id'] خونده می‌شد که هیچ‌وقت
    // ست نمی‌شد (همیشه خالی بود)؛ حالا از همون فیلد مخفی فرم می‌خونیم.
    // نکته‌ی امنیتی: مقدار خودِ فیلد فرم (hidden_telegram_id) دیگر
    // برای هویت معتبر نیست، چون کلاینت می‌تونست هر عددی توش بذاره.
    // آی‌دی تلگرام معتبر فقط همونیه که سرور قبلاً (با بررسی امضای
    // واقعی تلگرام در identity-sync.php) در سشن ثبت کرده.
    $telegram_id = trim((string)($_SESSION['reg_telegram_id'] ?? ''));
    $gender = $_POST['gender'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';

    // ==========================================================
    // راند ۳۰: هویت ثبت‌کننده فقط از حساب تأییدشدهٔ سمت سرور خوانده
    // می‌شود. نام/شماره/telegram_id/user_id ارسالی از فرم برای هویت
    // معتبر نیستند؛ شمارهٔ قفل‌شدهٔ پروفایل جایگزین می‌شود تا حتی
    // درخواست دستی HTTP هم نتواند شمارهٔ دیگری را ثبت کند.
    // ==========================================================
    $__id30 = melkinoCurrentIdentity();
    $__u30  = $__id30['user'] ?? null;
    $__phone30 = trim((string)($__u30['phone'] ?? ''));
    $__verified30 = $__phone30 !== ''
        && (!empty($__u30['phone_verified']) || !empty($__u30['phone_locked']));
    if (!$__verified30) {
        http_response_code(403);
        echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>شماره تماس تأییدشده لازم است</title></head><body style='font-family:Vazirmatn,Tahoma,sans-serif;text-align:center;padding:50px;background:#f3f4f6;'><div style='background:#fff;max-width:650px;margin:auto;padding:30px;border-radius:14px;border:1px solid #e5e7eb;'><h3 style='color:#b45309'>📵 ثبت شمارهٔ تماس الزامی است</h3><p style='line-height:2.1;color:#374151'>برای ثبت آگهی، ابتدا باید شمارهٔ تماس خود را در پروفایل ثبت و تأیید کنید. پس از تأیید، شماره قفل می‌شود و فقط ادمین می‌تواند آن را تغییر دهد. نام و شمارهٔ شما در همهٔ آگهی‌ها به‌صورت خودکار از پروفایلتان خوانده می‌شود.</p><a href='profile.php' style='display:inline-block;margin-top:12px;padding:12px 26px;background:#064e4e;color:#fff;text-decoration:none;border-radius:8px'>تکمیل شماره تماس</a> <a href='javascript:history.back()' style='display:inline-block;margin-top:12px;padding:12px 26px;background:#e5e7eb;color:#111827;text-decoration:none;border-radius:8px'>بازگشت</a></div></body></html>";
        exit;
    }
    $phone = $__phone30;
    $__name30 = trim((string)($__u30['name'] ?? ''));
    if ($__name30 !== '') {
        $last_name = $__name30;
    }
    if ($telegram_id === '' && !empty($__id30['telegram_id'])) {
        $telegram_id = (string)$__id30['telegram_id'];
    }
    $__ownerUserId30 = (int)($__id30['user_id'] ?? 0);
    $transaction_type = $_POST['transaction_type'] ?? 'فروش';
    $property_type = 'ویلایی';
    
    // دریافت اطلاعات از فرم
    $title = $_POST['title'] ?? '';
    $location = $_POST['location'] ?? '';
    $address = $_POST['address'] ?? '';
    $description = $_POST['full_description'] ?? '';
    $publish_photos = $_POST['publish_photos'] ?? 'yes';
    
    // دریافت مسیر تصاویر از فیلد مخفی
    $imagePaths = isset($_POST['uploaded_images']) ? explode(',', $_POST['uploaded_images']) : [];
    $uploadedImages = array_filter($imagePaths);

    // ==============================================
    // دریافت فیلدهای قیمت بر اساس نوع معامله
    // ==============================================
    $price_sell = '0';
    $price_condition = '';
    $deposit = '0';
    $rent_monthly = '0';
    $full_rent_enabled = '0';
    $full_rent = '0';
    $total_price = '0';
    $down_payment = '0';
    $payment_terms = '';
    $exchange_interested = isset($_POST['exchange_interested']) ? '1' : '0';
    $exchange_with = trim((string)($_POST['exchange_with'] ?? ''));
    $visit_hours = trim((string)($_POST['visit_hours'] ?? ''));
    $delivery_date = trim((string)($_POST['delivery_date'] ?? ''));
    $vacancy_date = trim((string)($_POST['vacancy_date'] ?? ''));
    $is_vacant = isset($_POST['is_vacant']) ? '1' : '0';
    $is_not_keyed = isset($_POST['is_not_keyed']) ? '1' : '0';
    $is_old = isset($_POST['is_old']) ? '1' : '0';
    $is_renovated = isset($_POST['is_renovated']) ? '1' : '0';

    if ($transaction_type === 'فروش') {
        $price_sell = $_POST['price_sell'] ?? '0';
        $price_condition = isset($_POST['price_condition']) ? $_POST['price_condition'] : 'negotiable';
    } elseif ($transaction_type === 'اجاره') {
        $deposit = $_POST['deposit'] ?? '0';
        $rent_monthly = $_POST['rent_monthly'] ?? '0';
        $full_rent_enabled = isset($_POST['full_rent_enabled']) ? '1' : '0';
        $full_rent = $_POST['full_rent'] ?? '0';
    } elseif ($transaction_type === 'پیش فروش') {
        $total_price = $_POST['total_price'] ?? '0';
        $down_payment = $_POST['down_payment'] ?? '0';
        $payment_terms = $_POST['payment_terms'] ?? '';
    }

    // ==============================================
    // فیلدهای وام (فقط فروش و پیش‌فروش)
    // ==============================================
    $has_loan = '0';
    $loan_amount = '0';
    $loan_type = '';
    $loan_duration = '';
    $loan_bank = '';
    $loan_installment = '0';
    $loan_installments_paid = '';
    $loan_notes = '';
    if (($transaction_type === 'فروش' || $transaction_type === 'پیش فروش') && isset($_POST['has_loan'])) {
        $has_loan = '1';
        $loan_amount = $_POST['loan_amount'] ?? '0';
        $loan_type = trim((string)($_POST['loan_type'] ?? ''));
        $loan_duration = trim((string)($_POST['loan_duration'] ?? ''));
        $loan_bank = trim((string)($_POST['loan_bank'] ?? ''));
        $loan_installment = $_POST['loan_installment'] ?? '0';
        $loan_installments_paid = trim((string)($_POST['loan_installments_paid'] ?? ''));
        $loan_notes = trim((string)($_POST['loan_notes'] ?? ''));
    }
    // ==============================================
    // نوع سند + گزینه‌های معاوضه (راند ۱۴)
    // ==============================================
    $deed_type = trim((string)($_POST['deed_type'] ?? ''));
    $deed_notes = trim((string)($_POST['deed_notes'] ?? ''));
    $exchange_types = '';
    if (!empty($_POST['exchange_types']) && is_array($_POST['exchange_types'])) {
        $__allowedExchange = ['آپارتمان', 'باغ', 'ویلایی', 'اداری', 'مغازه', 'زمین', 'خودرو'];
        $__pickedExchange = [];
        foreach ($_POST['exchange_types'] as $__x) {
            $__x = trim((string)$__x);
            if (in_array($__x, $__allowedExchange, true) && !in_array($__x, $__pickedExchange, true)) {
                $__pickedExchange[] = $__x;
            }
        }
        $exchange_types = implode(',', $__pickedExchange);
    }
    if ($exchange_interested !== '1') {
        $exchange_types = ''; // گزینه‌های معاوضه فقط وقتی ذخیره می‌شوند که «مایل به معاوضه» تیک داشته باشد
    }

    // ==============================================
    // اعتبارسنجی سمت سرور
    // ==============================================
    $errors = [];

    // گام ۱: اطلاعات تماس (به جز لوکیشن اجباری)
    if (empty($gender) || !in_array($gender, ['آقا', 'خانم'])) $errors[] = 'لطفاً جنسیت خود را انتخاب کنید.';
    if (empty($last_name)) $errors[] = 'لطفاً نام خانوادگی خود را وارد کنید.';
    if (empty($phone)) $errors[] = 'لطفاً شماره تماس خود را وارد کنید.';
    if (is_file(__DIR__ . '/map-lib.php')) {
        require_once __DIR__ . '/map-lib.php';
        $__mapSet = (isset($pdo) && $pdo instanceof PDO) ? melkinoMapSettings($pdo) : ['require_location' => true];
        $__mapLoc = melkinoMapPostedLocation($errors, $__mapSet);
    }


    // گام ۲: مشخصات اجباری ویلا
    if (empty($_POST['land_villa'])) $errors[] = 'لطفاً متراژ زمین را وارد کنید.';
    if (empty($_POST['built_villa'])) $errors[] = 'لطفاً زیربنا را وارد کنید.';
    if (empty($_POST['rooms_villa']) || $_POST['rooms_villa'] === '') $errors[] = 'لطفاً تعداد اتاق را انتخاب کنید.';
    if (empty($_POST['year_villa'])) $errors[] = 'لطفاً سال ساخت ملک را وارد کنید.';

    // گام ۴: قیمت بر اساس نوع معامله
    $isSell = ($transaction_type === 'فروش');
    $isPreSell = ($transaction_type === 'پیش فروش');
    $isRent = ($transaction_type === 'اجاره');

    if ($isSell && empty($_POST['price_sell'])) {
        $errors[] = 'لطفاً قیمت فروش را وارد کنید.';
    }
    if ($isPreSell && empty($_POST['total_price'])) {
        $errors[] = 'لطفاً قیمت کل (پیش فروش) را وارد کنید.';
    }

    // گام ۴ (وام): اگر تیک «وام دارد» زده شده، مبلغ وام لازم است و باید از قیمت کمتر باشد
    if ($has_loan === '1') {
        $toNum = static function ($value): float {
            $s = str_replace(
                ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩','٬','،',',',' '],
                ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9','','','',''],
                trim((string)$value)
            );
            return is_numeric($s) ? (float)$s : 0.0;
        };
        $loanAmountNum = $toNum($loan_amount);
        $basePriceNum = $isSell ? $toNum($price_sell) : $toNum($total_price);
        if ($loanAmountNum <= 0) {
            $errors[] = 'مبلغ وام را وارد کنید (یا تیک «وام دارد» را بردارید).';
        } elseif ($basePriceNum > 0 && $loanAmountNum >= $basePriceNum) {
            $errors[] = 'مبلغ وام باید از قیمت ملک کمتر باشد (قیمت منهای وام باید مثبت بماند).';
        }
    }

    // گام ۵ (سند): نوع سند اختیاری است اما اگر انتخاب شد باید از فهرست مجاز باشد
    $__deedAllowed = ['طلق', 'وقفی', 'مشاعی', 'عرصه', 'اعیان', 'رهنی', 'قولنامه عادی', 'قولنامه شورایی', 'برگه واگذاری'];
    if ($deed_type !== '' && !in_array($deed_type, $__deedAllowed, true)) {
        $errors[] = 'نوع سند انتخاب‌شده معتبر نیست.';
    }
    if ($isRent) {
        $full_rent_enabled = isset($_POST['full_rent_enabled']) ? '1' : '0';
        if ($full_rent_enabled === '1') {
            if (empty($_POST['full_rent'])) {
                $errors[] = 'لطفاً مبلغ رهن کامل را وارد کنید.';
            }
        } else {
            if (empty($_POST['deposit'])) {
                $errors[] = 'لطفاً مبلغ ودیعه را وارد کنید.';
            }
            if (empty($_POST['rent_monthly'])) {
                $errors[] = 'لطفاً مبلغ اجاره ماهانه را وارد کنید.';
            }
        }
    }

    // اگر خطایی وجود داشت، نمایش داده و متوقف کن
    if (!empty($errors)) {
        echo "<!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'><title>خطا در ثبت</title></head>
        <body style='font-family: Vazirmatn, sans-serif; text-align: center; padding: 50px; direction: rtl; background: #f3f4f6;'>
            <div style='background: #fff; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                <h3 style='color: #ef4444; margin-bottom: 20px;'>❌ اطلاعات ناقص است!</h3>
                <div style='background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; padding: 15px; text-align: right; margin-bottom: 20px;'>";
        foreach ($errors as $err) {
            echo "<p style='margin: 5px 0; color: #b91c1c; font-weight: 600;'>- $err</p>";
        }
        echo "    </div>
                <a href='javascript:history.back()' style='display: inline-block; padding: 12px 24px; background: #064e4e; color: #fff; text-decoration: none; border-radius: 8px;'>بازگشت و اصلاح</a>
            </div>
        </body>
        </html>";
        exit;
    }

    // ==============================================
    // ذخیره اصلی در دیتابیس MySQL
    // ==============================================
    $adId = 'AD-' . date('Ymd') . '-' . rand(1000, 9999);
    $newAd = [
        'id' => $adId,
        'title' => $title,
        'location' => $location,
        'address' => $address,
        'latitude' => $__mapLoc['latitude'] ?? null,
        'longitude' => $__mapLoc['longitude'] ?? null,
        'location_source' => $__mapLoc['location_source'] ?? null,
        'location_accuracy' => $__mapLoc['location_accuracy'] ?? null,
        'location_received' => $__mapLoc['location_received'] ?? '0',
        'status' => 'pending',
        'tags' => [],
        'gender' => $gender,
        'last_name' => $last_name,
        'phone' => $phone,
        'telegram_id' => $telegram_id,
        'owner_user_id' => $__ownerUserId30,
        'propertyType' => $property_type,
        'transactionType' => $transaction_type,
        // فیلدهای اختصاصی ویلا
        'land_area' => $_POST['land_villa'] ?? '0',
        'built_area' => $_POST['built_villa'] ?? '0',
        'land_villa' => $_POST['land_villa'] ?? '0',
        'built_villa' => $_POST['built_villa'] ?? '0',
        'area' => $_POST['land_villa'] ?? '0',
        'rooms' => $_POST['rooms_villa'] ?? '0',
        'year' => $_POST['year_villa'] ?? '1403',
        'building_age' => function_exists('melkinoBuildingAge') ? melkinoBuildingAge($_POST['year_villa'] ?? '') : null,
        'flooring' => $_POST['flooring_villa'] ?? '',
        'cabinet' => $_POST['cabinet_villa'] ?? '',
        'cooling' => $_POST['cooling_villa'] ?? '',
        'heating' => $_POST['heating_villa'] ?? '',
        // راند ۲۵: نوع ویلایی (رادیویی) — اگر انتخاب نشود «فلت» محاسبه می‌شود
        'villa_type' => (static function () {
            $v = trim((string)($_POST['villa_type'] ?? ''));
            return in_array($v, ['فلت', 'دوبلکس', 'تریبلکس'], true) ? $v : 'فلت';
        })(),
        'amenities' => isset($_POST['amenities_villa']) ? array_values($_POST['amenities_villa']) : [],
        'description' => $description,
        'images' => $uploadedImages,
        'selectedImages' => $uploadedImages,
        'publish_photos' => $publish_photos,
        'created_at' => date('Y-m-d H:i:s'),
        'price_sell' => $price_sell,
        'price_condition' => $price_condition,
        'deposit' => $deposit,
        'rent_monthly' => $rent_monthly,
        'full_rent_enabled' => $full_rent_enabled,
        'full_rent' => $full_rent,
        'total_price' => $total_price,
        'down_payment' => $down_payment,
        'payment_terms' => $payment_terms,
        'display_price' => $price_sell ?: ($total_price ?: ($deposit ?: $rent_monthly)),
        // فیلدهای جدید
        'is_not_keyed' => $is_not_keyed,
        'is_old' => $is_old,
        'is_renovated' => $is_renovated,
        'exchange_interested' => $exchange_interested,
        'exchange_with' => $exchange_with,
        'visit_hours' => $visit_hours,
        'delivery_date' => $delivery_date,
        'vacancy_date' => $vacancy_date,
        'is_vacant' => $is_vacant,
        // فیلدهای وام
        'has_loan' => $has_loan,
        'loan_amount' => $loan_amount,
        'loan_type' => $loan_type,
        'loan_duration' => $loan_duration,
        'loan_bank' => $loan_bank,
        'loan_installment' => $loan_installment,
        'loan_installments_paid' => $loan_installments_paid,
        'loan_notes' => $loan_notes,
        // سند و معاوضه (راند ۱۴)
        'deed_type' => $deed_type,
        'deed_notes' => $deed_notes,
        'exchange_types' => $exchange_types,
    ];

    // ==============================================
    // ذخیره اصلی آگهی در MySQL
    // JSON دیگر منبع ذخیره‌سازی نیست.
    // ==============================================
    require_once __DIR__ . '/property-db-helper.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        http_response_code(500);
        echo "<div style='direction:rtl;text-align:center;padding:40px;font-family:Vazirmatn,sans-serif'>❌ اتصال به دیتابیس برقرار نشد.</div>";
        exit;
    }

    $dbResult = savePropertyToDatabase($pdo, $newAd);
    if ($dbResult['success']) {
        if ($telegram_id !== '') {
            $_SESSION['reg_telegram_id'] = $telegram_id;
        }

        // نکته‌ی امنیتی: تایپ‌کردن یک شماره در فرم، اثبات مالکیت آن
        // نیست. قبلاً اینجا $_SESSION['user_phone'] بی‌قیدوشرط با
        // همین $phone پر می‌شد؛ یعنی اگه کسی به‌جای شماره‌ی خودش
        // شماره‌ی یک نفر دیگه رو تایپ می‌کرد، سشنش به هویت اون فرد
        // وصل می‌شد و «ملک‌های من»/«درخواست‌های من» اون فرد رو
        // می‌دید! حالا این وصل‌شدن فقط وقتی انجام می‌شه که واقعاً
        // خودِ این مرورگر صاحب اون شماره باشه (شماره‌ی تازه، یا توکن
        // معتبرِ ذخیره‌شده از ثبت‌نام قبلی همین مرورگر).
        $providedToken = $_COOKIE['melkino_access_token'] ?? '';
        $identity = melkinoUpsertUser($telegram_id, $phone, $last_name, '', $providedToken);

        if (!empty($identity['trusted'])) {
            if ($phone !== '') {
                $_SESSION['user_phone'] = $phone;
            }
            if ($last_name !== '') {
                $_SESSION['user_name'] = $last_name;
            }
            if (!empty($identity['token'])) {
                melkinoSetAccessTokenCookie((string) $identity['token']);
            }
        }
    }
    if (!$dbResult['success']) {
        http_response_code(500);
        echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>خطا در ثبت</title></head><body style='font-family:Vazirmatn,sans-serif;text-align:center;padding:50px;background:#f3f4f6;'><div style='background:#fff;max-width:650px;margin:auto;padding:30px;border-radius:14px;border:1px solid #e5e7eb;'><h3 style='color:#dc2626'>❌ ثبت آگهی انجام نشد</h3><p style='line-height:2;color:#374151'>خطایی هنگام ذخیره اطلاعات در دیتابیس رخ داد. اطلاعات فرم تغییر نکرده است.</p><a href='javascript:history.back()' style='display:inline-block;padding:12px 24px;background:#064e4e;color:#fff;text-decoration:none;border-radius:8px'>بازگشت و اصلاح</a></div></body></html>";
        exit;
    }



    // نمایش پیام موفقیت
    echo "<!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><title>ثبت موفق</title></head>
    <body style='font-family: Vazirmatn, sans-serif; text-align: center; padding: 50px; direction: rtl;'>
        <h2 style='color: green;'>✅ آگهی ویلا با موفقیت ثبت شد!</h2>
        <p>شناسه پیگیری: <strong style='direction: ltr; display: inline-block;'>" . $newAd['id'] . "</strong></p>
        <p>اطلاعات شما ذخیره شد. به زودی با شما تماس می‌گیریم.</p>
        <a href='home.php' style='display: inline-block; margin-top: 20px; padding: 12px 24px; background: #064e4e; color: #fff; text-decoration: none; border-radius: 8px;'>بازگشت به خانه</a>
        <script>setTimeout(function() { window.location.href = 'home.php'; }, 3000);</script>
    </body>
    </html>";
    exit;
}

require_once __DIR__ . '/header.php';
?>
<style>
    .main-content { flex: 1; overflow-y: auto; background: var(--bg); display: flex; flex-direction: column; padding-bottom: calc(var(--reg-bottom-nav-h, 74px) + 90px); }
    .stepper-container { padding: var(--space-2) var(--space-3); display: flex; align-items: center; gap: var(--space-1); background: var(--surface); flex-shrink: 0; }
    .step-content { display: none; padding: 0 var(--space-3); flex-direction: column; gap: var(--space-2); animation: fadeIn 0.3s ease forwards; }
    .step-content.active { display: flex; }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .step-title { font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: var(--space-1); }
    .step-subtitle { font-size: 14px; color: var(--text-secondary); margin-bottom: var(--space-2); }
    
    .preview-section { margin-bottom: var(--space-3); }
    .preview-card { background: var(--surface); border-radius: var(--radius-md); padding: var(--space-3); border: 1px solid var(--border); margin-bottom: var(--space-2); }
    .preview-card-title { font-size: 16px; font-weight: 700; color: var(--primary); margin-bottom: var(--space-2); border-bottom: 1px solid var(--border); padding-bottom: var(--space-1); }
    .preview-row { display: flex; justify-content: space-between; padding: var(--space-1) 0; border-bottom: 1px solid var(--border); }
    .preview-row:last-child { border-bottom: none; }
    .preview-label { color: var(--text-secondary); font-weight: 600; }
    .preview-value { color: var(--text-primary); font-weight: 500; text-align: left; direction: ltr; }
    .preview-value.rtl { direction: rtl; text-align: right; }
    
    .image-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: var(--space-1); margin-top: var(--space-2); }
    .image-preview-item { position: relative; width: 100%; aspect-ratio: 1/1; border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; background: var(--bg); }
    .image-preview-item img { width: 100%; height: 100%; object-fit: cover; }
    .image-preview-item .remove-btn { position: absolute; top: 2px; right: 2px; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; color: var(--danger); font-weight: bold; display: flex; justify-content: center; align-items: center; }

    .form-group { display: flex; flex-direction: column; gap: var(--space-1); margin-bottom: var(--space-2); }
    .form-group label { font-size: 14px; font-weight: 600; color: var(--text-primary); }
    .form-input, .form-select, .form-textarea { width: 100%; height: 50px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg); padding: 0 var(--space-2); font-size: 15px; font-family: 'Vazirmatn', sans-serif; color: var(--text-primary); outline: none; transition: border 0.2s ease; }
    .form-textarea { height: 100px; padding-top: var(--space-2); resize: none; }
    .row-half { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); }
    
    .upload-area { width: 100%; height: 120px; border: 2px dashed var(--border); border-radius: var(--radius-md); background: var(--bg); display: flex; flex-direction: column; justify-content: center; align-items: center; color: var(--text-secondary); gap: var(--space-1); cursor: pointer; transition: 0.3s; }
    .upload-area:hover { border-color: var(--primary); }
    
    .bottom-actions {
        flex-shrink: 0;
        width: 100%;
        padding: 10px 16px;
        margin: 0 0 var(--bottom-nav-height, 75px) 0;
        background: var(--surface);
        border-top: 1px solid var(--border);
        display: flex;
        gap: 10px;
        z-index: 80;
        box-sizing: border-box;
        box-shadow: 0 -4px 15px rgba(0,0,0,.08);
    }
    .bottom-actions .btn-secondary,
    .bottom-actions .btn-primary-full {
        flex: 1;
        min-width: 0;
        font-family: inherit;
    }
    .btn-secondary, .btn-primary-full { height: 52px; border-radius: var(--radius-md); font-weight: 700; font-size: 16px; display: flex; justify-content: center; align-items: center; padding: 0 var(--space-2); box-sizing: border-box; border: none; flex: 1; transition: all 0.2s ease; }
    .btn-secondary { background: var(--bg); color: var(--text-secondary); border: 1px solid var(--border); }
    .btn-primary-full { background: var(--primary); color: #ffffff; }
    #fullRentGroup { display: none; }
    #fullRentGroup.visible { display: block; }
    #exchangeGroup { display: none; }
    #exchangeGroup.visible { display: block; }
    
    .radio-group { display: flex; gap: var(--space-3); margin-top: var(--space-1); flex-wrap: wrap; }
    .radio-label { display: flex; align-items: center; gap: var(--space-1); font-size: 14px; color: var(--text-primary); cursor: pointer; }
    .radio-label input[type="radio"] { width: 18px; height: 18px; accent-color: var(--primary); }
</style>
<?php require_once __DIR__ . '/jalali-picker.php'; ?>

<div class="stepper-container">
    <span class="step-text" id="stepText">مرحله ۱ از ۶</span>
    <div class="step-line active" id="line1"></div>
    <div class="step-line" id="line2"></div>
    <div class="step-line" id="line3"></div>
    <div class="step-line" id="line4"></div>
    <div class="step-line" id="line5"></div>
</div>

<div class="main-content" id="mainContent">
    <form method="POST" action="" id="propertyForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(melkinoCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <!-- ===== فیلدهای مخفی برای اطلاعات کاربر ===== -->
        <input type="hidden" name="gender" id="hidden_gender" value="">
        <input type="hidden" name="last_name" id="hidden_last_name" value="">
        <input type="hidden" name="phone" id="hidden_phone" value="">
        <input type="hidden" name="telegram_id" id="hidden_telegram_id" value="">
        <input type="hidden" name="transaction_type" id="hidden_transaction_type" value="">
        <input type="hidden" name="uploaded_images" id="uploaded_images" value="">
        
        <!-- STEP 1 -->
        <div class="step-content active" id="step1">
            <h2 class="step-title">اطلاعات پایه و موقعیت</h2>
            <div class="form-group"><label>عنوان آگهی</label><input type="text" class="form-input" id="regTitle" name="title" placeholder="ویلای لوکس ۳۰۰ متری در چالوس" required></div>
            <div class="form-group"><label>محله / خیابان اصلی</label><input type="text" class="form-input" id="regLocation" name="location" placeholder="خیابان بهار" required></div>
            <div class="form-group"><label>آدرس دقیق</label><input type="text" class="form-input" id="regAddress" name="address" placeholder="خ بهار کوچه بیستم..." required></div>
            <?php require __DIR__ . '/map-location-fields.php'; ?>
        </div>

        <!-- STEP 2 -->
        <div class="step-content" id="step2">
            <h2 class="step-title">مشخصات ویلا</h2>
            <div id="step2Dynamic">
                <!-- راند ۲۵: نوع ویلایی — اگر انتخاب نشود «فلت» محاسبه می‌شود -->
                <div class="form-group" style="display:flex; flex-direction:column; gap:var(--space-1);">
                    <label style="font-size:14px;font-weight:600;color:var(--text-primary);">نوع ویلایی</label>
                    <div style="display:flex; flex-wrap:wrap; gap:var(--space-1);">
                        <label style="display:flex; align-items:center; gap:6px; padding:9px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; background:var(--bg);"><input type="radio" name="villa_type" value="فلت" checked style="width:16px;height:16px;accent-color:var(--primary);"><span style="font-size:13px;color:var(--text-primary);">فلت</span></label>
                        <label style="display:flex; align-items:center; gap:6px; padding:9px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; background:var(--bg);"><input type="radio" name="villa_type" value="دوبلکس" style="width:16px;height:16px;accent-color:var(--primary);"><span style="font-size:13px;color:var(--text-primary);">دوبلکس</span></label>
                        <label style="display:flex; align-items:center; gap:6px; padding:9px 14px; border:1px solid var(--border); border-radius:var(--radius-sm); cursor:pointer; background:var(--bg);"><input type="radio" name="villa_type" value="تریبلکس" style="width:16px;height:16px;accent-color:var(--primary);"><span style="font-size:13px;color:var(--text-primary);">تریبلکس</span></label>
                    </div>
                </div>

                <div class="row-half">
                    <div class="form-group"><label>متراژ زمین (متر مربع)</label><input type="text" class="form-input numeric-input" id="regLandVilla" name="land_villa" placeholder="۵۰۰" required></div>
                    <div class="form-group"><label>زیربنا (متر مربع)</label><input type="text" class="form-input numeric-input" id="regBuiltVilla" name="built_villa" placeholder="۳۰۰" required></div>
                </div>
                <div class="row-half">
                    <div class="form-group"><label>تعداد اتاق</label><select class="form-select" id="regRoomsVilla" name="rooms_villa" required><?= function_exists('melkinoFormSelectOptions') ? melkinoFormSelectOptions('rooms') : '<option value="">انتخاب کنید</option>' ?></select></div>
                    <div class="form-group"><label>سال ساخت</label><input type="text" class="form-input numeric-input" id="regYearVilla" name="year_villa" placeholder="۱۴۰۲" required></div>
                </div>
                <div class="row-half">
                    <div class="form-group"><label>سن بنا</label><input type="text" class="form-input" id="regBuildingAge" name="building_age" readonly tabindex="-1" placeholder="خودکار"></div>
                </div>

                <!-- سه چک‌باکس (فقط در فروش و اجاره) -->
                <div id="conditionCheckboxesContainer">
                    <div class="form-group" style="display:flex; flex-direction:column; gap:var(--space-1); margin-bottom:var(--space-1);">
                        <label style="font-size:14px;font-weight:600;color:var(--text-primary);">وضعیت ملک</label>
                        <div style="display:flex; flex-wrap:wrap; gap:var(--space-2);">
                            <label style="display:flex; align-items:center; gap:var(--space-1);">
                                <input type="checkbox" id="regIsNotKeyed" name="is_not_keyed" value="1" style="width:18px;height:18px;accent-color:var(--primary);">
                                <span style="font-size:12px;color:var(--text-secondary);">کلید نخورده</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:var(--space-1);">
                                <input type="checkbox" id="regIsOld" name="is_old" value="1" style="width:18px;height:18px;accent-color:var(--primary);">
                                <span style="font-size:12px;color:var(--text-secondary);">کلنگی</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:var(--space-1);">
                                <input type="checkbox" id="regIsRenovated" name="is_renovated" value="1" style="width:18px;height:18px;accent-color:var(--primary);">
                                <span style="font-size:12px;color:var(--text-secondary);">بازسازی شده</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row-half" id="yearRow">
                    <div class="form-group" style="grid-column:1;"><!-- سال ساخت قبلاً در بالا قرار دارد، بنابراین اینجا خالی است --></div>
                    <div class="form-group" id="deliveryDateContainer" style="display:none;">
                        <label>تاریخ تحویل</label>
                        <input type="text" class="form-input" id="regDeliveryDateJalali" placeholder="انتخاب تاریخ شمسی" autocomplete="off" inputmode="none">
                        <input type="hidden" id="regDeliveryDate" name="delivery_date">
                    </div>
                </div>

                <!-- فیلدهای اجاره (تاریخ تخلیه و ساختمان تخلیه است) -->
                <div id="rentalFieldsContainer" style="display:none;">
                    <div class="row-half">
                        <div class="form-group">
                            <label>تاریخ تخلیه</label>
                            <input type="date" class="form-input" id="regVacancyDate" name="vacancy_date">
                        </div>
                        <div class="form-group" style="display:flex; flex-direction:column; gap:var(--space-1);">
                            <label style="font-size:14px;font-weight:600;color:var(--text-primary);">ساختمان تخلیه است</label>
                            <div style="display:flex; align-items:center; gap:var(--space-1);">
                                <input type="checkbox" id="regIsVacant" name="is_vacant" value="1" style="width:18px;height:18px;accent-color:var(--primary);" onchange="toggleVacancy()">
                                <span style="font-size:12px;color:var(--text-secondary);">در حال حاضر ساختمان تخلیه است</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row-half">
                    <div class="form-group"><label>نوع پوشش کف</label><select class="form-select" id="regFlooringVilla" name="flooring_villa" required><?= function_exists('melkinoFormSelectOptions') ? melkinoFormSelectOptions('flooring') : '' ?></select></div>
                    <div class="form-group"><label>نوع کابینت</label><select class="form-select" id="regCabinetVilla" name="cabinet_villa" required><?= function_exists('melkinoFormSelectOptions') ? melkinoFormSelectOptions('cabinet') : '' ?></select></div>
                </div>
                <div class="row-half">
                    <div class="form-group"><label>سیستم سرمایش</label><select class="form-select" id="regCoolingVilla" name="cooling_villa" required><?= function_exists('melkinoFormSelectOptions') ? melkinoFormSelectOptions('cooling') : '' ?></select></div>
                    <div class="form-group"><label>سیستم گرمایش</label><select class="form-select" id="regHeatingVilla" name="heating_villa" required><?= function_exists('melkinoFormSelectOptions') ? melkinoFormSelectOptions('heating') : '' ?></select></div>
                </div>
            </div>
        </div>

        <!-- STEP 3 -->
        <div class="step-content" id="step3">
            <h2 class="step-title">امکانات ویلا</h2>
            <div class="amenities-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-1);">
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="آسانسور"> آسانسور</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="پارکینگ"> پارکینگ</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="انباری"> انباری</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="مطبخ"> مطبخ</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="بالکن / تراس"> بالکن / تراس</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="حیاط اختصاصی"> حیاط اختصاصی</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="روف گاردن"> روف گاردن</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="لاندری روم"> لاندری روم</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="کلوزت"> کلوزت</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="اتاق مستر"> اتاق مستر</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="نگهبانی"> نگهبانی</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="استخر"> استخر</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="سونا"> سونا</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="جکوزی"> جکوزی</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="زیرزمین"> زیرزمین</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="گلخانه"> گلخانه</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="حیاط خلوت"> حیاط خلوت</label>
                <label class="checkbox-label"><input type="checkbox" name="amenities_villa[]" value="دوبلکس"> دوبلکس</label>
            </div>
        </div>

        <!-- STEP 4 -->
        <div class="step-content" id="step4">
            <h2 class="step-title">قیمت</h2>
            
            <div id="price-sell" style="display:none;">
                <div class="form-group"><label>قیمت (تومان)</label><input type="text" class="form-input price-input" name="price_sell" placeholder="۲,۸۰۰,۰۰۰,۰۰۰"></div>
                <div style="display:flex;gap:var(--space-2);margin-top:var(--space-1);">
                    <label><input type="checkbox" name="price_condition" value="negotiable" onchange="togglePriceCondition(this,'fixed')"> قابل مذاکره</label>
                    <label><input type="checkbox" name="price_condition" value="fixed" onchange="togglePriceCondition(this,'negotiable')"> مقطوع</label>
                </div>
                <div style="margin-top:var(--space-2);">
                    <label style="display:flex;align-items:center;gap:var(--space-1);">
                        <input type="checkbox" id="exchangeCheckbox" name="exchange_interested" value="1" onchange="toggleExchange()">
                        مایل به معاوضه هستم
                    </label>
                    <div id="exchangeGroup" style="margin-top:var(--space-1);">
                            <div class="form-group">
                                <label>معاوضه با (می‌توانید چند مورد انتخاب کنید):</label>
                                <div style="display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:6px;">
                                    <label style="display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;"><input type="checkbox" name="exchange_types[]" value="آپارتمان"> آپارتمان</label>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;"><input type="checkbox" name="exchange_types[]" value="باغ"> باغ</label>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;"><input type="checkbox" name="exchange_types[]" value="ویلایی"> ویلایی</label>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;"><input type="checkbox" name="exchange_types[]" value="اداری"> اداری</label>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;"><input type="checkbox" name="exchange_types[]" value="مغازه"> مغازه</label>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;"><input type="checkbox" name="exchange_types[]" value="زمین"> زمین</label>
                                    <label style="display:flex;align-items:center;gap:6px;font-size:14px;cursor:pointer;"><input type="checkbox" name="exchange_types[]" value="خودرو"> خودرو</label>
                                </div>
                            </div>
                        <div class="form-group">
                            <label>معاوضه با:</label>
                            <textarea class="form-textarea" name="exchange_with" placeholder="مثلاً: معاوضه با ویلا بزرگ‌تر یا معاوضه با آپارتمان یا ... برای ما شرایط خود را توضیح دهید." rows="3" disabled></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div id="price-rent" style="display:none;">
                <div class="row-half">
                    <div class="form-group"><label>ودیعه</label><input type="text" class="form-input price-input" name="deposit" placeholder="۵۰۰,۰۰۰,۰۰۰"></div>
                    <div class="form-group"><label>اجاره ماهانه</label><input type="text" class="form-input price-input" name="rent_monthly" placeholder="۳۰,۰۰۰,۰۰۰"></div>
                </div>
                <div style="margin-top:var(--space-1);"><label><input type="checkbox" id="fullRentCheckbox" onchange="toggleFullRent()"> رهن کامل</label></div>
                <div id="fullRentGroup"><div class="form-group"><label>مبلغ رهن کامل</label><input type="text" class="form-input price-input" name="full_rent" placeholder="۱,۰۰۰,۰۰۰,۰۰۰"></div></div>
            </div>

            <div id="price-pre-sell" style="display:none;">
                <div class="form-group"><label>قیمت کل</label><input type="text" class="form-input price-input" name="total_price" placeholder="۳,۰۰۰,۰۰۰,۰۰۰"></div>
                <div class="form-group"><label>پیش‌پرداخت</label><input type="text" class="form-input price-input" name="down_payment" placeholder="۹۰۰,۰۰۰,۰۰۰"></div>
                <div class="form-group"><label>شرایط پرداخت</label><textarea class="form-textarea" name="payment_terms" rows="3" placeholder="مثال: ۳۰٪ قرارداد، ۲۰٪ اسکلت..."></textarea></div>
            </div>

            <!-- ===== وام (فقط فروش و پیش‌فروش) ===== -->
            <div id="loanSection" style="display:none; margin-top:var(--space-3); border:1px solid var(--border); border-radius:12px; padding:var(--space-2);">
                <div style="font-size:13px; color:var(--text-secondary); line-height:2; margin-bottom:var(--space-1); background:var(--gold-bg); border-radius:8px; padding:8px 10px;">
                    ℹ️ مبلغ وام از قیمت درج شده کسر می‌شود؛ در کارت‌ها و صفحهٔ آگهی هر دو قیمت نمایش داده می‌شود:
                    قیمت کامل و «قیمت منهای مبلغ وام + وام»
                    (مثلاً: ۱۵,۰۰۰,۰۰۰,۰۰۰ تومان ← ۱۴,۷۰۰,۰۰۰,۰۰۰ تومان + ۳۰۰,۰۰۰,۰۰۰ تومان وام).
                </div>
                <label style="display:flex;align-items:center;gap:var(--space-1);font-weight:600;">
                    <input type="checkbox" id="hasLoanCheckbox" name="has_loan" value="1" onchange="toggleLoan()">
                    وام دارد
                </label>
                <div id="loanGroup" style="display:none; margin-top:var(--space-2);">
                    <div class="row-half">
                        <div class="form-group"><label>مبلغ وام (تومان)</label><input type="text" class="form-input price-input" name="loan_amount" placeholder="۳۰۰,۰۰۰,۰۰۰"></div>
                        <div class="form-group"><label>نوع وام</label><input type="text" class="form-input" name="loan_type" placeholder="مثلاً: وام مسکن / اوراق"></div>
                    </div>
                    <div class="row-half">
                        <div class="form-group"><label>مدت وام</label><input type="text" class="form-input" name="loan_duration" placeholder="مثلاً: ۱۲ سال"></div>
                        <div class="form-group"><label>بانک</label><input type="text" class="form-input" name="loan_bank" placeholder="مثلاً: بانک مسکن"></div>
                    </div>
                    <div class="row-half">
                        <div class="form-group"><label>مبلغ هر قسط (تومان)</label><input type="text" class="form-input price-input" name="loan_installment" placeholder="۵,۰۰۰,۰۰۰"></div>
                        <div class="form-group"><label>تعداد اقساط پرداخت شده</label><input type="text" class="form-input" name="loan_installments_paid" placeholder="مثلاً: ۲۴"></div>
                    </div>
                    <div class="form-group"><label>توضیحات تکمیلی وام</label><textarea class="form-textarea" name="loan_notes" rows="2" placeholder="توضیحات بیشتر دربارهٔ وام (اختیاری)"></textarea></div>
                </div>
            </div>

            <script>
                const step4TransType = sessionStorage.getItem('reg_transaction_type') || 'فروش';
                if (step4TransType === 'فروش') {
                    document.getElementById('price-sell').style.display = 'block';
                    document.getElementById('exchangeGroup').classList.remove('visible');
                    document.querySelector('#exchangeGroup textarea').disabled = true;
                } else if (step4TransType === 'اجاره') {
                    document.getElementById('price-rent').style.display = 'block';
                    const fullRentCheckbox = document.getElementById('fullRentCheckbox');
                    if (fullRentCheckbox) {
                        toggleFullRent();
                    }
                } else if (step4TransType === 'پیش فروش') {
                    document.getElementById('price-pre-sell').style.display = 'block';
                }

                // بخش وام فقط برای فروش و پیش‌فروش نمایش داده می‌شود
                (function () {
                    const loanSection = document.getElementById('loanSection');
                    if (!loanSection) return;
                    if (step4TransType === 'فروش' || step4TransType === 'پیش فروش') {
                        loanSection.style.display = 'block';
                    } else {
                        loanSection.style.display = 'none';
                    }
                    if (typeof toggleLoan === 'function') toggleLoan();
                })();

                function toggleLoan() {
                    const chk = document.getElementById('hasLoanCheckbox');
                    const grp = document.getElementById('loanGroup');
                    if (!chk || !grp) return;
                    const inputs = grp.querySelectorAll('input, textarea');
                    if (chk.checked) {
                        grp.style.display = 'block';
                        inputs.forEach(el => { el.disabled = false; });
                    } else {
                        grp.style.display = 'none';
                        inputs.forEach(el => { el.disabled = true; el.value = ''; });
                    }
                }
            </script>
        </div>

                <!-- ===== STEP 5: نوع سند (راند ۱۴) ===== -->
        <div class="step-content" id="step5">
            <h2 class="step-title">نوع سند</h2>

            <div class="form-group">
                <label>نوع سند ملک</label>
                <div class="radio-group" style="gap:var(--space-2);">
                    <label class="radio-label"><input type="radio" name="deed_type" value="طلق"> طلق</label>
                    <label class="radio-label"><input type="radio" name="deed_type" value="وقفی"> وقفی</label>
                    <label class="radio-label"><input type="radio" name="deed_type" value="مشاعی"> مشاعی</label>
                    <label class="radio-label"><input type="radio" name="deed_type" value="عرصه"> عرصه</label>
                    <label class="radio-label"><input type="radio" name="deed_type" value="اعیان"> اعیان</label>
                    <label class="radio-label"><input type="radio" name="deed_type" value="رهنی"> رهنی</label>
                    <label class="radio-label"><input type="radio" name="deed_type" value="قولنامه عادی"> قولنامه عادی</label>
                    <label class="radio-label"><input type="radio" name="deed_type" value="قولنامه شورایی"> قولنامه شورایی</label>
                    <label class="radio-label"><input type="radio" name="deed_type" value="برگه واگذاری"> برگه واگذاری</label>
                </div>
            </div>

            <div class="form-group">
                <label>توضیحات</label>
                <textarea class="form-textarea" name="deed_notes" rows="3" placeholder="اگر سند نیازمند توضیح است در این کادر بنویسید"></textarea>
            </div>
        </div>

        <!-- ===== STEP 6: تصاویر، توضیحات و ثبت نهایی ===== -->
        <div class="step-content" id="step6">
            <h2 class="step-title">تصاویر، توضیحات و ثبت نهایی</h2>
            
            <!-- بخش اول: آپلود تصاویر با AJAX (با کلیک) -->
            <div class="form-group">
                <label>آپلود تصاویر (حداکثر ۵ مگابایت، فرمت JPG, PNG, WEBP)</label>
                <div id="dropZone" class="upload-area" style="cursor:pointer; text-align:center; padding:20px;" onclick="document.getElementById('fileInput').click()">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                    <br>
                    <span>برای آپلود تصاویر کلیک کنید یا بکشید و رها کنید</span>
                    <br>
                    <small style="color:var(--text-secondary);">حداکثر ۵ مگابایت - فرمت‌های مجاز: JPG, PNG, WEBP</small>
                </div>
                <input type="file" id="fileInput" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none;" onchange="handleImageUpload(this)">
                <div id="uploadProgress" style="display:none; margin-top:8px;">
                    <div style="width:100%; height:6px; background:var(--border); border-radius:3px; overflow:hidden;">
                        <div id="progressBar" style="width:0%; height:100%; background:var(--primary); transition:width 0.3s;"></div>
                    </div>
                    <span id="progressText" style="font-size:13px; color:var(--text-secondary);">در حال آپلود...</span>
                </div>
                <div id="imagePreviewContainer" class="image-preview-grid"></div>
            </div>

            <!-- ===== گزینه جدید: انتخاب حالت انتشار تصاویر (بعد از آپلود و فقط در صورت وجود عکس) ===== -->
            <div id="publishOptionsContainer" style="display: none; margin-top: var(--space-2);">
                <div class="form-group">
                    <label>انتخاب حالت انتشار تصاویر</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="publish_photos" value="yes" checked> مایل به انتشار عکس‌ها در آگهی هستم</label>
                        <label class="radio-label"><input type="radio" name="publish_photos" value="no"> مایل به انتشار عکس‌ها در آگهی نیستم و فقط عکس‌ها به مشتریان حضوری نمایش داده شود</label>
                    </div>
                </div>
            </div>

            <!-- بخش دوم: توضیحات تکمیلی -->
            <div class="form-group">
                <label>توضیحات تکمیلی (حداکثر ۱۰۰۰ کاراکتر)</label>
                <textarea class="form-textarea" id="regFullDesc" name="full_description" placeholder="شرح کامل امکانات و شرایط ملک..." maxlength="1000" oninput="document.getElementById('charCount').innerText = this.value.length"></textarea>
                <small style="color:var(--text-secondary);">کاراکتر وارد شده: <span id="charCount">0</span> / ۱۰۰۰</small>
            </div>

            <!-- بخش جدید: زمان بازدید -->
            <div class="form-group" style="margin-top:var(--space-1);">
                <label>چه زمان‌هایی میشه بازدید کرد؟</label>
                <textarea class="form-textarea" name="visit_hours" rows="3" placeholder="مثال: فقط عصرها میشه بازدید کرد یا همیشه میشه بازدید کرد با هماهنگی قبلی یک ساعت زودتر یا ..."></textarea>
            </div>

            <!-- بخش سوم: پیش‌نمایش نهایی -->
            <div id="finalPreviewContainer" style="display:none;">
                <div class="preview-card">
                    <div class="preview-card-title">📋 پیش‌نمایش نهایی آگهی</div>
                    <div id="previewContent"></div>
                </div>

                <div style="display:flex; gap:var(--space-2); flex-wrap:wrap; margin-top:var(--space-3);">
                    <button type="button" class="btn-secondary" onclick="editPreview()" style="flex:1;max-width:none;">ویرایش اطلاعات</button>
                    <button type="submit" name="submit_property" class="btn-primary-full" style="flex:1;max-width:none;">ثبت نهایی آگهی</button>
                </div>
            </div>
        </div>

        <!-- ===== دکمه‌های ناوبری (درون فرم) ===== -->
    </form>
</div>

<div class="bottom-actions" id="regBottomActions">
    <button type="button" class="btn-secondary" id="prevBtn" onclick="changeStep(-1)" style="display:none;">مرحله قبل</button>
    <button type="button" class="btn-primary-full" id="nextBtn" onclick="changeStep(1)">مرحله بعد</button>
</div>
<script src="form-wizard.js?v=<?= (int) @filemtime(__DIR__ . '/form-wizard.js') ?>"></script>

<script>
    // ==============================================
    // خواندن اطلاعات کاربر از sessionStorage
    // ==============================================
        document.addEventListener('DOMContentLoaded', function() {
        if (typeof window.melkinoInitJalaliPicker === 'function') {
            var jalaliDelivery = document.getElementById('regDeliveryDateJalali');
            if (jalaliDelivery) {
                window.melkinoInitJalaliPicker(jalaliDelivery, { hiddenGregorianId: 'regDeliveryDate', maxMonths: 36 });
            }
        }
        const gender = sessionStorage.getItem('reg_gender') || 'آقا';
        const MELKINO_P = window.MELKINO_PROFILE || {};
        // راند ۳۰: مقادیر سمت سرور (MELKINO_PROFILE) بر sessionStorage

        // اولویت دارند — دادهٔ نمایشی تماس باید از بک‌اند بیاید.

        const lastName = (MELKINO_P.logged_in && MELKINO_P.name) ? MELKINO_P.name : (sessionStorage.getItem('reg_last_name') || '');

        const phone = (MELKINO_P.logged_in && MELKINO_P.phone) ? MELKINO_P.phone : (sessionStorage.getItem('reg_phone') || '');
        const telegramId = sessionStorage.getItem('reg_telegram_id') || '';
        const transactionType = sessionStorage.getItem('reg_transaction_type') || 'فروش';
        
        document.getElementById('hidden_gender').value = gender;
        document.getElementById('hidden_last_name').value = lastName;
        document.getElementById('hidden_phone').value = phone;
        document.getElementById('hidden_telegram_id').value = telegramId;
        document.getElementById('hidden_transaction_type').value = transactionType;
        
        console.log('اطلاعات کاربر:', { gender, lastName, phone, transactionType });

        // راند ۳۰: اگر شمارهٔ تأییدشده ندارد، هشدار + لینک پروفایل
        // (بک‌اند هم ثبت نهایی را مسدود می‌کند؛ این فقط راهنمای کاربر است)
        if (MELKINO_P.logged_in && !MELKINO_P.phone_verified) {
            const warn30 = document.createElement('div');
            warn30.style.cssText = 'margin:10px 0 14px;padding:12px 14px;border-radius:12px;background:rgba(217,119,6,.12);border:1px solid rgba(217,119,6,.35);font-size:12.5px;line-height:2.1;color:#FFD47E;text-align:center;';
            warn30.innerHTML = '📵 برای ثبت آگهی، ابتدا باید شمارهٔ تماس خود را در پروفایل ثبت و تأیید کنید. <a href="profile.php" style="color:#fff;background:#064e4e;padding:5px 14px;border-radius:8px;text-decoration:none;display:inline-block;margin-inline-start:6px;">تکمیل شماره تماس</a>';
            const mc30 = document.getElementById('mainContent');
            if (mc30) { mc30.insertBefore(warn30, mc30.firstChild); }
        }

        
        // راند ۱۸: کارت «📇 تکمیل اطلاعات تماس» طبق درخواست از فرم حذف شد.
        // نام و شماره همچنان از پروفایل کاربر خوانده و در فیلدهای مخفی
        // (hidden_last_name / hidden_phone) قرار می‌گیرد.
        
        // ==============================================
        // Drag and Drop
        // ==============================================
        const dropZone = document.getElementById('dropZone');
        
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--primary)';
            this.style.background = 'var(--gold-bg)';
        });
        
        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--border)';
            this.style.background = 'var(--bg)';
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = 'var(--border)';
            this.style.background = 'var(--bg)';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('fileInput');
                fileInput.files = files;
                handleImageUpload(fileInput);
            }
        });

        // ==============================================
        // به‌روزرسانی فیلدهای استپ ۲ بر اساس نوع معامله
        // ==============================================
        updateStep2Fields(transactionType);
    });

    let currentStep = 1;
    const totalSteps = 6;
    const transType = sessionStorage.getItem('reg_transaction_type') || 'فروش';
    let uploadedImages = [];

    // ===================== توابع قیمت =====================
    function togglePriceCondition(el, otherVal) {
        const parent = el.closest('div');
        const other = parent.querySelector(`input[value="${otherVal}"]`);
        if (el.checked && other) other.checked = false;
    }

    function toggleFullRent() {
        const chk = document.getElementById('fullRentCheckbox');
        const grp = document.getElementById('fullRentGroup');
        const inp = grp.querySelector('input');
        const depositInput = document.querySelector('input[name="deposit"]');
        const rentInput = document.querySelector('input[name="rent_monthly"]');

        if (chk.checked) {
            grp.classList.add('visible');
            inp.disabled = false;
            depositInput.required = false;
            rentInput.required = false;
        } else {
            grp.classList.remove('visible');
            inp.disabled = true;
            inp.required = false;
            inp.value = '';
            depositInput.required = false;
            rentInput.required = false;
        }
    }

    function toggleExchange() {
        var chk = document.getElementById('exchangeCheckbox');
        var grp = document.getElementById('exchangeGroup');
        var inp = grp.querySelector('textarea');
        var boxes = grp.querySelectorAll('input[type="checkbox"]');

        if (chk.checked) {
            grp.classList.add('visible');
            inp.disabled = false;
            inp.required = false;
            boxes.forEach(function (b) { b.disabled = false; });
        } else {
            grp.classList.remove('visible');
            inp.disabled = true;
            inp.required = false;
            inp.value = '';
            boxes.forEach(function (b) { b.disabled = true; b.checked = false; });
        }
    }

    // ===================== توابع استپ ۲ =====================
    function updateStep2Fields(transactionType) {
        const conditionContainer = document.getElementById('conditionCheckboxesContainer');
        const deliveryDateContainer = document.getElementById('deliveryDateContainer');
        const rentalFieldsContainer = document.getElementById('rentalFieldsContainer');
        const yearRow = document.getElementById('yearRow');

        // مخفی کردن همه‌ی بخش‌های اضافی
        conditionContainer.style.display = 'none';
        deliveryDateContainer.style.display = 'none';
        rentalFieldsContainer.style.display = 'none';

        // نمایش بر اساس نوع معامله
        if (transactionType === 'فروش') {
            conditionContainer.style.display = 'block';
            yearRow.style.gridTemplateColumns = '1fr 1fr';
        } else if (transactionType === 'پیش فروش') {
            deliveryDateContainer.style.display = 'block';
            yearRow.style.gridTemplateColumns = '1fr 1fr';
        } else if (transactionType === 'اجاره') {
            conditionContainer.style.display = 'block';
            rentalFieldsContainer.style.display = 'block';
            yearRow.style.gridTemplateColumns = '1fr'; // سال ساخت تنها در یک ستون
        }
    }

    function toggleVacancy() {
        const chk = document.getElementById('regIsVacant');
        const dateInput = document.getElementById('regVacancyDate');
        
        if (chk.checked) {
            dateInput.disabled = true;
            dateInput.value = '';
            dateInput.required = false;
        } else {
            dateInput.disabled = false;
            dateInput.required = false;
        }
    }

    // ===================== مدیریت نمایش گزینه‌های انتشار بر اساس وجود عکس =====================
    function togglePublishOptions() {
        const container = document.getElementById('publishOptionsContainer');
        if (uploadedImages.length > 0) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }

    // ==============================================
    // آپلود تصاویر با AJAX (مطمئن‌ترین روش)
    // ==============================================
    function handleImageUpload(input) {
        const files = Array.from(input.files);
        const maxSize = 5 * 1024 * 1024;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (files.length === 0) {
            alert('لطفاً حداقل یک تصویر انتخاب کنید.');
            return;
        }

        // بررسی فایل‌ها
        const validFiles = [];
        files.forEach(file => {
            if (!allowedTypes.includes(file.type)) {
                alert(`فرمت ${file.type} مجاز نیست. فقط JPG, PNG, WEBP مجاز است.`);
                return;
            }
            if (file.size > maxSize) {
                alert(`حجم فایل ${file.name} بیش از ۵ مگابایت است.`);
                return;
            }
            validFiles.push(file);
        });

        if (validFiles.length === 0) return;

        // نمایش پیش‌نمایش
        validFiles.forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const container = document.getElementById('imagePreviewContainer');
                const div = document.createElement('div');
                div.className = 'image-preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="تصویر ${uploadedImages.length + 1}">
                    <button class="remove-btn" onclick="removeImage(${uploadedImages.length})">✕</button>
                `;
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        });

        // ارسال فایل‌ها با AJAX
        const formData = new FormData();
        validFiles.forEach(file => {
            formData.append('images[]', file);
        });
        formData.append('action', 'upload_images');

        const progressDiv = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        
        progressDiv.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.innerText = 'در حال آپلود...';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressText.innerText = 'آپلود: ' + percent + '%';
            }
        };
        
        xhr.onload = function() {
            progressDiv.style.display = 'none';
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        uploadedImages = uploadedImages.concat(response.images);
                        document.getElementById('uploaded_images').value = uploadedImages.join(',');
                        togglePublishOptions();
                        console.log('✅ تصاویر آپلود شدند:', uploadedImages);
                    } else {
                        alert('خطا در آپلود تصاویر');
                    }
                } catch (e) {
                    alert('خطا در پردازش پاسخ سرور');
                }
            } else {
                alert('خطا در ارتباط با سرور');
            }
            input.value = '';
        };
        
        xhr.onerror = function() {
            progressDiv.style.display = 'none';
            alert('خطا در آپلود تصاویر');
            input.value = '';
        };
        
        xhr.send(formData);
    }

    function removeImage(index) {
        uploadedImages.splice(index, 1);
        document.getElementById('uploaded_images').value = uploadedImages.join(',');
        
        // حذف از پیش‌نمایش
        const container = document.getElementById('imagePreviewContainer');
        const items = container.querySelectorAll('.image-preview-item');
        if (items[index]) {
            items[index].remove();
        }
        
        togglePublishOptions();
    }

    // ===================== پیش‌نمایش نهایی =====================
    function updatePreview() {
        const previewContainer = document.getElementById('finalPreviewContainer');
        previewContainer.style.display = 'block';

        const gender = document.getElementById('hidden_gender').value || '-';
        const lastName = document.getElementById('hidden_last_name').value || '-';
        const phone = document.getElementById('hidden_phone').value || '-';
        const transTypeLabel = transType === 'فروش' ? 'خرید و فروش' : transType === 'پیش فروش' ? 'پیش فروش' : 'اجاره';
        const propertyType = 'ویلایی';
        const title = document.getElementById('regTitle').value || '-';
        const location = document.getElementById('regLocation').value || '-';
        const address = document.getElementById('regAddress').value || '-';
        const description = document.getElementById('regFullDesc').value || '-';
        
        const publishPhotos = document.querySelector('input[name="publish_photos"]:checked');
        const publishStatus = publishPhotos ? (publishPhotos.value === 'yes' ? '✅ منتشر شود' : '❌ منتشر نشود (فقط حضوری)') : '-';

        let specsHtml = '';
        document.querySelectorAll('#step2 .form-group').forEach(group => {
            const label = group.querySelector('label')?.innerText || '';
            let value = '';
            const input = group.querySelector('input, select');
            if (input) {
                if (input.tagName === 'SELECT') {
                    value = input.options[input.selectedIndex]?.innerText || '-';
                } else {
                    value = input.value || '-';
                }
            }
            specsHtml += `<div class="preview-row"><span class="preview-label">${label}</span><span class="preview-value rtl">${value}</span></div>`;
        });

        // اضافه کردن سه چک‌باکس به پیش‌نمایش
        const isNotKeyed = document.getElementById('regIsNotKeyed');
        const isOld = document.getElementById('regIsOld');
        const isRenovated = document.getElementById('regIsRenovated');
        if (isNotKeyed && isNotKeyed.checked) {
            specsHtml += `<div class="preview-row"><span class="preview-label">کلید نخورده</span><span class="preview-value rtl">بله</span></div>`;
        }
        if (isOld && isOld.checked) {
            specsHtml += `<div class="preview-row"><span class="preview-label">کلنگی</span><span class="preview-value rtl">بله</span></div>`;
        }
        if (isRenovated && isRenovated.checked) {
            specsHtml += `<div class="preview-row"><span class="preview-label">بازسازی شده</span><span class="preview-value rtl">بله</span></div>`;
        }

        // اضافه کردن تاریخ تحویل (پیش‌فروش)
        const deliveryDateJalali = document.getElementById('regDeliveryDateJalali');
        const deliveryDate = document.getElementById('regDeliveryDate');
        const deliveryShown = (deliveryDateJalali && deliveryDateJalali.value) ? deliveryDateJalali.value : (deliveryDate && deliveryDate.value ? deliveryDate.value : '');
        if (deliveryShown) {
            specsHtml += `<div class="preview-row"><span class="preview-label">تاریخ تحویل</span><span class="preview-value rtl">${deliveryShown}</span></div>`;
        }

        // اضافه کردن تاریخ تخلیه و ساختمان تخلیه است (اجاره)
        const vacancyDate = document.getElementById('regVacancyDate');
        const isVacant = document.getElementById('regIsVacant');
        if (vacancyDate && vacancyDate.value) {
            specsHtml += `<div class="preview-row"><span class="preview-label">تاریخ تخلیه</span><span class="preview-value rtl">${vacancyDate.value}</span></div>`;
        }
        if (isVacant && isVacant.checked) {
            specsHtml += `<div class="preview-row"><span class="preview-label">ساختمان تخلیه است</span><span class="preview-value rtl">بله</span></div>`;
        }

        let amenitiesHtml = '';
        document.querySelectorAll('#step3 input[type="checkbox"]:checked').forEach(cb => {
            amenitiesHtml += `<span style="display:inline-block;background:var(--bg);padding:2px 8px;border-radius:4px;border:1px solid var(--border);margin:2px;">${cb.value}</span> `;
        });
        if (!amenitiesHtml) amenitiesHtml = 'هیچکدام';

        let priceHtml = '';
        if (transType === 'فروش') {
            const price = document.querySelector('input[name="price_sell"]').value || '-';
            const condition = document.querySelector('input[name="price_condition"]:checked')?.value || 'negotiable';
            const exchangeChk = document.getElementById('exchangeCheckbox');
            const exchangeVal = exchangeChk && exchangeChk.checked ? document.querySelector('textarea[name="exchange_with"]').value : '-';
            priceHtml = `<div class="preview-row"><span class="preview-label">قیمت</span><span class="preview-value">${price} تومان</span></div>`;
            priceHtml += `<div class="preview-row"><span class="preview-label">وضعیت</span><span class="preview-value rtl">${condition === 'negotiable' ? 'قابل مذاکره' : 'مقطوع'}</span></div>`;
            if (exchangeChk && exchangeChk.checked) {
                priceHtml += `<div class="preview-row"><span class="preview-label">معاوضه با</span><span class="preview-value rtl">${exchangeVal}</span></div>`;
            }
        } else if (transType === 'اجاره') {
            const deposit = document.querySelector('input[name="deposit"]').value || '-';
            const rent = document.querySelector('input[name="rent_monthly"]').value || '-';
            const fullRentChk = document.getElementById('fullRentCheckbox');
            const fullRentVal = fullRentChk.checked ? document.querySelector('input[name="full_rent"]').value : '-';
            priceHtml = `<div class="preview-row"><span class="preview-label">ودیعه</span><span class="preview-value">${deposit} تومان</span></div>`;
            priceHtml += `<div class="preview-row"><span class="preview-label">اجاره ماهانه</span><span class="preview-value">${rent} تومان</span></div>`;
            if (fullRentChk.checked) {
                priceHtml += `<div class="preview-row"><span class="preview-label">رهن کامل</span><span class="preview-value">${fullRentVal} تومان</span></div>`;
            }
        } else if (transType === 'پیش فروش') {
            const total = document.querySelector('input[name="total_price"]').value || '-';
            const down = document.querySelector('input[name="down_payment"]').value || '-';
            const terms = document.querySelector('textarea[name="payment_terms"]').value || '-';
            priceHtml = `<div class="preview-row"><span class="preview-label">قیمت کل</span><span class="preview-value">${total} تومان</span></div>`;
            priceHtml += `<div class="preview-row"><span class="preview-label">پیش‌پرداخت</span><span class="preview-value">${down} تومان</span></div>`;
            priceHtml += `<div class="preview-row"><span class="preview-label">شرایط پرداخت</span><span class="preview-value rtl">${terms}</span></div>`;
        }

        // اضافه کردن ساعت بازدید به پیش‌نمایش
        const visitHours = document.querySelector('textarea[name="visit_hours"]').value || '-';

                // ===== سند، معاوضه و وام در پیش‌نمایش (راند ۱۴) =====
        const deedCheckedBox = document.querySelector('input[name="deed_type"]:checked');
        const deedTypeVal = deedCheckedBox ? deedCheckedBox.value : '-';
        const deedNotesEl = document.querySelector('textarea[name="deed_notes"]');
        const deedNotesVal = (deedNotesEl && deedNotesEl.value) ? deedNotesEl.value : '-';
        const exTypesChecked = Array.from(document.querySelectorAll('input[name="exchange_types[]"]:checked')).map(c => c.value);
        const exMainChk = document.getElementById('exchangeCheckbox');
        const wantsExchange = !!(exMainChk && exMainChk.checked);
        const exWithEl = document.querySelector('textarea[name="exchange_with"]');
        const exWithVal = (wantsExchange && exWithEl && exWithEl.value) ? exWithEl.value : '';
        let exchangePreviewVal = 'خیر';
        if (wantsExchange) {
            const parts = [];
            if (exTypesChecked.length) parts.push(exTypesChecked.join('، '));
            if (exWithVal) parts.push(exWithVal);
            exchangePreviewVal = 'بله' + (parts.length ? ' — ' + parts.join(' — ') : '');
        }
        let loanPreviewHtml = '';
        const hasLoanChk = document.getElementById('hasLoanCheckbox');
        if (hasLoanChk && hasLoanChk.checked) {
            const lv = function (n) { const el = document.querySelector('[name="' + n + '"]'); return (el && el.value) ? el.value : '-'; };
            loanPreviewHtml = `
            <div class="preview-row"><span class="preview-label">وام دارد</span><span class="preview-value rtl">بله</span></div>
            <div class="preview-row"><span class="preview-label">مبلغ وام</span><span class="preview-value">${lv('loan_amount')} تومان</span></div>
            <div class="preview-row"><span class="preview-label">نوع وام</span><span class="preview-value rtl">${lv('loan_type')}</span></div>
            <div class="preview-row"><span class="preview-label">مدت وام</span><span class="preview-value rtl">${lv('loan_duration')}</span></div>
            <div class="preview-row"><span class="preview-label">بانک</span><span class="preview-value rtl">${lv('loan_bank')}</span></div>
            <div class="preview-row"><span class="preview-label">مبلغ هر قسط</span><span class="preview-value">${lv('loan_installment')} تومان</span></div>
            <div class="preview-row"><span class="preview-label">تعداد اقساط پرداخت شده</span><span class="preview-value rtl">${lv('loan_installments_paid')}</span></div>
            <div class="preview-row"><span class="preview-label">توضیحات وام</span><span class="preview-value rtl" style="font-weight:normal;">${lv('loan_notes')}</span></div>`;
        }

        document.getElementById('previewContent').innerHTML = `
            <div class="preview-card-title" style="font-size:14px;margin-top:0;">اطلاعات تماس</div>
            <div class="preview-row"><span class="preview-label">جنسیت</span><span class="preview-value rtl">${gender}</span></div>
            <div class="preview-row"><span class="preview-label">نام خانوادگی</span><span class="preview-value rtl">${lastName}</span></div>
            <div class="preview-row"><span class="preview-label">شماره تماس</span><span class="preview-value">${phone}</span></div>
            
            <div class="preview-card-title" style="font-size:14px;">نوع معامله و ملک</div>
            <div class="preview-row"><span class="preview-label">نوع معامله</span><span class="preview-value rtl">${transTypeLabel}</span></div>
            <div class="preview-row"><span class="preview-label">نوع ملک</span><span class="preview-value rtl">${propertyType}</span></div>

            <div class="preview-card-title" style="font-size:14px;">اطلاعات پایه</div>
            <div class="preview-row"><span class="preview-label">عنوان</span><span class="preview-value rtl">${title}</span></div>
            <div class="preview-row"><span class="preview-label">محله</span><span class="preview-value rtl">${location}</span></div>
            <div class="preview-row"><span class="preview-label">آدرس دقیق</span><span class="preview-value rtl">${address}</span></div>

            <div class="preview-card-title" style="font-size:14px;">مشخصات ملک</div>
            ${specsHtml}

            <div class="preview-card-title" style="font-size:14px;">امکانات</div>
            <div class="preview-row"><span class="preview-label">انتخاب شده</span><span class="preview-value rtl" style="font-weight:normal;">${amenitiesHtml}</span></div>

            <div class="preview-card-title" style="font-size:14px;">قیمت</div>
            ${priceHtml}${loanPreviewHtml}

            <div class="preview-card-title" style="font-size:14px;">سند و معاوضه</div>
            <div class="preview-row"><span class="preview-label">نوع سند</span><span class="preview-value rtl">${deedTypeVal}</span></div>
            <div class="preview-row"><span class="preview-label">توضیحات سند</span><span class="preview-value rtl" style="font-weight:normal;white-space:pre-wrap;">${deedNotesVal}</span></div>
            <div class="preview-row"><span class="preview-label">مایل به معاوضه</span><span class="preview-value rtl" style="font-weight:normal;">${exchangePreviewVal}</span></div>

            <div class="preview-card-title" style="font-size:14px;">تصاویر</div>
            <div class="preview-row"><span class="preview-label">وضعیت انتشار</span><span class="preview-value rtl">${publishStatus}</span></div>
            <div id="previewImages" class="image-preview-grid"></div>

            <div class="preview-card-title" style="font-size:14px;">توضیحات و زمان بازدید</div>
            <div class="preview-row"><span class="preview-label">توضیحات</span><span class="preview-value rtl" style="font-weight:normal;white-space:pre-wrap;">${description}</span></div>
            <div class="preview-row"><span class="preview-label">زمان‌های بازدید</span><span class="preview-value rtl" style="font-weight:normal;">${visitHours}</span></div>
        `;
        renderPreviewImages();
    }

    function renderPreviewImages() {
        const container = document.getElementById('previewImages');
        container.innerHTML = '';
        uploadedImages.forEach((imgPath, index) => {
            const div = document.createElement('div');
            div.className = 'image-preview-item';
            div.innerHTML = `<img src="${imgPath}" alt="تصویر ${index+1}">`;
            container.appendChild(div);
        });
    }

    // ===================== دکمه‌های گام ۵ =====================
    function editPreview() {
        document.getElementById('finalPreviewContainer').style.display = 'none';
        document.getElementById('prevBtn').style.display = (currentStep === 1) ? 'none' : 'flex';
        document.getElementById('nextBtn').style.display = 'flex';
        document.getElementById('nextBtn').innerText = 'مرحله بعد';
        changeStep(-5);
    }

    // ===================== تغییر گام‌ها =====================
    function changeStep(direction) {
        if (direction === 1 && typeof melkinoValidateWizardStep === 'function') {
            if (!melkinoValidateWizardStep('step' + currentStep)) {
                return;
            }
        }
        if (currentStep === totalSteps && direction === 1) {
            document.getElementById('prevBtn').style.display = 'none';
            document.getElementById('nextBtn').style.display = 'none';
            updatePreview();
            return;
        }

        document.getElementById(`step${currentStep}`).classList.remove('active');
        currentStep += direction;
        if(currentStep < 1) currentStep = 1;
        if(currentStep > totalSteps) currentStep = totalSteps;
        document.getElementById(`step${currentStep}`).classList.add('active');
        document.getElementById('stepText').innerText = `مرحله ${currentStep} از ${totalSteps}`;
        for(let i=1; i<totalSteps; i++) {
            let line = document.getElementById(`line${i}`);
            if(i < currentStep) line.classList.add('active');
            else line.classList.remove('active');
        }

        document.getElementById('prevBtn').style.display = (currentStep === 1) ? 'none' : 'flex';
        document.getElementById('nextBtn').innerText = (currentStep === totalSteps) ? 'پیش‌نمایش و ثبت' : 'مرحله بعد';
        document.getElementById('mainContent').scrollTop = 0;
    }

    // ===================== تبدیل اعداد فارسی به انگلیسی =====================
    function toEnglishDigits(str) {
        const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        for (let i = 0; i < 10; i++) {
            str = str.replaceAll(persianDigits[i], i);
            str = str.replaceAll(arabicDigits[i], i);
        }
        // راند ۲۷: ممیز عربی ٫ → نقطه و جداکنندهٔ هزارگان ٬ → کاما
        str = str.replaceAll('\u066B', '.').replaceAll('\u066C', ',');
        return str;
    }

    function formatPrice(input) {
        let val = toEnglishDigits(input.value);
        // راند ۲۷: قیمت تومان عدد صحیح است — از اولین ممیز به بعد حذف می‌شود
        // (قبلاً ۱۲٫۵ به «۱۲۵» تبدیل می‌شد!)
        val = val.split(/[.]/)[0];
        val = val.replace(/,/g, '').replace(/[^0-9]/g, '');
        if(val === '') { input.value = ''; return; }
        let num = parseInt(val, 10);
        input.value = num.toLocaleString('en-US');
    }

    function normalizeNumeric(input) {
        let val = toEnglishDigits(input.value);
        val = val.replace(/[^0-9.]/g, '');
        if(val === '') { input.value = ''; return; }
        if ((val.match(/\./g) || []).length > 1) {
            val = val.replace(/\.(?=.*\.)/g, '');
        }
        input.value = val;
    }

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('price-input')) {
            formatPrice(e.target);
        }
        if (e.target.classList.contains('numeric-input')) {
            normalizeNumeric(e.target);
        }
    });

    (function () {
        function syncRegNavH() {
            var nav = document.querySelector('.bottom-nav');
            var h = nav ? Math.round(nav.getBoundingClientRect().height) : 74;
            if (h < 48) h = 74;
            document.documentElement.style.setProperty('--reg-bottom-nav-h', h + 'px');
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', syncRegNavH);
        } else {
            syncRegNavH();
        }
        window.addEventListener('load', syncRegNavH);
        window.addEventListener('resize', syncRegNavH);
        window.addEventListener('orientationchange', syncRegNavH);
    })();
</script>
<?php
if (function_exists('melkinoBuildingAgeBindScript')) {
    echo melkinoBuildingAgeBindScript('regYearVilla', 'regBuildingAge');
}
require_once __DIR__ . '/footer.php';
?>