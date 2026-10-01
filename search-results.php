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
// =====================================================
// DATABASE DATA SOURCE
// =====================================================
require_once __DIR__ . '/config.php';
if (is_file(__DIR__ . '/jalali-lib.php')) {
    require_once __DIR__ . '/jalali-lib.php';
}

$dbAds = [];
if ($pdo instanceof PDO) {
    try {
        $stmt = $pdo->query("
            SELECT
                a.id, a.title, a.status, a.transaction_type, a.property_type,
                a.gender, a.last_name, a.phone, a.location, a.address,
                a.location_received, a.latitude, a.longitude, a.area, a.land_area,
                a.built_area, a.rooms, a.floor, a.year, a.price_sell,
                a.price_condition, a.deposit, a.rent_monthly, a.full_rent_enabled,
                a.full_rent, a.total_price, a.down_payment, a.payment_terms,
                a.display_price, a.price_hidden, a.description, a.publish_photos,
                a.is_vip, a.tags, a.property_details, a.created_at,
                i.filename AS primary_image
            FROM ads a
            LEFT JOIN images i
                ON i.id = (
                    SELECT ii.id
                    FROM images ii
                    WHERE ii.ad_id = a.id
                      AND ii.is_selected = 1
                    ORDER BY ii.is_primary DESC, ii.sort_order ASC, ii.id ASC
                    LIMIT 1
                )
            WHERE a.status = 'published'
            ORDER BY a.created_at DESC, a.numeric_id DESC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $details = [];
            if (!empty($row['property_details'])) {
                $decoded = json_decode((string)$row['property_details'], true);
                if (is_array($decoded)) $details = $decoded;
            }

            $tags = [];
            if (!empty($row['tags'])) {
                $decodedTags = json_decode((string)$row['tags'], true);
                if (is_array($decodedTags)) $tags = array_values($decodedTags);
            }

            $row['tags'] = $tags;
            $row['property_details'] = $details;
            $row['images'] = $row['primary_image'] ? [$row['primary_image']] : [];
            $row['image'] = $row['primary_image'] ?: '';

            // Amenities can be stored in property_details or ad_amenities.
            foreach (['parking', 'elevator', 'انباری', 'پارکینگ', 'آسانسور'] as $key) {
                if (array_key_exists($key, $details)) {
                    $row[$key] = $details[$key];
                }
            }

            $amenList = [];
            if (!empty($details['amenities']) && is_array($details['amenities'])) {
                $amenList = $details['amenities'];
            }
            $row['amenities'] = $amenList;
            $amenText = implode(' ', array_map('strval', $amenList));

            $copyKeys = [
                'apartment_type', 'villa_type', 'land_type', 'land_width', 'land_length',
                'land_deed_status', 'land_ownership', 'units_per_floor', 'office_units_per_floor',
                'flooring', 'cabinet', 'cooling', 'heating', 'is_not_keyed',
                'tree_types', 'irrigation_type', 'has_well', 'front', 'location_type',
                'location_features', 'office_orientation', 'office_usage', 'office_floor',
                'office_rooms', 'office_year',
            ];
            foreach ($copyKeys as $k) {
                if (isset($details[$k]) && $details[$k] !== '' && $details[$k] !== null) {
                    $row[$k] = $details[$k];
                }
            }

            $row['parking'] = !empty($row['parking']) || !empty($details['parking'])
                || (mb_strpos($amenText, 'پارکینگ') !== false);
            $row['elevator'] = !empty($row['elevator']) || !empty($details['elevator'])
                || (mb_strpos($amenText, 'آسانسور') !== false);
            $row['storage'] = (mb_strpos($amenText, 'انبار') !== false);
            $row['balcony'] = (mb_strpos($amenText, 'بالکن') !== false) || (mb_strpos($amenText, 'تراس') !== false);
            $row['pool'] = (mb_strpos($amenText, 'استخر') !== false);
            $row['master'] = (mb_strpos($amenText, 'مستر') !== false);
            $row['walled'] = (mb_strpos($amenText, 'دیوار') !== false);
            $row['util_water'] = (mb_strpos($amenText, 'آب') !== false);
            $row['util_power'] = (mb_strpos($amenText, 'برق') !== false);
            $row['util_gas'] = (mb_strpos($amenText, 'گاز') !== false);

            $dbAds[] = $row;
        }
    } catch (Throwable $e) {
        $dbAds = [];
    }
}

$initialAdsJson = json_encode(
    $dbAds,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($initialAdsJson === false) $initialAdsJson = '[]';
?>
<?php
/* =====================================================
   GET SITE LOGO URL
   ===================================================== */

function getSiteLogoUrl(): string
{
    // راند ۶۹: لوگوی نسخه‌دار مشترک (ملکینو-لوگو) تا کشِ لوگوی کهنه شکسته شود
    require_once __DIR__ . '/melkino-logo.php';
    return melkinoSiteLogoUrl();
}

$siteLogoUrl = getSiteLogoUrl();

// قبلاً این مقدار از یک فایل JSON قدیمی (settings/global.json) خونده می‌شد
// که هیچ‌وقت با تغییرات پنل ادمین آپدیت نمی‌شد. حالا مستقیم از دیتابیس.
$__gp = getGlobalSettings();
$__hideAllPublicPrices = (!$__gp['show_prices'] || !empty($__gp['hide_all_prices']));

require_once __DIR__ . '/header.php';
if (is_file(__DIR__ . '/ad-cards-bootstrap.php')) {
    require_once __DIR__ . '/ad-cards-bootstrap.php';
    echo function_exists('melkinoAdCardsHead') ? melkinoAdCardsHead('search') : '';
}
?>

<style>
    .main-content {
        flex: 1;
        overflow-y: auto;
        padding-bottom: 75px;
        background: var(--bg);
    }

    .result-card {
        background: var(--surface);
        border-radius: var(--radius-md);
        padding: var(--space-2);
        box-shadow: var(--shadow-card);
        border: 1px solid var(--border);
        margin-bottom: var(--space-2);
    }

    .icon-btn-round {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--surface);
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: var(--shadow-card);
        color: var(--text-secondary);
    }

    .feature-tag {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 13px;
        color: var(--text-secondary);
        background: var(--bg);
        padding: 4px var(--space-1);
        border-radius: var(--radius-sm);
    }

    .result-card .result-price {
        line-height: 1.8;
        text-align: left;
    }

    .result-card .result-price strong {
        font-weight: 800;
        color: inherit;
    }

    .result-card .price-separator {
        color: var(--text-secondary);
        padding: 0 3px;
    }

    .result-detail-btn {
        width: 100%;
        height: 45px;
        background: var(--bg);
        color: var(--primary);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        font-weight: 600;
        display: flex;
        justify-content: center;
        align-items: center;
        text-decoration: none;
    }

    .result-detail-btn:active {
        transform: scale(.98);
    }

    .result-compare-btn {
        height: 45px;
        padding: 0 14px;
        background: var(--surface);
        color: var(--text-secondary);
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        font-weight: 700;
        font-family: inherit;
        font-size: 13px;
        cursor: pointer;
        white-space: nowrap;
        flex: 0 0 auto;
    }

    .result-compare-btn.in-compare {
        color: var(--primary);
        border-color: var(--primary);
    }

    .empty-state {
        text-align: center;
        color: var(--text-secondary);
        padding: var(--space-4);
    }

    .loading-state {
        text-align: center;
        padding: var(--space-4);
        color: var(--text-secondary);
    }

    .filter-bar {
        display: flex;
        gap: var(--space-2);
        padding: var(--space-2) var(--space-3);
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-bar select {
        height: 40px;
        padding: 0 var(--space-2);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--bg);
        font-family: 'Vazirmatn', sans-serif;
        font-size: 14px;
        color: var(--text-primary);
        outline: none;
        min-width: 130px;
        cursor: pointer;
    }

    .filter-bar select:focus {
        border-color: var(--primary);
    }

    .filter-bar .filter-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .filter-bar .filter-reset {
        font-size: 13px;
        color: var(--primary);
        cursor: pointer;
        font-weight: 600;
        padding: 4px 12px;
        border: 1px solid var(--primary);
        border-radius: var(--radius-sm);
        background: transparent;
        transition: 0.2s;
        text-decoration: none;
    }

    .filter-bar .filter-reset:hover {
        background: var(--primary);
        color: #fff;
    }

    .filter-bar .filter-reset:active {
        transform: scale(.98);
    }

    .empty-reset-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 44px;
        padding: 0 18px;
        margin-top: 12px;
        border-radius: var(--radius-sm);
        background: var(--primary);
        color: #fff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
    }
</style>

<div class="main-content">

    <div
        id="resultsHeader"
        style="
            padding: var(--space-2) var(--space-3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-1);
        "
    >
        <span
            id="resultCount"
            style="
                font-size: 15px;
                color: var(--text-secondary);
                font-weight: 500;
            "
        >
            بارگذاری...
        </span>

        <div
            style="
                display: flex;
                align-items: center;
                gap: var(--space-1);
                color: var(--primary);
                font-size: 14px;
                font-weight: 600;
                background: var(--surface);
                padding: 6px var(--space-2);
                border-radius: var(--radius-sm);
                box-shadow: var(--shadow-card);
                border: 1px solid var(--border);
            "
        >
            مرتب‌سازی
        </div>
    </div>


    <div class="filter-bar">

        <span class="filter-label">
            🔍 فیلترها:
        </span>

        <select
            id="filterTransactionType"
            onchange="applyFilters({ source: 'dropdown' })"
        >
            <option value="">همه معاملات</option>
            <option value="فروش">خرید و فروش</option>
            <option value="پیش فروش">پیش فروش</option>
            <option value="اجاره">رهن و اجاره</option>
        </select>


        <select
            id="filterPropertyType"
            onchange="applyFilters({ source: 'dropdown' })"
        >
            <option value="">همه املاک</option>
            <option value="آپارتمان">آپارتمان</option>
            <option value="ویلا">ویلایی</option>
            <option value="زمین">زمین</option>
            <option value="تجاری">تجاری</option>
            <option value="باغ">باغ</option>
            <option value="اداری">اداری</option>
        </select>


        <a
            href="search-results.php"
            class="filter-reset"
        >
            حذف فیلترها
        </a>

    </div>


    <div
        id="adsListContainer"
        class="properties-list"
        style="padding: 0 var(--space-3);"
    >
        <div class="loading-state">
            در حال بارگذاری آگهی‌ها...
        </div>
    </div>

</div>

<script>
const MELKINO_PRICE_VISIBILITY={hide: <?= $__hideAllPublicPrices ? 'true' : 'false' ?>};
window.MELKINO_JALALI_YEAR = <?= (int) (function_exists('melkinoJalaliCurrentYear') ? melkinoJalaliCurrentYear() : 1405) ?>;
(function () {

    'use strict';


    /* ============================================================
       داده‌های نمونه
    ============================================================ */

    var sampleAds = [
        {
            id: "MLK-001",
            title: "آپارتمان ۹۰ متری نوساز در شهرک غرب",
            price: "۲,۸۰۰,۰۰۰,۰۰۰",
            priceNumeric: 2800000000,
            location: "شهرک غرب، تهران",
            tags: ["لوکس"],
            bedrooms: "۲",
            area: 90,
            type: "فروش",
            transactionType: "فروش",
            propertyType: "آپارتمان",
            neighborhood: "شهرک غرب",
            parking: true,
            elevator: false,
            timestamp: Date.now() - 60000
        },
        {
            id: "MLK-002",
            title: "ویلای لوکس ۳۰۰ متری در چالوس",
            price: "۶,۵۰۰,۰۰۰,۰۰۰",
            priceNumeric: 6500000000,
            location: "چالوس، مازندران",
            tags: ["VIP"],
            bedrooms: "۴",
            area: 300,
            type: "فروش",
            transactionType: "فروش",
            propertyType: "ویلا",
            neighborhood: "چالوس",
            parking: true,
            elevator: false,
            timestamp: Date.now() - 86400000
        },
        {
            id: "MLK-003",
            title: "مغازه تجاری ۵۰ متری در سعادت آباد",
            price: "۱,۵۰۰,۰۰۰,۰۰۰",
            priceNumeric: 1500000000,
            location: "سعادت آباد، تهران",
            tags: ["خوش قیمت"],
            bedrooms: "-",
            area: 50,
            type: "فروش",
            transactionType: "فروش",
            propertyType: "تجاری",
            neighborhood: "سعادت آباد",
            parking: false,
            elevator: true,
            timestamp: Date.now() - 300000
        },
        {
            id: "MLK-004",
            title: "باغ ۱۰۰۰ متری با استخر در مزرعه نو",
            price: "۴,۲۰۰,۰۰۰,۰۰۰",
            priceNumeric: 4200000000,
            location: "مزرعه نو، شاهرود",
            tags: [],
            bedrooms: "-",
            area: 1000,
            type: "فروش",
            transactionType: "فروش",
            propertyType: "باغ",
            neighborhood: "مزرعه نو",
            parking: true,
            elevator: false,
            timestamp: Date.now() - 120000
        },
        {
            id: "MLK-005",
            title: "واحد اداری ۱۲۰ متری در مرکز شهر",
            price: "۳,۵۰۰,۰۰۰,۰۰۰",
            priceNumeric: 3500000000,
            location: "مرکز شهر، تهران",
            tags: [],
            bedrooms: "۳",
            area: 120,
            type: "فروش",
            transactionType: "فروش",
            propertyType: "اداری",
            neighborhood: "مرکز شهر",
            parking: true,
            elevator: true,
            timestamp: Date.now() - 180000
        },
        {
            id: "MLK-006",
            title: "زمین ۵۰۰ متری با دسترسی به خیابان اصلی",
            price: "۲,۲۰۰,۰۰۰,۰۰۰",
            priceNumeric: 2200000000,
            location: "شهرک غرب، تهران",
            tags: [],
            bedrooms: "-",
            area: 500,
            type: "فروش",
            transactionType: "فروش",
            propertyType: "زمین",
            neighborhood: "شهرک غرب",
            parking: false,
            elevator: false,
            timestamp: Date.now() - 240000
        },
        {
            id: "MLK-007",
            title: "آپارتمان ۸۰ متری اجاره‌ای در شهرک غرب",
            price: "",
            priceNumeric: 0,
            deposit: "500000000",
            rent_monthly: "30000000",
            location: "شهرک غرب، تهران",
            tags: [],
            bedrooms: "۲",
            area: 80,
            type: "اجاره",
            transactionType: "اجاره",
            propertyType: "آپارتمان",
            neighborhood: "شهرک غرب",
            parking: false,
            elevator: false,
            timestamp: Date.now() - 120000
        }
    ];


    /* ============================================================
       متغیر اصلی آگهی‌ها
    ============================================================ */

    var allAds = [];
    var siteLogoUrl = '<?= $siteLogoUrl ?>';


    /* ============================================================
       تبدیل مقادیر انگلیسی فرم search.php به فارسی
    ============================================================ */

    var propertyTypeMap = {
        apartment: 'آپارتمان',
        villa: 'ویلا',
        commercial: 'تجاری',
        land: 'زمین',
        garden: 'باغ',
        office: 'اداری'
    };


    var transactionTypeMap = {
        sell: 'فروش',
        pre_sell: 'پیش فروش',
        rent: 'رهن و اجاره'
    };


    /* ============================================================
       تبدیل اعداد فارسی / عربی به انگلیسی
    ============================================================ */

    function normalizeDigits(value) {

        return String(value || '')
            .replace(/[۰-۹]/g, function (digit) {
                return '۰۱۲۳۴۵۶۷۸۹'.indexOf(digit);
            })
            .replace(/[٠-٩]/g, function (digit) {
                return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit);
            });

    }


    /* ============================================================
       تبدیل قیمت
    ============================================================ */

    function parseNumber(value) {

        var normalized =
            normalizeDigits(value)
                .replace(/[,\s٬،]/g, '');

        if (!normalized) {
            return 0;
        }

        return parseInt(normalized, 10) || 0;

    }


    /* ============================================================
       گرفتن اولین تصویر معتبر آگهی
    ============================================================ */

    function normalizeImageValue(value) {

        if (Array.isArray(value)) {

            for (var i = 0; i < value.length; i++) {

                var item = value[i];

                if (typeof item === 'string' && item.trim() !== '') {
                    return item.trim();
                }

                if (item && typeof item === 'object') {

                    var keys = [
                        'url', 'src', 'path', 'image',
                        'file', 'file_path', 'image_url'
                    ];

                    for (var k = 0; k < keys.length; k++) {
                        var candidate = item[keys[k]];

                        if (
                            typeof candidate === 'string' &&
                            candidate.trim() !== ''
                        ) {
                            return candidate.trim();
                        }
                    }
                }
            }

            return '';
        }

        if (value && typeof value === 'object') {
            return normalizeImageValue([value]);
        }

        if (typeof value === 'string' && value.trim() !== '') {

            var trimmed = value.trim();

            if (
                trimmed.charAt(0) === '[' ||
                trimmed.charAt(0) === '{'
            ) {
                try {
                    var decoded = JSON.parse(trimmed);
                    var decodedImage = normalizeImageValue(decoded);

                    if (decodedImage) {
                        return decodedImage;
                    }
                } catch (e) {}
            }

            return trimmed;
        }

        return '';
    }


    function getFirstAdImage(ad) {

        var imageSources = [
            ad.image,
            ad.image_url,
            ad.imageUrl,
            ad.thumbnail,
            ad.thumbnail_url,
            ad.photo,
            ad.photo_url,
            ad.photos,
            ad.images,
            ad.image_urls,
            ad.gallery,
            ad.media
        ];

        for (var i = 0; i < imageSources.length; i++) {
            var image = normalizeImageValue(imageSources[i]);
            if (image) {
                return image;
            }
        }

        return '';
    }


    /* ============================================================
       نرمال‌سازی مقدار پول
    ============================================================ */

    function formatMoneyValue(value) {

        if (value === null || value === undefined || value === '') {
            return '';
        }

        var normalized = normalizeDigits(value)
            .replace(/[,_٬،\s]/g, '');

        var digitsOnly = normalized.replace(/[^0-9]/g, '');

        if (!digitsOnly) {
            return '';
        }

        var number = parseInt(digitsOnly, 10) || 0;

        if (number <= 0) {
            return '';
        }

        return number.toLocaleString('en-US');
    }


    /* ============================================================
       استخراج مبلغ ودیعه / اجاره از رشته قیمت
    ============================================================ */

    function extractRentalAmounts(value) {

        var result = {
            deposit: '',
            rent: ''
        };

        var text = normalizeDigits(value || '');

        if (!text) {
            return result;
        }

        var depositMatch = text.match(
            /(?:رهن|ودیعه|دپوزیت)\s*[:：]?\s*([0-9۰-۹,٬،]+)/u
        );

        var rentMatch = text.match(
            /(?:اجاره|ماهانه)\s*[:：]?\s*([0-9۰-۹,٬،]+)/u
        );

        if (depositMatch) {
            result.deposit = formatMoneyValue(depositMatch[1]);
        }

        if (rentMatch) {
            result.rent = formatMoneyValue(rentMatch[1]);
        }

        return result;
    }


    /* ============================================================
       نرمال‌سازی یک آگهی
    ============================================================ */

    function normalizeAd(ad) {

        var transactionType =
            ad.transactionType ||
            ad.transaction_type ||
            ad.type ||
            '';

        var propertyType =
            ad.propertyType ||
            ad.property_type ||
            '';

        if (transactionTypeMap[transactionType]) {
            transactionType =
                transactionTypeMap[transactionType];
        }

        if (propertyTypeMap[propertyType]) {
            propertyType =
                propertyTypeMap[propertyType];
        }

        // ===== فیلدهای قیمتی =====
        var deposit =
            ad.deposit ??
            ad.deposit_amount ??
            ad.depositAmount ??
            ad.rahn ??
            ad.rahn_amount ??
            ad.down_payment ??
            '';

        var rentMonthly =
            ad.rent_monthly ??
            ad.rentMonthly ??
            ad.rent ??
            ad.rent_amount ??
            ad.monthly_rent ??
            '';

        var rawPrice =
            ad.display_price ??
            ad.displayPrice ??
            ad.price_sell ??
            ad.price ??
            ad.total_price ??
            '';

        var rentalAmounts = extractRentalAmounts(rawPrice);

        if (!deposit && rentalAmounts.deposit) {
            deposit = rentalAmounts.deposit;
        }

        if (!rentMonthly && rentalAmounts.rent) {
            rentMonthly = rentalAmounts.rent;
        }

        var displayPrice = rawPrice || '۰';

        var isRental =
            transactionType === 'اجاره' ||
            transactionType === 'رهن و اجاره' ||
            transactionType === 'رهن' ||
            transactionType === 'rent';

        if (isRental) {
            displayPrice = '۰';
        } else if (displayPrice === '' || displayPrice === '0') {
            if (ad.price_sell && ad.price_sell !== '0') {
                displayPrice = ad.price_sell;
            } else if (ad.total_price && ad.total_price !== '0') {
                displayPrice = ad.total_price;
            }
        }

        var priceNumeric =
            parseNumber(
                ad.priceNumeric ||
                displayPrice
            );

        var area =
            parseNumber(
                ad.area ||
                ad.land_area ||
                ad.office_area ||
                ad.building_area ||
                0
            );

        var location =
            ad.location ||
            ad.neighborhood ||
            'نامشخص';

        var neighborhood =
            ad.neighborhood ||
            (
                location
                    ? String(location).split('،')[0].trim()
                    : ''
            );

        var createdTimestamp =
            ad.created_at
                ? new Date(ad.created_at).getTime()
                : (
                    ad.timestamp ||
                    Date.now()
                );

        return {

            id: ad.id || 'N/A',

            title:
                ad.title ||
                'بدون عنوان',

            price:
                String(displayPrice),

            priceNumeric:
                priceNumeric,

            location:
                location,

            lat: parseFloat(ad.latitude || ad.lat || '') || null,
            lng: parseFloat(ad.longitude || ad.lng || '') || null,

            tags:
                Array.isArray(ad.tags)
                    ? ad.tags
                    : [],

            bedrooms:
                ad.rooms ||
                ad.office_rooms ||
                ad.bedrooms ||
                '-',

            area:
                area,

            type:
                transactionType ||
                'نامشخص',

            transactionType:
                transactionType ||
                'نامشخص',

            propertyType:
                propertyType ||
                'نامشخص',

            neighborhood:
                neighborhood,

            parking:
                ad.parking === true ||
                ad.parking === '1' ||
                ad.parking === 1,

            elevator:
                ad.elevator === true ||
                ad.elevator === '1' ||
                ad.elevator === 1,

            image:
                getFirstAdImage(ad),

            timestamp:
                isNaN(createdTimestamp)
                    ? Date.now()
                    : createdTimestamp,

            status:
                ad.status ||
                'pending',

            // ===== اضافه کردن فیلدهای قیمتی =====
            deposit: deposit,
            rent_monthly: rentMonthly,
            price_hidden: !!(ad.price_hidden || ad.priceHidden),

            details: ad.property_details || ad.details || {},

            apartmentType: ad.apartment_type || (ad.property_details && ad.property_details.apartment_type) || '',
            villaType: ad.villa_type || '',
            landType: ad.land_type || '',
            landWidth: ad.land_width || '',
            landLength: ad.land_length || '',
            landDeedStatus: ad.land_deed_status || '',
            landOwnership: ad.land_ownership || '',
            unitsPerFloor: ad.units_per_floor || ad.office_units_per_floor || '',
            flooring: ad.flooring || '',
            cabinet: ad.cabinet || '',
            cooling: ad.cooling || '',
            heating: ad.heating || '',
            isNotKeyed: ad.is_not_keyed === true || ad.is_not_keyed === '1' || ad.is_not_keyed === 1,
            treeTypes: ad.tree_types || '',
            irrigationType: ad.irrigation_type || '',
            hasWell: ad.has_well === true || ad.has_well === '1' || ad.has_well === 1,
            front: ad.front || '',
            locationType: ad.location_type || ad.location_features || '',
            officeOrientation: ad.office_orientation || '',
            officeUsage: ad.office_usage || '',
            floor: ad.floor || ad.office_floor || '',
            year: ad.year || ad.office_year || '',
            builtArea: ad.built_area || '',
            address: ad.address || '',

        };

    }


    /* ============================================================
       بارگذاری آگهی‌ها از MySQL
    ============================================================ */

    function loadAdsFromDatabase() {

        var data = <?php echo $initialAdsJson; ?>;

        if (!Array.isArray(data)) {
            data = [];
        }

        allAds = data.map(normalizeAd);

        applyFilters({
            source: 'initial'
        });
    }


    /* ============================================================
       DISPLAY PRICE
       فروش: قیمت ... تومان
       اجاره / رهن و اجاره: ودیعه ... تومان | اجاره ... تومان
    ============================================================ */

    function displayPrice(ad) {
        if (MELKINO_PRICE_VISIBILITY.hide || ad.price_hidden) return 'برای استعلام قیمت تماس بگیرید';

        var transactionType = String(
            ad.transactionType || ad.type || ''
        ).trim();

        var isRental =
            transactionType === 'اجاره' ||
            transactionType === 'رهن و اجاره' ||
            transactionType === 'رهن' ||
            transactionType === 'rent';

        if (isRental) {

            var deposit = formatMoneyValue(ad.deposit);
            var rent = formatMoneyValue(ad.rent_monthly);

            if ((!deposit || !rent) && ad.price) {
                var extracted = extractRentalAmounts(ad.price);

                if (!deposit) {
                    deposit = extracted.deposit;
                }

                if (!rent) {
                    rent = extracted.rent;
                }
            }

            var parts = [];

            if (deposit) {
                parts.push(
                    '<strong>ودیعه:</strong> ' +
                    deposit +
                    ' تومان'
                );
            }

            if (rent) {
                parts.push(
                    '<strong>اجاره:</strong> ' +
                    rent +
                    ' تومان'
                );
            }

            if (parts.length) {
                return parts.join(' <span class="price-separator">|</span> ');
            }

            return 'تماس بگیرید';
        }

        var price = parseNumber(ad.price);

        if (!price && ad.priceNumeric) {
            price = parseNumber(ad.priceNumeric);
        }

        if (price > 0) {
            return price.toLocaleString('fa-IR') + ' تومان';
        }

        return 'تماس بگیرید';
    }


    /* ============================================================
       نمایش نتایج
    ============================================================ */

    function displayAds(ads) {

        var container =
            document.getElementById(
                'adsListContainer'
            );

        var countEl =
            document.getElementById(
                'resultCount'
            );


        if (
            !ads ||
            ads.length === 0
        ) {

            countEl.innerText =
                'هیچ ملکی یافت نشد';


            container.innerHTML = `
                <div class="empty-state">

                    <svg
                        width="60"
                        height="60"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        style="margin:auto;"
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

                    <h3
                        style="
                            color:var(--text-primary);
                            margin-top:var(--space-2);
                        "
                    >
                        هیچ آگهی‌ای با این فیلترها یافت نشد.
                    </h3>

                    <p
                        style="
                            color:var(--text-secondary);
                        "
                    >
                        فیلترهای دیگری را امتحان کنید.
                    </p>

                    <a
                        href="search-results.php"
                        class="empty-reset-btn"
                    >
                        مشاهده همه املاک
                    </a>

                </div>
            `;

            return;
        }


        countEl.innerText =
            ads.length + ' ملک یافت شد';

        if (typeof window.mkRenderAdCards === 'function') {
            container.classList.add('properties-list');
            window.mkRenderAdCards(container, ads);
            return;
        }

        container.innerHTML = '';


        for (
            var i = 0;
            i < ads.length;
            i++
        ) {

            var ad =
                ads[i];


            var card =
                document.createElement('div');


            card.className =
                'result-card';


            var tagsHtml =
                '';


            if (
                ad.tags &&
                ad.tags.length > 0
            ) {

                var tagArr = [];


                for (
                    var t = 0;
                    t < ad.tags.length;
                    t++
                ) {

                    tagArr.push(
                        `
                        <span
                            style="
                                background:var(--gold-bg);
                                color:var(--gold);
                                padding:2px 8px;
                                border-radius:var(--radius-sm);
                                font-size:11px;
                                font-weight:600;
                            "
                        >
                            ${escapeHtml(ad.tags[t])}
                        </span>
                        `
                    );

                }


                tagsHtml =
                    tagArr.join(' ');

            }


            var detailsUrl =
                'property-details.php?id=' +
                encodeURIComponent(ad.id);


            // ===== تصویر واقعی آگهی؛ فقط در صورت نبود عکس لوگو =====
            var imageHtml = '';
            var firstImage = String(ad.image || '').trim();

            if (firstImage) {
                imageHtml = `
                    <img
                        src="${escapeHtml(firstImage)}"
                        alt="${escapeHtml(ad.title)}"
                        loading="lazy"
                        style="
                            width:100%;
                            height:100%;
                            object-fit:cover;
                            display:block;
                        "
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    >
                    <div
                        style="
                            display:none;
                            position:absolute;
                            inset:0;
                            align-items:center;
                            justify-content:center;
                            background:var(--surface);
                        "
                    >
                        ${siteLogoUrl
                            ? `<img src="${escapeHtml(siteLogoUrl)}" alt="لوگوی ملکینو" style="width:70%;height:70%;object-fit:contain;padding:12px;box-sizing:border-box;">`
                            : '<span>تصویر ملک</span>'
                        }
                    </div>
                `;
            } else if (siteLogoUrl) {
                imageHtml = `
                    <img
                        src="${escapeHtml(siteLogoUrl)}"
                        alt="لوگوی ملکینو"
                        loading="lazy"
                        style="
                            width:70%;
                            height:70%;
                            object-fit:contain;
                            padding:12px;
                            box-sizing:border-box;
                        "
                    >
                `;
            } else {
                imageHtml = '<span>تصویر ملک</span>';
            }

            card.innerHTML = `

                <div
                    style="
                        position:relative;
                        width:100%;
                        height:150px;
                        border-radius:var(--radius-sm);
                        background:var(--gold-bg);
                        display:flex;
                        justify-content:center;
                        align-items:center;
                        color:var(--text-secondary);
                    "
                >

                    ${imageHtml}


                    ${
                        tagsHtml
                            ? `
                                <div
                                    style="
                                        position:absolute;
                                        top:var(--space-2);
                                        right:var(--space-2);
                                        display:flex;
                                        gap:4px;
                                    "
                                >
                                    ${tagsHtml}
                                </div>
                            `
                            : ''
                    }


                    <div
                        style="
                            position:absolute;
                            bottom:var(--space-2);
                            right:var(--space-2);
                            display:flex;
                            gap:var(--space-1);
                        "
                    >

                        <div class="icon-btn-round">

                            <svg
                                width="18"
                                height="18"
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

                        </div>


                        <div class="icon-btn-round">

                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle cx="18" cy="5" r="3"></circle>
                                <circle cx="6" cy="12" r="3"></circle>
                                <circle cx="18" cy="19" r="3"></circle>

                                <line
                                    x1="8.59"
                                    y1="13.51"
                                    x2="15.42"
                                    y2="17.49"
                                ></line>

                                <line
                                    x1="15.41"
                                    y1="6.51"
                                    x2="8.59"
                                    y2="10.49"
                                ></line>
                            </svg>

                        </div>

                    </div>

                </div>


                <div
                    style="
                        margin-top:var(--space-2);
                    "
                >

                    <div
                        style="
                            font-size:12px;
                            color:var(--text-secondary);
                        "
                    >
                        کد ملک:
                        ${escapeHtml(ad.id)}
                    </div>


                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:flex-start;
                            gap:10px;
                            margin:var(--space-1) 0;
                        "
                    >

                        <span
                            style="
                                font-size:17px;
                                font-weight:700;
                                color:var(--text-primary);
                            "
                        >
                            ${escapeHtml(ad.title)}
                        </span>


                        <span
                            style="
                                font-size:16px;
                                font-weight:800;
                                color:var(--gold);
                                white-space:normal;
                                line-height:1.8;
                                text-align:left;
                            "
                        >
                            ${displayPrice(ad)}
                        </span>

                    </div>


                    <div
                        style="
                            font-size:14px;
                            color:var(--text-secondary);
                        "
                    >
                        ${escapeHtml(ad.location)}
                    </div>


                    <div
                        style="
                            display:flex;
                            gap:var(--space-2);
                            margin-top:var(--space-1);
                            flex-wrap:wrap;
                            border-top:1px solid var(--border);
                            padding-top:var(--space-2);
                        "
                    >

                        <span class="feature-tag">
                            ${ad.area || 0} متر
                        </span>


                        ${
                            ad.bedrooms !== '-'
                                ? `
                                    <span class="feature-tag">
                                        ${escapeHtml(ad.bedrooms)} خواب
                                    </span>
                                `
                                : ''
                        }


                        <span
                            class="feature-tag"
                            style="
                                color:var(--primary);
                            "
                        >
                            ${escapeHtml(ad.type)}
                        </span>


                        ${
                            ad.parking
                                ? `
                                    <span class="feature-tag">
                                        🚗 پارکینگ
                                    </span>
                                `
                                : ''
                        }


                        ${
                            ad.elevator
                                ? `
                                    <span class="feature-tag">
                                        🛗 آسانسور
                                    </span>
                                `
                                : ''
                        }

                    </div>

                </div>


                <div style="display:flex;gap:8px;margin-top:4px;">

                    <a
                        href="${detailsUrl}"
                        class="result-detail-btn"
                        style="flex:1;width:auto;"
                    >
                        مشاهده جزئیات
                    </a>

                    <button
                        type="button"
                        class="result-compare-btn"
                        data-compare-add="${escapeHtml(ad.id)}"
                        title="افزودن به مقایسه"
                    >
                        ⚖️ مقایسه
                    </button>

                </div>

            `;


            container.appendChild(card);

        }

    }


    /* ============================================================
       جلوگیری از ورود HTML ناسالم به کارت
    ============================================================ */

    function escapeHtml(value) {

        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    }


    /* ============================================================
       گرفتن پارامترهای URL
    ============================================================ */

    function getUrlFilters() {

        var params =
            new URLSearchParams(
                window.location.search
            );


        var propertyType =
            params.get('property_type') || '';


        var transactionType =
            params.get('transaction_type') || '';


        if (
            propertyTypeMap[propertyType]
        ) {

            propertyType =
                propertyTypeMap[propertyType];

        }


        if (
            transactionTypeMap[transactionType]
        ) {

            transactionType =
                transactionTypeMap[
                    transactionType
                ];

        }


        return {

            filter:
                params.get('filter') || '',

            propertyType:
                propertyType,

            transactionType:
                transactionType,

            neighborhood:
                params.get('neighborhood') || '',

            areaMin:
                parseNumber(
                    params.get('area_min')
                ),

            areaMax:
                params.get('area_max')
                    ? parseNumber(
                        params.get('area_max')
                    )
                    : Infinity,

            bedrooms:
                normalizeDigits(
                    params.get('bedrooms') || ''
                ),

            parking:
                params.get('parking') === '1',

            elevator:
                params.get('elevator') === '1',

            storage: params.get('storage') === '1',
            balcony: params.get('balcony') === '1',
            pool: params.get('pool') === '1',
            master: params.get('master') === '1',
            walled: params.get('walled') === '1',
            util_water: params.get('util_water') === '1',
            util_power: params.get('util_power') === '1',
            util_gas: params.get('util_gas') === '1',
            renovated: params.get('renovated') === '1',
            isNotKeyed: params.get('is_not_keyed') === '1',
            hasWell: params.get('has_well') === '1',

            apartmentType: params.get('apartment_type') || '',
            villaType: params.get('villa_type') || '',
            landType: params.get('land_type') || '',
            landOwnership: params.get('land_ownership') || '',
            landDeedStatus: params.get('land_deed_status') || '',
            unitsPerFloor: params.get('units_per_floor') || '',
            flooring: params.get('flooring') || '',
            cabinet: params.get('cabinet') || '',
            cooling: params.get('cooling') || '',
            heating: params.get('heating') || '',
            treeTypes: params.get('tree_types') || '',
            irrigationType: params.get('irrigation_type') || '',
            locationType: params.get('location_type') || '',
            officeOrientation: params.get('office_orientation') || '',
            officeUsage: params.get('office_usage') || '',

            floorMin: params.get('floor_min') ? parseNumber(params.get('floor_min')) : 0,
            floorMax: params.get('floor_max') ? parseNumber(params.get('floor_max')) : Infinity,
            minAge: params.get('min_age') ? parseNumber(params.get('min_age')) : 0,
            maxAge: params.get('max_age') ? parseNumber(params.get('max_age')) : Infinity,
            landWidthMin: params.get('land_width_min') ? parseNumber(params.get('land_width_min')) : 0,
            landLengthMin: params.get('land_length_min') ? parseNumber(params.get('land_length_min')) : 0,
            builtAreaMin: params.get('built_area_min') ? parseNumber(params.get('built_area_min')) : 0,
            frontMin: params.get('front_min') ? parseNumber(params.get('front_min')) : 0,

            depositMin: parseNumber(params.get('deposit_min')),
            depositMax: params.get('deposit_max') ? parseNumber(params.get('deposit_max')) : Infinity,
            rentMin: parseNumber(params.get('rent_min')),
            rentMax: params.get('rent_max') ? parseNumber(params.get('rent_max')) : Infinity,

            priceMin:
                parseNumber(
                    params.get('price_min')
                ),

            priceMax:
                params.get('price_max')
                    ? parseNumber(
                        params.get('price_max')
                    )
                    : Infinity

        };

    }


    /* ============================================================
       اعمال فیلترها
    ============================================================ */

    function applyFilters(options) {

        options =
            options || {};


        var source =
            options.source || 'normal';


        var filterTransaction =
            document.getElementById(
                'filterTransactionType'
            ).value;


        var filterProperty =
            document.getElementById(
                'filterPropertyType'
            ).value;


        var urlFilters =
            getUrlFilters();


        var filtered =
            allAds.slice();


        /*
        |--------------------------------------------------------------------------
        | عنوان
        |--------------------------------------------------------------------------
        */

        var topbarTitle =
            document.getElementById(
                'topbar-title'
            );


        if (
            urlFilters.filter === 'vip'
        ) {

            filtered =
                filtered.filter(
                    function (ad) {

                        return (
                            Array.isArray(ad.tags) &&
                            ad.tags.includes('VIP')
                        );

                    }
                );


            if (topbarTitle) {
                topbarTitle.innerText =
                    'فایل‌های VIP';
            }

        } else if (
            urlFilters.filter === 'all'
        ) {

            if (topbarTitle) {
                topbarTitle.innerText =
                    'همه آگهی‌ها';
            }

        } else {

            if (topbarTitle) {
                topbarTitle.innerText =
                    'نتایج جستجو';
            }

        }


        var effectivePropertyType =
            filterProperty ||
            urlFilters.propertyType;


        var effectiveTransactionType =
            filterTransaction ||
            urlFilters.transactionType;


        if (
            effectivePropertyType
        ) {

            filtered =
                filtered.filter(
                    function (ad) {

                        return (
                            ad.propertyType ===
                            effectivePropertyType
                        );

                    }
                );

        }


        if (
            effectiveTransactionType
        ) {

            filtered =
                filtered.filter(
                    function (ad) {

                        return (
                            ad.transactionType ===
                            effectiveTransactionType
                        );

                    }
                );

        }


        if (Array.isArray(urlFilters.mapPoly) && urlFilters.mapPoly.length >= 3) {
            filtered = filtered.filter(function (ad) {
                var lat = Number(ad.lat);
                var lng = Number(ad.lng);
                if (!isFinite(lat) || !isFinite(lng)) return false;
                var poly = urlFilters.mapPoly;
                var inside = false;
                for (var i = 0, j = poly.length - 1; i < poly.length; j = i++) {
                    var yi = Number(poly[i].lat), xi = Number(poly[i].lng);
                    var yj = Number(poly[j].lat), xj = Number(poly[j].lng);
                    var hit = ((yi > lat) !== (yj > lat)) &&
                        (lng < (xj - xi) * (lat - yi) / ((yj - yi) || 1e-12) + xi);
                    if (hit) inside = !inside;
                }
                return inside;
            });
        }


        filtered =
            filtered.filter(
                function (ad) {

                    return (
                        ad.area >=
                            urlFilters.areaMin &&

                        ad.area <=
                            urlFilters.areaMax
                    );

                }
            );


        if (
            urlFilters.bedrooms
        ) {

            filtered =
                filtered.filter(
                    function (ad) {

                        var want = parseNumber(urlFilters.bedrooms);
                        var have = parseNumber(ad.bedrooms);
                        if (!have) return false;
                        if (want >= 5) return have >= 5;
                        return have === want;

                    }
                );

        }


        if (
            urlFilters.parking
        ) {

            filtered =
                filtered.filter(
                    function (ad) {

                        return ad.parking === true;

                    }
                );

        }


        if (
            urlFilters.elevator
        ) {

            filtered =
                filtered.filter(
                    function (ad) {

                        return ad.elevator === true;

                    }
                );

        }

        function amenText(ad) {
            var list = Array.isArray(ad.amenities) ? ad.amenities.join(' ') : '';
            return list + ' ' + JSON.stringify(ad.details || {});
        }

        function matchExact(adVal, want) {
            if (!want) return true;
            return String(adVal || '').trim() === String(want).trim();
        }

        function inRange(val, min, max) {
            var n = parseNumber(val);
            if (!n && min) return false;
            if (min && n < min) return false;
            if (max !== Infinity && n > max) return false;
            return true;
        }

        function matchLoose(adVal, want) {
            if (!want) return true;
            var a = String(adVal || '').trim();
            var w = String(want).trim();
            if (!a) return false;
            return a === w || a.indexOf(w) !== -1 || w.indexOf(a) !== -1;
        }

        if (urlFilters.apartmentType) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.apartmentType, urlFilters.apartmentType); });
        }
        if (urlFilters.villaType) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.villaType, urlFilters.villaType); });
        }
        if (urlFilters.landType) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.landType, urlFilters.landType); });
        }
        if (urlFilters.landOwnership) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.landOwnership, urlFilters.landOwnership); });
        }
        if (urlFilters.landDeedStatus) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.landDeedStatus, urlFilters.landDeedStatus); });
        }
        if (urlFilters.unitsPerFloor) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.unitsPerFloor, urlFilters.unitsPerFloor); });
        }
        if (urlFilters.flooring) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.flooring, urlFilters.flooring); });
        }
        if (urlFilters.cabinet) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.cabinet, urlFilters.cabinet); });
        }
        if (urlFilters.cooling) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.cooling, urlFilters.cooling); });
        }
        if (urlFilters.heating) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.heating, urlFilters.heating); });
        }
        if (urlFilters.treeTypes) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.treeTypes, urlFilters.treeTypes); });
        }
        if (urlFilters.irrigationType) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.irrigationType, urlFilters.irrigationType); });
        }
        if (urlFilters.locationType) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.locationType, urlFilters.locationType); });
        }
        if (urlFilters.officeOrientation) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.officeOrientation, urlFilters.officeOrientation); });
        }
        if (urlFilters.officeUsage) {
            filtered = filtered.filter(function (ad) { return matchLoose(ad.officeUsage, urlFilters.officeUsage); });
        }
        if (urlFilters.floorMin || urlFilters.floorMax !== Infinity) {
            filtered = filtered.filter(function (ad) { return inRange(ad.floor, urlFilters.floorMin, urlFilters.floorMax); });
        }
        if (urlFilters.landWidthMin) {
            filtered = filtered.filter(function (ad) { return inRange(ad.landWidth, urlFilters.landWidthMin, Infinity); });
        }
        if (urlFilters.landLengthMin) {
            filtered = filtered.filter(function (ad) { return inRange(ad.landLength, urlFilters.landLengthMin, Infinity); });
        }
        if (urlFilters.builtAreaMin) {
            filtered = filtered.filter(function (ad) { return inRange(ad.builtArea, urlFilters.builtAreaMin, Infinity); });
        }
        if (urlFilters.frontMin) {
            filtered = filtered.filter(function (ad) { return inRange(ad.front, urlFilters.frontMin, Infinity); });
        }
        if (urlFilters.isNotKeyed) {
            filtered = filtered.filter(function (ad) { return !!ad.isNotKeyed; });
        }
        if (urlFilters.hasWell) {
            filtered = filtered.filter(function (ad) { return !!ad.hasWell; });
        }

        if (urlFilters.minAge || urlFilters.maxAge !== Infinity) {
            var now = new Date();
            var jy = window.MELKINO_JALALI_YEAR || (now.getFullYear() - 621);
            if (!window.MELKINO_JALALI_YEAR && (now.getMonth() < 2 || (now.getMonth() === 2 && now.getDate() < 21))) jy -= 1;
            filtered = filtered.filter(function (ad) {
                var y = parseNumber(ad.year);
                if (!y) return false;
                var age = jy - y;
                if (age < 0) age = 0;
                if (urlFilters.minAge && age < urlFilters.minAge) return false;
                if (urlFilters.maxAge !== Infinity && age > urlFilters.maxAge) return false;
                return true;
            });
        }

        if (urlFilters.depositMin || urlFilters.depositMax !== Infinity) {
            filtered = filtered.filter(function (ad) {
                var n = parseNumber(ad.deposit);
                if (!n && urlFilters.depositMin) return false;
                if (urlFilters.depositMin && n < urlFilters.depositMin) return false;
                if (urlFilters.depositMax !== Infinity && n > urlFilters.depositMax) return false;
                return true;
            });
        }
        if (urlFilters.rentMin || urlFilters.rentMax !== Infinity) {
            filtered = filtered.filter(function (ad) {
                var n = parseNumber(ad.rent_monthly);
                if (!n && urlFilters.rentMin) return false;
                if (urlFilters.rentMin && n < urlFilters.rentMin) return false;
                if (urlFilters.rentMax !== Infinity && n > urlFilters.rentMax) return false;
                return true;
            });
        }

        var flagMap = [
            ['storage', 'انبار'],
            ['balcony', 'بالکن'],
            ['pool', 'استخر'],
            ['master', 'مستر'],
            ['walled', 'دیوار'],
            ['util_water', 'آب'],
            ['util_power', 'برق'],
            ['util_gas', 'گاز']
        ];
        flagMap.forEach(function (pair) {
            if (!urlFilters[pair[0]]) return;
            filtered = filtered.filter(function (ad) {
                if (ad[pair[0]]) return true;
                return amenText(ad).indexOf(pair[1]) !== -1;
            });
        });
        if (urlFilters.renovated) {
            filtered = filtered.filter(function (ad) { return !!ad.renovated; });
        }


        if (
            urlFilters.priceMin ||
            urlFilters.priceMax !== Infinity
        ) {

            filtered =
                filtered.filter(
                    function (ad) {

                        if (
                            ad.priceNumeric === 0
                        ) {

                            return false;

                        }


                        return (
                            ad.priceNumeric >=
                                urlFilters.priceMin &&

                            ad.priceNumeric <=
                                urlFilters.priceMax
                        );

                    }
                );

        }


        filtered.sort(
            function (a, b) {

                return (
                    b.timestamp -
                    a.timestamp
                );

            }
        );


        if (
            source === 'initial' ||
            source === 'url'
        ) {

            var transactionSelect =
                document.getElementById(
                    'filterTransactionType'
                );

            var propertySelect =
                document.getElementById(
                    'filterPropertyType'
                );


            if (
                !transactionSelect.value &&
                urlFilters.transactionType
            ) {

                transactionSelect.value =
                    urlFilters.transactionType;

            }


            if (
                !propertySelect.value &&
                urlFilters.propertyType
            ) {

                propertySelect.value =
                    urlFilters.propertyType;

            }

        }


        displayAds(filtered);

    }


    /* ============================================================
       کنترل URL با تغییر dropdown
    ============================================================ */

    function updateUrlFromDropdowns() {

        var params =
            new URLSearchParams();


        var currentParams =
            new URLSearchParams(
                window.location.search
            );


        var neighborhood =
            currentParams.get(
                'neighborhood'
            );


        var areaMin =
            currentParams.get(
                'area_min'
            );


        var areaMax =
            currentParams.get(
                'area_max'
            );


        var bedrooms =
            currentParams.get(
                'bedrooms'
            );


        var parking =
            currentParams.get(
                'parking'
            );


        var elevator =
            currentParams.get(
                'elevator'
            );


        var priceMin =
            currentParams.get(
                'price_min'
            );


        var priceMax =
            currentParams.get(
                'price_max'
            );


        var specialFilter =
            currentParams.get(
                'filter'
            );


        var property =
            document.getElementById(
                'filterPropertyType'
            ).value;


        var transaction =
            document.getElementById(
                'filterTransactionType'
            ).value;


        if (property) {
            params.set(
                'property_type',
                property
            );
        }


        if (transaction) {
            params.set(
                'transaction_type',
                transaction
            );
        }


        if (neighborhood) {
            params.set(
                'neighborhood',
                neighborhood
            );
        }


        if (areaMin) {
            params.set(
                'area_min',
                areaMin
            );
        }


        if (areaMax) {
            params.set(
                'area_max',
                areaMax
            );
        }


        if (bedrooms) {
            params.set(
                'bedrooms',
                bedrooms
            );
        }


        if (parking === '1') {
            params.set(
                'parking',
                '1'
            );
        }


        if (elevator === '1') {
            params.set(
                'elevator',
                '1'
            );
        }


        if (priceMin) {
            params.set(
                'price_min',
                priceMin
            );
        }


        if (priceMax) {
            params.set(
                'price_max',
                priceMax
            );
        }


        if (specialFilter) {
            params.set(
                'filter',
                specialFilter
            );
        }


        var newUrl =
            window.location.pathname +
            (
                params.toString()
                    ? '?' + params.toString()
                    : ''
            );


        window.history.replaceState(
            {},
            '',
            newUrl
        );

    }


    window.applyFilters =
        function (options) {

            options =
                options || {};


            if (
                options.source ===
                'dropdown'
            ) {

                updateUrlFromDropdowns();

            }


            applyFilters(options);

        };


    document.addEventListener(
        'DOMContentLoaded',
        function () {

            loadAdsFromDatabase();

        }
    );

})();
</script>

<?php
require_once __DIR__ . '/footer.php';
?> }


        if (elevator === '1') {
            params.set(
                'elevator',
                '1'
            );
        }


        if (priceMin) {
            params.set(
                'price_min',
                priceMin
            );
        }


        if (priceMax) {
            params.set(
                'price_max',
                priceMax
            );
        }


        if (specialFilter) {
            params.set(
                'filter',
                specialFilter
            );
        }


        var newUrl =
            window.location.pathname +
            (
                params.toString()
                    ? '?' + params.toString()
                    : ''
            );


        window.history.replaceState(
            {},
            '',
            newUrl
        );

    }


    window.applyFilters =
        function (options) {

            options =
                options || {};


            if (
                options.source ===
                'dropdown'
            ) {

                updateUrlFromDropdowns();

            }


            applyFilters(options);

        };


    document.addEventListener(
        'DOMContentLoaded',
        function () {

            loadAdsFromDatabase();

        }
    );

})();
</script>

<?php
require_once __DIR__ . '/footer.php';
?>