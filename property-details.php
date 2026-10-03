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
if (is_file(__DIR__ . '/db-settings.php')) {
    require_once __DIR__ . '/db-settings.php';
}
require_once __DIR__ . '/consultant_helper.php';
require_once __DIR__ . '/field-display.php';
require_once __DIR__ . '/jalali-lib.php';
require_once __DIR__ . '/visit-request-lib.php';

// راند ۲۹: تنظیمات بخش‌ها/فیلدهای صفحهٔ جزئیات (+ draft پیش‌نمایش پنل ادمین)
$fdDet = melkinoFdPreviewSettings('details');
$fdDetCfg = $fdDet['config'];
$fdShow = static function (string $sec, string $key) use ($fdDetCfg): bool {
    return !empty($fdDetCfg['fields'][$sec][$key]['visible']);
};
$fdF = static function (string $sec, string $key) use ($fdDetCfg): array {
    return $fdDetCfg['fields'][$sec][$key] ?? [];
};

// این صفحه بدون دیتابیس قابل ارائه نیست؛ به‌جای fatal/صفحه‌ی سفید،
// پیام 503 قابل فهم نشان بده.
melkinoRequireDb();

$propertyId = isset($_GET['id'])
    ? trim((string) $_GET['id'])
    : '';

$propertyData = null;

$isMockMode =
    defined('MOCK_MODE')
        ? MOCK_MODE
        : false; // فرض می‌کنیم ماک مود غیرفعال است چون از دیتابیس استفاده می‌کنیم

/* =====================================================
   CONSULTANT SETTINGS (Fallback)
   ===================================================== */
$consultantName = function_exists('getConsultantName')
    ? getConsultantName()
    : 'مشاور ملکینو';

$consultantPhone = function_exists('getConsultantPhone')
    ? getConsultantPhone()
    : '';

$consultantTelegram = function_exists('getConsultantTelegramLink')
    ? getConsultantTelegramLink()
    : '';


/* =====================================================
   GET SITE LOGO URL
   ===================================================== */
function getSiteLogoUrl(): string
{
    // راند ۶۹: لوگوی نسخه‌دار مشترک (ملکینو-لوگو) تا کشِ لوگوی کهنه شکسته شود
    require_once __DIR__ . '/melkino-logo.php';
    return melkinoSiteLogoUrl();
}


/* =====================================================
   JSON ARRAY HELPER
   ===================================================== */

function normalizeArrayValue($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}


/* =====================================================
   محاسبه قیمت هر متر مربع
   ===================================================== */
function calculatePricePerSquareMeter(array $ad): ?string
{
    $area = $ad['area'] ?? null;
    if (!$area || $area <= 0) {
        return null;
    }

    $price = null;
    $priceKeys = ['price_sell', 'total_price', 'display_price', 'price'];
    foreach ($priceKeys as $key) {
        if (isset($ad[$key]) && is_numeric(str_replace(',', '', (string)$ad[$key])) && (float)str_replace(',', '', (string)$ad[$key]) > 0) {
            $price = (float) str_replace(',', '', (string)$ad[$key]);
            break;
        }
    }

    if (!$price) {
        return null;
    }

    $pricePerMeter = $price / $area;
    $formatted = number_format($pricePerMeter, 0, '.', ',');

    return $formatted . ' تومان';
}


/* =====================================================
   PUBLIC SPECS — بازنویسی کامل با پوشش همه کلیدهای تخصصی
   ===================================================== */

function buildPublicSpecs(array $ad, array $orderedLabels = []): array
{
    $specDefinitions = function_exists('melkinoPdSpecDefinitions')
        ? melkinoPdSpecDefinitions()
        : [];

    $propertyType = trim((string)($ad['property_type'] ?? ''));
    if ($propertyType === '') {
        $propertyType = 'آپارتمان';
    }

    $existing = $ad['property_details'] ?? [];
    if (is_string($existing)) {
        $decoded = json_decode($existing, true);
        $existing = is_array($decoded) ? $decoded : [];
    }
    $existing = is_array($existing) ? $existing : [];
    if (isset($existing['property_details']) && is_array($existing['property_details'])) {
        $existing = array_merge($existing['property_details'], $existing);
        unset($existing['property_details']);
    }

    $allData = array_merge($existing, $ad);
    unset($allData['property_details']);

        // راند ۲۹: لیبل‌های فعال و ترتیب آن‌ها از تنظیمات پنل ادمین؛
    // در نبود تنظیمات، همان ترتیب پیش‌فرض هر نوع ملک (رفتار قبلی).
    if (!empty($orderedLabels)) {
        $desiredLabels = $orderedLabels;
    } else {
        $desiredLabels = function_exists('melkinoPdTypeSpecOrder')
            ? melkinoPdTypeSpecOrder($propertyType)
            : array_keys($specDefinitions);
    }

    $result = [];

    foreach ($desiredLabels as $label) {
        $possibleKeys = $specDefinitions[$label] ?? [];
        $value = null;
        $foundKey = null;

        foreach ($possibleKeys as $key) {
            if (array_key_exists($key, $allData)) {
                $val = $allData[$key];
                if ($val !== null && $val !== '' && $val !== '0' && $val !== 0 && $val !== '[]') {
                    $value = $val;
                    $foundKey = $key;
                    break;
                }
            }
        }

        $isVilla = (mb_strpos($propertyType, 'ویلا') !== false);
        if ($isVilla && $label === 'متراژ زمین (متر مربع)') {
            $land = $allData['land_area'] ?? $allData['land_villa'] ?? null;
            if ($land !== null && $land !== '' && $land !== '0' && $land !== 0) {
                $value = $land;
                $foundKey = 'land_area';
            }
        }
        if ($isVilla && $label === 'زیربنا (متر مربع)') {
            $land = $allData['land_area'] ?? $allData['land_villa'] ?? null;
            $built = $allData['built_area'] ?? $allData['built_villa'] ?? null;
            if ($built !== null && $built !== '' && $built !== '0' && $built !== 0) {
                $value = $built;
                $foundKey = 'built_area';
            } elseif (isset($allData['area']) && $allData['area'] !== '' && $allData['area'] !== '0' && (string)$allData['area'] !== (string)$land) {
                $value = $allData['area'];
                $foundKey = 'area';
            }
        }

        if ($value === null || $value === '' || $value === '0' || $value === 0 || $value === '[]') {
            continue;
        }

        // تبدیل مقادیر بولی برای برخی کلیدها
        if (in_array($foundKey, ['parking', 'elevator', 'warehouse', 'balcony', 'renovated', 'has_well', 'has_pond', 'has_building', 'is_vip', 'featured', 'urgent', 'key_not_turned', 'is_not_keyed', 'is_new', 'new_building', 'never_lived'])) {
            $value = ($value == 1 || $value === true || $value === '1' || $value === 'true') ? 'دارد' : 'ندارد';
        }

        // تغییر ویژه برای کلید نخورده: به جای «دارد» جمله کامل نمایش داده شود
        if (in_array($foundKey, ['is_not_keyed', 'key_not_turned', 'is_new', 'new_building', 'never_lived']) && $value === 'دارد') {
            $value = 'این ملک کلید نخورده است';
        }

        $result[$label] = $value;
    }

    if (function_exists('melkinoBuildingAgeDisplay')) {
        $yearVal = $result['سال ساخت'] ?? ($allData['year'] ?? $allData['year_apt'] ?? $allData['year_villa'] ?? $allData['office_year'] ?? '');
        $ageDisp = melkinoBuildingAgeDisplay($yearVal);
        if ($ageDisp === '' && isset($allData['building_age']) && $allData['building_age'] !== '' && $allData['building_age'] !== null) {
            $ageDisp = function_exists('melkinoFaDigits') ? melkinoFaDigits((string) $allData['building_age']) : (string) $allData['building_age'];
        }
        if ($ageDisp !== '' && !isset($result['سن بنا'])) {
            $new = [];
            $inserted = false;
            foreach ($result as $lk => $lv) {
                $new[$lk] = $lv;
                if ($lk === 'سال ساخت') {
                    $new['سن بنا'] = $ageDisp;
                    $inserted = true;
                }
            }
            if (!$inserted) {
                $new['سن بنا'] = $ageDisp;
            }
            $result = $new;
        }
    }

    if (in_array($propertyType, ['تجاری', 'مغازه'], true) && function_exists('melkinoPdTypeSpecOrder')) {
        foreach (melkinoPdTypeSpecOrder('تجاری') as $commLabel) {
            if (isset($result[$commLabel])) {
                continue;
            }
            $possibleKeys = $specDefinitions[$commLabel] ?? [];
            foreach ($possibleKeys as $key) {
                if (!array_key_exists($key, $allData)) {
                    continue;
                }
                $val = $allData[$key];
                if ($val !== null && $val !== '' && $val !== '0' && $val !== 0 && $val !== '[]') {
                    $result[$commLabel] = is_array($val) ? implode('، ', array_filter(array_map('strval', $val))) : $val;
                    break;
                }
            }
        }
    }

    // معاوضه (راند ۱۹): تمایل به معاوضه + گزینه‌ها برای همهٔ انواع ملک
    if (!empty($ad['exchange_interested'])) {
        $__exTypes = trim((string)($ad['exchange_types'] ?? ''));
        if ($__exTypes !== '') {
            $__exTypes = str_replace(',', '، ', $__exTypes);
            $result['معاوضه'] = 'مایل به معاوضه — ' . $__exTypes;
        } else {
            $result['معاوضه'] = 'مایل به معاوضه';
        }
    }

    return $result;
}


/* =====================================================
   PUBLIC IMAGES
   ===================================================== */

function normalizePublicImages($images): array
{
    $images = normalizeArrayValue($images);
    $result = [];

    foreach ($images as $img) {
        if (!is_string($img)) continue;
        $img = trim($img);
        if ($img === '') continue;
        if (!in_array($img, $result, true)) {
            $result[] = $img;
        }
    }

    return $result;
}


/* =====================================================
   IMAGE PATH
   ===================================================== */

function normalizeImagePath(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';

    if (preg_match('#^https?://#i', $path)) return $path;
    if (strpos($path, 'data:image/') === 0) return $path;
    if (strpos($path, '/') === 0) return $path;

    return $path;
}


/* =====================================================
   TELEGRAM CONTACT
   ===================================================== */

function findTelegramLink(array $ad): string
{
    $possibleValues = [
        $ad['telegram_link'] ?? '',
        $ad['telegram'] ?? '',
        $ad['consultant_telegram'] ?? '',
        $ad['consultant_telegram_link'] ?? '',
        $ad['contact_telegram'] ?? '',
        $ad['telegram_username'] ?? ''
    ];

    foreach ($possibleValues as $value) {
        $value = trim((string) $value);
        if ($value === '') continue;

        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if (strpos($value, '@') === 0) {
            return 'https://t.me/' . ltrim($value, '@');
        }
        if (preg_match('/^[A-Za-z0-9_]{4,}$/', $value)) {
            return 'https://t.me/' . $value;
        }
    }

    $configCandidates = [
        defined('CONSULTANT_TELEGRAM') ? CONSULTANT_TELEGRAM : '',
        defined('TELEGRAM_CONSULTANT') ? TELEGRAM_CONSULTANT : '',
        defined('CONSULTANT_TELEGRAM_LINK') ? CONSULTANT_TELEGRAM_LINK : ''
    ];

    foreach ($configCandidates as $value) {
        $value = trim((string) $value);
        if ($value === '') continue;

        if (preg_match('#^https?://#i', $value)) return $value;
        if (strpos($value, '@') === 0) {
            return 'https://t.me/' . ltrim($value, '@');
        }
        return 'https://t.me/' . $value;
    }

    return '';
}


/* =====================================================
   LOAD DATA
   ===================================================== */

if ($isMockMode) {
    // Mock mode (اگر فعال باشد)
    $jsonFile = __DIR__ . '/ads.json';
    $ads = [];

    if (file_exists($jsonFile)) {
        $content = file_get_contents($jsonFile);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $ads = $decoded;
        }
    }

    foreach ($ads as $ad) {
        if ((string)($ad['id'] ?? '') !== $propertyId) continue;
        if (($ad['status'] ?? '') !== 'published') continue;

        $images = normalizePublicImages($ad['images'] ?? []);
        $selectedImages = normalizePublicImages($ad['selected_images'] ?? ($ad['selectedImages'] ?? []));

        $publicImages = count($selectedImages) > 0 ? $selectedImages : $images;
        if (count($selectedImages) > 0 && count($images) > 0) {
            $publicImages = normalizePublicImages(array_merge($selectedImages, $images));
        }

        // راند ۶۴: آگهی بدون عکس → گالری عکس‌های تزیینی نوع ملک
        if (empty($publicImages)) {
            // راند ۶۶: فقط یک عکس تزیینی (نه کل ست) + برچسب توضیح
            $melkinoDecorOne = melkinoDefaultImageForAd((array)$ad);
            $publicImages = $melkinoDecorOne !== '' ? [$melkinoDecorOne] : [];
            if ($publicImages) { $melkinoDecorGallery = true; }
        }

        if (empty($publicImages)) {
            $logoUrl = getSiteLogoUrl();
            if (!empty($logoUrl)) {
                $publicImages = [$logoUrl];
            }
        }

        $amenities = normalizeArrayValue($ad['amenities'] ?? []);
        $__fdType1 = trim((string)($ad['property_type'] ?? ''));
        if ($__fdType1 === '') { $__fdType1 = 'آپارتمان'; }
        $__fdOrder1 = melkinoFdSpecLabelOrder($fdDetCfg, $__fdType1);
        $specs = buildPublicSpecs($ad, array_map(static fn($x) => $x[0], $__fdOrder1));
        [$specs, $__fdSpecMeta] = melkinoFdApplySpecConfig($specs, $__fdOrder1);

        // استخراج متراژ
        $area = null;
        $possibleAreaKeys = ['area', 'built_area', 'land_area', 'office_area', 'garden_area', 'area_apt', 'area_comm', 'land_villa', 'built_villa'];
        foreach ($possibleAreaKeys as $key) {
            if (isset($ad[$key]) && is_numeric($ad[$key]) && $ad[$key] > 0) {
                $area = (float) $ad[$key];
                break;
            }
        }
        if (!$area) {
            $details = $ad['property_details'] ?? [];
            if (is_string($details)) {
                $details = json_decode($details, true);
            }
            if (is_array($details)) {
                foreach ($possibleAreaKeys as $key) {
                    if (isset($details[$key]) && is_numeric($details[$key]) && $details[$key] > 0) {
                        $area = (float) $details[$key];
                        break;
                    }
                }
            }
        }

        // راند ۷۳: قیمت از همهٔ ستون‌ها با نادیده‌گرفتن 0.00
        $price = function_exists('melkinoAdDisplayPrice') ? melkinoAdDisplayPrice($ad) : '';

        $telegramLink = findTelegramLink($ad);
        $isNotKeyed = isset($ad['is_not_keyed']) ? (int)$ad['is_not_keyed'] : 0;
        if (!$isNotKeyed) {
            // ممکن است در property_details باشد
            $details = $ad['property_details'] ?? [];
            if (is_string($details)) $details = json_decode($details, true);
            if (is_array($details) && isset($details['is_not_keyed'])) {
                $isNotKeyed = (int)$details['is_not_keyed'];
            }
        }

        $propertyData = [
            'id' => (string) $ad['id'],
            'title' => $ad['title'] ?? 'ملک بدون عنوان',
            'transaction_type' => $ad['transaction_type'] ?? ($ad['transactionType'] ?? 'فروش'),
            'property_type' => $ad['property_type'] ?? ($ad['propertyType'] ?? 'آپارتمان'),
            'price' => $price,
            'priceCondition' => (($ad['price_condition'] ?? '') === 'fixed') ? 'مقطوع' : 'قابل مذاکره',
            'location' => $ad['location'] ?? '',
            'neighborhood' => $ad['neighborhood'] ?? ($ad['location'] ?? ''),
            'description' => $ad['description'] ?? '',
            'images' => $publicImages,
            'amenities' => $amenities,
            'specs' => $specs,
            'specsMeta' => (object)($__fdSpecMeta ?? []),
            'isVip' => false,
            'phone' => $ad['phone'] ?? $ad['mobile'] ?? '',
            'last_name' => $ad['last_name'] ?? '',
            'telegram_link' => $telegramLink,
            'deposit'          => $ad['deposit'] ?? '',
            'rent_monthly'     => $ad['rent_monthly'] ?? '',
            'full_rent'        => $ad['full_rent'] ?? '',
            'full_rent_enabled'=> $ad['full_rent_enabled'] ?? 0,
            'price_sell'       => $ad['price_sell'] ?? '',
            'total_price'      => $ad['total_price'] ?? '',
            'area'             => $area,
            'is_not_keyed'     => $isNotKeyed,
            // فیلدهای وام
            'has_loan'                 => $ad['has_loan'] ?? 0,
            'loan_amount'              => $ad['loan_amount'] ?? '',
            'loan_type'                => $ad['loan_type'] ?? '',
            'loan_duration'            => $ad['loan_duration'] ?? '',
            'loan_bank'                => $ad['loan_bank'] ?? '',
            'loan_installment'         => $ad['loan_installment'] ?? '',
            'loan_installments_paid'   => $ad['loan_installments_paid'] ?? '',
            'loan_notes'               => $ad['loan_notes'] ?? '',
        ];

        break;
    }

} else {
    // حالت دیتابیس (اصلی)
    if (isset($pdo) && $propertyId !== '') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM ads WHERE id = ? AND status = 'published' LIMIT 1");
            $stmt->execute([$propertyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $publicImages = [];
                if (isset($pdo)) {
                    $imageStmt = $pdo->prepare("
                        SELECT filename, storage_path
                        FROM images
                        WHERE ad_id = ?
                          AND is_selected = 1
                          AND publish_publicly = 1
                        ORDER BY is_primary DESC, sort_order ASC, id ASC
                    ");
                    $imageStmt->execute([$propertyId]);
                    $imageRows = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($imageRows as $imageRow) {
                        $path = trim((string)($imageRow['storage_path'] ?? ''));
                        if ($path === '') {
                            $path = trim((string)($imageRow['filename'] ?? ''));
                        }
                        if ($path !== '') {
                            $publicImages[] = $path;
                        }
                    }
                    $publicImages = normalizePublicImages($publicImages);
                }

                // راند ۶۴: آگهی بدون عکس → گالری عکس‌های تزیینی نوع ملک
                if (empty($publicImages)) {
                    // راند ۶۶: فقط یک عکس تزیینی (نه کل ست) + برچسب توضیح
                    $melkinoDecorOne = melkinoDefaultImageForAd((array)$row);
                    $publicImages = $melkinoDecorOne !== '' ? [$melkinoDecorOne] : [];
                    if ($publicImages) { $melkinoDecorGallery = true; }
                }

                if (empty($publicImages)) {
                    $logoUrl = getSiteLogoUrl();
                    if (!empty($logoUrl)) {
                        $publicImages = [$logoUrl];
                    }
                }

                $amenities = [];
                if (isset($pdo)) {
                    $amenStmt = $pdo->prepare("
                        SELECT am.name
                        FROM ad_amenities aa
                        INNER JOIN amenities am ON am.id = aa.amenity_id
                        WHERE aa.ad_id = ?
                        ORDER BY am.sort_order, am.id
                    ");
                    $amenStmt->execute([$propertyId]);
                    $amenities = $amenStmt->fetchAll(PDO::FETCH_COLUMN);
                }
                $amenitiesFromColumn = normalizeArrayValue($row['amenities'] ?? []);
                $amenities = array_unique(array_merge($amenities, $amenitiesFromColumn));

                $row['property_details'] = normalizeArrayValue($row['property_details'] ?? []);

                // استخراج متراژ
                $area = null;
                $possibleAreaKeys = ['area', 'built_area', 'land_area', 'office_area', 'garden_area', 'area_apt', 'area_comm', 'land_villa', 'built_villa'];
                foreach ($possibleAreaKeys as $key) {
                    if (isset($row[$key]) && is_numeric($row[$key]) && $row[$key] > 0) {
                        $area = (float) $row[$key];
                        break;
                    }
                }
                if (!$area) {
                    $details = $row['property_details'] ?? [];
                    if (is_array($details)) {
                        foreach ($possibleAreaKeys as $key) {
                            if (isset($details[$key]) && is_numeric($details[$key]) && $details[$key] > 0) {
                                $area = (float) $details[$key];
                                break;
                            }
                        }
                    }
                }

                // راند ۷۳: قیمت از همهٔ ستون‌ها با نادیده‌گرفتن 0.00
                $price = function_exists('melkinoAdDisplayPrice') ? melkinoAdDisplayPrice($row) : '';

                $telegramLink = findTelegramLink($row);

                // خواندن is_not_keyed از ستون جدول
                $isNotKeyed = isset($row['is_not_keyed']) ? (int)$row['is_not_keyed'] : 0;

                // راند ۲۹: مشخصات با ترتیب/لیبل/آیکون تنظیم‌شده در پنل
                $__fdType2 = trim((string)($row['property_type'] ?? ''));
                if ($__fdType2 === '') { $__fdType2 = 'آپارتمان'; }
                $__fdOrder2 = melkinoFdSpecLabelOrder($fdDetCfg, $__fdType2);
                $__fdSpecs2 = buildPublicSpecs($row, array_map(static fn($x) => $x[0], $__fdOrder2));
                [$__fdSpecs2, $__fdSpecMeta2] = melkinoFdApplySpecConfig($__fdSpecs2, $__fdOrder2);

                $propertyData = [
                    'id' => (string) $row['id'],
                    'title' => $row['title'] ?? 'ملک بدون عنوان',
                    'transaction_type' => $row['transaction_type'] ?? 'فروش',
                    'property_type' => $row['property_type'] ?? 'آپارتمان',
                    'price' => $price,
                    'priceCondition' => (($row['price_condition'] ?? '') === 'fixed') ? 'مقطوع' : 'قابل مذاکره',
                    'location' => $row['location'] ?? '',
                    'neighborhood' => $row['neighborhood'] ?? ($row['location'] ?? ''),
                    'description' => $row['description'] ?? '',
                    'images' => $publicImages,
                    'amenities' => $amenities,
                    'specs' => $__fdSpecs2,
                    'specsMeta' => (object)$__fdSpecMeta2,
                    'isVip' => false,
                    'phone' => $row['phone'] ?? $row['mobile'] ?? '',
                    'last_name' => $row['last_name'] ?? '',
                    'telegram_link' => $telegramLink,
                    'deposit'          => $row['deposit'] ?? '',
                    'rent_monthly'     => $row['rent_monthly'] ?? '',
                    'full_rent'        => $row['full_rent'] ?? '',
                    'full_rent_enabled'=> $row['full_rent_enabled'] ?? 0,
                    'price_sell'       => $row['price_sell'] ?? '',
                    'total_price'      => $row['total_price'] ?? '',
                    'area'             => $area,
                    'is_not_keyed'     => $isNotKeyed,
                    'melkino_visited'  => (int)($row['melkino_visited'] ?? 0),
                    'melkino_rating'   => $row['melkino_rating'] ?? null,
                    'melkino_review'   => $row['melkino_review'] ?? '',
                    // فیلدهای وام
                    'has_loan'                 => $row['has_loan'] ?? 0,
                    'loan_amount'              => $row['loan_amount'] ?? '',
                    'loan_type'                => $row['loan_type'] ?? '',
                    'loan_duration'            => $row['loan_duration'] ?? '',
                    'loan_bank'                => $row['loan_bank'] ?? '',
                    'loan_installment'         => $row['loan_installment'] ?? '',
                    'loan_installments_paid'   => $row['loan_installments_paid'] ?? '',
                    'loan_notes'               => $row['loan_notes'] ?? '',
                ];
            }
        } catch (PDOException $e) {
            $propertyData = null;
        }
    }
}


/* =====================================================
   OVERRIDE CONSULTANT WITH SPECIALIZED ONE
   ===================================================== */
if (is_array($propertyData)) {
    $specializedConsultant = findConsultantForAd(
        $propertyData['property_type'] ?? '',
        $propertyData['transaction_type'] ?? ''
    );

    if ($specializedConsultant) {
        $consultantName = $specializedConsultant['name'] ?? $consultantName;
        $consultantPhone = $specializedConsultant['phone'] ?? $consultantPhone;

        $telegramLink = $specializedConsultant['telegram_link'] ?? '';
        if (empty($telegramLink) && !empty($specializedConsultant['telegram_username'])) {
            $telegramLink = 'https://t.me/' . ltrim($specializedConsultant['telegram_username'], '@');
        }
        if (!empty($telegramLink)) {
            $consultantTelegram = $telegramLink;
        }
    }
}


/* =====================================================
   CENTRAL CONSULTANT OVERRIDE
   ===================================================== */
if (is_array($propertyData)) {
    $propertyData['consultant'] = [
        'name' => $consultantName,
        'phone' => $consultantPhone,
        'telegram' => $consultantTelegram
    ];
}


/* =====================================================
   شمار مقایسه (برای حباب شناور «⚖️ مقایسه (N)»)
   ===================================================== */
// قبلاً این عدد فقط سمت مرورگر و با یک fetch جدا گرفته می‌شد؛ اگر
// ویجت دیر لود می‌شد یا درخواست می‌پرید، حباب هیچ‌وقت ظاهر نمی‌شد.
// حالا تعداد مستقیم از سرور خوانده و داخل خود صفحه چاپ می‌شود.
$compareCount = 0;
$inCompare = false;
try {
    if (isset($pdo) && $pdo instanceof PDO && $propertyId !== '' && is_array($propertyData) && function_exists('melkinoRecordAdView') && function_exists('melkinoCurrentIdentity')) {
        $vwIdn = melkinoCurrentIdentity();
        $vwUid = (int) ($vwIdn['user_id'] ?? 0);
        if ($vwUid > 0) {
            melkinoRecordAdView(
                $vwUid,
                (string) $propertyId,
                (string) ($propertyData['title'] ?? ''),
                (string) ($vwIdn['telegram_id'] ?? ''),
                (string) ($vwIdn['bale_id'] ?? '')
            );
        }
    }
} catch (Throwable $eViewRec) {
}
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        require_once __DIR__ . '/db_helpers.php';
        require_once __DIR__ . '/compare-lib.php';
        melkinoEnsureCompareTables($pdo);
        $cmpIdentity = melkinoCurrentIdentity();
        melkinoCompareMergeGuest($pdo, $cmpIdentity);
        [$cmpWhere, $cmpParams, , , ] = melkinoCompareOwner($cmpIdentity, 'ci');
        if ($cmpWhere !== '') {
            $cmpStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM compare_items ci
                 INNER JOIN ads a ON a.id = ci.ad_id AND a.status = 'published'
                 WHERE $cmpWhere"
            );
            $cmpStmt->execute($cmpParams);
            $compareCount = (int)$cmpStmt->fetchColumn();

            if ($propertyId !== '') {
                $inStmt = $pdo->prepare("SELECT 1 FROM compare_items ci WHERE $cmpWhere AND ci.ad_id = ? LIMIT 1");
                $inParams = $cmpParams;
                $inParams[] = (string)$propertyId;
                $inStmt->execute($inParams);
                $inCompare = (bool)$inStmt->fetchColumn();
            }
        }
    }
} catch (Throwable $e) {
    $compareCount = 0;
    $inCompare = false;
}

/* =====================================================
   HEADER
   ===================================================== */

require_once __DIR__ . '/header.php';
?>


<style>

:root {

    --detail-shadow:
        0 12px 36px rgba(15,23,42,.08);

    --detail-soft:
        rgba(6,78,78,.08);

    --detail-gold:
        linear-gradient(
            135deg,
            #c89d32,
            #f0d878
        );
}
/* =========================================================
   FINAL FIX — PROPERTY DETAILS CONTACT BAR
   ========================================================= */

/* فاصله پایین محتوای صفحه */
.main-content {
    /* راند ۲۳: فاصله بر اساس ارتفاع واقعی ناوبری پایین (متغیر با JS ست می‌شود) */
    padding-bottom:
        calc(
            var(--pd-bottom-nav-h, 78px) +
            148px
        ) !important;
}


/* نوار تماس مشاور */
body .bottom-actions-fixed {
    position: fixed !important;

    right: 0 !important;
    left: 0 !important;

    /*
     * ارتفاع فوتر فعلی ملکینو حدود 75px است.
     * نوار تماس دقیقاً بالای آن قرار می‌گیرد.
     */
    /*
     * راند ۲۳: به‌جای عدد ثابت، ارتفاع واقعیِ ناوبری پایین (که روی
     * گوشی‌ها با safe-area بزرگ‌تر می‌شود) با متغیر --pd-bottom-nav-h
     * توسط اسکریپت انتهای صفحه اندازه گرفته و ست می‌شود.
     */
    bottom:
        calc(
            var(--pd-bottom-nav-h, 78px) +
            6px
        ) !important;

    width: 100% !important;

    margin: 0 !important;

    padding:
        10px
        14px
        calc(
            10px +
            env(safe-area-inset-bottom)
        ) !important;

    box-sizing: border-box !important;

    z-index: 900 !important;

    background:
        rgba(255,255,255,.97) !important;

    border-top:
        1px solid
        var(--border) !important;

    box-shadow:
        0 -8px 24px
        rgba(0,0,0,.08) !important;

    backdrop-filter:
        blur(18px);

    -webkit-backdrop-filter:
        blur(18px);
}


/*
 * خود فوتر باید بالاتر از نوار تماس باشد
 */
body .bottom-nav,
body .bottom-navigation,
body .app-bottom-nav {
    z-index: 1000 !important;
}


/* محتوای نوار تماس */
body .consultant-bar {
    width:
        min(
            760px,
            100%
        ) !important;

    margin:
        0 auto !important;

    display:
        flex !important;

    align-items:
        center !important;

    justify-content:
        space-between !important;

    gap:
        10px !important;
}


/* دکمه‌ها */
body .consultant-actions {
    display:
        flex !important;

    gap:
        8px !important;

    flex:
        0 0 auto !important;
}

body .visit-request-open-btn,
.visit-request-open-btn {
    display: block;
    width: 100%;
    height: 44px;
    margin: 0 0 8px;
    border: 0;
    border-radius: 13px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    color: #fff;
    background: linear-gradient(135deg, #0E7C6E, #0B5D5B);
    position: relative;
    z-index: 2;
    pointer-events: auto;
    -webkit-tap-highlight-color: rgba(255,255,255,.25);
    touch-action: manipulation;
}
.vr-modal-overlay {
    display: none !important;
    position: fixed !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    left: 0 !important;
    z-index: 10050 !important;
    background: rgba(0,0,0,.5);
    align-items: flex-end;
    justify-content: center;
    pointer-events: none;
}
.vr-modal-overlay.is-open {
    display: flex !important;
    pointer-events: auto;
}
.vr-modal {
    width: min(520px, 100%);
    background: var(--surface, #fff);
    border-radius: 18px 18px 0 0;
    padding: 16px 16px calc(16px + env(safe-area-inset-bottom));
    max-height: 88vh;
    overflow: auto;
}
.vr-modal h3 { margin: 0 0 12px; font-size: 16px; }
.vr-days {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 12px;
}
.vr-day {
    border: 1px solid var(--border);
    background: var(--bg, #fff);
    color: var(--text-primary);
    border-radius: 12px;
    padding: 10px 8px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}
.vr-day.is-on {
    border-color: #0E7C6E;
    background: rgba(14,124,110,.1);
    color: #0E7C6E;
}
.vr-day.is-fri,
.vr-day.is-closed {
    color: #c0392b;
    border-color: #e74c3c;
    background: #fdecea;
    cursor: not-allowed;
    text-decoration: line-through;
}
.vr-note {
    font-size: 12px;
    line-height: 1.9;
    color: var(--text-secondary, #555);
    background: rgba(14,124,110,.08);
    border-radius: 12px;
    padding: 10px 12px;
    margin: 0 0 12px;
}
.vr-alt-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.8;
    margin: 0 0 6px;
}
.vr-alt {
    width: 100%;
    box-sizing: border-box;
    min-height: 72px;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px;
    margin: 0 0 12px;
    font-family: inherit;
    font-size: 12px;
    background: var(--bg, #fff);
    color: var(--text-primary);
    resize: vertical;
}
.vr-form-msg {
    display: none;
    font-size: 12px;
    line-height: 1.8;
    margin: 0 0 10px;
    padding: 8px 10px;
    border-radius: 10px;
}
.vr-form-msg.is-err {
    display: block;
    background: #fdecea;
    color: #8e1b12;
}
.vr-form-msg.is-ok {
    display: block;
    background: #e8f6ef;
    color: #0b5d5b;
}
.vr-slots { display: flex; gap: 8px; margin-bottom: 14px; }
.vr-slots label {
    flex: 1;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px;
    text-align: center;
    font-weight: 800;
    font-size: 13px;
    cursor: pointer;
}
.vr-slots input { accent-color: #0E7C6E; }
.vr-submit {
    width: 100%;
    height: 46px;
    border: 0;
    border-radius: 13px;
    font-family: inherit;
    font-weight: 800;
    font-size: 14px;
    color: #fff;
    background: linear-gradient(135deg, #c89d32, #f0d878);
    color: #3a2a00;
    cursor: pointer;
}
.vr-cancel {
    width: 100%;
    margin-top: 8px;
    height: 40px;
    border: 0;
    background: transparent;
    color: var(--text-secondary);
    font-family: inherit;
    cursor: pointer;
}


body .consultant-btn {
    height:
        44px !important;

    min-width:
        92px !important;

    display:
        flex !important;

    align-items:
        center !important;

    justify-content:
        center !important;

    border-radius:
        13px !important;

    text-decoration:
        none !important;

    font-size:
        11px !important;

    font-weight:
        800 !important;
}


/* حالت موبایل */
@media (max-width: 600px) {

    .main-content {
        padding-bottom:
            calc(
                var(--pd-bottom-nav-h, 76px) +
                140px
            ) !important;
    }

    body .bottom-actions-fixed {

        bottom:
            calc(
                var(--pd-bottom-nav-h, 76px) +
                5px
            ) !important;

        padding:
            8px
            10px !important;
    }

    body .consultant-info {
        display:
            none !important;
    }

    body .consultant-bar {
        width:
            100% !important;
    }

    body .consultant-actions {
        width:
            100% !important;

        display:
            grid !important;

        grid-template-columns:
            1fr 1fr !important;

        gap:
            8px !important;
    }

    body .consultant-btn {
        width:
            100% !important;

        min-width:
            0 !important;

        height:
            46px !important;
    }
}


/* حالت Dark */
[data-theme="dark"] body .bottom-actions-fixed {

    background:
        rgba(10,22,22,.97) !important;

    border-top-color:
        #294646 !important;

    box-shadow:
        0 -8px 24px
        rgba(0,0,0,.35) !important;
}

/* =========================================================
   MAIN
========================================================= */

.main-content {

    flex: 1;

    overflow-y: auto;

    background: var(--bg);

    padding:
        0 0
        190px;

    display:
        flex;

    flex-direction:
        column;
}


/* =========================================================
   SHELL
========================================================= */

.property-shell {

    padding:
        var(--space-3);

    display:
        flex;

    flex-direction:
        column;

    gap:
        var(--space-3);
}


/* =========================================================
   GALLERY
========================================================= */

.mk-decor-note{margin:10px 14px 0;font-size:12px;color:var(--text-secondary,#8a8f98);background:rgba(128,128,128,.10);border:1px dashed rgba(128,128,128,.45);border-radius:10px;padding:6px 12px;text-align:center;}
.hero-gallery {

    position: relative;

    width: 100%;

    height:
        clamp(
            300px,
            52vw,
            480px
        );

    background:
        #0f172a;

    overflow: hidden;

    border-radius:
        22px;

    box-shadow:
        var(--detail-shadow);
}


.gallery-slide {

    height: 100%;

    display: flex;

    direction: ltr;

    transition:
        transform .45s ease;

    will-change:
        transform;

    touch-action:
        pan-y;
}


.gallery-slide > div {

    flex: 0 0 100%;

    width: 100%;

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #061B1B;

    position: relative;
}


.gallery-slide img {

    width: 100%;

    height: 100%;

    display: block;

    object-fit: contain;

    object-position: center;

    background: #061B1B;

    user-select: none;

    -webkit-user-drag: none;
}

.gallery-slide .mk-photo-wm {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 6;
}

.gallery-slide .mk-photo-wm img {
    background: transparent;
}


.gallery-slide .no-image {

    width: 100%;

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: center;
}


.hero-gallery::after {

    content: '';

    position:
        absolute;

    right: 0;
    bottom: 0;
    left: 0;

    height: 42%;

    background:
        linear-gradient(
            transparent,
            rgba(0,0,0,.72)
        );

    pointer-events:
        none;
}


.gallery-topbar {

    position:
        absolute;

    top: 16px;
    right: 16px;
    left: 16px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    z-index:
        8;
}


.glass-btn {

    width: 44px;
    height: 44px;

    border:
        1px solid
        rgba(255,255,255,.22);

    background:
        rgba(15,23,42,.44);

    backdrop-filter:
        blur(12px);

    -webkit-backdrop-filter:
        blur(12px);

    color:
        #fff;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    cursor:
        pointer;

    transition:
        transform .18s ease,
        background .18s ease,
        color .18s ease;
}


.glass-btn:hover {

    transform:
        translateY(-2px);
}


.glass-btn:active {

    transform:
        scale(.95);
}


.glass-btn.in-compare {

    color:
        var(--primary);

    border-color:
        var(--primary);

}


/* ردیف اقدامِ زیر اطلاعات ملک — دکمهٔ بزرگ و برچسب‌دار مقایسه */
.pd-action-row {

    display: flex;

    gap: 8px;

    margin: 12px 0 2px;
}


.pd-action-btn {

    flex: 1;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 12px 16px;

    border-radius: 14px;

    border: 1px solid var(--border);

    background: var(--surface);

    color: var(--text-primary);

    font-family: inherit;

    font-size: 13px;

    font-weight: 800;

    cursor: pointer;

    transition: border-color .2s ease, color .2s ease, background .2s ease;
}


.pd-action-btn:active {

    transform: scale(.98);
}


.pd-action-btn.in-compare {

    border-color: var(--primary);

    color: var(--primary);

    background: rgba(11, 93, 91, .08);
}

.glass-btn.active {

    color:
        #e11d48;

    background:
        rgba(255,255,255,.94);

    border-color:
        rgba(255,255,255,.9);
}


.gallery-counter {

    background:
        rgba(15,23,42,.52);

    border:
        1px solid
        rgba(255,255,255,.16);

    backdrop-filter:
        blur(12px);

    -webkit-backdrop-filter:
        blur(12px);

    color:
        #fff;

    padding:
        7px 12px;

    border-radius:
        999px;

    font-size:
        12px;

    font-weight:
        700;
}


.gallery-bottom {

    position:
        absolute;

    right:
        16px;

    left:
        16px;

    bottom:
        16px;

    display:
        flex;

    align-items:
        flex-end;

    justify-content:
        space-between;

    gap:
        12px;

    z-index:
        8;
}


.gallery-code {

    color:
        #fff;

    font-size:
        12px;

    padding:
        6px 10px;

    border-radius:
        999px;

    background:
        rgba(15,23,42,.52);

    backdrop-filter:
        blur(12px);

    -webkit-backdrop-filter:
        blur(12px);
}


.gallery-actions {

    display:
        flex;

    gap:
        8px;
}


.gallery-nav {

    width:
        42px;

    height:
        42px;

    border:
        1px solid
        rgba(255,255,255,.18);

    background:
        rgba(255,255,255,.92);

    color:
        #111827;

    border-radius:
        50%;

    font-size:
        24px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    cursor:
        pointer;

    box-shadow:
        0 6px 18px
        rgba(0,0,0,.18);

    z-index:
        8;
}


.gallery-nav:active {

    transform:
        scale(.94);
}


/* =========================================================
   THUMBS
========================================================= */

.gallery-thumbs {

    display:
        flex;

    gap:
        8px;

    overflow-x:
        auto;

    padding-top:
        2px;

    scrollbar-width:
        none;

    direction:
        rtl;
}


.gallery-thumbs::-webkit-scrollbar {
    display:
        none;
}


.gallery-thumb {

    flex:
        0 0 76px;

    height:
        60px;

    padding:
        0;

    border:
        2px solid
        transparent;

    border-radius:
        10px;

    overflow:
        hidden;

    background:
        var(--surface);

    cursor:
        pointer;

    opacity:
        .68;

    transition:
        .2s ease;
}


.gallery-thumb.active {

    border-color:
        var(--gold);

    opacity:
        1;

    transform:
        translateY(-1px);
}


.gallery-thumb img {

    width:
        100%;

    height:
        100%;

    object-fit:
        cover;

    display:
        block;
}


/* =========================================================
   INFO
========================================================= */

.info-head {

    display:
        flex;

    flex-direction:
        column;

    gap:
        10px;
}


.property-code {

    font-size:
        12px;

    color:
        var(--text-secondary);

    display:
        inline-flex;

    align-items:
        center;

    width:
        max-content;

    padding:
        5px 10px;

    border:
        1px solid
        var(--border);

    border-radius:
        999px;

    background:
        var(--surface);
}


.property-title {

    font-size:
        26px;

    font-weight:
        900;

    line-height:
        1.35;

    color:
        var(--text-primary);

    margin:
        0;
}


.property-meta {

    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        8px;
}


.meta-chip svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.meta-chip {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        5px;

    padding:
        7px 11px;

    border-radius:
        999px;

    background:
        var(--surface);

    border:
        1px solid
        var(--border);

    color:
        var(--text-secondary);

    font-size:
        12px;

    font-weight:
        700;
}

/* برچسب کلید نخورده */
.meta-chip.key-not-turned {
    background: var(--gold-bg);
    color: var(--gold-dark);
    border-color: var(--gold);
    font-weight: 800;
}

/* =========================================================
   PRICE
========================================================= */

.price-card {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        12px;

    flex-wrap:
        wrap;

    padding:
        16px;

    border-radius:
        18px;

    background:
        var(--surface);

    border:
        1px solid
        var(--border);

    box-shadow:
        var(--detail-shadow);
}


.price-label {

    font-size:
        12px;

    color:
        var(--text-secondary);

    margin-bottom:
        4px;
}


.price-large {

    font-size:
        28px;

    font-weight:
        900;

    color:
        var(--gold);
}


/* =========================================================
   SECTION
========================================================= */

.section-card {

    background:
        var(--surface);

    border:
        1px solid
        var(--border);

    border-radius:
        18px;

    padding:
        18px;

    box-shadow:
        var(--detail-shadow);
}


<?php echo melkinoFdHomeCss(); ?>

.fd-sec{ display:contents; }
.fd-preview-badge{
    position:fixed; top:10px; left:50%; transform:translateX(-50%);
    z-index:9999; background:#7c3aed; color:#fff; padding:8px 16px;
    border-radius:999px; font-size:12.5px; font-weight:700;
    box-shadow:0 6px 20px rgba(0,0,0,.35);
}

.section-title {

    display:
        flex;

    align-items:
        center;

    gap:
        8px;

    font-size:
        18px;

    font-weight:
        800;

    color:
        var(--text-primary);

    margin:
        0 0 14px;
}


.section-title::before {

    content:
        '';

    width:
        4px;

    height:
        20px;

    border-radius:
        4px;

    background:
        var(--gold);
}


/* =========================================================
   SPECS
========================================================= */

.specs-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap:
        10px;
}


.specs-item {

    min-height:
        56px;

    padding:
        12px 13px;

    border:
        1px solid
        var(--border);

    border-radius:
        12px;

    background:
        var(--bg);

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    gap:
        3px;
}


.specs-label {

    font-size:
        11px;

    color:
        var(--text-secondary);

    display: inline-flex;

    align-items: center;

    gap: 6px;
}

.specs-label svg,
.amenity-name svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    color: var(--primary);
}


.specs-value {

    font-size:
        14px;

    font-weight:
        800;

    color:
        var(--text-primary);

    word-break:
        break-word;
}


/* =========================================================
   AMENITIES
========================================================= */

.amenities-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap:
        10px;
}


.amenity-item {

    display:
        flex;

    align-items:
        center;

    gap:
        8px;

    padding:
        11px 12px;

    border:
        1px solid
        var(--border);

    border-radius:
        12px;

    background:
        var(--bg);
}


.amenity-check {

    width:
        24px;

    height:
        24px;

    border-radius:
        50%;

    background:
        var(--detail-soft);

    color:
        var(--primary);

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-weight:
        900;

    flex:
        none;
}


.amenity-check.cross {
    background: var(--danger-bg);
    color: var(--danger);
}


.amenity-name {

    font-size:
        13px;

    font-weight:
        700;

    color:
        var(--text-primary);

    display: inline-flex;

    align-items: center;

    gap: 6px;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.description {

    font-size:
        15px;

    line-height:
        2;

    color:
        var(--text-secondary);

    white-space:
        pre-wrap;

    margin:
        0;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-box {

    padding:
        22px;

    text-align:
        center;

    color:
        var(--text-secondary);

    border:
        1px dashed
        var(--border);

    border-radius:
        14px;
}


/* =========================================================
   BOTTOM ACTIONS
========================================================= */

.bottom-actions-fixed {

    position: fixed;

    right: 0;
    left: 0;
    bottom: 0;

    z-index: 1200;

    padding:
        10px
        var(--space-3)
        calc(
            10px + env(safe-area-inset-bottom)
        );

    background:
        rgba(255,255,255,.96);

    backdrop-filter: blur(18px);

    -webkit-backdrop-filter: blur(18px);

    border-top: 1px solid var(--border);

    box-shadow:
        0 -10px 30px rgba(0,0,0,.08);
}


.consultant-bar {

    width: min(760px, 100%);
    margin: 0 auto;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}


.consultant-info {

    display: flex;
    align-items: center;
    gap: 9px;
    min-width: 0;
}


.consultant-avatar {

    width: 40px;
    height: 40px;
    flex: 0 0 auto;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;

    background:
        linear-gradient(135deg,#0A5C5C,#063D3D);

    color: #F0D36A;
    font-size: 18px;
}


.consultant-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 2px;
}


.consultant-text strong {
    color: var(--text-primary);
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}


.consultant-text span {
    color: var(--text-secondary);
    font-size: 8px;
}


.consultant-actions {
    display: flex;
    gap: 7px;
    flex: 0 0 auto;
}


.consultant-btn {
    min-width: 88px;
    height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;

    border-radius: 12px;
    text-decoration: none;
    font-family: inherit;
    font-size: 10px;
    font-weight: 800;

    transition:
        transform .18s ease,
        opacity .18s ease;
}


.consultant-btn:active {
    transform: scale(.96);
}


.consultant-btn.call {
    background: var(--primary);
    color: #fff;
}


.consultant-btn.telegram {
    background: linear-gradient(135deg,#D4AF37,#F0D36A);
    color: #142020;
}


.consultant-btn.disabled {
    opacity: .45;
    cursor: default;
}


[data-theme="dark"] .bottom-actions-fixed {
    background: rgba(10,22,22,.96);
    border-top-color: #294646;
    box-shadow: 0 -10px 30px rgba(0,0,0,.35);
}


[data-theme="dark"] .consultant-text strong {
    color: #F4F8F7;
}


@media (max-width: 480px) {

    .consultant-bar {
        gap: 8px;
    }

    .consultant-info {
        flex: 0 0 auto;
    }

    .consultant-text {
        display: none;
    }

    .consultant-avatar {
        width: 38px;
        height: 38px;
    }

    .consultant-actions {
        flex: 1;
    }

    .consultant-btn {
        flex: 1;
        min-width: 0;
        height: 44px;
        font-size: 10px;
    }
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    .hero-gallery {
        height: min(62vh, 520px);
        min-height: 260px;
    }
}


@media (max-width: 520px) {

    .main-content {

        padding-bottom:
            175px;
    }


    .property-shell {

        padding:
            12px;

        gap:
            12px;
    }


    .hero-gallery {

        height:
            310px;

        border-radius:
            0;

        margin:
            0 -12px;

        width:
            calc(
                100% + 24px
            );
    }


    .property-title {

        font-size:
            22px;
    }


    .specs-grid,
    .amenities-grid {

        grid-template-columns:
            1fr 1fr;
    }


    .price-large {

        font-size:
            24px;
    }


    .gallery-topbar {

        top:
            12px;

        right:
            12px;

        left:
            12px;
    }


    .gallery-bottom {

        right:
            12px;

        left:
            12px;

        bottom:
            12px;
    }


    .action-btn {

        height:
            48px;

        font-size:
            12px;
    }
}


@media (max-width: 380px) {

    .specs-grid,
    .amenities-grid {

        grid-template-columns:
            1fr;
    }


    .action-row {

        gap:
            7px;
    }


    .action-btn {

        font-size:
            11px;
    }

}


/* =========================================================
   DARK MODE
========================================================= */

[data-theme="dark"] .bottom-actions-fixed {

    background:
        rgba(11,22,22,.95);

    border-top-color:
        #294646;

    box-shadow:
        0 -8px 28px
        rgba(0,0,0,.35);
}


[data-theme="dark"] .property-card {

    background:
        #142525;
}

</style>


<div
    class="main-content"
    id="mainContent"
>

<?php if ($propertyData === null): ?>

    <div
        style="
            text-align:center;
            padding:70px 20px;
        "
    >

        <div
            style="font-size:58px;"
        >
            🏠
        </div>


        <h3
            style="
                color:var(--text-primary);
                margin-top:18px;
            "
        >
            ملک مورد نظر یافت نشد
        </h3>


        <p
            style="
                color:var(--text-secondary);
            "
        >
            ممکن است آگهی حذف یا از حالت انتشار خارج شده باشد.
        </p>


        <a
            href="home.php"
            style="
                display:inline-block;
                margin-top:18px;
                padding:12px 28px;
                background:var(--primary);
                color:#fff;
                border-radius:12px;
                text-decoration:none;
            "
        >
            بازگشت به خانه
        </a>

    </div>

<?php else: ?>


    <div class="property-shell">

        <?php
        // راند ۲۹: هر بخش در متغیر گرفته می‌شود و در انتها به ترتیب تنظیم‌شده چاپ می‌شود
        $fdSecHtml = [];
        ob_start();
        ?>


        <!-- =====================================================
             GALLERY
        ====================================================== -->

        <?php if (!empty($melkinoDecorGallery)): ?>
        <div class="mk-decor-note">عکس نمایش‌داده‌شده تزیینی است و مربوط به این ملک نیست</div>
        <?php endif; ?>

        <div
            class="hero-gallery"
            id="galleryContainer"
        >

            <div
                class="gallery-slide"
                id="gallerySlide"
            ></div>


            <div class="gallery-topbar">

                <div
                    class="gallery-counter"
                    id="galleryCounter"
                >
                    📸 ۱ / ۱
                </div>


                <button
                    type="button"
                    class="glass-btn"
                    id="favoriteBtn"
                    onclick="toggleFavorite()"
                    aria-label="افزودن به علاقه‌مندی"
                    title="افزودن به علاقه‌مندی"
                >

                    <svg
                        width="21"
                        height="21"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >

                        <path
                            d="
                                M20.84 4.61
                                a5.5 5.5 0 0 0-7.78 0
                                L12 5.67
                                l-1.06-1.06
                                a5.5 5.5 0 0 0-7.78 7.78
                                l1.06 1.06
                                L12 21.23
                                l7.78-7.78
                                1.06-1.06
                                a5.5 5.5 0 0 0 0-7.78z
                            "
                        ></path>

                    </svg>

                </button>

            </div>


            <div class="gallery-bottom">

                <div class="gallery-code">

                    کد ملک:
                    <?= htmlspecialchars(
                        $propertyData['id'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div class="gallery-actions">

                    <button
                        type="button"
                        class="gallery-nav prev"
                        onclick="changeImage(-1)"
                        aria-label="تصویر قبلی"
                    >
                        ‹
                    </button>


                    <button
                        type="button"
                        class="gallery-nav next"
                        onclick="changeImage(1)"
                        aria-label="تصویر بعدی"
                    >
                        ›
                    </button>

                </div>

            </div>

        </div>


        <div
            class="gallery-thumbs"
            id="galleryThumbs"
        ></div>


        <!-- =====================================================
             INFO
        ====================================================== -->

        <?php $fdSecHtml['gallery'] = ob_get_clean(); ob_start(); ?>

        <div class="info-head">

            <?php if ($fdShow('header', 'property_code')): ?>
            <div class="property-code<?= melkinoFdRclass($fdF('header', 'property_code')) ?>">

                ملکینو •
                <?= htmlspecialchars(
                    $propertyData['property_type']
                    ?? 'ملک',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>
            <?php endif; ?>


            <?php if ($fdShow('header', 'title')): ?>
            <h1 class="property-title<?= melkinoFdRclass($fdF('header', 'title')) ?>">

                <?= htmlspecialchars(
                    $propertyData['title'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h1>
            <?php endif; ?>


            <div class="property-meta">

                <?php if ($fdShow('header', 'chip_transaction')): ?>
                <span class="meta-chip<?= melkinoFdRclass($fdF('header', 'chip_transaction')) ?>">

                    <?= function_exists('melkinoFieldIcon') ? melkinoFieldIcon((($fdF('header', 'chip_transaction')['icon'] ?? '') !== '' ? $fdF('header', 'chip_transaction')['icon'] : 'transaction')) : melkinoFdEsc(($fdF('header', 'chip_transaction')['icon'] ?? '') !== '' ? $fdF('header', 'chip_transaction')['icon'] : '🏷️') ?>
                    <?= htmlspecialchars(
                        $propertyData['transaction_type']
                        ?? 'فروش',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>
                <?php endif; ?>


                <?php if ($fdShow('header', 'chip_location')): ?>
                <span class="meta-chip<?= melkinoFdRclass($fdF('header', 'chip_location')) ?>">

                    <?= function_exists('melkinoFieldIcon') ? melkinoFieldIcon((($fdF('header', 'chip_location')['icon'] ?? '') !== '' ? $fdF('header', 'chip_location')['icon'] : 'location')) : melkinoFdEsc(($fdF('header', 'chip_location')['icon'] ?? '') !== '' ? $fdF('header', 'chip_location')['icon'] : '📍') ?>
                    <?= htmlspecialchars(
                        $propertyData['neighborhood']
                        ?? $propertyData['location']
                        ?? 'موقعیت نامشخص',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>
                <?php endif; ?>

                <?php if (!empty($propertyData['is_not_keyed']) && $fdShow('header', 'chip_key')): ?>
                    <?php
                    $__ck = $fdF('header', 'chip_key');
                    $__ckIcon = ($__ck['icon'] ?? '') !== '' ? $__ck['icon'] : '🔑';
                    $__ckText = ($__ck['label'] ?? '') !== '' ? $__ck['label'] : 'کلید نخورده';
                    ?>
                    <span class="meta-chip key-not-turned<?= melkinoFdRclass($__ck) ?>">
                        <?= melkinoFdEsc(trim($__ckIcon . ' ' . $__ckText)) ?>
                    </span>
                <?php endif; ?>

            </div>

        </div>


        <!-- دکمهٔ مقایسه: ردیف اقدامِ زیر اطلاعات ملک (جای دیدگی و
             مشخص، به‌جای آیکون کوچک روی عکس گالری) -->
        <?php if ($fdShow('header', 'compare_btn')): ?>
        <div class="pd-action-row<?= melkinoFdRclass($fdF('header', 'compare_btn')) ?>">

            <button
                type="button"
                id="compareBtn"
                class="pd-action-btn<?= $inCompare ? ' in-compare' : '' ?>"
                data-compare-add="<?= htmlspecialchars($propertyId, ENT_QUOTES, 'UTF-8') ?>"
                aria-label="افزودن به مقایسه"
                aria-pressed="<?= $inCompare ? 'true' : 'false' ?>"
            >
                <span aria-hidden="true">⚖️</span>
                <span id="compareBtnLabel"><?= $inCompare ? 'در مقایسه' : 'افزودن به مقایسه' ?></span>
            </button>

        </div>
        <?php endif; ?>


        <?php $fdSecHtml['header'] = ob_get_clean(); ob_start(); ?>

        <!-- =====================================================
             PRICE
        ====================================================== -->

        <div class="price-card">

            <div style="flex:1;">

                <div class="price-label">
                    شرایط معامله
                </div>

                <?php
                /*
                 * کنترل نمایش قیمت فقط در همین بخش:
                 * - تنظیم عمومی دیتابیس (settings.global.show_prices)
                 * - مخفی‌سازی اختصاصی آگهی price_hidden
                 *
                 * قیمت اصلی هیچ‌وقت حذف نمی‌شود؛
                 * فقط در نمایش عمومی جایگزین می‌شود.
                 *
                 * قبلاً این مقدار از یک فایل JSON قدیمی (settings/global.json)
                 * خونده می‌شد که هیچ‌وقت با تغییرات پنل ادمین آپدیت نمی‌شد.
                 */

                $globalPriceSettings = getGlobalSettings();

                $globalShowPrices =
                    !empty($globalPriceSettings['show_prices']) &&
                    empty($globalPriceSettings['hide_all_prices']);

                /*
                 * بررسی مخفی بودن قیمت همین آگهی
                 */
                $adPriceHidden = false;

                if ($isMockMode) {

                    $priceAdsFile =
                        __DIR__ . '/ads.json';

                    if (is_file($priceAdsFile)) {

                        $priceAds =
                            json_decode(
                                (string) @file_get_contents($priceAdsFile),
                                true
                            );

                        if (is_array($priceAds)) {

                            foreach ($priceAds as $priceAd) {

                                if (
                                    (string) ($priceAd['id'] ?? '') ===
                                    (string) $propertyId
                                ) {
                                    $adPriceHidden =
                                        !empty($priceAd['price_hidden']);

                                    break;
                                }
                            }
                        }
                    }

                } elseif (
                    isset($pdo) &&
                    $propertyId !== ''
                ) {

                    /*
                     * در حالت دیتابیس، اگر ستون price_hidden وجود داشته باشد
                     * مقدار آن را می‌خوانیم.
                     */
                    try {

                        $priceStmt =
                            $pdo->prepare(
                                "SELECT price_hidden FROM ads WHERE id = ? LIMIT 1"
                            );

                        $priceStmt->execute([
                            $propertyId
                        ]);

                        $priceRow =
                            $priceStmt->fetch(PDO::FETCH_ASSOC);

                        if (is_array($priceRow)) {
                            $adPriceHidden =
                                !empty($priceRow['price_hidden']);
                        }

                    } catch (Throwable $e) {
                        /*
                         * اگر ستون هنوز در دیتابیس وجود نداشته باشد،
                         * نمایش عادی قیمت ادامه پیدا می‌کند.
                         */
                        $adPriceHidden = false;
                    }
                }


                /*
                 * اگر نمایش قیمت عمومی خاموش باشد
                 * یا همین آگهی مخفی شده باشد.
                 */
                if (
                    !$globalShowPrices ||
                    $adPriceHidden
                ) {
                    echo '
                        <div
                            style="
                                font-size:18px;
                                font-weight:800;
                                color:var(--gold);
                            "
                        >
                            برای استعلام قیمت تماس بگیرید
                        </div>
                    ';

                } else {

                    $hasPrice = false;
                    $priceHtml = '';

                    /*
                     * قالب‌بندی مبلغ
                     * - بدون اعشار
                     * - با جداکننده هزارگان
                     */
                    $formatToman = static function ($value): string {
                        if ($value === null || trim((string)$value) === '') {
                            return '0';
                        }

                        $normalized = str_replace(',', '', trim((string)$value));

                        if (!is_numeric($normalized)) {
                            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                        }

                        return number_format((float)$normalized, 0, '.', ',');
                    };

                    $transactionType = trim((string)($propertyData['transaction_type'] ?? ''));

                    /*
                     * فروش: فقط قیمت فروش
                     */
                    if ($transactionType === 'فروش') {
                        $priceSell = $propertyData['price_sell'] ?? 0;
                        $priceSellNumber = (float)str_replace(',', '', (string)$priceSell);

                        if ($priceSellNumber > 0) {
                            $hasPrice = true;

                            $priceHtml .= '
                                <div
                                    style="
                                        font-size:18px;
                                        font-weight:700;
                                        color:var(--gold);
                                        line-height:1.9;
                                    "
                                >
                                    💰 قیمت فروش: ' . $formatToman($priceSell) . ' تومان
                                </div>
                            ';
                        }
                    } else {
                        /*
                         * رهن کامل
                         */
                        $fullRentEnabled = $propertyData['full_rent_enabled'] ?? false;
                        $fullRent = $propertyData['full_rent'] ?? 0;
                        $fullRentNumber = (float)str_replace(',', '', (string)$fullRent);

                        if (!empty($fullRentEnabled) && $fullRentNumber > 0) {
                            $hasPrice = true;

                            $priceHtml .= '
                                <div
                                    style="
                                        font-size:18px;
                                        font-weight:700;
                                        color:var(--gold);
                                        line-height:1.9;
                                    "
                                >
                                    🏠 رهن کامل: ' . $formatToman($fullRent) . ' تومان
                                </div>
                            ';
                        }

                        /*
                         * ودیعه
                         */
                        $deposit = $propertyData['deposit'] ?? 0;
                        $depositNumber = (float)str_replace(',', '', (string)$deposit);

                        if ($depositNumber > 0) {
                            $hasPrice = true;

                            $priceHtml .= '
                                <div
                                    style="
                                        font-size:18px;
                                        font-weight:700;
                                        color:var(--gold);
                                        line-height:1.9;
                                    "
                                >
                                    ودیعه: ' . $formatToman($deposit) . ' تومان
                                </div>
                            ';
                        }

                        /*
                         * اجاره
                         */
                        $rentMonthly = $propertyData['rent_monthly'] ?? 0;
                        $rentMonthlyNumber = (float)str_replace(',', '', (string)$rentMonthly);

                        if ($rentMonthlyNumber > 0) {
                            $hasPrice = true;

                            $priceHtml .= '
                                <div
                                    style="
                                        font-size:18px;
                                        font-weight:700;
                                        color:var(--gold);
                                        line-height:1.9;
                                    "
                                >
                                    اجاره: ' . $formatToman($rentMonthly) . ' تومان
                                </div>
                            ';
                        }
                    }

                    /*
                     * پیش‌فروش
                     */
                    if (
                        !empty($propertyData['total_price']) &&
                        $propertyData['total_price'] != '0'
                    ) {

                        $hasPrice = true;

                        $priceHtml .= '
                            <div
                                style="
                                    font-size:18px;
                                    font-weight:700;
                                    color:var(--gold);
                                    line-height:1.9;
                                "
                            >
                                📋 قیمت کل:
                                ' . $formatToman($propertyData['total_price']) . ' تومان
                            </div>
                        ';
                    }

                    /*
                     * ملک وام‌دار: خط دوم قیمت
                     * «(قیمت منهای وام) + (مبلغ وام) وام»
                     */
                    $loanInfo = function_exists('melkinoLoanInfo')
                        ? melkinoLoanInfo($propertyData)
                        : ['has' => false];

                    if (!empty($loanInfo['has']) && $hasPrice) {
                        if (!empty($loanInfo['net'])) {
                            $priceHtml .= '
                                <div
                                    style="
                                        font-size:16px;
                                        font-weight:700;
                                        color:#0a7a55;
                                        line-height:1.9;
                                    "
                                >
                                    💵 نقد + وام: ' . $formatToman($loanInfo['net']) . ' تومان + '
                                    . $formatToman($loanInfo['amount']) . ' تومان وام
                                </div>
                            ';
                        } else {
                            $priceHtml .= '
                                <div
                                    style="
                                        font-size:15px;
                                        font-weight:700;
                                        color:#0a7a55;
                                        line-height:1.9;
                                    "
                                >
                                    ' . melkinoSvgIcon('bank') . ' این ملک ' . $formatToman($loanInfo['amount']) . ' تومان وام دارد (از قیمت کسر می‌شود)
                                </div>
                            ';
                        }
                    }

                    if (!$hasPrice) {

                        $priceHtml = '
                            <div
                                style="
                                    font-size:18px;
                                    font-weight:700;
                                    color:var(--gold);
                                "
                            >
                                تماس بگیرید
                            </div>
                        ';
                    }

                    // راند ۲۹: نمایش قیمت اصلی طبق تنظیمات پنل
                    if ($fdShow('price', 'price_main')) {
                        echo '<div class="fd-price-main' . melkinoFdRclass($fdF('price', 'price_main')) . '">' . $priceHtml . '</div>';
                    }
                }

                // ===== بخش جدید: محاسبه و نمایش قیمت هر متر مربع =====
                $pricePerMeterText = calculatePricePerSquareMeter($propertyData);
                if ($pricePerMeterText !== null && $fdShow('price', 'price_per_meter')) {
                    echo '<div class="' . trim(melkinoFdRclass($fdF('price', 'price_per_meter'))) . '" style="margin-top:10px; padding-top:10px; border-top:1px solid var(--border); font-size:14px; color:var(--text-secondary);">';
                    echo '💰 قیمت هر متر مربع: <strong style="color:var(--gold); font-size:18px;">' . $pricePerMeterText . '</strong>';
                    echo '</div>';
                }
                // ===== پایان بخش جدید =====

                // ===== مشخصات کامل وام (فقط برای ملک‌های وام‌دار) =====
                if (!empty($loanInfo['has']) && $fdShow('price', 'loan_box')) {
                    $loanRows = [];
                    $loanRows[] = ['مبلغ وام', $formatToman($loanInfo['amount']) . ' تومان'];
                    if ($loanInfo['type'] !== '')     { $loanRows[] = ['نوع وام', $loanInfo['type']]; }
                    if ($loanInfo['bank'] !== '')     { $loanRows[] = ['بانک', $loanInfo['bank']]; }
                    if ($loanInfo['duration'] !== '') { $loanRows[] = ['مدت وام', $loanInfo['duration']]; }
                    $loanInst = (float)str_replace(',', '', (string)$loanInfo['installment']);
                    if ($loanInst > 0)                { $loanRows[] = ['مبلغ هر قسط', $formatToman($loanInfo['installment']) . ' تومان']; }
                    if ($loanInfo['paid'] !== '')     { $loanRows[] = ['تعداد اقساط پرداخت شده', $loanInfo['paid']]; }

                    echo '<div class="' . trim(melkinoFdRclass($fdF('price', 'loan_box'))) . '" style="margin-top:12px; padding:12px; border:1px solid var(--border); border-radius:12px; background:var(--bg);">';
                    echo '<div style="font-weight:700; font-size:15px; margin-bottom:6px;">🏦 مشخصات وام</div>';
                    echo '<div style="font-size:12.5px; color:var(--text-secondary); margin-bottom:8px; line-height:1.9;">مبلغ وام از قیمت درج شده کسر می‌شود.</div>';
                    foreach ($loanRows as $__lr) {
                        echo '<div style="display:flex; justify-content:space-between; gap:10px; font-size:13.5px; line-height:2.1; border-top:1px dashed var(--border);">';
                        echo '<span style="color:var(--text-secondary);">' . htmlspecialchars($__lr[0], ENT_QUOTES, 'UTF-8') . '</span>';
                        echo '<strong>' . htmlspecialchars($__lr[1], ENT_QUOTES, 'UTF-8') . '</strong>';
                        echo '</div>';
                    }
                    if ($loanInfo['notes'] !== '') {
                        echo '<div style="margin-top:8px; font-size:13px; line-height:2; color:var(--text-secondary);">📄 ' . htmlspecialchars($loanInfo['notes'], ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    echo '</div>';
                }
                ?>

            </div>

            <!-- قیمت شرایط (قابل مذاکره / مقطوع) به‌طور کامل حذف شد -->

        </div>


        <?php $fdSecHtml['price'] = ob_get_clean(); ?>

        <!-- =====================================================
             SPECS
        ====================================================== -->

        <?php
        $fdSecHtml['specs'] = '<div class="specs-grid" id="specsGrid"></div>';
        ?>


        <!-- =====================================================
             AMENITIES
        ====================================================== -->

        <?php
        $fdSecHtml['amenities'] = '<div class="amenities-grid" id="amenitiesGrid"></div>';
        ?>


        <!-- =====================================================
             DESCRIPTION
        ====================================================== -->

        <?php ob_start(); ?>

            <p
                class="description"
                id="propertyDescription"
            >

                <?= htmlspecialchars(
                    $propertyData['description']
                    ?? 'توضیحی برای این ملک ثبت نشده است.',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </p>

        <?php $fdSecHtml['description'] = ob_get_clean(); ?>


        <?php
        // راند ۲۹: چاپ بخش‌ها به ترتیب/عنوان/آیکون/ریسپانسیو تنظیم‌شده در پنل
        foreach (melkinoFdOrderedSections($fdDetCfg) as $__sk => $__sc) {
            if ($__sk === 'contact') {
                continue; // نوار تماس ثابت است و جای خود را دارد (فقط نمایش/ریسپانسیو)
            }
            $__inner = $fdSecHtml[$__sk] ?? '';
            if (trim($__inner) === '') {
                continue;
            }
            $__rc = melkinoFdRclass($__sc);
            $__title = trim((($__sc['icon'] ?? '') !== '' ? ($__sc['icon'] . ' ') : '') . (string)($__sc['title'] ?? ''));
            if (in_array($__sk, ['specs', 'amenities', 'description'], true)) {
                echo '<section class="section-card' . $__rc . '">';
                if (!empty($__sc['show_title']) && $__title !== '') {
                    echo '<h2 class="section-title">' . melkinoFdEsc($__title) . '</h2>';
                }
                echo $__inner . '</section>';
            } else {
                echo '<div class="fd-sec' . $__rc . '">' . $__inner . '</div>';
            }
        }
        if (!empty($fdDet['preview'])) {
            echo '<div class="fd-preview-badge">👁 حالت پیش‌نمایش پنل — تنظیمات هنوز ذخیره نشده</div>';
        }

        $__mkRatingOn = function_exists('melkinoRatingFeatureEnabled') && melkinoRatingFeatureEnabled();
        $__mkVisited = !empty($propertyData['melkino_visited']);
        $__mkReview = trim((string)($propertyData['melkino_review'] ?? ''));
        $__mkStars = (float)($propertyData['melkino_rating'] ?? 0);
        if ($__mkRatingOn && $__mkVisited && ($__mkReview !== '' || $__mkStars > 0)) {
            echo '<section class="section-card mk-melkino-review">';
            echo '<h2 class="section-title">نظر بازدید ملکینو</h2>';
            if ($__mkStars > 0) {
                $n = (int)round(max(1, min(5, $__mkStars)));
                echo '<div class="mk-melkino-rating" style="display:flex;gap:3px;color:#c9a227;margin:0 0 10px;">';
                for ($i = 1; $i <= 5; $i++) {
                    $col = $i <= $n ? '#c9a227' : '#d5d0c4';
                    echo '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="' . $col . '" d="m12 3 2.7 5.8 6.3.8-4.6 4.3 1.2 6.1L12 17l-5.6 3 1.2-6.1L3 9.6l6.3-.8z"/></svg>';
                }
                echo '</div>';
            }
            if ($__mkReview !== '') {
                echo '<p class="description">' . htmlspecialchars($__mkReview, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            echo '</section>';
        }
        ?>

    </div>

<?php endif; ?>

</div>


<!-- =========================================================
     BOTTOM CONTACT BAR
========================================================== -->

<?php if ($propertyData !== null): ?>

    <?php
        $consultantPhoneHref = trim((string)($consultantPhone ?? ''));
        // اگر مشاور تخصصی/مرکزی شماره نداشت → شمارهٔ تماسِ خودِ آگهی
        // (همان شماره‌ای که آگهی با آن ثبت شده) تا دکمهٔ «تماس با مشاور»
        // همیشه کار کند.
        if ($consultantPhoneHref === '' && is_array($propertyData)) {
            $__adPhone = trim((string)($propertyData['phone'] ?? ''));
            $__adPhone = strtr($__adPhone, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);
            $consultantPhoneHref = preg_replace('/[^0-9+]/', '', $__adPhone);
        }
        if ($consultantPhoneHref !== '' && is_array($propertyData) && isset($propertyData['consultant']) && is_array($propertyData['consultant'])) {
            // جاوااسکریپت (callConsultant) هم همان شماره را ببیند
            $propertyData['consultant']['phone'] = $consultantPhoneHref;
        }
        $consultantTelegramHref = trim((string)($consultantTelegram ?? ''));
        $vrShowContact = !empty(($fdDetCfg['sections']['contact'] ?? [])["visible"]);
        $vrIdentity = function_exists('melkinoCurrentIdentity') ? melkinoCurrentIdentity() : [];
        $vrGate = function_exists('melkinoVisitRequesterGate')
            ? melkinoVisitRequesterGate($vrIdentity)
            : ['ok' => false, 'login' => true, 'need_phone' => false, 'message' => 'برای درخواست بازدید ابتدا وارد حساب شوید.'];
        $vrDays = function_exists('melkinoVisitNextDays') ? melkinoVisitNextDays(7) : [];
        // ظرفیت روزانه (تنظیم ادمین): روزهایی که همهٔ بازه‌های محدوددارشان پر است غیرقابل انتخاب می‌شوند
        try {
            if ($vrDays && function_exists('melkinoVisitCapacity') && function_exists('melkinoVisitDayCounts')) {
                $vrCap = melkinoVisitCapacity();
                $vrCnt = melkinoVisitDayCounts(array_map(static fn($d) => (string) $d['date'], $vrDays));
                $vrLimited = array_filter($vrCap, static fn($v) => (int) $v > 0);
                foreach ($vrDays as $__i => $__d) {
                    $__iso = (string) $__d['date'];
                    $vrDays[$__i]['capacity'] = $vrCap;
                    $vrDays[$__i]['slots_state'] = [];
                    $__anyFull = false;
                    foreach (['morning', 'evening'] as $__sk) {
                        $__limit = (int) ($vrCap[$__sk] ?? 0);
                        $__used = (int) ($vrCnt[$__iso][$__sk] ?? 0);
                        $__full = ($__limit > 0 && $__used >= $__limit);
                        $vrDays[$__i]['slots_state'][$__sk] = ['used' => $__used, 'limit' => $__limit, 'full' => $__full];
                        if ($__full) $__anyFull = true;
                    }
                    // «کاملاً پر» = هر بازهٔ محدوددارِ آن روز پر باشد
                    $vrDays[$__i]['full'] = !empty($vrLimited) && $__anyFull
                        && !array_filter($vrDays[$__i]['slots_state'], static fn($st) => ((int) $st['limit'] > 0 && !$st['full']));
                }
            }
        } catch (Throwable $e) {
        }
        $vrAdTitle = trim((string)($propertyData['title'] ?? ''));
    ?>

    <div class="bottom-actions-fixed<?= $vrShowContact ? melkinoFdRclass($fdDetCfg['sections']['contact'] ?? []) : '' ?>">

        <button type="button" class="visit-request-open-btn" id="visitRequestOpenBtn" data-tour="visit-request" onclick="melkinoOpenVisitRequest(); return false;">درخواست بازدید</button>

        <?php if ($vrShowContact): ?>
        <div class="consultant-bar">

            <div class="consultant-info">

                <div class="consultant-avatar">
                    👤
                </div>

                <div class="consultant-text">

                    <strong>
                        <?= htmlspecialchars(
                            $consultantName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>

                    <span>مشاور ملکینو</span>

                </div>

            </div>


            <div class="consultant-actions">

                <?php if ($consultantTelegramHref !== ''): ?>
                    <a
                        href="<?= htmlspecialchars($consultantTelegramHref, ENT_QUOTES, 'UTF-8') ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="consultant-btn telegram"
                    >
                        <span>💬</span>
                        پیام به مشاور
                    </a>
                <?php else: ?>
                    <a
                        href="javascript:void(0)"
                        class="consultant-btn telegram disabled"
                        onclick="showToast('لینک تلگرام مشاور تنظیم نشده است.')"
                    >
                        <span>💬</span>
                        پیام به مشاور
                    </a>
                <?php endif; ?>


                <?php if ($consultantPhoneHref !== ''): ?>
                    <a
                        href="tel:<?= htmlspecialchars($consultantPhoneHref, ENT_QUOTES, 'UTF-8') ?>"
                        class="consultant-btn call"
                        onclick="melkinoTryCall(this, event)"
                    >
                        <span>📞</span>
                        تماس با مشاور
                    </a>
                <?php else: ?>
                    <a
                        href="javascript:void(0)"
                        class="consultant-btn call disabled"
                        onclick="showToast('شماره تماس مشاور تنظیم نشده است.')"
                    >
                        <span>📞</span>
                        تماس با مشاور
                    </a>
                <?php endif; ?>

            </div>

        </div>
        <?php endif; ?>

    </div>

    <div class="vr-modal-overlay" id="visitRequestModal" onclick="if(event.target===this){melkinoCloseVisitRequest();}">
        <div class="vr-modal" role="dialog" aria-modal="true" aria-labelledby="visitRequestTitle">
            <h3 id="visitRequestTitle">درخواست بازدید</h3>
            <p style="font-size:12px;color:var(--text-secondary);margin:0 0 10px;">از فردا تا یک هفته بعد، روز و بازه زمانی را انتخاب کنید. روزهای تعطیل قرمز و غیرقابل انتخاب هستند.</p>
            <div class="vr-days" id="visitRequestDays"></div>
            <div class="vr-slots">
                <label><input type="radio" name="vr_slot" value="morning" checked> صبح</label>
                <label><input type="radio" name="vr_slot" value="evening"> عصر</label>
            </div>
            <p class="vr-note">مشاورین ملکینو برای تاریخ و زمان انتخابی شما با مالک هماهنگ و به صورت تلفنی به شما اطلاع می‌دهند.</p>
            <label class="vr-alt-label" for="visitRequestAlt">در صورت عدم امکان بازدید در تاریخ و زمان درخواستی شما تاریخ و زمان جایگزین را بنویسید</label>
            <textarea class="vr-alt" id="visitRequestAlt" rows="3" placeholder="مثلاً یکشنبه ۵ مهر عصر"></textarea>
            <div class="vr-form-msg" id="visitRequestMsg"></div>
            <button type="button" class="vr-submit" id="visitRequestSubmit" onclick="melkinoSubmitVisitRequest(); return false;">ثبت درخواست بازدید</button>
            <button type="button" class="vr-cancel" id="visitRequestCancel" onclick="melkinoCloseVisitRequest(); return false;">انصراف</button>
        </div>
    </div>
    <script type="application/json" id="melkinoVisitCfg"><?php
        $vrClosed = function_exists('melkinoVisitClosedSettings') ? melkinoVisitClosedSettings() : ['weekdays' => [5], 'dates' => []];
        echo json_encode([
            'adId' => (string)$propertyId,
            'adTitle' => $vrAdTitle,
            'days' => $vrDays,
            'closed_weekdays' => $vrClosed['weekdays'] ?? [5],
            'closed_dates' => $vrClosed['dates'] ?? [],
            'can_request' => !empty($vrGate['ok']),
            'need_login' => !empty($vrGate['login']),
            'need_phone' => !empty($vrGate['need_phone']),
            'gate_message' => (string) ($vrGate['message'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    ?></script>
    <script>
    window.MELKINO_VISIT = { adId: '', adTitle: '', days: [] };
    window.MELKINO_VISIT_DATE = '';
    function melkinoVisitFaDigits(s) {
        return String(s).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.charAt(d); });
    }
    function melkinoGregorianToJalali(gy, gm, gd) {
        var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var gy2 = (gm > 2) ? (gy + 1) : gy;
        var days = 355666 + (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100)
            + Math.floor((gy2 + 399) / 400) + gd + gdm[gm - 1];
        var jy = -1595 + (33 * Math.floor(days / 12053));
        days %= 12053;
        jy += 4 * Math.floor(days / 1461);
        days %= 1461;
        if (days > 365) {
            jy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var jm, jd;
        if (days < 186) {
            jm = 1 + Math.floor(days / 31);
            jd = 1 + (days % 31);
        } else {
            jm = 7 + Math.floor((days - 186) / 30);
            jd = 1 + ((days - 186) % 30);
        }
        return [jy, jm, jd];
    }
    function melkinoBuildVisitDays() {
        var weekdays = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
        var months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
        var closedW = window.MELKINO_VISIT.closed_weekdays || [5];
        var closedD = window.MELKINO_VISIT.closed_dates || [];
        var now = new Date();
        var days = [];
        for (var i = 1; i <= 7; i++) {
            var d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i);
            var gy = d.getFullYear();
            var gm = d.getMonth() + 1;
            var gd = d.getDate();
            var j = melkinoGregorianToJalali(gy, gm, gd);
            var iso = gy + '-' + (gm < 10 ? '0' : '') + gm + '-' + (gd < 10 ? '0' : '') + gd;
            var isClosed = closedW.indexOf(d.getDay()) !== -1 || closedD.indexOf(iso) !== -1;
            days.push({
                date: iso,
                label: weekdays[d.getDay()] + ' ' + melkinoVisitFaDigits(j[2]) + ' ' + months[j[1] - 1],
                closed: isClosed,
                selectable: !isClosed
            });
        }
        return days;
    }
    function melkinoVisitMsg(text, ok) {
        var el = document.getElementById('visitRequestMsg');
        if (!el) {
            if (text) alert(text);
            return;
        }
        el.className = 'vr-form-msg' + (text ? (ok ? ' is-ok' : ' is-err') : '');
        el.textContent = text || '';
    }
    function melkinoLoadVisitCfg() {
        try {
            var el = document.getElementById('melkinoVisitCfg');
            var parsed = el ? JSON.parse(el.textContent || el.innerHTML || '{}') : {};
            if (parsed && typeof parsed === 'object') {
                window.MELKINO_VISIT.adId = parsed.adId || window.MELKINO_VISIT.adId;
                window.MELKINO_VISIT.adTitle = parsed.adTitle || '';
                window.MELKINO_VISIT.closed_weekdays = parsed.closed_weekdays || [5];
                window.MELKINO_VISIT.closed_dates = parsed.closed_dates || [];
                window.MELKINO_VISIT.can_request = !!parsed.can_request;
                window.MELKINO_VISIT.need_login = !!parsed.need_login;
                window.MELKINO_VISIT.need_phone = !!parsed.need_phone;
                window.MELKINO_VISIT.gate_message = parsed.gate_message || '';
                if (parsed.days && parsed.days.length) window.MELKINO_VISIT.days = parsed.days;
            }
        } catch (e) {}
        var q = new URLSearchParams(window.location.search || '');
        if (!window.MELKINO_VISIT.adId && q.get('id')) window.MELKINO_VISIT.adId = q.get('id');
        if (!window.MELKINO_VISIT.days || !window.MELKINO_VISIT.days.length) {
            window.MELKINO_VISIT.days = melkinoBuildVisitDays();
        }
    }
    function melkinoRenderVisitDays() {
        var box = document.getElementById('visitRequestDays');
        if (!box) return;
        melkinoLoadVisitCfg();
        var days = window.MELKINO_VISIT.days || [];
        var html = '';
        var first = '';
        for (var j = 0; j < days.length; j++) {
            var closed = days[j].selectable === false || !!days[j].closed || !!days[j].friday || !!days[j].full;
            var on = !closed && !first;
            if (on) first = String(days[j].date || '');
            html += '<button type="button" class="vr-day' + (closed ? ' is-closed' : '') + (on ? ' is-on' : '') + '" data-date="' + String(days[j].date || '') + '" data-closed="' + (closed ? '1' : '0') + '" data-full="' + (days[j].full ? '1' : '0') + '" onclick="melkinoPickVisitDay(this)">' + String(days[j].label || days[j].date) + (days[j].full ? ' (پر)' : '') + '</button>';
        }
        box.innerHTML = html;
        window.MELKINO_VISIT_DATE = first;
        melkinoVisitMsg('', true);
    }
    function melkinoPickVisitDay(btn) {
        if (!btn) return false;
        if (btn.getAttribute('data-full') === '1') {
            var lbl = btn.textContent || '';
            melkinoVisitMsg('به علت کامل بودن برنامه بازدیدها برای «' + lbl.replace(' (پر)', '') + '» امکان ثبت بازدید نیست. لطفاً روز دیگری را انتخاب کنید.');
            return false;
        }
        if (btn.getAttribute('data-closed') === '1' || btn.classList.contains('is-closed') || btn.classList.contains('is-fri')) {
            melkinoVisitMsg('این روز تعطیل است و برای بازدید قابل انتخاب نیست.');
            return false;
        }
        var box = document.getElementById('visitRequestDays');
        if (!box) return false;
        var list = box.querySelectorAll('.vr-day');
        for (var i = 0; i < list.length; i++) list[i].classList.remove('is-on');
        btn.classList.add('is-on');
        window.MELKINO_VISIT_DATE = btn.getAttribute('data-date') || '';
        melkinoVisitMsg('', true);
        return false;
    }
    function melkinoVisitFaToEnDigits(s) {
        return String(s || '').replace(/[۰-۹]/g, function (d) {
            return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
        }).replace(/[٠-٩]/g, function (d) {
            return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d));
        });
    }
    function melkinoVisitClientGate() {
        melkinoLoadVisitCfg();
        var cfg = window.MELKINO_VISIT || {};
        if (cfg.can_request === true) {
            return { ok: true, msg: '', login: false, phone: false };
        }
        if (cfg.can_request === false || cfg.need_login || cfg.need_phone) {
            var msg = cfg.gate_message || (cfg.need_phone
                ? 'برای درخواست بازدید ابتدا شماره موبایل خود را در پروفایل ثبت کنید.'
                : 'برای درخواست بازدید ابتدا وارد حساب شوید.');
            try { alert(msg); } catch (e) {}
            return { ok: false, msg: msg, login: !!cfg.need_login, phone: !!cfg.need_phone };
        }
        var p = window.MELKINO_PROFILE || {};
        var logged = !!p.logged_in || !!(p.telegram_id);
        var raw = melkinoVisitFaToEnDigits(p.phone || '').replace(/\D+/g, '');
        if (raw.indexOf('98') === 0 && raw.length >= 12) raw = raw.replace(/^98/, '');
        if (raw.length === 10 && raw.charAt(0) === '9') raw = '0' + raw;
        var phoneOk = /^09\d{9}$/.test(raw);
        if (!logged) {
            var m1 = 'برای درخواست بازدید ابتدا وارد حساب شوید.';
            try { alert(m1); } catch (e1) {}
            return { ok: false, msg: m1, login: true, phone: false };
        }
        if (!phoneOk) {
            var m2 = 'برای درخواست بازدید ابتدا شماره موبایل خود را در پروفایل ثبت کنید.';
            try { alert(m2); } catch (e2) {}
            return { ok: false, msg: m2, login: false, phone: true };
        }
        return { ok: true, msg: '', login: false, phone: false };
    }
    function melkinoOpenVisitRequest() {
        var gate = melkinoVisitClientGate();
        if (!gate.ok) {
            return false;
        }
        var overlay = document.getElementById('visitRequestModal');
        if (!overlay) {
            alert('فرم بازدید در دسترس نیست.');
            return false;
        }
        melkinoRenderVisitDays();
        overlay.classList.add('is-open');
        overlay.style.display = 'flex';
        overlay.style.zIndex = '10050';
        return false;
    }
    function melkinoCloseVisitRequest() {
        var overlay = document.getElementById('visitRequestModal');
        if (!overlay) return false;
        overlay.classList.remove('is-open');
        overlay.style.display = 'none';
        return false;
    }
    function melkinoSubmitVisitRequest() {
        var gate = melkinoVisitClientGate();
        if (!gate.ok) {
            melkinoVisitMsg(gate.msg);
            return false;
        }
        melkinoLoadVisitCfg();
        var cfg = window.MELKINO_VISIT || {};
        var selectedDate = window.MELKINO_VISIT_DATE || '';
        var picked = document.querySelector('#visitRequestDays .vr-day.is-on');
        if (picked && picked.getAttribute('data-closed') !== '1') {
            selectedDate = picked.getAttribute('data-date') || selectedDate;
        }
        if (!selectedDate) {
            melkinoVisitMsg('روز بازدید را انتخاب کنید.');
            return false;
        }
        if (picked && picked.getAttribute('data-closed') === '1') {
            melkinoVisitMsg('این روز تعطیل است و برای بازدید قابل انتخاب نیست.');
            return false;
        }
        var slotEl = document.querySelector('#visitRequestModal input[name="vr_slot"]:checked');
        var slot = slotEl ? slotEl.value : 'morning';
        var submitBtn = document.getElementById('visitRequestSubmit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال ثبت…';
        }
        melkinoVisitMsg('');
        var fd = new FormData();
        fd.append('ad_id', cfg.adId || '');
        fd.append('ad_title', cfg.adTitle || '');
        fd.append('preferred_date', selectedDate);
        fd.append('time_slot', slot);
        var altEl = document.getElementById('visitRequestAlt');
        fd.append('alternative_datetime', altEl ? String(altEl.value || '').trim() : '');
        if (window.MELKINO_CSRF) fd.append('csrf_token', window.MELKINO_CSRF);
        var headers = {};
        if (window.MELKINO_CSRF) headers['X-CSRF-Token'] = window.MELKINO_CSRF;
        fetch('visit-request-api.php?action=create', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: headers
        }).then(function (r) {
            return r.text().then(function (t) {
                var data = null;
                try { data = JSON.parse(t); } catch (e) { data = null; }
                return { ok: r.ok, status: r.status, data: data, raw: t };
            });
        }).then(function (res) {
            var data = res.data || {};
            var msg = data.message || (res.ok ? 'ثبت شد.' : ('خطا در ثبت درخواست' + (res.status ? ' (' + res.status + ')' : '')));
            if (data.login) {
                melkinoVisitMsg(data.message || 'برای درخواست بازدید ابتدا وارد حساب شوید.');
                try { alert(data.message || 'برای درخواست بازدید ابتدا وارد حساب شوید.'); } catch (e3) {}
                return;
            }
            if (data.need_phone) {
                melkinoVisitMsg(data.message || 'برای درخواست بازدید ابتدا شماره موبایل خود را در پروفایل ثبت کنید.');
                try { alert(data.message || 'برای درخواست بازدید ابتدا شماره موبایل خود را در پروفایل ثبت کنید.'); } catch (e4) {}
                return;
            }
            if (data.success) {
                melkinoVisitMsg(msg, true);
                setTimeout(function () { melkinoCloseVisitRequest(); }, 1400);
                if (typeof showToast === 'function') showToast(msg);
            } else {
                melkinoVisitMsg(msg);
            }
        }).catch(function () {
            melkinoVisitMsg('ثبت درخواست انجام نشد. اتصال را بررسی کنید.');
        }).then(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'ثبت درخواست بازدید';
            }
        });
        return false;
    }
    </script>

    <script>
    /* ==============================================================
       راند ۲۳: ارتفاع واقعی ناوبری پایین (.bottom-nav) را اندازه
       می‌گیرد و در متغیر --pd-bottom-nav-h می‌گذارد تا نوار «تماس
       با مشاور» دقیقاً بالای آن بماند؛ حتی روی گوشی‌هایی که
       safe-area (نوار ژست) ارتفاع فوتر را زیاد می‌کند.
    ============================================================== */
    (function () {
        function pdSyncNavHeight() {
            var nav = document.querySelector('.bottom-nav');
            if (!nav) return;
            var h = Math.round(nav.getBoundingClientRect().height);
            if (h > 10) {
                document.documentElement.style.setProperty('--pd-bottom-nav-h', h + 'px');
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', pdSyncNavHeight);
        } else {
            pdSyncNavHeight();
        }
        window.addEventListener('load', pdSyncNavHeight);
        window.addEventListener('resize', pdSyncNavHeight);
        setTimeout(pdSyncNavHeight, 500);
        setTimeout(pdSyncNavHeight, 1800);
    })();
    </script>


<?php endif; ?>


<script>

/* =========================================================
   PROPERTY DATA
========================================================= */

const propertyData = <?= melkinoJsJson($propertyData) ?>;


/* =========================================================
   GALLERY STATE
========================================================= */

let currentImageIndex = 0;

let galleryImages = [];

let galleryStartX = null;

let galleryStartY = null;


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value) {

    return String(
        value ?? ''
    ).replace(
        /[&<>"']/g,
        function (ch) {

            return {

                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'

            }[ch];

        }
    );

}


/* =========================================================
   NORMALIZE IMAGE PATH
========================================================= */

function normalizeImagePath(path) {

    if (!path) {
        return '';
    }


    path =
        String(path).trim();


    if (!path) {
        return '';
    }


    /*
     * URL کامل
     */

    if (
        /^https?:\/\//i.test(path)
    ) {

        return path;

    }


    /*
     * Data URI
     */

    if (
        path.indexOf(
            'data:image/'
        ) === 0
    ) {

        return path;

    }


    return path;

}


/* =========================================================
   INIT GALLERY
========================================================= */

function initGallery() {

    const slide =
        document.getElementById(
            'gallerySlide'
        );


    const thumbs =
        document.getElementById(
            'galleryThumbs'
        );


    if (!slide || !thumbs) {
        return;
    }


    slide.innerHTML = '';

    thumbs.innerHTML = '';


    /*
     * تصاویر نرمال شده
     */

    galleryImages =
        Array.isArray(
            propertyData?.images
        )
            ? propertyData.images
                .map(
                    normalizeImagePath
                )
                .filter(Boolean)
            : [];


    /*
     * حذف تکراری‌ها
     */

    galleryImages =
        galleryImages.filter(
            function (value, index, self) {

                return (
                    self.indexOf(value) ===
                    index
                );

            }
        );


    currentImageIndex = 0;


    /*
     * بدون تصویر
     */

    if (
        galleryImages.length === 0
    ) {

        slide.innerHTML = `

            <div class="no-image">

                <div
                    style="
                        display:flex;
                        flex-direction:column;
                        align-items:center;
                        gap:10px;
                        color:rgba(255,255,255,.55);
                    "
                >

                    <span
                        style="
                            font-size:45px;
                        "
                    >
                        📷
                    </span>

                    <span>
                        تصویر برای این ملک منتشر نشده است
                    </span>

                </div>

            </div>

        `;


        thumbs.style.display =
            'none';


        updateGalleryCounter();


        return;
    }


    /*
     * ساخت تصاویر
     */

    galleryImages.forEach(
        function (src, index) {

            const wrapper =
                document.createElement(
                    'div'
                );


            wrapper.style.cssText =
                `
                    flex:0 0 100%;
                    width:100%;
                    height:100%;
                    position:relative;
                    background:#111827;
                `;


            const img =
                document.createElement(
                    'img'
                );


            img.src =
                src;


            img.alt =
                `تصویر ملک ${index + 1}`;


            img.loading =
                index === 0
                    ? 'eager'
                    : 'lazy';


            img.decoding =
                'async';


            img.draggable =
                false;


            img.onerror =
                function () {

                    wrapper.innerHTML = `

                        <div
                            class="no-image"
                            style="
                                width:100%;
                                height:100%;
                            "
                        >
                            📷
                            تصویر در دسترس نیست
                        </div>

                    `;

                };


            wrapper.appendChild(
                img
            );

            (function () {
                if (typeof window.mkPhotoWmMount === 'function') {
                    window.mkPhotoWmMount(wrapper, 'details');
                    return;
                }
                var cfg = window.MELKINO_PHOTO_WM || {};
                var logo = String(cfg.logo_url || '').trim();
                if (cfg.enabled === false || cfg.on_details === false || !logo) return;
                var op = Number(cfg.opacity);
                if (!isFinite(op)) op = 0.32;
                if (op > 1) op = op / 100;
                var sz = Number(cfg.size_details != null ? cfg.size_details : cfg.size);
                if (!isFinite(sz)) sz = 30;
                var wm = document.createElement('div');
                wm.className = 'mk-photo-wm';
                wm.setAttribute('aria-hidden', 'true');
                wm.style.setProperty('--mk-wm-opacity', String(Math.max(0.04, Math.min(0.92, op))));
                wm.style.setProperty('--mk-wm-size', Math.max(8, Math.min(85, sz)) + '%');
                wm.style.setProperty('--mk-wm-top', '50%');
                wm.style.setProperty('--mk-wm-left', '50%');
                wm.style.setProperty('--mk-wm-tx', '-50%');
                wm.style.setProperty('--mk-wm-ty', '-50%');
                var wimg = document.createElement('img');
                wimg.src = logo;
                wimg.alt = '';
                wimg.draggable = false;
                wm.appendChild(wimg);
                wrapper.appendChild(wm);
            })();


            slide.appendChild(
                wrapper
            );


            /*
             * thumbnail
             */

            const thumb =
                document.createElement(
                    'button'
                );


            thumb.type =
                'button';


            thumb.className =
                'gallery-thumb'
                +
                (
                    index === 0
                        ? ' active'
                        : ''
                );


            thumb.setAttribute(
                'aria-label',
                `تصویر ${index + 1}`
            );


            const thumbImg =
                document.createElement(
                    'img'
                );


            thumbImg.src =
                src;


            thumbImg.alt =
                `تصویر کوچک ${index + 1}`;


            thumbImg.loading =
                'lazy';


            thumb.appendChild(
                thumbImg
            );


            thumb.addEventListener(
                'click',
                function () {

                    currentImageIndex =
                        index;


                    updateGalleryPosition();

                    updateGalleryCounter();

                    centerActiveThumbnail();

                }
            );


            thumbs.appendChild(
                thumb
            );

        }
    );


    thumbs.style.display =
        galleryImages.length > 1
            ? 'flex'
            : 'none';


    updateGalleryPosition();

    updateGalleryCounter();

}


/* =========================================================
   UPDATE GALLERY POSITION
========================================================= */

function updateGalleryPosition() {

    const slide =
        document.getElementById(
            'gallerySlide'
        );


    if (!slide) {
        return;
    }


    slide.style.transform =
        `translateX(-${currentImageIndex * 100}%)`;


    document
        .querySelectorAll(
            '.gallery-thumb'
        )
        .forEach(
            function (el, index) {

                el.classList.toggle(
                    'active',
                    index === currentImageIndex
                );

            }
        );

}


/* =========================================================
   CENTER ACTIVE THUMBNAIL
========================================================= */

function centerActiveThumbnail() {

    const active =
        document.querySelector(
            '.gallery-thumb.active'
        );


    if (!active) {
        return;
    }


    active.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest',
        inline: 'center'
    });

}


/* =========================================================
   COUNTER
========================================================= */

function updateGalleryCounter() {

    const counter =
        document.getElementById(
            'galleryCounter'
        );


    if (!counter) {
        return;
    }


    const total =
        galleryImages.length ||
        1;


    const current =
        galleryImages.length
            ? currentImageIndex + 1
            : 1;


    counter.innerText =
        `📸 ${current} / ${total}`;

}


/* =========================================================
   CHANGE IMAGE
========================================================= */

function changeImage(direction) {

    if (
        galleryImages.length <= 1
    ) {
        return;
    }


    currentImageIndex =
        (
            currentImageIndex +
            direction +
            galleryImages.length
        ) %
        galleryImages.length;


    updateGalleryPosition();

    updateGalleryCounter();

    centerActiveThumbnail();

}


/* =========================================================
   GALLERY SWIPE
========================================================= */

function initGallerySwipe() {

    const gallery =
        document.getElementById(
            'galleryContainer'
        );


    if (!gallery) {
        return;
    }


    gallery.addEventListener(
        'touchstart',
        function (event) {

            if (
                !event.touches ||
                event.touches.length !== 1
            ) {
                return;
            }


            galleryStartX =
                event.touches[0].clientX;


            galleryStartY =
                event.touches[0].clientY;

        },
        {
            passive: true
        }
    );


    gallery.addEventListener(
        'touchend',
        function (event) {

            if (
                galleryStartX === null ||
                galleryStartY === null
            ) {
                return;
            }


            const endX =
                event.changedTouches[0].clientX;


            const endY =
                event.changedTouches[0].clientY;


            const diffX =
                endX -
                galleryStartX;


            const diffY =
                endY -
                galleryStartY;


            galleryStartX = null;

            galleryStartY = null;


            if (
                Math.abs(diffX) < 45 ||
                Math.abs(diffX) <
                Math.abs(diffY)
            ) {
                return;
            }


            /*
             * در گالری:
             * swipe left = next
             * swipe right = previous
             */

            if (
                diffX < 0
            ) {

                changeImage(1);

            } else {

                changeImage(-1);

            }

        },
        {
            passive: true
        }
    );

}


/* =========================================================
   SPECS
========================================================= */

function renderSpecs() {

    const container =
        document.getElementById(
            'specsGrid'
        );


    if (!container) {
        return;
    }


    const specs =
        propertyData?.specs &&
        typeof propertyData.specs === 'object'
            ? propertyData.specs
            : {};


    const specsMeta =
        (propertyData && propertyData.specsMeta) || {};

    const entries =
        Object.entries(
            specs
        ).filter(
            function ([key, value]) {

                return (
                    value !== '' &&
                    value !== null &&
                    value !== undefined &&
                    value !== '0' &&
                    value !== 0
                );

            }
        );


    if (
        entries.length === 0
    ) {

        container.innerHTML = `

            <div
                class="empty-box"
                style="
                    grid-column:1/-1;
                "
            >
                مشخصات اختصاصی برای این ملک ثبت نشده است.
            </div>

        `;


        return;
    }


    container.innerHTML =
        entries
            .map(
                function ([key, value]) {
                    var icon = (typeof window.mkFieldIcon === 'function')
                        ? (window.mkFieldIcon('', key, '') || '')
                        : '';

                    return `

                        <div class="specs-item${specsMeta[key] || ''}">

                            <span class="specs-label">

                                ${icon}${escapeHtml(key)}

                            </span>


                            <span class="specs-value">

                                ${escapeHtml(value)}

                            </span>

                        </div>

                    `;

                }
            )
            .join('');

}


/* =========================================================
   AMENITIES
========================================================= */

function renderAmenities() {
    const container = document.getElementById('amenitiesGrid');
    if (!container) return;

    const amenities = Array.isArray(propertyData?.amenities) ? propertyData.amenities : [];
    const requiredThree = ['آسانسور', 'پارکینگ', 'انباری'];
    const propertyType = propertyData?.property_type || '';

    let items = [];

    if (propertyType === 'آپارتمان') {
        // Apartment: always show the three core amenities
        requiredThree.forEach(function (name) {
            const exists = amenities.indexOf(name) !== -1;
            items.push({
                name: name,
                exists: exists,
                forceCross: false
            });
        });

        // Add any other amenities (not in the core three)
        amenities.forEach(function (name) {
            if (requiredThree.indexOf(name) === -1) {
                items.push({
                    name: name,
                    exists: true,
                    forceCross: false
                });
            }
        });
    } else {
        // Non-apartment: show core amenities only if they exist, but mark them with a cross
        amenities.forEach(function (name) {
            const forceCross = requiredThree.indexOf(name) !== -1;
            items.push({
                name: name,
                exists: true,
                forceCross: forceCross
            });
        });
    }

    if (items.length === 0) {
        container.innerHTML = `
            <div class="empty-box" style="grid-column:1/-1;">
                امکاناتی برای این ملک ثبت نشده است.
            </div>
        `;
        return;
    }

    container.innerHTML = items.map(function (item) {
        // Show ✓ only if exists and not forced to cross
        const checkMark = (item.exists && !item.forceCross) ? '✓' : '✕';
        const extraClass = (item.exists && !item.forceCross) ? '' : ' cross';
        var aicon = (typeof window.mkFieldIcon === 'function')
            ? (window.mkFieldIcon('', item.name, '') || '')
            : '';
        return `
            <div class="amenity-item">
                <span class="amenity-check${extraClass}">
                    ${checkMark}
                </span>
                <span class="amenity-name">
                    ${aicon}${escapeHtml(item.name)}
                </span>
            </div>
        `;
    }).join('');
}


/* =========================================================
   FAVORITES — DATABASE
========================================================= */

function getFavoriteTelegramId() {
    try {
        return String(localStorage.getItem('melkino_telegram_id') || sessionStorage.getItem('reg_telegram_id') || '');
    } catch (e) { return ''; }
}

async function requestFavorite(action, propertyId) {
    const telegramId = getFavoriteTelegramId();
    const response = await fetch(
        'favorites.php?action=' + encodeURIComponent(action) +
        '&telegram_id=' + encodeURIComponent(telegramId),
        {
            method: action === 'toggle' ? 'POST' : 'GET',
            headers: action === 'toggle' ? {'Content-Type':'application/x-www-form-urlencoded'} : {},
            body: action === 'toggle' ? 'ad_id=' + encodeURIComponent(propertyId) : undefined,
            cache: 'no-store'
        }
    );
    const data = await response.json();
    if (!data.success) throw new Error(data.message || 'عملیات علاقه‌مندی انجام نشد.');
    return data;
}

async function toggleFavorite() {
    if (!propertyData) return;
    const button = document.getElementById('favoriteBtn');
    if (!button) return;
    const propertyId = String(propertyData.id || '').trim();
    if (!propertyId) { showToast('کد ملک معتبر نیست.'); return; }

    button.disabled = true;
    try {
        const data = await requestFavorite('toggle', propertyId);
        button.classList.toggle('active', !!data.favorited);
        button.setAttribute('aria-label', data.favorited ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی');
        button.title = data.favorited ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی';
        showToast(data.favorited ? 'ملک به علاقه‌مندی‌ها اضافه شد ❤️' : 'ملک از علاقه‌مندی‌ها حذف شد.');
        window.dispatchEvent(new CustomEvent('melkino:favorites-changed', {detail:{propertyId, favorited:!!data.favorited}}));
    } catch (error) {
        console.error('Favorite error:', error);
        showToast(error.message || 'خطا در ذخیره علاقه‌مندی.');
    } finally {
        button.disabled = false;
    }
}

async function checkFavoriteStatus() {
    if (!propertyData) return;
    const button = document.getElementById('favoriteBtn');
    if (!button) return;
    const propertyId = String(propertyData.id || '').trim();
    if (!propertyId) return;
    try {
        const data = await requestFavorite('list', '');
        const exists = Array.isArray(data.favorites) && data.favorites.some(function(ad){ return String(ad.id) === propertyId; });
        button.classList.toggle('active', exists);
        button.setAttribute('aria-label', exists ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی');
        button.title = exists ? 'حذف از علاقه‌مندی' : 'افزودن به علاقه‌مندی';
    } catch (error) {
        console.warn('Could not load favorite state:', error);
    }
}

/* =========================================================
   TOAST
========================================================= */

function showToast(message) {

    const oldToast =
        document.getElementById(
            'melkinoDetailToast'
        );


    if (oldToast) {
        oldToast.remove();
    }


    const toast =
        document.createElement(
            'div'
        );


    toast.id =
        'melkinoDetailToast';


    toast.textContent =
        message;


    toast.style.cssText = `

        position:fixed;

        left:50%;

        bottom:
            calc(
                112px +
                env(safe-area-inset-bottom)
            );

        transform:
            translateX(-50%);

        z-index:3000;

        max-width:
            calc(100vw - 36px);

        padding:
            11px 17px;

        border-radius:
            999px;

        background:
            rgba(3,29,29,.95);

        color:#fff;

        border:
            1px solid
            rgba(212,175,55,.22);

        box-shadow:
            0 12px 35px
            rgba(0,0,0,.25);

        backdrop-filter:
            blur(12px);

        font-family:
            "Vazirmatn",
            sans-serif;

        font-size:
            11px;

        font-weight:
            700;

        white-space:
            nowrap;

    `;


    document.body.appendChild(
        toast
    );


    setTimeout(
        function () {

            toast.style.opacity =
                '0';

            toast.style.transition =
                'opacity .2s ease';


            setTimeout(
                function () {

                    toast.remove();

                },
                220
            );

        },
        1800
    );

}


/* =========================================================
   CALL CONSULTANT
========================================================= */

/* [TEL-FALLBACK] راند ۲۲: وب‌ویو داخلی بله/تلگرام گاهی لینک tel: را
   بی‌صدا مسدود می‌کند (هیچ اتفاقی نمی‌افتد). ۱.۵ ثانیه بعد از تپ، اگر
   صفحه هنوز جلوی چشم باشد یعنی شماره‌گیر باز نشده → شماره کپی و راهنما. */
function melkinoCopyText(txt) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).catch(function () { melkinoCopyTextLegacy(txt); });
        return;
    }
    melkinoCopyTextLegacy(txt);
}

function melkinoCopyTextLegacy(txt) {
    try {
        var ta = document.createElement('textarea');
        ta.value = txt;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    } catch (e) {}
}

function melkinoTryCall(el, ev) {
    var href = String((el && el.getAttribute && el.getAttribute('href')) || '');
    if (href.indexOf('tel:') !== 0) return; // شاخهٔ غیرفعال toast خودش را دارد
    if (ev && ev.preventDefault) ev.preventDefault();
    var num = href.slice(4);
    var dialerOpened = false;
    var onVis = function () { dialerOpened = true; document.removeEventListener('visibilitychange', onVis); };
    document.addEventListener('visibilitychange', onVis);
    setTimeout(function () {
        document.removeEventListener('visibilitychange', onVis);
        if (!dialerOpened && !document.hidden) {
            melkinoCopyText(num);
            showToast('شماره‌گیر باز نشد؛ شماره «' + num + '» کپی شد — آن را در شماره‌گیر گوشی بزنید.');
        }
    }, 1500);
    window.location.href = href;
}

function callConsultant() {

    const phone =
        String(
            propertyData?.consultant?.phone ||
            ''
        ).trim();

    if (phone) {
        window.location.href = 'tel:' + phone;
        return;
    }

    showToast('شماره تماس مشاور تنظیم نشده است.');
}


/* =========================================================
   CHAT CONSULTANT
========================================================= */

function chatWithConsultant() {

    const telegram =
        String(
            propertyData?.consultant?.telegram ||
            ''
        ).trim();

    if (telegram) {
        window.open(telegram, '_blank', 'noopener,noreferrer');
        return;
    }

    showToast('لینک تلگرام مشاور تنظیم نشده است.');
}


/* =========================================================
   ESC / KEYBOARD GALLERY
========================================================= */

document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key === 'ArrowLeft'
        ) {

            changeImage(1);

        }


        if (
            event.key === 'ArrowRight'
        ) {

            changeImage(-1);

        }

    }
);


/* =========================================================
   INITIALIZE
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        if (!propertyData) {
            return;
        }


        initGallery();

        initGallerySwipe();

        renderSpecs();

        renderAmenities();

        checkFavoriteStatus();


        if (
            typeof updateBadge ===
            'function'
        ) {

            updateBadge();

        }

    }
);

</script>


<script>
/* همگام‌سازی برچسب دکمهٔ مقایسه با وضعیتش:
   ویجت ⚖️ فقط کلاس in-compare را روی دکمه کم/زیاد می‌کند؛ اینجا
   متن و aria-pressed را با آن هماهنگ نگه می‌داریم. */
window.addEventListener('melkino:compare-changed', function () {
    var b = document.getElementById('compareBtn');
    var l = document.getElementById('compareBtnLabel');
    if (!b || !l) { return; }
    var inC = b.classList.contains('in-compare');
    b.setAttribute('aria-pressed', inC ? 'true' : 'false');
    l.textContent = inC ? 'در مقایسه' : 'افزودن به مقایسه';
});
</script>


<section id="mkSimilarMap" style="margin:16px 12px 80px;padding:14px;border:1px solid var(--border);border-radius:16px;background:var(--surface);">
    <h3 style="margin:0 0 10px;font-size:15px;">املاک مشابه در محدوده</h3>
    <div id="mkSimilarList" style="font-size:13px;color:var(--text-secondary);">در حال بررسی…</div>
</section>
<script>
(function () {
    var id = <?= json_encode((string)($propertyId ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    var box = document.getElementById('mkSimilarList');
    if (!id || !box) return;
    fetch('map-api.php?action=similar&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var items = (d && d.items) || [];
            if (!items.length) { box.textContent = 'ملک مشابهی در محدوده پیدا نشد.'; return; }
            box.innerHTML = items.map(function (it) {
                return '<a href="property-details.php?id=' + encodeURIComponent(it.id) + '" style="display:block;padding:8px 0;color:inherit;text-decoration:none;border-bottom:1px solid var(--border)">' +
                    (it.title || '') + ' · ' + (it.transaction_type || '') + (it.price ? ' · ' + it.price : '') + '</a>';
            }).join('');
        })
        .catch(function () { box.textContent = 'بارگذاری نشد.'; });
})();
</script>
<?php
require_once __DIR__ . '/footer.php';
?>
