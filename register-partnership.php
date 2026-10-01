<?php
/**
|--------------------------------------------------------------------------
| ملکینو — ثبت درخواست «مشارکت در ساخت»
|--------------------------------------------------------------------------
| فرم ۴ مرحله‌ای با الگوی دقیق register-land.php:
|   ۱) معرفی ملک  ۲) موقعیت و مشخصات  ۳) وضعیت ساخت  ۴) مدارک و انتشار
| منطق دیتابیس: partnership-lib.php
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
}
$_mkPage = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')));
$_mkAllow = ['login.php','logout.php','auth.php','auth-telegram.php','auth-bale.php','auth-eitaa.php','request-otp.php','verify-otp.php','admin-login.php','admin-logout.php','telegram.php','bale.php','eitaa.php','telegram-relay.php','identity-sync.php','bale-ok.php','r.php'];
if (
    $_mkPage !== ''
    && !in_array($_mkPage, $_mkAllow, true)
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
// هویت کاربر به db_helpers وابسته است؛ باید «قبل از» خواندن prefill لود شود
if (is_file(__DIR__ . '/db_helpers.php')) {
    require_once __DIR__ . '/db_helpers.php';
}
require_once __DIR__ . '/partnership-lib.php';
melkinoEnsurePartnershipSchema();

global $pdo;

/* هویت کاربر (دقیقاً مثل بقیهٔ فرم‌های ثبت) */
$melkinoPrefill = function_exists('melkinoProfilePrefill')
    ? melkinoProfilePrefill()
    : ['logged_in' => false, 'name' => '', 'phone' => ''];
$mkOwnerName  = trim((string)($melkinoPrefill['name'] ?? ($_SESSION['user_name'] ?? '')));
$mkOwnerPhone = trim((string)($melkinoPrefill['phone'] ?? ($_SESSION['user_phone'] ?? '')));

/* ---------------- آجاکس: آپلود مدارک (تکی: doc / چندتایی: images[]) ---------------- */
if (isset($_POST['action']) && $_POST['action'] === 'upload_doc') {
    header('Content-Type: application/json; charset=utf-8');
    if (function_exists('melkinoCsrfCheck')) {
        melkinoCsrfCheck();
    }
    $targetDir = __DIR__ . "/uploads/partnership";
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    if (!is_dir($targetDir) || !is_writable($targetDir)) {
        echo json_encode(['success' => false, 'message' => 'پوشه‌ی uploads/partnership قابل نوشتن نیست.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $maxDocSize = 8 * 1024 * 1024;
    $allowedDocExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

    /* چندتایی (مدارک دیگر) */
    if (isset($_FILES['images']['name']) && is_array($_FILES['images']['name'])) {
        $saved = [];
        $errs = [];
        $count = min(count($_FILES['images']['name']), 5);
        for ($i = 0; $i < $count; $i++) {
            if (empty($_FILES['images']['name'][$i]) || ($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            if ($_FILES['images']['size'][$i] > $maxDocSize) {
                $errs[] = $_FILES['images']['name'][$i] . ': حجم بیش از ۸ مگابایت';
                continue;
            }
            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedDocExt, true)) {
                $errs[] = $_FILES['images']['name'][$i] . ': فرمت مجاز نیست (تصویر یا PDF)';
                continue;
            }
            if ($finfo) {
                $mime = (string) finfo_file($finfo, $_FILES['images']['tmp_name'][$i]);
                if (strpos($mime, 'image/') !== 0 && $mime !== 'application/pdf') {
                    $errs[] = $_FILES['images']['name'][$i] . ': نوع فایل مجاز نیست';
                    continue;
                }
            }
            $fileName = 'doc_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetDir . '/' . $fileName)) {
                $saved[] = 'uploads/partnership/' . $fileName;
            } else {
                $errs[] = $_FILES['images']['name'][$i] . ': ذخیره ناموفق';
            }
        }
        if ($finfo) {
            finfo_close($finfo);
        }
        echo json_encode(['success' => count($saved) > 0, 'paths' => $saved, 'errors' => $errs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* تکی (سند / پروانه / پایان‌کار) */
    $file = $_FILES['doc'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) <= 0) {
        echo json_encode(['success' => false, 'message' => 'فایلی انتخاب نشده است.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($file['size'] > $maxDocSize) {
        echo json_encode(['success' => false, 'message' => 'حجم فایل بیش از ۸ مگابایت است.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedDocExt, true)) {
        echo json_encode(['success' => false, 'message' => 'فقط تصویر یا PDF مجاز است.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($finfo) {
        $mime = (string) finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (strpos($mime, 'image/') !== 0 && $mime !== 'application/pdf') {
            echo json_encode(['success' => false, 'message' => 'نوع فایل مجاز نیست.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    $fileName = 'doc_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $targetDir . '/' . $fileName)) {
        echo json_encode(['success' => false, 'message' => 'ذخیرهٔ فایل ناموفق بود.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'path' => 'uploads/partnership/' . $fileName], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------- ثبت نهایی (POST عادی — دقیقاً مثل register-land) ---------------- */
if (isset($_POST['submit_partnership'])) {
    if (function_exists('melkinoCsrfCheck')) {
        melkinoCsrfCheck();
    }
    $mkFail = static function (string $msg) {
        echo "<!DOCTYPE html>\n<html lang=\"fa\" dir=\"rtl\">\n<head><meta charset='UTF-8'><title>خطا در ثبت</title></head>\n"
            . "<body style='font-family: Vazirmatn, Tahoma, sans-serif; text-align: center; padding: 50px; direction: rtl;'>\n"
            . "<h2 style='color:#C0392B;'>⚠️ " . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</h2>\n"
            . "<a href='register-partnership.php' style='display:inline-block;margin-top:20px;padding:12px 24px;background:#064e4e;color:#fff;text-decoration:none;border-radius:8px;'>بازگشت به فرم</a>\n"
            . "</body>\n</html>";
        exit;
    };
    if (!($pdo instanceof PDO)) {
        $mkFail('دیتابیس در دسترس نیست. لطفاً بعداً تلاش کنید.');
    }
    // ==========================================================
    // راند ۳۰ (عیناً مثل register-land.php): هویت ثبت‌کننده فقط از
    // حساب تأییدشدهٔ سمت سرور خوانده می‌شود؛ شمارهٔ قفل‌شدهٔ پروفایل
    // جایگزین می‌شود تا درخواست دستی HTTP هم نتواند شمارهٔ دیگری ثبت کند.
    // ==========================================================
    $__idp = melkinoCurrentIdentity();
    $__up  = $__idp['user'] ?? null;
    $__phoneP = trim((string)($__up['phone'] ?? ''));
    $__verifiedP = $__phoneP !== ''
        && (!empty($__up['phone_verified']) || !empty($__up['phone_locked']));
    if (!$__verifiedP) {
        http_response_code(403);
        echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>شماره تماس تأییدشده لازم است</title></head><body style='font-family:Vazirmatn,Tahoma,sans-serif;text-align:center;padding:50px;background:#f3f4f6;'><div style='background:#fff;max-width:650px;margin:auto;padding:30px;border-radius:14px;border:1px solid #e5e7eb;'><h3 style='color:#b45309'>📵 ثبت شمارهٔ تماس الزامی است</h3><p style='line-height:2.1;color:#374151'>برای ثبت درخواست، ابتدا باید شمارهٔ تماس خود را در پروفایل ثبت و تأیید کنید. پس از تأیید، شماره قفل می‌شود و فقط ادمین می‌تواند آن را تغییر دهد. نام و شمارهٔ شما در همهٔ درخواست‌ها به‌صورت خودکار از پروفایلتان خوانده می‌شود.</p><a href='profile.php' style='display:inline-block;margin-top:12px;padding:12px 26px;background:#064e4e;color:#fff;text-decoration:none;border-radius:8px'>تکمیل شماره تماس</a> <a href='javascript:history.back()' style='display:inline-block;margin-top:12px;padding:12px 26px;background:#e5e7eb;color:#111827;text-decoration:none;border-radius:8px'>بازگشت</a></div></body></html>";
        exit;
    }
    $mkOwnerPhone = $__phoneP;
    $__nameP = trim((string)($__up['name'] ?? ''));
    if ($__nameP !== '') {
        $mkOwnerName = $__nameP;
    }
    $in = json_decode((string)($_POST['partnership_payload'] ?? ''), true);
    if (!is_array($in)) {
        $mkFail('داده ارسالی نامعتبر است. لطفاً دوباره فرم را کامل کنید.');
    }
    $mapRequire = false;
    if (function_exists('melkinoMapPublicSettings')) {
        try {
            $ps = melkinoMapPublicSettings($pdo);
            $mapRequire = !empty($ps['require_location']);
        } catch (Throwable $e) {
        }
    }
    $data = melkinoPartSanitize($in);
    $errors = melkinoPartValidate($data, $mapRequire);
    if ($errors) {
        $mkFail('چند مورد نیاز به اصلاح دارد: ' . implode(' | ', $errors));
    }
    $code = melkinoPartCode();
    try {
        $st = $pdo->prepare("INSERT INTO partnership_requests (
            code, user_id, owner_name, phone, status,
            property_type, title, area, current_status, photos,
            city, neighborhood, address, latitude, longitude, location_source,
            passage_width, land_width, br_count, direction,
            building_age, current_floors, current_units, current_parkings, capacity_known,
            density, occupancy_rate, buildable_floors, buildable_area, buildable_units,
            permit_status, permit_number, permit_date, permit_floors, permit_area,
            owner_share, builder_share, balaghz, balaghz_amount, division_method, unit_shares,
            partner_parkings, partner_storage, duration, funding, value_from, value_to, notes,
            deed_status, deed_kind, owners_count, occupancy, legal_status,
            doc_deed, doc_permit, doc_endjob, doc_other, completeness, created_at
        ) VALUES (
            ?, ?, ?, ?, 'pending',
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, NOW()
        )");
        $st->execute([
            $code, (int)($_SESSION['user_id'] ?? 0) ?: null, $mkOwnerName, $mkOwnerPhone,
            $data['property_type'], $data['title'], $data['area'], $data['current_status'], $data['photos'],
            $data['city'], $data['neighborhood'], $data['address'], $data['latitude'], $data['longitude'], $data['location_source'],
            $data['passage_width'], $data['land_width'], $data['br_count'], $data['direction'],
            $data['building_age'], $data['current_floors'], $data['current_units'], $data['current_parkings'], $data['capacity_known'],
            $data['density'], $data['occupancy_rate'], $data['buildable_floors'], $data['buildable_area'], $data['buildable_units'],
            $data['permit_status'], $data['permit_number'], $data['permit_date'], $data['permit_floors'], $data['permit_area'],
            $data['owner_share'], $data['builder_share'], $data['balaghz'], $data['balaghz_amount'], $data['division_method'], $data['unit_shares'],
            $data['partner_parkings'], $data['partner_storage'], $data['duration'], $data['funding'], $data['value_from'], $data['value_to'], $data['notes'],
            $data['deed_status'], $data['deed_kind'], $data['owners_count'], $data['occupancy'], $data['legal_status'],
            $data['doc_deed'], $data['doc_permit'], $data['doc_endjob'], $data['doc_other'], $data['completeness'],
        ]);
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            error_log('[melkino-partnership] insert failed: ' . $e->getMessage());
        }
        $mkFail('ثبت درخواست ناموفق بود؛ لطفاً صفحه را یک‌بار رفرش کنید و دوباره تلاش کنید. اگر تکرار شد به پشتیبانی اطلاع دهید.');
    }

    /* صفحهٔ موفقیت — دقیقاً با همان سبک register-land.php */
    echo "<!DOCTYPE html>\n<html lang=\"fa\" dir=\"rtl\">\n<head><meta charset='UTF-8'><title>ثبت موفق</title></head>\n"
        . "<body style='font-family: Vazirmatn, Tahoma, sans-serif; text-align: center; padding: 50px; direction: rtl;'>\n"
        . "<h2 style='color: green;'>✅ درخواست مشارکت در ساخت با موفقیت ثبت شد!</h2>\n"
        . "<p>کد پیگیری: <strong style='direction: ltr; display: inline-block;'>" . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . "</strong></p>\n"
        . "<p>کارشناسان ملکینو درخواست شما را بررسی می‌کنند و با شما تماس می‌گیرند.</p>\n"
        . "<a href='home.php' style='display: inline-block; margin-top: 20px; padding: 12px 24px; background: #064e4e; color: #fff; text-decoration: none; border-radius: 8px;'>بازگشت به خانه</a>\n"
        . "<script>setTimeout(function() { window.location.href = 'home.php'; }, 3000);</script>\n"
        . "</body>\n</html>";
    exit;
}

require_once __DIR__ . '/header.php';
$opt = melkinoPartOptions();
?>
<style>
    /* ===== تمام استایل‌های مشابه سایر فرم‌های ثبت ملکینو ===== */
    .main-content { flex: 1; overflow-y: auto; background: var(--bg); display: flex; flex-direction: column; padding-bottom: calc(var(--reg-bottom-nav-h, 74px) + 90px); }
    .stepper-container { padding: var(--space-2) var(--space-3); display: flex; align-items: center; gap: var(--space-1); background: var(--surface); flex-shrink: 0; flex-wrap: wrap; }
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
    .image-preview-item.doc-item { aspect-ratio: auto; padding: 6px; font-size: 10.5px; text-align: center; color: var(--text-secondary); word-break: break-all; display: flex; align-items: center; justify-content: center; }
    .image-preview-item .remove-btn { position: absolute; top: 2px; right: 2px; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; color: var(--danger); font-weight: bold; display: flex; justify-content: center; align-items: center; }

    .form-group { display: flex; flex-direction: column; gap: var(--space-1); margin-bottom: var(--space-2); }
    .form-group label { font-size: 14px; font-weight: 600; color: var(--text-primary); }
    .form-input, .form-select, .form-textarea { width: 100%; height: 50px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg); padding: 0 var(--space-2); font-size: 15px; font-family: 'Vazirmatn', sans-serif; color: var(--text-primary); outline: none; transition: border 0.2s ease; }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--primary); }
    .form-textarea { height: 100px; padding-top: var(--space-2); resize: none; }
    .row-half { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); }
    .form-hint { font-size: 12px; color: var(--text-secondary); line-height: 1.9; margin: 0; }

    .upload-area { width: 100%; height: 120px; border: 2px dashed var(--border); border-radius: var(--radius-md); background: var(--bg); display: flex; flex-direction: column; justify-content: center; align-items: center; color: var(--text-secondary); gap: var(--space-1); cursor: pointer; transition: 0.3s; }
    .upload-area:hover { border-color: var(--primary); }
    .upload-area.doc-area { height: 90px; }

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

    .radio-group { display: flex; gap: var(--space-3); margin-top: var(--space-1); flex-wrap: wrap; }
    .radio-label { display: flex; align-items: center; gap: var(--space-1); font-size: 14px; color: var(--text-primary); cursor: pointer; }
    .radio-label input[type="radio"] { width: 18px; height: 18px; accent-color: var(--primary); }
    .checkbox-label { display: flex; align-items: center; gap: 6px; font-size: 13.5px; color: var(--text-primary); cursor: pointer; }
    .checkbox-label input[type="checkbox"] { width: 17px; height: 17px; accent-color: var(--primary); }
    .options-group { display: flex; flex-wrap: wrap; gap: var(--space-1); }
    .option-btn { padding: 10px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg); font-family: 'Vazirmatn', sans-serif; font-size: 14px; font-weight: 500; color: var(--text-secondary); cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-block; }
    .option-btn.selected { background: rgba(6, 78, 78, 0.1); border-color: var(--primary); color: var(--primary); }
    .option-btn:active { transform: scale(0.95); }

    /* ===== گروه‌های شرطی — همان الگوی fullRentGroup ===== */
    #mkpCapacityGroup.hide, #mkpOccupancyGroup.hide, #mkpDocEndjob.hide { display: none; }

    /* ===== نوار «کامل بودن اطلاعات» — ظریف و هماهنگ با تم ===== */
    .mkp-meter { flex-basis: 100%; display: flex; align-items: center; gap: var(--space-1); margin-top: var(--space-1); }
    .mkp-meter .mkp-meter-track { flex: 1; height: 6px; border-radius: 99px; background: var(--bg-tertiary, #ECEEE8); overflow: hidden; }
    .mkp-meter .mkp-meter-fill { display: block; height: 100%; width: 0%; border-radius: 99px; background: linear-gradient(90deg, var(--gold), var(--gold-dark, #A98416)); transition: width .35s ease; }
    .mkp-meter b { font-size: 11.5px; font-weight: 800; color: var(--gold-dark, #A98416); white-space: nowrap; }

    .mkp-privacy { font-size: 12px; color: var(--text-secondary); background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px 12px; line-height: 2; margin: 0; }
    .mkp-savedraft { background: none; border: none; color: var(--text-secondary); font-family: inherit; font-size: 12.5px; cursor: pointer; padding: 2px 0 8px; text-align: center; width: 100%; }
</style>

<div class="stepper-container">
    <span class="step-text" id="stepText">مرحله ۱ از ۴</span>
    <div class="step-line active" id="line1"></div>
    <div class="step-line" id="line2"></div>
    <div class="step-line" id="line3"></div>
    <div class="mkp-meter" title="امتیاز کامل بودن اطلاعات">
        <div class="mkp-meter-track"><span class="mkp-meter-fill" id="mkpMeterFill"></span></div>
        <b id="mkpMeterText">کامل بودن: ۰٪</b>
    </div>
</div>

<div class="main-content" id="mainContent">
    <form method="POST" action="" id="partnershipForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(function_exists('melkinoCsrfToken') ? melkinoCsrfToken() : '', ENT_QUOTES, 'UTF-8') ?>">
        <!-- فیلدهای مخفی اطلاعات کاربر — دقیقاً مثل register-land -->
        <input type="hidden" name="owner_name" id="hidden_owner_name" value="<?= htmlspecialchars($mkOwnerName, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="phone" id="hidden_phone" value="<?= htmlspecialchars($mkOwnerPhone, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="partnership_payload" id="partnership_payload" value="">

        <!-- ===== STEP 1: معرفی ملک ===== -->
        <div class="step-content active" id="step1">
            <h2 class="step-title">معرفی ملک</h2>
            <p class="step-subtitle">مشخصات اصلی ملک خود را وارد کنید.</p>

            <div class="form-group">
                <label>نوع ملک *</label>
                <div class="radio-group" id="mkpPropertyType">
                    <?php foreach ($opt['property_types'] as $i => $pt): ?>
                        <label class="radio-label"><input type="radio" name="property_type" value="<?= htmlspecialchars($pt, ENT_QUOTES, 'UTF-8') ?>"<?= $i === 0 ? ' checked' : '' ?>> <?= htmlspecialchars($pt, ENT_QUOTES, 'UTF-8') ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="mkpPropertyTypeInput" value="<?= htmlspecialchars($opt['property_types'][0], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label>مساحت ملک (متر مربع) *</label>
                <input type="text" class="form-input" id="mkpArea" name="area" inputmode="numeric" maxlength="10" placeholder="مثلاً ۳۰۰" required>
            </div>

            <div class="form-group">
                <label>وضعیت فعلی ملک *</label>
                <div class="radio-group" id="mkpCurrentStatus">
                    <?php foreach ($opt['current_statuses'] as $cs): ?>
                        <label class="radio-label"><input type="radio" name="current_status" value="<?= htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') ?>"> <?= htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="mkpCurrentStatusInput" value="">
            </div>
        </div>

        <!-- ===== STEP 2: موقعیت و مشخصات ===== -->
        <div class="step-content" id="step2">
            <h2 class="step-title">موقعیت و مشخصات ملک</h2>

            <div class="form-group">
                <label>محله *</label>
                <input type="text" class="form-input" id="mkpNeighborhood" name="neighborhood" placeholder="مثلاً خ فردوسی" required>
            </div>

            <div class="form-group">
                <label>آدرس دقیق *</label>
                <textarea class="form-textarea" id="mkpAddress" name="address" rows="3" placeholder="نام خیابان، کوچه، پلاک و..." required></textarea>
                <p class="form-hint">آدرس دقیق فقط برای بررسی و ارتباط با متقاضیان استفاده می‌شود و در آگهی عمومی نمایش داده نمی‌شود.</p>
            </div>

            <?php require __DIR__ . '/map-location-fields.php'; ?>

            <div class="row-half">
                <div class="form-group">
                    <label>عرض کوچه یا گذر (متر)</label>
                    <input type="text" class="form-input" id="mkpPassage" name="passage_width" inputmode="numeric" maxlength="5" placeholder="مثلاً ۸">
                    <label class="checkbox-label"><input type="checkbox" id="mkpPassageUnk" onchange="togglePassageUnknown()"> نمی‌دانم</label>
                </div>
                <div class="form-group">
                    <label>عرض زمین (متر)</label>
                    <input type="text" class="form-input" id="mkpLandWidth" name="land_width" inputmode="numeric" maxlength="5" placeholder="مثلاً ۱۰">
                </div>
            </div>

            <div class="form-group">
                <label>ملک چند بر دارد؟</label>
                <div class="radio-group" id="mkpBrCount">
                    <?php foreach ($opt['br_counts'] as $br): ?>
                        <label class="radio-label"><input type="radio" name="br_count" value="<?= htmlspecialchars($br, ENT_QUOTES, 'UTF-8') ?>"> <?= htmlspecialchars($br, ENT_QUOTES, 'UTF-8') ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="mkpBrCountInput" value="">
            </div>

            <div class="form-group">
                <label>جهت ملک</label>
                <div class="radio-group" id="mkpDirection">
                    <?php foreach ($opt['directions'] as $dr): ?>
                        <label class="radio-label"><input type="radio" name="direction" value="<?= htmlspecialchars($dr, ENT_QUOTES, 'UTF-8') ?>"> <?= htmlspecialchars($dr, ENT_QUOTES, 'UTF-8') ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="mkpDirectionInput" value="">
            </div>
        </div>

        <!-- ===== STEP 3: وضعیت ساخت ===== -->
        <div class="step-content" id="step3">
            <h2 class="step-title">وضعیت ساخت</h2>

            <div class="form-group">
                <label>وضعیت پروانه ساخت</label>
                <div class="radio-group" id="mkpPermitStatus">
                    <?php foreach ($opt['permit_statuses'] as $i => $ps): ?>
                        <label class="radio-label"><input type="radio" name="permit_status" value="<?= htmlspecialchars($ps, ENT_QUOTES, 'UTF-8') ?>"> <?= htmlspecialchars($ps, ENT_QUOTES, 'UTF-8') ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="mkpPermitStatusInput" value="">
            </div>

            <div id="mkpCapacityGroup" class="hide">
                <p class="step-subtitle">ظرفیت ساخت ملک را مشخص کنید (اگر اطلاعاتی ندارید خالی بگذارید).</p>
                <div class="row-half">
                    <div class="form-group">
                        <label>تراکم مجاز (٪)</label>
                        <input type="text" class="form-input" id="mkpDensity" name="density" inputmode="numeric" maxlength="4" placeholder="مثلاً ۱۸۰">
                    </div>
                    <div class="form-group">
                        <label>سطح اشغال مجاز (٪)</label>
                        <input type="text" class="form-input" id="mkpOccupancyRate" name="occupancy_rate" inputmode="numeric" maxlength="4" placeholder="مثلاً ۶۰">
                    </div>
                </div>
                <div class="row-half">
                    <div class="form-group">
                        <label>تعداد طبقات قابل ساخت</label>
                        <input type="text" class="form-input" id="mkpBuildFloors" name="buildable_floors" inputmode="numeric" maxlength="3" placeholder="مثلاً ۵">
                    </div>
                    <div class="form-group">
                        <label>زیربنای قابل ساخت (متر مربع)</label>
                        <input type="text" class="form-input" id="mkpBuildArea" name="buildable_area" inputmode="numeric" maxlength="6" placeholder="مثلاً ۹۰۰">
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== STEP 4: مالکیت، مدارک و انتشار ===== -->
        <div class="step-content" id="step4">
            <h2 class="step-title">مالکیت، مدارک و انتشار</h2>

            <div class="form-group">
                <label>وضعیت سند ملک *</label>
                <div class="radio-group" id="mkpDeed">
                    <?php foreach ($opt['deed_statuses'] as $ds): ?>
                        <label class="radio-label"><input type="radio" name="deed_status" value="<?= htmlspecialchars($ds, ENT_QUOTES, 'UTF-8') ?>"> <?= htmlspecialchars($ds, ENT_QUOTES, 'UTF-8') ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="mkpDeedInput" value="">
            </div>

            <div class="form-group">
                <label>نوع سند</label>
                <div class="radio-group" id="mkpDeedKind">
                    <?php foreach ($opt['deed_kinds'] as $dk): ?>
                        <label class="radio-label"><input type="radio" name="deed_kind" value="<?= htmlspecialchars($dk, ENT_QUOTES, 'UTF-8') ?>"> <?= htmlspecialchars($dk, ENT_QUOTES, 'UTF-8') ?></label>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="mkpDeedKindInput" value="">
            </div>

            <div class="row-half">
                <div class="form-group">
                    <label>تعداد مالکین</label>
                    <input type="text" class="form-input" id="mkpOwnersCount" name="owners_count" inputmode="numeric" maxlength="3" placeholder="مثلاً ۲">
                </div>
                <div class="form-group" id="mkpOccupancyGroup">
                    <label>وضعیت فعلی ملک</label>
                    <select class="form-select" id="mkpOccupancy" name="occupancy">
                        <option value="">— انتخاب کنید —</option>
                        <?php foreach ($opt['occupancies'] as $oc): ?>
                            <option value="<?= htmlspecialchars($oc, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($oc, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>آیا ملک وضعیت خاصی دارد؟</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-1);">
                    <?php foreach ($opt['legal_flags'] as $lg): ?>
                        <label class="checkbox-label"><input type="checkbox" name="legal_status[]" value="<?= htmlspecialchars($lg, ENT_QUOTES, 'UTF-8') ?>" onchange="syncLegal(this)"> <?= htmlspecialchars($lg, ENT_QUOTES, 'UTF-8') ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>تصویر سند (اختیاری)</label>
                <p class="form-hint">برای افزایش اعتماد سازندگان می‌توانید تصویر سند را بارگذاری کنید. (تصویر یا PDF، حداکثر ۸ مگابایت)</p>
                <div class="upload-area doc-area" onclick="document.getElementById('docDeedInput').click()">
                    <span>🖼️ بارگذاری تصویر سند</span>
                </div>
                <input type="file" id="docDeedInput" accept="image/jpeg,image/png,image/webp,application/pdf" hidden onchange="handleDocUpload(this, 'doc_deed')">
                <div id="preview_doc_deed" class="image-preview-grid"></div>
            </div>

            <div class="form-group">
                <label>تصویر پروانه ساخت (اختیاری)</label>
                <div class="upload-area doc-area" onclick="document.getElementById('docPermitInput').click()">
                    <span>📜 بارگذاری تصویر پروانه</span>
                </div>
                <input type="file" id="docPermitInput" accept="image/jpeg,image/png,image/webp,application/pdf" hidden onchange="handleDocUpload(this, 'doc_permit')">
                <div id="preview_doc_permit" class="image-preview-grid"></div>
            </div>

            <div class="form-group" id="mkpDocEndjob">
                <label>تصویر پایان کار (اختیاری)</label>
                <div class="upload-area doc-area" onclick="document.getElementById('docEndjobInput').click()">
                    <span>✅ بارگذاری تصویر پایان کار</span>
                </div>
                <input type="file" id="docEndjobInput" accept="image/jpeg,image/png,image/webp,application/pdf" hidden onchange="handleDocUpload(this, 'doc_endjob')">
                <div id="preview_doc_endjob" class="image-preview-grid"></div>
            </div>

            <div class="form-group">
                <label>مدارک دیگر (حداکثر ۵ فایل — نقشه، پروانه و...)</label>
                <div class="upload-area doc-area" onclick="document.getElementById('docOtherInput').click()">
                    <span>📎 افزودن فایل</span>
                </div>
                <input type="file" id="docOtherInput" accept="image/jpeg,image/png,image/webp,application/pdf" multiple hidden onchange="handleOtherDocs(this)">
                <div id="preview_doc_other" class="image-preview-grid"></div>
            </div>

            <p class="mkp-privacy">🔒 آدرس دقیق، شماره تماس و مدارک شما فقط برای ملکینو ثبت می‌شود و در آگهی عمومی نمایش داده نمی‌شود؛ اطلاعات حساس فقط پس از تأیید، در اختیار متقاضی واقعی قرار می‌گیرد.</p>

            <!-- بخش پیش‌نمایش نهایی — دقیقاً مثل register-land -->
            <div id="finalPreviewContainer" style="display:none;">
                <div class="preview-card">
                    <div class="preview-card-title" id="mkpPreviewTitle">📋 پیش‌نمایش نهایی درخواست</div>
                    <div id="mkpPreviewBody"></div>
                </div>

                <div style="display:flex; gap:var(--space-2); flex-wrap:wrap; margin-top:var(--space-3);">
                    <button type="button" class="btn-secondary" onclick="editPreview()" style="flex:1;max-width:none;">ویرایش اطلاعات</button>
                    <button type="submit" name="submit_partnership" class="btn-primary-full" style="flex:1;max-width:none;">ثبت نهایی درخواست</button>
                </div>
            </div>

            <div class="form-group">
                <label class="radio-label"><input type="checkbox" id="mkpConfirm" required> اطلاعات واردشده را بررسی کرده‌ام و صحت آن را تأیید می‌کنم.</label>
            </div>
        </div>

        <button type="button" class="mkp-savedraft" id="mkpSaveDraft">💾 ذخیره و ادامه بعداً</button>

        <!-- دکمه‌های ناوبری — دقیقاً مانند سایر فرم‌ها -->
        <div class="bottom-actions" id="regBottomActions">
            <button type="button" class="btn-secondary" id="prevBtn" onclick="changeStep(-1)" style="display:none;">مرحله قبل</button>
            <button type="button" class="btn-primary-full" id="nextBtn" onclick="changeStep(1)">مرحله بعد</button>
        </div>
    </form>
</div>

<script src="form-wizard.js?v=<?= (int) @filemtime(__DIR__ . '/form-wizard.js') ?>"></script>
<script>
    /* ==========================================================
       منطق فرم — با همان سبک و اسامی توابع register-land.php
       ========================================================== */
    var currentStep = 1;
    var totalSteps = 4;
    var docFiles = { doc_deed: '', doc_permit: '', doc_endjob: '' };
    var otherDocs = [];

    var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
    function toFa(n) { return String(n).replace(/\d/g, function (d) { return FA_DIGITS[+d]; }); }
    function toEn(s) {
        return String(s || '')
            .replace(/[۰-۹]/g, function (d) { return String(FA_DIGITS.indexOf(d)); })
            .replace(/[٠-٩]/g, function (d) { return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)); });
    }

    /* ===================== رادیوهای مرحله ۱ و ۲ ===================== */
    document.querySelectorAll('input[name="property_type"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('mkpPropertyTypeInput').value = this.value;
            updateCompleteness();
        });
    });
    document.querySelectorAll('input[name="current_status"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('mkpCurrentStatusInput').value = this.value;
            applyConditions();
            updateCompleteness();
        });
    });
    document.querySelectorAll('input[name="br_count"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('mkpBrCountInput').value = this.value;
            updateCompleteness();
        });
    });

    document.querySelectorAll('input[name="direction"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('mkpDirectionInput').value = this.value;
            updateCompleteness();
        });
    });

    /* ===================== پروانه و ظرفیت ساخت (مرحله ۳) ===================== */
    document.querySelectorAll('input[name="permit_status"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('mkpPermitStatusInput').value = this.value;
            applyConditions();
            updateCompleteness();
        });
    });

    /* ===================== سند و نوع سند (مرحله ۴) ===================== */
    document.querySelectorAll('input[name="deed_status"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('mkpDeedInput').value = this.value;
            updateCompleteness();
        });
    });
    document.querySelectorAll('input[name="deed_kind"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('mkpDeedKindInput').value = this.value;
            updateCompleteness();
        });
    });

    /* ===================== مدیریت مراحل (مثل register-land) ===================== */
    function changeStep(direction) {
        if (direction === 1 && typeof melkinoValidateWizardStep === 'function') {
            if (!melkinoValidateWizardStep('step' + currentStep)) {
                return;
            }
        }
        /* اعتبارسنجی‌های خاص فرم مشارکت */
        if (direction === 1) {
            var err = stepError(currentStep);
            if (err) { alert(err); return; }
        }
        if (currentStep === totalSteps && direction === 1) {
            document.getElementById('prevBtn').style.display = 'none';
            document.getElementById('nextBtn').style.display = 'none';
            updatePreview();
            return;
        }

        document.getElementById('step' + currentStep).classList.remove('active');
        currentStep += direction;
        if (currentStep < 1) currentStep = 1;
        if (currentStep > totalSteps) currentStep = totalSteps;
        document.getElementById('step' + currentStep).classList.add('active');
        document.getElementById('stepText').innerText = 'مرحله ' + toFa(currentStep) + ' از ' + toFa(totalSteps);
        for (var i = 1; i < totalSteps; i++) {
            var line = document.getElementById('line' + i);
            if (line) {
                if (i < currentStep) line.classList.add('active');
                else line.classList.remove('active');
            }
        }

        document.getElementById('prevBtn').style.display = (currentStep === 1) ? 'none' : 'flex';
        document.getElementById('nextBtn').innerText = (currentStep === totalSteps) ? 'پیش‌نمایش و ثبت' : 'مرحله بعد';
        document.getElementById('mainContent').scrollTop = 0;
    }

    function stepError(step) {
        if (step === 1) {
            if (!document.getElementById('mkpPropertyTypeInput').value) return 'نوع ملک را انتخاب کنید.';
            if (!(parseFloat(toEn(document.getElementById('mkpArea').value)) > 0)) return 'مساحت ملک را وارد کنید.';
            if (!document.getElementById('mkpCurrentStatusInput').value) return 'وضعیت فعلی ملک را انتخاب کنید.';
        }
        if (step === 2) {
            if (!document.getElementById('mkpNeighborhood').value.trim()) return 'محله را مشخص کنید.';
            if (document.getElementById('mkpAddress').value.trim().length < 8) return 'آدرس را کامل‌تر بنویسید.';
            var mapCfg = window.MELKINO_MAP_PICKER || {};
            var lat = document.getElementById('map_lat') ? document.getElementById('map_lat').value : '';
            var lng = document.getElementById('map_lng') ? document.getElementById('map_lng').value : '';
            if (mapCfg.require !== false && !(lat && lng)) return 'موقعیت ملک را روی نقشه مشخص کنید.';
        }
        if (step === 4) {
            if (!document.getElementById('mkpDeedInput').value) return 'وضعیت سند را انتخاب کنید.';
            if (!document.getElementById('mkpConfirm').checked) return 'تأیید صحت اطلاعات را علامت بزنید.';
        }
        return '';
    }

    /* ===================== آپلود مدارک ===================== */
    function handleDocUpload(input, key) {
        var file = input.files && input.files[0];
        if (!file) return;
        if (file.size > 8 * 1024 * 1024) {
            alert('حجم فایل بیش از ۸ مگابایت است.');
            input.value = '';
            return;
        }
        var formData = new FormData();
        formData.append('doc', file);
        formData.append('action', 'upload_doc');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success && res.path) {
                        docFiles[key] = res.path;
                        var pv = document.getElementById('preview_' + key);
                        pv.innerHTML = '<div class="image-preview-item doc-item">' + file.name + ' ✓' +
                            '<button type="button" class="remove-btn" onclick="removeDoc(\'' + key + '\')">✕</button></div>';
                        updateCompleteness();
                    } else {
                        alert((res && res.message) || 'خطا در بارگذاری مدرک');
                    }
                } catch (e) {
                    alert('خطا در پردازش پاسخ سرور');
                }
            } else {
                alert('خطا در ارتباط با سرور');
            }
            input.value = '';
        };
        xhr.onerror = function () { alert('خطا در بارگذاری مدرک'); input.value = ''; };
        xhr.send(formData);
    }

    function removeDoc(key) {
        docFiles[key] = '';
        document.getElementById('preview_' + key).innerHTML = '';
        updateCompleteness();
    }

    function handleOtherDocs(input) {
        var files = Array.from(input.files || []);
        var room = 5 - otherDocs.length;
        if (room <= 0) { alert('حداکثر ۵ فایل.'); input.value = ''; return; }
        if (files.length > room) {
            alert('حداکثر ۵ فایل مجاز است؛ بقیه نادیده گرفته شد.');
            files = files.slice(0, room);
        }
        if (!files.length) { input.value = ''; return; }
        var formData = new FormData();
        files.forEach(function (f) { formData.append('images[]', f); });
        formData.append('action', 'upload_doc');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success && res.paths) {
                        otherDocs = otherDocs.concat(res.paths);
                        renderOtherDocs();
                        if (res.errors && res.errors.length) alert(res.errors.join('\n'));
                    } else {
                        alert((res && res.message) || 'خطا در بارگذاری');
                    }
                } catch (e) { alert('خطا در پردازش پاسخ سرور'); }
            } else { alert('خطا در ارتباط با سرور'); }
            updateCompleteness();
            input.value = '';
        };
        xhr.onerror = function () { alert('خطا در بارگذاری'); input.value = ''; };
        xhr.send(formData);
    }

    function renderOtherDocs() {
        var pv = document.getElementById('preview_doc_other');
        pv.innerHTML = '';
        otherDocs.forEach(function (p, i) {
            var div = document.createElement('div');
            div.className = 'image-preview-item doc-item';
            div.textContent = p.split('_').pop();
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'remove-btn';
            btn.textContent = '✕';
            btn.onclick = function () { otherDocs.splice(i, 1); renderOtherDocs(); updateCompleteness(); };
            div.appendChild(btn);
            pv.appendChild(div);
        });
    }

    /* ===================== شرطی‌سازی ===================== */
    function isLand() {
        return document.getElementById('mkpCurrentStatusInput').value === 'زمین خالی';
    }
    function setGroupVisible(id, visible) {
        var g = document.getElementById(id);
        if (!g) return;
        g.classList.toggle('hide', !visible);
        g.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = !visible;
            if (!visible && el.type !== 'checkbox') el.value = '';
        });
    }
    function applyConditions() {
        var land = isLand();
        setGroupVisible('mkpOccupancyGroup', !land);
        var dj = document.getElementById('mkpDocEndjob');
        if (dj) {
            dj.classList.toggle('hide', land);
            if (land && docFiles.doc_endjob) removeDoc('doc_endjob');
        }
        /* ظرفیت ساخت فقط وقتی پروانه در حال اخذ یا صادرشده است نمایش داده می‌شود */
        var permit = document.getElementById('mkpPermitStatusInput').value;
        var showCapacity = (permit === 'در حال اخذ پروانه' || permit === 'پروانه صادر شده');
        setGroupVisible('mkpCapacityGroup', showCapacity);
        updateCompleteness();
    }
    function togglePassageUnknown() {
        var unk = document.getElementById('mkpPassageUnk').checked;
        var inp = document.getElementById('mkpPassage');
        inp.disabled = unk;
        if (unk) inp.value = '';
    }

    /* ===================== وضعیت حقوقی: انحصاری‌ها ===================== */
    function syncLegal(input) {
        var exclusive = (input.value === 'هیچ‌کدام' || input.value === 'نمی‌دانم');
        document.querySelectorAll('input[name="legal_status[]"]').forEach(function (c) {
            if (input.checked && exclusive) {
                if (c !== input) c.checked = false;
            } else if (input.checked && (c.value === 'هیچ‌کدام' || c.value === 'نمی‌دانم')) {
                c.checked = false;
            }
        });
    }

    /* ===================== کامل بودن اطلاعات (همان فرمول سرور) ===================== */
    function val(id) { var el = document.getElementById(id); return el ? String(el.value || '').trim() : ''; }
    function updateCompleteness() {
        var sc = 0;
        if (parseFloat(toEn(val('mkpArea'))) > 0) sc += 5;
        if (val('mkpPropertyTypeInput')) sc += 5;
        if (val('mkpCurrentStatusInput')) sc += 4;
        if (val('mkpNeighborhood')) sc += 6;
        if (val('mkpAddress').length >= 4) sc += 5;
        if (val('map_lat') && val('map_lng')) sc += 7;
        if (val('mkpBrCountInput')) sc += 4;
        var permit = val('mkpPermitStatusInput');
        if (permit) sc += 7;
        if (permit === 'پروانه ندارم') {
            sc += 4;
        } else if (val('mkpDensity') || val('mkpOccupancyRate') || val('mkpBuildFloors') || val('mkpBuildArea')) {
            sc += 8;
        }
        if (val('mkpDeedInput')) sc += 10;
        if (val('mkpDeedKindInput')) sc += 4;
        if (val('mkpOccupancy')) sc += 4;
        if (document.querySelectorAll('input[name="legal_status[]"]:checked').length) sc += 6;
        if (docFiles.doc_deed) sc += 9;
        if (docFiles.doc_permit) sc += 6;
        if (isLand() || docFiles.doc_endjob) sc += 4; // زمین خالی پایان‌کار ندارد
        if (otherDocs.length) sc += 6;
        sc = Math.max(0, Math.min(100, sc));
        document.getElementById('mkpMeterFill').style.width = sc + '%';
        document.getElementById('mkpMeterText').textContent = 'کامل بودن: ' + toFa(sc) + '٪';
        return sc;
    }

    /* ===================== جمع‌آوری داده و پیش‌نمایش ===================== */
    function collectLegal() {
        var out = [];
        document.querySelectorAll('input[name="legal_status[]"]:checked').forEach(function (c) { out.push(c.value); });
        return out;
    }
    function collect() {
        return {
            property_type: val('mkpPropertyTypeInput'),
            area: toEn(val('mkpArea')).replace(/[^\d.]/g, ''),
            current_status: val('mkpCurrentStatusInput'),
            neighborhood: val('mkpNeighborhood'),
            address: val('mkpAddress'),
            latitude: val('map_lat'),
            longitude: val('map_lng'),
            location_source: val('map_source') || 'map',
            passage_width: val('mkpPassage') ? toEn(val('mkpPassage')).replace(/[^\d.]/g, '') : '',
            land_width: toEn(val('mkpLandWidth')).replace(/[^\d.]/g, ''),
            br_count: val('mkpBrCountInput'),
            direction: val('mkpDirectionInput'),
            density: toEn(val('mkpDensity')).replace(/[^\d.]/g, ''),
            occupancy_rate: toEn(val('mkpOccupancyRate')).replace(/[^\d.]/g, ''),
            buildable_floors: toEn(val('mkpBuildFloors')).replace(/[^\d.]/g, ''),
            buildable_area: toEn(val('mkpBuildArea')).replace(/[^\d.]/g, ''),
            permit_status: val('mkpPermitStatusInput'),
            deed_status: val('mkpDeedInput'),
            deed_kind: val('mkpDeedKindInput'),
            owners_count: toEn(val('mkpOwnersCount')).replace(/[^\d.]/g, ''),
            occupancy: isLand() ? 'خالی' : val('mkpOccupancy'),
            legal_status: collectLegal(),
            doc_deed: docFiles.doc_deed,
            doc_permit: docFiles.doc_permit,
            doc_endjob: isLand() ? '' : docFiles.doc_endjob,
            doc_other: otherDocs.slice(0, 5)
        };
    }

    function editPreview() {
        document.getElementById('finalPreviewContainer').style.display = 'none';
        document.getElementById('prevBtn').style.display = (currentStep === 1) ? 'none' : 'flex';
        document.getElementById('nextBtn').style.display = 'flex';
        document.getElementById('nextBtn').innerText = 'مرحله بعد';
        changeStep(-totalSteps);
    }

    function previewHeading(p) {
        var parts = ['مشارکت در ساخت'];
        if (p.property_type) parts.push(p.property_type);
        if (p.area) parts.push(toFa(p.area) + ' متری');
        if (p.neighborhood) parts.push('— ' + p.neighborhood);
        return parts.join(' ');
    }

    function updatePreview() {
        var p = collect();
        document.getElementById('finalPreviewContainer').style.display = 'block';
        document.getElementById('mkpPreviewTitle').textContent = '📋 ' + previewHeading(p);

        function rows(items) {
            var html = '';
            items.forEach(function (it) {
                if (!it.v) return;
                html += '<div class="preview-row"><span class="preview-label">' + it.k + '</span><span class="preview-value rtl">' + it.v + '</span></div>';
            });
            return html;
        }
        var legalTxt = p.legal_status.length ? p.legal_status.join('، ') : 'هیچ‌کدام';
        document.getElementById('mkpPreviewBody').innerHTML =
            rows([
                { k: 'نوع ملک', v: p.property_type },
                { k: 'مساحت', v: p.area ? toFa(p.area) + ' متر مربع' : '' },
                { k: 'وضعیت فعلی', v: p.current_status },
                { k: 'بر', v: p.br_count },
                { k: 'جهت ملک', v: p.direction },
                { k: 'عرض زمین', v: p.land_width ? toFa(p.land_width) + ' متر' : '' }
            ]) +
            rows([
                { k: 'محله', v: p.neighborhood },
                { k: 'وضعیت پروانه', v: p.permit_status },
                { k: 'تراکم مجاز', v: p.density ? toFa(p.density) + '٪' : '' },
                { k: 'سطح اشغال مجاز', v: p.occupancy_rate ? toFa(p.occupancy_rate) + '٪' : '' },
                { k: 'طبقات قابل ساخت', v: p.buildable_floors ? toFa(p.buildable_floors) : '' },
                { k: 'زیربنای قابل ساخت', v: p.buildable_area ? toFa(p.buildable_area) + ' متر' : '' }
            ]) +
            rows([
                { k: 'وضعیت سند', v: p.deed_status },
                { k: 'نوع سند', v: p.deed_kind },
                { k: 'تعداد مالکین', v: p.owners_count ? toFa(p.owners_count) : '' },
                { k: 'وضعیت فعلی ملک', v: p.occupancy },
                { k: 'وضعیت حقوقی', v: legalTxt }
            ]);
    }

    /* ===================== ثبت نهایی (POST عادی مثل register-land) ===================== */
    document.getElementById('partnershipForm').addEventListener('submit', function (e) {
        var p = collect();
        if (!p.deed_status) {
            e.preventDefault();
            alert('وضعیت سند را انتخاب کنید.');
            return;
        }
        if (!document.getElementById('mkpConfirm').checked) {
            e.preventDefault();
            alert('تأیید صحت اطلاعات را علامت بزنید.');
            return;
        }
        document.getElementById('partnership_payload').value = JSON.stringify(p);
        try { localStorage.removeItem('melkino_partnership_draft_v4'); } catch (err) { }
    });

    /* ===================== پیش‌نویس ===================== */
    var DRAFT_KEY = 'melkino_partnership_draft_v4';
    var draftFields = ['mkpArea', 'mkpNeighborhood', 'mkpAddress', 'mkpPassage', 'mkpLandWidth',
        'mkpDensity', 'mkpOccupancyRate', 'mkpBuildFloors', 'mkpBuildArea',
        'mkpOwnersCount', 'mkpOccupancy'];
    var draftRadios = [['property_type', 'mkpPropertyTypeInput'], ['current_status', 'mkpCurrentStatusInput'],
        ['br_count', 'mkpBrCountInput'], ['direction', 'mkpDirectionInput'], ['permit_status', 'mkpPermitStatusInput'],
        ['deed_status', 'mkpDeedInput'], ['deed_kind', 'mkpDeedKindInput']];
    function saveDraft(silent) {
        try {
            var d = { checks: {}, radios: {}, legal: [], docs: docFiles, others: otherDocs, capUnk: false, passUnk: false };
            draftFields.forEach(function (id) { d.checks[id] = val(id); });
            draftRadios.forEach(function (pair) { d.radios[pair[0]] = val(pair[1]); });
            d.legal = collectLegal();
            d.passUnk = document.getElementById('mkpPassageUnk').checked;
            localStorage.setItem(DRAFT_KEY, JSON.stringify(d));
            if (!silent) alert('پیش‌نویس ذخیره شد ✓ — هر وقت خواستید ادامه دهید.');
        } catch (e) { }
    }
    function restoreDraft() {
        try {
            var raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            var d = JSON.parse(raw);
            if (!d || typeof d !== 'object') return;
            draftFields.forEach(function (id) { var el = document.getElementById(id); if (el && d.checks[id]) el.value = d.checks[id]; });
            (d.radios ? draftRadios : []).forEach(function (pair) {
                var v = d.radios[pair[0]];
                if (!v) return;
                var r = document.querySelector('input[name="' + pair[0] + '"][value="' + v.replace(/"/g, '\\"') + '"]');
                if (r) {
                    r.checked = true;
                    document.getElementById(pair[1]).value = v;
                }
            });
            (d.legal || []).forEach(function (v) {
                var c = document.querySelector('input[name="legal_status[]"][value="' + v.replace(/"/g, '\\"') + '"]');
                if (c) c.checked = true;
            });
            if (d.docs) {
                ['doc_deed', 'doc_permit', 'doc_endjob'].forEach(function (k) {
                    if (d.docs[k]) {
                        docFiles[k] = d.docs[k];
                        var pv = document.getElementById('preview_' + k);
                        if (pv) pv.innerHTML = '<div class="image-preview-item doc-item">' + d.docs[k].split('/').pop() + ' ✓' +
                            '<button type="button" class="remove-btn" onclick="removeDoc(\'' + k + '\')">✕</button></div>';
                    }
                });
            }
            if (Array.isArray(d.others)) { otherDocs = d.others.slice(0, 5); renderOtherDocs(); }
            if (d.passUnk) { document.getElementById('mkpPassageUnk').checked = true; togglePassageUnknown(); }
            applyConditions();
            updateCompleteness();
        } catch (e) { }
    }
    document.getElementById('mkpSaveDraft').onclick = function () {
        saveDraft(true);
        alert('پیش‌نویس ذخیره شد ✓');
        window.location.href = 'home.php';
    };
    /* فقط به‌روزرسانی متر — هیچ دست‌کاری زنده‌ای روی مقدار فیلدها انجام نمی‌شود */
    ['mkpArea', 'mkpNeighborhood', 'mkpAddress', 'mkpPassage', 'mkpLandWidth',
     'mkpDensity', 'mkpOccupancyRate', 'mkpBuildFloors', 'mkpBuildArea',
     'mkpOwnersCount'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', updateCompleteness);
    });
    ['mkpOccupancy'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', updateCompleteness);
    });

    /* شروع */
    document.addEventListener('DOMContentLoaded', function () {
        restoreDraft();
        applyConditions();
        updateCompleteness();
        document.getElementById('prevBtn').style.display = 'none';
        document.getElementById('nextBtn').innerText = 'مرحله بعد';
        document.getElementById('stepText').innerText = 'مرحله ' + toFa(1) + ' از ' + toFa(totalSteps);
    });
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
