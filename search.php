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
require_once __DIR__ . '/header.php';

?>

<style>

/* =========================================================
   MELKINO SEARCH PAGE
   Responsive / RTL / Light + Dark
   ========================================================= */

.main-content {
    flex: 1;
    width: 100%;
    min-height: 0;

    overflow-y: auto;
    overflow-x: hidden;

    box-sizing: border-box;

    padding: 10px 14px 0;

    background: var(--bg);

    -webkit-overflow-scrolling: touch;
}


/* =========================================================
   SEARCH FORM
   ========================================================= */

#searchForm {
    width: 100%;
    min-height: 100%;
    box-sizing: border-box;
}

.search-content {
    width: 100%;
    max-width: 1100px;

    margin: 0 auto;

    box-sizing: border-box;

    padding-bottom: 135px;
}


/* =========================================================
   FILTER CARDS
   ========================================================= */

.filter-group {
    width: 100%;
    box-sizing: border-box;

    margin: 0 0 10px;
    padding: 14px;

    border: 1px solid var(--border);
    border-radius: 15px;

    background: var(--surface);

    box-shadow:
        0 3px 12px rgba(0, 0, 0, .035);
}

.filter-group:last-child {
    margin-bottom: 0;
}


/* =========================================================
   SECTION TITLE
   ========================================================= */

.filter-group-title {
    position: relative;

    display: flex;
    align-items: center;

    margin: 0 0 11px;
    padding-right: 9px;

    color: var(--text-primary);

    font-size: 14px;
    font-weight: 850;

    line-height: 1.5;
}

.filter-group-title::before {
    content: "";

    position: absolute;

    right: 0;
    top: 2px;

    width: 3px;
    height: 17px;

    border-radius: 10px;

    background: var(--primary);
}

.filter-group-title::after {
    content: "";

    flex: 1;

    height: 1px;

    margin-right: 10px;

    background: var(--border);
}


/* =========================================================
   GRID
   ========================================================= */

.row-half {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 10px;
}

.row-third {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 10px;
}


/* =========================================================
   FORM GROUP
   ========================================================= */

.form-group {
    display: flex;
    flex-direction: column;

    gap: 4px;

    margin: 0;
}

.form-group label {
    display: block;

    color: var(--text-secondary);

    font-size: 10px;
    font-weight: 700;

    line-height: 1.4;
}


/* =========================================================
   INPUT / SELECT
   ========================================================= */

.form-input,
.form-select {
    width: 100%;
    height: 42px;

    box-sizing: border-box;

    padding: 0 11px;

    border: 1px solid var(--border);
    border-radius: 10px;

    outline: none;

    background: var(--bg);
    color: var(--text-primary);

    font-family: 'Vazirmatn', sans-serif;
    font-size: 12px;

    transition:
        border-color .18s ease,
        box-shadow .18s ease,
        background .18s ease;
}

.form-input::placeholder {
    color: var(--text-secondary);
    opacity: .55;
}

.form-input:hover,
.form-select:hover {
    border-color: var(--primary);
}

.form-input:focus,
.form-select:focus {
    border-color: var(--primary);

    background: var(--surface);

    box-shadow:
        0 0 0 3px
        color-mix(
            in srgb,
            var(--primary) 9%,
            transparent
        );
}


/* =========================================================
   RANGE HINT
   ========================================================= */

.range-hint {
    margin: -3px 0 9px;

    color: var(--text-secondary);

    font-size: 9px;

    line-height: 1.5;
}


/* =========================================================
   FEATURE ROW
   ========================================================= */

.feature-row {
    display: flex;

    align-items: center;
    justify-content: space-between;

    min-height: 43px;

    padding: 4px 1px;

    box-sizing: border-box;

    border-top: 1px solid var(--border);
}

.feature-info {
    display: flex;

    align-items: center;

    gap: 8px;
}

.feature-icon {
    width: 29px;
    height: 29px;

    display: flex;

    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 8px;

    background:
        color-mix(
            in srgb,
            var(--primary) 9%,
            var(--bg)
        );

    color: var(--primary);

    font-size: 14px;
}

.switch-label {
    color: var(--text-primary);

    font-size: 11px;
    font-weight: 700;
}


/* =========================================================
   SWITCH
   ========================================================= */

.switch {
    position: relative;

    display: inline-block;

    width: 42px;
    height: 23px;

    flex-shrink: 0;
}

.switch input {
    width: 0;
    height: 0;

    opacity: 0;
}

.slider {
    position: absolute;

    inset: 0;

    cursor: pointer;

    border-radius: 30px;

    background: var(--border);

    transition: .2s ease;
}

.slider::before {
    content: "";

    position: absolute;

    left: 3px;
    bottom: 3px;

    width: 17px;
    height: 17px;

    border-radius: 50%;

    background: #fff;

    box-shadow:
        0 1px 4px rgba(0,0,0,.18);

    transition: .2s ease;
}

.switch input:checked + .slider {
    background: var(--primary);
}

.switch input:checked + .slider::before {
    transform: translateX(19px);
}


/* =========================================================
   SEARCH ACTIONS
   ========================================================= */

.search-actions {
    position: fixed;

    left: 0;
    right: 0;

    bottom:
        var(--melkino-footer-height, 75px);

    width: 100%;

    display: flex;

    align-items: center;

    gap: 9px;

    padding: 9px 14px;

    box-sizing: border-box;

    background:
        color-mix(
            in srgb,
            var(--bg) 94%,
            transparent
        );

    border-top: 1px solid var(--border);

    backdrop-filter:
        blur(10px);

    -webkit-backdrop-filter:
        blur(10px);

    z-index: 999;

    box-shadow:
        0 -5px 18px rgba(0, 0, 0, .07);
}


/* =========================================================
   RESET
   ========================================================= */

.search-actions .btn-reset {
    height: 46px;

    flex: 0 0 auto;

    padding: 0 16px;

    border: 1px solid var(--border);

    border-radius: 12px;

    background: var(--surface);

    color: var(--text-secondary);

    font-family: 'Vazirmatn', sans-serif;

    font-size: 11px;
    font-weight: 700;

    cursor: pointer;

    transition:
        border-color .18s ease,
        color .18s ease,
        transform .18s ease,
        background .18s ease;
}

.search-actions .btn-reset:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.search-actions .btn-reset:active {
    transform: scale(.97);
}


/* =========================================================
   PRIMARY SEARCH BUTTON
   ========================================================= */

.search-actions .btn-primary-full {
    flex: 1;

    height: 46px;

    display: flex;

    align-items: center;
    justify-content: center;

    gap: 7px;

    padding: 0 14px;

    border: 0;

    border-radius: 12px;

    background:
        linear-gradient(
            135deg,
            var(--primary),
            color-mix(
                in srgb,
                var(--primary) 82%,
                #000 18%
            )
        );

    color: #fff;

    font-family: 'Vazirmatn', sans-serif;

    font-size: 13px;
    font-weight: 850;

    cursor: pointer;

    box-shadow:
        0 5px 15px
        color-mix(
            in srgb,
            var(--primary) 22%,
            transparent
        );

    transition:
        transform .18s ease,
        opacity .18s ease,
        box-shadow .18s ease;
}

.search-actions .btn-primary-full::before {
    content: "⌕";

    font-size: 19px;

    line-height: 1;
}

.search-actions .btn-primary-full:hover {
    box-shadow:
        0 7px 18px
        color-mix(
            in srgb,
            var(--primary) 27%,
            transparent
        );

    transform: translateY(-1px);
}

.search-actions .btn-primary-full:active {
    transform: scale(.985);
    opacity: .9;
}


/* =========================================================
   DESKTOP
   ========================================================= */

@media (min-width: 900px) {

    .main-content {
        padding:
            18px 20px 0;
    }

    .search-content {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        align-items: start;

        gap: 12px;
    }

    /*
     * بعضی بخش‌ها تمام عرض باشند
     */

    .filter-group:nth-child(1),
    .filter-group:nth-child(5) {
        grid-column: 1 / -1;
    }

    .filter-group {
        margin-bottom: 0;
        padding: 15px;
    }

    .search-actions {
        padding-left: 20px;
        padding-right: 20px;
    }
}


/* =========================================================
   TABLET
   ========================================================= */

@media (min-width: 521px) and (max-width: 899px) {

    .main-content {
        padding:
            12px 14px 0;
    }

    .search-content {
        max-width: 760px;
    }

    .filter-group {
        padding: 13px;
    }
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 520px) {

    .main-content {
        padding:
            7px 9px 0;
    }

    .search-content {
        padding-bottom: 128px;
    }

    .filter-group {
        padding: 11px;

        margin-bottom: 7px;

        border-radius: 13px;
    }

    .filter-group-title {
        font-size: 12px;

        margin-bottom: 8px;
    }

    .filter-group-title::before {
        height: 15px;
    }

    .row-half {
        gap: 7px;
    }

    .form-group {
        gap: 3px;
    }

    .form-group label {
        font-size: 9px;
    }

    .form-input,
    .form-select {
        height: 39px;

        padding:
            0 9px;

        border-radius: 9px;

        font-size: 11px;
    }

    .range-hint {
        font-size: 8px;

        margin-bottom: 7px;
    }

    .feature-row {
        min-height: 39px;
    }

    .feature-icon {
        width: 26px;
        height: 26px;

        font-size: 12px;
    }

    .switch-label {
        font-size: 10px;
    }

    .switch {
        width: 39px;
        height: 21px;
    }

    .slider::before {
        width: 15px;
        height: 15px;
    }

    .switch input:checked + .slider::before {
        transform: translateX(18px);
    }

    .search-actions {
        bottom:
            var(--melkino-footer-height, 75px);

        gap: 7px;

        padding:
            7px 9px;
    }

    .search-actions .btn-reset,
    .search-actions .btn-primary-full {
        height: 43px;
    }

    .search-actions .btn-reset {
        padding:
            0 11px;

        font-size: 10px;
    }

    .search-actions .btn-primary-full {
        font-size: 12px;
    }
}


/* =========================================================
   VERY SMALL MOBILE
   ========================================================= */

@media (max-width: 360px) {

    .row-half {
        grid-template-columns:
            1fr;

        gap: 5px;
    }

    .filter-group {
        padding: 10px;
    }

    .search-actions .btn-reset {
        padding:
            0 9px;
    }
}


/* =========================================================
   DARK MODE
   ========================================================= */

/* =========================================================
   ADVANCED SEARCH ACCORDION
   ========================================================= */

.adv-search {
    overflow: hidden;
}

.adv-search-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    cursor: pointer;
    text-align: right;
}

.adv-search-btn:disabled {
    cursor: not-allowed;
    opacity: .55;
}

.adv-search-btn-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.adv-search-chevron {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    background: color-mix(in srgb, var(--primary) 12%, var(--bg));
    color: var(--primary);
    font-size: 12px;
    transition: transform .22s ease;
}

.adv-search.is-open .adv-search-chevron {
    transform: rotate(180deg);
}

.adv-search-hint {
    margin: 8px 0 0;
    color: var(--text-secondary);
    font-size: 11px;
    line-height: 1.7;
}

.adv-search-body {
    display: none;
    padding-top: 12px;
}

.adv-search.is-open .adv-search-body {
    display: block;
}

.adv-pane {
    display: none;
}

.adv-pane.is-on {
    display: block;
}

.adv-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
}

.adv-chip {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--bg);
    font-size: 12px;
    color: var(--text-primary);
    cursor: pointer;
}

.adv-chip input {
    width: 15px;
    height: 15px;
    accent-color: var(--primary);
}

[data-theme="dark"] .filter-group {
    box-shadow:
        0 4px 14px rgba(0, 0, 0, .16);
}

[data-theme="dark"] .form-input,
[data-theme="dark"] .form-select {
    background:
        color-mix(
            in srgb,
            var(--bg) 90%,
            #fff 10%
        );
}

[data-theme="dark"] .search-actions {
    background:
        color-mix(
            in srgb,
            var(--bg) 94%,
            #000 6%
        );

    box-shadow:
        0 -5px 20px rgba(0, 0, 0, .24);
}

</style>


<div class="main-content">

    <form
        method="GET"
        action="search-results.php"
        id="searchForm"
        data-tour="home-search"
    >

        <div class="search-content">

            <!-- =================================================
                 نوع ملک و معامله
                 ================================================= -->

            <div class="filter-group">

                <div class="filter-group-title">
                    نوع ملک و معامله
                </div>

                <div class="row-half">

                    <div class="form-group">

                        <label for="property_type">
                            نوع ملک
                        </label>

                        <select
                            class="form-select"
                            name="property_type"
                            id="property_type"
                        >

                            <option value="">
                                همه
                            </option>

                            <option value="apartment">
                                آپارتمان
                            </option>

                            <option value="villa">
                                ویلا
                            </option>

                            <option value="commercial">
                                تجاری
                            </option>

                            <option value="land">
                                زمین
                            </option>

                            <option value="garden">
                                باغ
                            </option>

                            <option value="office">
                                اداری
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="transaction_type">
                            نوع معامله
                        </label>

                        <select
                            class="form-select"
                            name="transaction_type"
                            id="transaction_type"
                        >

                            <option value="">
                                همه
                            </option>

                            <option value="sell">
                                خرید و فروش
                            </option>

                            <option value="pre_sell">
                                پیش فروش
                            </option>

                            <option value="rent">
                                اجاره
                            </option>

                        </select>

                    </div>

                </div>

            </div>


            <div class="filter-group">
                <div class="filter-group-title">محدوده روی نقشه</div>
                <p style="font-size:12px;line-height:1.8;color:var(--text-secondary);margin:0 0 8px;">چهار گوشهٔ محدوده را روی نقشه بزنید. اگر خالی بگذارید در کل شهر جستجو می‌شود.</p>
                <input type="hidden" name="map_poly" id="map_poly" value="">
                <div id="mkPolyMap" style="height:220px;border-radius:14px;overflow:hidden;border:1px solid var(--border);margin-bottom:8px;"></div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <button type="button" class="btn-secondary" id="mkPolyReset">شروع دوباره</button>
                    <span id="mkPolyStatus" style="font-size:12px;color:var(--text-secondary);">نقطه ۱ از ۴ را روی نقشه بزنید یا خالی بگذارید.</span>
                </div>
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                <script src="map-polygon-picker.js?v=<?php echo (int)@filemtime(__DIR__ . '/map-polygon-picker.js'); ?>"></script>
            </div>


            <!-- =================================================
                 قیمت / رهن و اجاره
                 ================================================= -->

            <div class="filter-group" id="priceSellGroup">
                <div class="filter-group-title">بازه قیمت</div>
                <div class="range-hint">قیمت را به تومان وارد کنید</div>
                <div class="row-half">
                    <div class="form-group">
                        <label for="price_min">حداقل</label>
                        <input type="text" class="form-input" name="price_min" id="price_min" inputmode="numeric" autocomplete="off" placeholder="۵۰۰,۰۰۰,۰۰۰">
                    </div>
                    <div class="form-group">
                        <label for="price_max">حداکثر</label>
                        <input type="text" class="form-input" name="price_max" id="price_max" inputmode="numeric" autocomplete="off" placeholder="۵,۰۰۰,۰۰۰,۰۰۰">
                    </div>
                </div>
            </div>

            <div class="filter-group" id="priceRentGroup" hidden>
                <div class="filter-group-title">ودیعه و اجاره</div>
                <div class="range-hint">مبالغ را به تومان وارد کنید</div>
                <div class="row-half">
                    <div class="form-group">
                        <label for="deposit_min">حداقل ودیعه</label>
                        <input type="text" class="form-input price-fmt" name="deposit_min" id="deposit_min" inputmode="numeric" autocomplete="off" placeholder="۲۰۰,۰۰۰,۰۰۰">
                    </div>
                    <div class="form-group">
                        <label for="deposit_max">حداکثر ودیعه</label>
                        <input type="text" class="form-input price-fmt" name="deposit_max" id="deposit_max" inputmode="numeric" autocomplete="off" placeholder="۵۰۰,۰۰۰,۰۰۰">
                    </div>
                </div>
                <div class="row-half">
                    <div class="form-group">
                        <label for="rent_min">حداقل اجاره ماهانه</label>
                        <input type="text" class="form-input price-fmt" name="rent_min" id="rent_min" inputmode="numeric" autocomplete="off" placeholder="۲۰,۰۰۰,۰۰۰">
                    </div>
                    <div class="form-group">
                        <label for="rent_max">حداکثر اجاره ماهانه</label>
                        <input type="text" class="form-input price-fmt" name="rent_max" id="rent_max" inputmode="numeric" autocomplete="off" placeholder="۵۰,۰۰۰,۰۰۰">
                    </div>
                </div>
            </div>


            <!-- =================================================
                 متراژ
                 ================================================= -->

            <div class="filter-group">
                <div class="filter-group-title">بازه متراژ</div>
                <div class="range-hint">متراژ به متر مربع</div>
                <div class="row-half">
                    <div class="form-group">
                        <label for="area_min">حداقل</label>
                        <input type="text" inputmode="numeric" class="form-input" name="area_min" id="area_min" placeholder="۵۰">
                    </div>
                    <div class="form-group">
                        <label for="area_max">حداکثر</label>
                        <input type="text" inputmode="numeric" class="form-input" name="area_max" id="area_max" placeholder="۳۰۰">
                    </div>
                </div>
            </div>


            <!-- =================================================
                 مشخصات اصلی (اتاق / پارکینگ / آسانسور / سن)
                 ================================================= -->

            <div class="filter-group" id="coreSpecsGroup" hidden>
                <div class="filter-group-title">مشخصات اصلی</div>

                <div class="form-group" id="roomsWrap">
                    <label for="bedrooms">تعداد اتاق</label>
                    <select class="form-select" name="bedrooms" id="bedrooms">
                        <option value="">بدون محدودیت</option>
                        <option value="1">۱ اتاق</option>
                        <option value="2">۲ اتاق</option>
                        <option value="3">۳ اتاق</option>
                        <option value="4">۴ اتاق</option>
                        <option value="5">۵ اتاق و بیشتر</option>
                    </select>
                </div>

                <div class="row-half" id="ageWrap">
                    <div class="form-group">
                        <label for="min_age">حداقل سن بنا</label>
                        <select class="form-select" name="min_age" id="min_age">
                            <option value="">بدون محدودیت</option>
                            <option value="0">نوساز</option>
                            <option value="5">۵ سال</option>
                            <option value="10">۱۰ سال</option>
                            <option value="15">۱۵ سال</option>
                            <option value="20">۲۰ سال</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="max_age">حداکثر سن بنا</label>
                        <select class="form-select" name="max_age" id="max_age">
                            <option value="">بدون محدودیت</option>
                            <option value="5">تا ۵ سال</option>
                            <option value="10">تا ۱۰ سال</option>
                            <option value="15">تا ۱۵ سال</option>
                            <option value="20">تا ۲۰ سال</option>
                            <option value="30">تا ۳۰ سال</option>
                        </select>
                    </div>
                </div>

                <div class="feature-row" id="parkingWrap">
                    <div class="feature-info">
                        <div class="feature-icon">🚗</div>
                        <span class="switch-label">پارکینگ</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="parking" value="1">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="feature-row" id="elevatorWrap">
                    <div class="feature-info">
                        <div class="feature-icon">🛗</div>
                        <span class="switch-label">آسانسور</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="elevator" value="1">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="feature-row" id="keyedWrap">
                    <div class="feature-info">
                        <div class="feature-icon">🔑</div>
                        <span class="switch-label">کلید نخورده</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="is_not_keyed" value="1">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>


            <!-- =================================================
                 جستجوی پیشرفته (فیلدهای همان نوع ملک)
                 ================================================= -->

            <div class="filter-group adv-search" id="advSearch">
                <button type="button" class="adv-search-btn" id="advSearchBtn" disabled aria-expanded="false" aria-controls="advSearchBody">
                    <span class="filter-group-title adv-search-btn-title" style="margin:0;flex:1;">جستجوی پیشرفته</span>
                    <span class="adv-search-chevron" aria-hidden="true">▼</span>
                </button>
                <p class="adv-search-hint" id="advSearchHint">ابتدا نوع ملک را انتخاب کنید تا فیلدهای دقیق همان مدل باز شود.</p>

                <div class="adv-search-body" id="advSearchBody">

                    <div class="adv-pane" data-type="apartment">
                        <div class="row-half">
                            <div class="form-group">
                                <label for="apartment_type">نوع آپارتمان</label>
                                <select class="form-select" name="apartment_type" id="apartment_type">
                                    <option value="">بدون محدودیت</option>
                                    <option value="فلت">فلت</option>
                                    <option value="دوبلکس">دوبلکس</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="units_per_floor">تعداد واحد در طبقه</label>
                                <select class="form-select" name="units_per_floor" id="units_per_floor">
                                    <option value="">بدون محدودیت</option>
                                    <option value="تک واحد">تک واحد</option>
                                    <option value="دو واحدی">دو واحدی</option>
                                    <option value="سه واحدی">سه واحدی</option>
                                    <option value="چهار واحدی">چهار واحدی</option>
                                    <option value="بیشتر">بیشتر</option>
                                </select>
                            </div>
                        </div>
                        <div class="row-half">
                            <div class="form-group">
                                <label for="floor_min">حداقل طبقه</label>
                                <input type="text" inputmode="numeric" class="form-input" name="floor_min" id="floor_min" placeholder="۱">
                            </div>
                            <div class="form-group">
                                <label for="floor_max">حداکثر طبقه</label>
                                <input type="text" inputmode="numeric" class="form-input" name="floor_max" id="floor_max" placeholder="۱۰">
                            </div>
                        </div>
                        <div class="row-half">
                            <div class="form-group">
                                <label for="flooring">نوع کفپوش</label>
                                <select class="form-select" name="flooring" id="flooring">
                                    <option value="">بدون محدودیت</option>
                                    <option value="سرامیک">سرامیک</option>
                                    <option value="پارکت">پارکت</option>
                                    <option value="موکت">موکت</option>
                                    <option value="سنگ">سنگ</option>
                                    <option value="کفپوش">کفپوش</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="cabinet">نوع کابینت</label>
                                <select class="form-select" name="cabinet" id="cabinet">
                                    <option value="">بدون محدودیت</option>
                                    <option value="ام دی اف">ام دی اف</option>
                                    <option value="هایگلاس">هایگلاس</option>
                                    <option value="چوبی">چوبی</option>
                                    <option value="فلزی">فلزی</option>
                                </select>
                            </div>
                        </div>
                        <div class="row-half">
                            <div class="form-group">
                                <label for="cooling">سیستم سرمایش</label>
                                <select class="form-select" name="cooling" id="cooling">
                                    <option value="">بدون محدودیت</option>
                                    <option value="کولر آبی">کولر آبی</option>
                                    <option value="اسپیلت">اسپیلت</option>
                                    <option value="داکت اسپلیت">داکت اسپلیت</option>
                                    <option value="چیلر">چیلر</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="heating">سیستم گرمایش</label>
                                <select class="form-select" name="heating" id="heating">
                                    <option value="">بدون محدودیت</option>
                                    <option value="بخاری">بخاری</option>
                                    <option value="شوفاژ">شوفاژ</option>
                                    <option value="پکیج رادیاتور">پکیج رادیاتور</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="adv-pane" data-type="villa">
                        <div class="form-group">
                            <label for="villa_type">نوع ویلایی</label>
                            <select class="form-select" name="villa_type" id="villa_type">
                                <option value="">بدون محدودیت</option>
                                <option value="فلت">فلت</option>
                                <option value="دوبلکس">دوبلکس</option>
                                <option value="تریبلکس">تریبلکس</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="built_area_min">حداقل زیربنا (متر مربع)</label>
                            <input type="text" inputmode="numeric" class="form-input" name="built_area_min" id="built_area_min" placeholder="۱۵۰">
                        </div>
                        <div class="row-half">
                            <div class="form-group">
                                <label for="flooring_villa">نوع کفپوش</label>
                                <select class="form-select" name="flooring" id="flooring_villa">
                                    <option value="">بدون محدودیت</option>
                                    <option value="سرامیک">سرامیک</option>
                                    <option value="پارکت">پارکت</option>
                                    <option value="سنگ">سنگ</option>
                                    <option value="کفپوش">کفپوش</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="cabinet_villa">نوع کابینت</label>
                                <select class="form-select" name="cabinet" id="cabinet_villa">
                                    <option value="">بدون محدودیت</option>
                                    <option value="ام دی اف">ام دی اف</option>
                                    <option value="هایگلاس">هایگلاس</option>
                                    <option value="چوبی">چوبی</option>
                                    <option value="فلزی">فلزی</option>
                                </select>
                            </div>
                        </div>
                        <div class="row-half">
                            <div class="form-group">
                                <label for="cooling_villa">سیستم سرمایش</label>
                                <select class="form-select" name="cooling" id="cooling_villa">
                                    <option value="">بدون محدودیت</option>
                                    <option value="کولر آبی">کولر آبی</option>
                                    <option value="اسپیلت">اسپیلت</option>
                                    <option value="داکت اسپلیت">داکت اسپلیت</option>
                                    <option value="چیلر">چیلر</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="heating_villa">سیستم گرمایش</label>
                                <select class="form-select" name="heating" id="heating_villa">
                                    <option value="">بدون محدودیت</option>
                                    <option value="بخاری">بخاری</option>
                                    <option value="شوفاژ">شوفاژ</option>
                                    <option value="پکیج رادیاتور">پکیج رادیاتور</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="adv-pane" data-type="commercial">
                        <div class="row-half">
                            <div class="form-group">
                                <label for="front_min">حداقل بر مغازه (متر)</label>
                                <input type="text" inputmode="numeric" class="form-input" name="front_min" id="front_min" placeholder="۴">
                            </div>
                            <div class="form-group">
                                <label for="location_type">موقعیت</label>
                                <select class="form-select" name="location_type" id="location_type">
                                    <option value="">بدون محدودیت</option>
                                    <option value="خیابان اصلی">خیابان اصلی</option>
                                    <option value="خیابان فرعی">خیابان فرعی</option>
                                    <option value="پاساژ">پاساژ</option>
                                    <option value="گاراژ">گاراژ</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="adv-pane" data-type="land">
                        <div class="form-group">
                            <label for="land_type">نوع کاربری</label>
                            <select class="form-select" name="land_type" id="land_type">
                                <option value="">بدون محدودیت</option>
                                <option value="مسکونی">مسکونی</option>
                                <option value="تجاری">تجاری</option>
                                <option value="کشاورزی">کشاورزی</option>
                                <option value="باغی">باغی</option>
                            </select>
                        </div>
                        <div class="row-half">
                            <div class="form-group">
                                <label for="land_width_min">حداقل عرض (متر)</label>
                                <input type="text" inputmode="numeric" class="form-input" name="land_width_min" id="land_width_min" placeholder="۱۲">
                            </div>
                            <div class="form-group">
                                <label for="land_length_min">حداقل طول (متر)</label>
                                <input type="text" inputmode="numeric" class="form-input" name="land_length_min" id="land_length_min" placeholder="۲۰">
                            </div>
                        </div>
                        <div class="row-half">
                            <div class="form-group">
                                <label for="land_deed_status">وضعیت سند</label>
                                <select class="form-select" name="land_deed_status" id="land_deed_status">
                                    <option value="">بدون محدودیت</option>
                                    <option value="سند رسمی">سند رسمی</option>
                                    <option value="سند عادی">سند عادی</option>
                                    <option value="قولنامه">قولنامه</option>
                                    <option value="در دست اقدام">در دست اقدام</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="land_ownership">وضعیت مالکیت</label>
                                <select class="form-select" name="land_ownership" id="land_ownership">
                                    <option value="">بدون محدودیت</option>
                                    <option value="شش‌دانگ">شش‌دانگ</option>
                                    <option value="مشاع">مشاع</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="adv-pane" data-type="garden">
                        <div class="form-group">
                            <label for="tree_types">نوع درختان</label>
                            <input type="text" class="form-input" name="tree_types" id="tree_types" placeholder="گردو، سیب، ...">
                        </div>
                        <div class="form-group">
                            <label for="irrigation_type">نوع آبیاری</label>
                            <select class="form-select" name="irrigation_type" id="irrigation_type">
                                <option value="">بدون محدودیت</option>
                                <option value="قطره‌ای">قطره‌ای</option>
                                <option value="بارانی">بارانی</option>
                                <option value="جوی و پشته">جوی و پشته</option>
                                <option value="آبیاری تحت فشار">آبیاری تحت فشار</option>
                                <option value="سطحی">سطحی</option>
                                <option value="ترکیبی">ترکیبی</option>
                                <option value="غرقابی">غرقابی</option>
                            </select>
                        </div>
                        <div class="feature-row">
                            <div class="feature-info">
                                <div class="feature-icon">💧</div>
                                <span class="switch-label">آب ملکی / چاه</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="has_well" value="1">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="adv-pane" data-type="office">
                        <div class="row-half">
                            <div class="form-group">
                                <label for="floor_min_off">حداقل طبقه</label>
                                <input type="text" inputmode="numeric" class="form-input" name="floor_min" id="floor_min_off" placeholder="۱">
                            </div>
                            <div class="form-group">
                                <label for="units_per_floor_off">تعداد واحد در طبقه</label>
                                <input type="text" inputmode="numeric" class="form-input" name="units_per_floor" id="units_per_floor_off" placeholder="۴">
                            </div>
                        </div>
                        <div class="row-half">
                            <div class="form-group">
                                <label for="office_usage">کاربری</label>
                                <select class="form-select" name="office_usage" id="office_usage">
                                    <option value="">بدون محدودیت</option>
                                    <option value="اداری">اداری</option>
                                    <option value="دفتر کار">دفتر کار</option>
                                    <option value="تجاری-اداری">تجاری-اداری</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="orientation">موقعیت واحد</label>
                                <select class="form-select" name="office_orientation" id="office_orientation">
                                    <option value="">بدون محدودیت</option>
                                    <option value="شمالی">شمالی</option>
                                    <option value="جنوبی">جنوبی</option>
                                    <option value="شرقی">شرقی</option>
                                    <option value="غربی">غربی</option>
                                </select>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        <!-- =================================================
             ACTION BUTTONS
             ================================================= -->

        <div class="search-actions">

            <button
                type="button"
                class="btn-reset"
                onclick="resetSearch()"
            >
                حذف فیلترها
            </button>

            <button
                type="submit"
                class="btn-primary-full"
            >
                جستجوی املاک
            </button>

        </div>

    </form>

</div>


<script>

/* =========================================================
   RESET SEARCH
   ========================================================= */

function resetSearch() {

    const form =
        document.getElementById('searchForm');

    if (!form) {
        return;
    }

    form.reset();


    form.querySelectorAll(
        'input[type="checkbox"]'
    ).forEach(function (checkbox) {

        checkbox.checked = false;

    });

    if (typeof syncAdvancedSearch === 'function') {
        syncAdvancedSearch({ close: true });
    }
    if (typeof syncCoreAndPrice === 'function') {
        syncCoreAndPrice();
    }

}

/* =========================================================
   ADVANCED SEARCH ACCORDION
   ========================================================= */

function syncAdvancedSearch(opts) {
    opts = opts || {};
    var box = document.getElementById('advSearch');
    var btn = document.getElementById('advSearchBtn');
    var hint = document.getElementById('advSearchHint');
    var typeEl = document.getElementById('property_type');
    if (!box || !btn || !typeEl) return;

    var type = (typeEl.value || '').trim();
    var labels = {
        apartment: 'آپارتمان',
        villa: 'ویلا',
        commercial: 'تجاری',
        land: 'زمین',
        garden: 'باغ',
        office: 'اداری'
    };

    btn.disabled = !type;

    var panes = box.querySelectorAll('.adv-pane');
    panes.forEach(function (pane) {
        var on = type !== '' && pane.getAttribute('data-type') === type;
        pane.classList.toggle('is-on', on);
        pane.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = !on;
        });
    });

    if (!type) {
        box.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        if (hint) {
            hint.style.display = '';
            hint.textContent = 'ابتدا نوع ملک را انتخاب کنید تا فیلدهای دقیق همان مدل باز شود.';
        }
        return;
    }

    if (hint) {
        hint.style.display = box.classList.contains('is-open') ? 'none' : '';
        hint.textContent = 'فیلدهای «' + (labels[type] || type) + '» را با دکمهٔ بالا باز کنید.';
    }

    if (opts.close) {
        box.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        if (hint) hint.style.display = '';
    }
}

(function () {
    var box = document.getElementById('advSearch');
    var btn = document.getElementById('advSearchBtn');
    var typeEl = document.getElementById('property_type');
    if (!box || !btn || !typeEl) return;

    btn.addEventListener('click', function () {
        if (btn.disabled) return;
        var open = !box.classList.contains('is-open');
        box.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        var hint = document.getElementById('advSearchHint');
        if (hint) hint.style.display = open ? 'none' : '';
    });

    typeEl.addEventListener('change', function () {
        var hadOpen = box.classList.contains('is-open');
        syncAdvancedSearch();
        syncCoreAndPrice();
        if (typeEl.value && hadOpen) {
            box.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');
            var hint = document.getElementById('advSearchHint');
            if (hint) hint.style.display = 'none';
        }
    });

    var tx = document.getElementById('transaction_type');
    if (tx) tx.addEventListener('change', syncCoreAndPrice);

    syncAdvancedSearch();
    syncCoreAndPrice();
})();

function setDisabled(root, off) {
    if (!root) return;
    root.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.disabled = !!off;
    });
}

function syncCoreAndPrice() {
    var typeEl = document.getElementById('property_type');
    var txEl = document.getElementById('transaction_type');
    var type = typeEl ? (typeEl.value || '').trim() : '';
    var tx = txEl ? (txEl.value || '').trim() : '';

    var sell = document.getElementById('priceSellGroup');
    var rent = document.getElementById('priceRentGroup');
    var isRent = tx === 'rent';
    if (sell) {
        sell.hidden = isRent;
        setDisabled(sell, isRent);
    }
    if (rent) {
        rent.hidden = !isRent;
        setDisabled(rent, !isRent);
    }

    var core = document.getElementById('coreSpecsGroup');
    var built = type === 'apartment' || type === 'villa' || type === 'office' || type === 'commercial';
    if (core) {
        core.hidden = !built;
        setDisabled(core, !built);
    }

    var rooms = document.getElementById('roomsWrap');
    var parking = document.getElementById('parkingWrap');
    var elevator = document.getElementById('elevatorWrap');
    var keyed = document.getElementById('keyedWrap');
    var age = document.getElementById('ageWrap');

    var showRooms = type === 'apartment' || type === 'villa' || type === 'office';
    var showPark = built;
    var showEl = type === 'apartment' || type === 'office' || type === 'commercial';
    var showAge = built;

    if (rooms) { rooms.hidden = !showRooms; setDisabled(rooms, !showRooms); }
    if (parking) { parking.hidden = !showPark; setDisabled(parking, !showPark); }
    if (elevator) { elevator.hidden = !showEl; setDisabled(elevator, !showEl); }
    if (keyed) { keyed.hidden = !showAge; setDisabled(keyed, !showAge); }
    if (age) { age.hidden = !showAge; setDisabled(age, !showAge); }
}


/* =========================================================
   PRICE FORMAT
   ========================================================= */

(function () {

    const inputs =
        document.querySelectorAll(
            'input[name="price_min"], input[name="price_max"], input.price-fmt'
        );

    const persianDigits =
        '۰۱۲۳۴۵۶۷۸۹';


    inputs.forEach(function (input) {

        input.addEventListener(
            'input',
            function () {

                let value =
                    this.value || '';


                /*
                 * حذف کاراکترهای غیر عدد
                 */
                value =
                    value.replace(
                        /[^\d۰-۹]/g,
                        ''
                    );


                /*
                 * تبدیل فارسی به انگلیسی
                 */
                value =
                    value.replace(
                        /[۰-۹]/g,
                        function (digit) {

                            return String(
                                persianDigits.indexOf(
                                    digit
                                )
                            );

                        }
                    );


                if (!value) {

                    this.value = '';

                    return;
                }


                /*
                 * جداکننده هزارگان
                 */
                this.value =
                    Number(value)
                        .toLocaleString('en-US');

            }
        );

    });

})();


/* =========================================================
   BEFORE SUBMIT
   جداکننده قیمت حذف می‌شود تا مقدار خام ارسال شود.
   ========================================================= */

document
    .getElementById('searchForm')
    ?.addEventListener(
        'submit',
        function () {

            const priceInputs =
                this.querySelectorAll(
                    'input[name="price_min"], input[name="price_max"], input.price-fmt'
                );


            priceInputs.forEach(
                function (input) {

                    input.value =
                        input.value.replace(
                            /,/g,
                            ''
                        );

                }
            );

        }
    );


/* =========================================================
   DETECT FOOTER HEIGHT
   ========================================================= */

(function () {

    function updateFooterHeight() {

        const footer =
            document.querySelector(
                '.bottom-nav'
            );


        if (!footer) {

            document.documentElement.style.setProperty(
                '--melkino-footer-height',
                '75px'
            );

            return;
        }


        const height =
            footer.getBoundingClientRect().height;


        document.documentElement.style.setProperty(
            '--melkino-footer-height',
            Math.ceil(height) + 'px'
        );

    }


    updateFooterHeight();


    window.addEventListener(
        'load',
        updateFooterHeight
    );


    window.addEventListener(
        'resize',
        updateFooterHeight
    );


    if (window.ResizeObserver) {

        const footer =
            document.querySelector(
                '.bottom-nav'
            );


        if (footer) {

            const observer =
                new ResizeObserver(
                    function () {

                        updateFooterHeight();

                    }
                );


            observer.observe(footer);
        }
    }


    setTimeout(
        updateFooterHeight,
        100
    );

    setTimeout(
        updateFooterHeight,
        400
    );

    setTimeout(
        updateFooterHeight,
        800
    );

})();

</script>


<script>
/* پذیرش ارقام فارسی/عربی در فیلدهای عددی جستجو */
(function () {
    var FA = '۰۱۲۳۴۵۶۷۸۹', AR = '٠١٢٣٤٥٦٧٨٩', EN = '0123456789';
    function toEn(v) {
        var s = String(v || '');
        for (var i = 0; i < 10; i++) {
            s = s.split(FA[i]).join(EN[i]).split(AR[i]).join(EN[i]);
        }
        return s;
    }
    document.addEventListener('input', function (e) {
        var t = e.target;
        if (t.tagName === 'INPUT' && t.getAttribute('inputmode') === 'numeric') {
            var cleaned = toEn(t.value).replace(/[^0-9]/g, '');
            if (cleaned !== (t.value || '')) t.value = cleaned;
        }
    });
})();
</script>

<?php

require_once __DIR__ . '/footer.php';

?>