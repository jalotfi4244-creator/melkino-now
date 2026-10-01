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
require_once __DIR__ . '/card-display.php';
if (is_file(__DIR__ . '/db-settings.php')) {
    require_once __DIR__ . '/db-settings.php';
}
require_once __DIR__ . '/ad-cards-bootstrap.php';
define('MELKINO_PROPERTIES_DATA_NO_OUTPUT', true);
require_once __DIR__ . '/properties-data.php';
$__listAds = [];
try {
    $__listAds = function_exists('melkinoPublishedAdsForCards')
        ? melkinoPublishedAdsForCards(isset($pdo) && $pdo instanceof PDO ? $pdo : null)
        : [];
} catch (Throwable $e) {
    $__listAds = [];
}
require_once __DIR__ . '/header.php';
?>

<style>
    .main-content {
        flex: 1;
        overflow-y: auto;
        padding-bottom: 80px;
        background: var(--bg);
    }
    .properties-page {
        width: 100%;
        max-width: 1100px;
        margin: 0 auto;
    }
    .properties-hero {
        position: relative;
        margin: var(--space-2) var(--space-3);
        padding: 22px 20px;
        border-radius: var(--radius-md);
        overflow: hidden;
        background:
            radial-gradient(circle at 85% 15%, rgba(212,175,55,.16), transparent 30%),
            linear-gradient(135deg, #073737, #052727 70%, #031C1C);
        border: 1px solid rgba(212,175,55,.12);
        box-shadow: var(--shadow-card);
    }
    .properties-hero-content { position: relative; z-index: 2; }
    .properties-kicker {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
        color: #F0D36A;
        font-size: 10px;
        font-weight: 800;
    }
    .properties-kicker-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #D4AF37;
        box-shadow: 0 0 10px rgba(212,175,55,.5);
    }
    .properties-title {
        margin: 0;
        color: #fff;
        font-size: clamp(22px, 6vw, 34px);
        line-height: 1.35;
        font-weight: 900;
    }
    .properties-title span { color: #F0D36A; }
    .properties-subtitle {
        margin: 7px 0 0;
        max-width: 650px;
        color: rgba(255,255,255,.58);
        font-size: 11px;
        line-height: 1.95;
    }
    .properties-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 0 var(--space-3);
        margin-bottom: var(--space-2);
        flex-wrap: wrap;
    }
    .properties-count {
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
    }
    .sort-box { display: flex; align-items: center; gap: 7px; }
    .sort-label { color: var(--text-secondary); font-size: 11px; white-space: nowrap; }
    .tx-chips {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .tx-chip {
        display: inline-flex;
        align-items: center;
        padding: 7px 16px;
        border-radius: 999px;
        background: var(--surface);
        border: 1px solid var(--border);
        color: var(--text-primary);
        font-size: 12.5px;
        font-weight: 800;
        cursor: pointer;
        text-decoration: none;
        transition: background .15s ease, border-color .15s ease;
    }
    .tx-chip.is-on {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }
    .sort-select {
        min-width: 150px;
        height: 40px;
        padding: 0 12px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--surface);
        color: var(--text-primary);
        font-family: 'Vazirmatn', sans-serif;
        font-size: 12px;
        outline: none;
    }
    #propertiesList.properties-list {
        padding: 0 var(--space-3);
        padding-bottom: 24px;
    }
    .properties-empty,
    .properties-error {
        grid-column: 1 / -1;
        margin: 0;
        padding: 55px 20px;
        text-align: center;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        color: var(--text-secondary);
    }
    .properties-empty h3 { margin: 0; color: var(--text-primary); font-size: 16px; }
    .properties-empty p { margin: 8px auto 0; max-width: 400px; line-height: 1.9; font-size: 12px; }
    .properties-loading {
        grid-column: 1 / -1;
        min-height: 230px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        color: var(--text-secondary);
        font-size: 12px;
    }
    .properties-loading-spinner {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        border: 3px solid rgba(212,175,55,.15);
        border-top-color: var(--gold, #D4AF37);
        animation: propertiesSpin .8s linear infinite;
    }
    @keyframes propertiesSpin { to { transform: rotate(360deg); } }
    @media (max-width: 480px) {
        .properties-hero { margin: 10px 12px 12px; padding: 18px 15px; }
        .properties-toolbar { flex-direction: column; align-items: stretch; padding: 0 12px; }
        .sort-select { width: 100%; }
        #propertiesList.properties-list { padding: 0 12px 24px; }
    }
    [data-theme="dark"] .properties-hero {
        background:
            radial-gradient(circle at 85% 15%, rgba(212,175,55,.10), transparent 30%),
            linear-gradient(135deg, #073535, #092727 70%, #071A1A);
        border-color: rgba(212,175,55,.13);
    }
</style>

<?= melkinoAdCardsHead('list') ?>

<div class="main-content">
    <div class="properties-page">
        <section class="properties-hero">
            <div class="properties-hero-content">
                <div class="properties-kicker">
                    <span class="properties-kicker-dot"></span>
                    ویترین ملکینو
                </div>
                <h1 class="properties-title">همه آگهی‌ها، <span>یک‌جا</span></h1>
                <p class="properties-subtitle">فایل‌های منتشرشده ملکینو را ببین، گزینه‌ها را مقایسه کن و ملک مناسب خودت را پیدا کن.</p>
            </div>
        </section>
        <div class="properties-toolbar">
            <div class="properties-count" id="propertiesCount">در حال بارگذاری...</div>
            <div class="tx-chips" id="txChips">
                <button type="button" class="tx-chip is-on" data-tx="all">همه</button>
                <button type="button" class="tx-chip" data-tx="فروش">فروش</button>
                <button type="button" class="tx-chip" data-tx="پیش فروش">پیش فروش</button>
                <button type="button" class="tx-chip" data-tx="اجاره">اجاره و رهن</button>
            </div>
            <div class="sort-box">
                <span class="sort-label">مرتب‌سازی:</span>
                <select id="sortSelect" class="sort-select">
                    <option value="newest">جدیدترین</option>
                    <option value="oldest">قدیمی‌ترین</option>
                    <option value="price_low">ارزان‌ترین</option>
                    <option value="price_high">گران‌ترین</option>
                    <option value="area_high">بیشترین متراژ</option>
                    <option value="area_low">کمترین متراژ</option>
                </select>
            </div>
        </div>
        <div id="propertiesList" class="properties-list">
            <div class="properties-loading">
                <div class="properties-loading-spinner"></div>
                <span>در حال بارگذاری آگهی‌ها...</span>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var listEl = document.getElementById('propertiesList');
    var countEl = document.getElementById('propertiesCount');
    var sortEl = document.getElementById('sortSelect');
    var allProperties = [];

    /* [TX-FILTER] فیلتر نوع معامله — از دکمه‌ها یا ?tx= هوم */
    var activeTx = 'all';
    try {
        var urlTx = new URLSearchParams(window.location.search).get('tx');
        if (urlTx) activeTx = urlTx;
    } catch (e) {}
    function txMatches(ad) {
        if (activeTx === 'all') return true;
        var tx = String(ad.transaction_type || '').trim();
        if (activeTx === 'اجاره') return tx.indexOf('اجاره') !== -1 || tx.indexOf('رهن') !== -1;
        return tx === activeTx;
    }
    function markTxChips() {
        document.querySelectorAll('#txChips .tx-chip').forEach(function (c) {
            c.classList.toggle('is-on', c.getAttribute('data-tx') === activeTx);
        });
    }
    document.querySelectorAll('#txChips .tx-chip').forEach(function (c) {
        c.addEventListener('click', function () {
            activeTx = c.getAttribute('data-tx') || 'all';
            markTxChips();
            render();
        });
    });
    markTxChips();

    function moneyOf(ad) {
        var n = Number(ad.priceNumeric || ad.price_sell || ad.total_price || ad.price || 0);
        return isFinite(n) ? n : 0;
    }
    function areaOf(ad) {
        var n = Number(ad.area || 0);
        return isFinite(n) ? n : 0;
    }
    function timeOf(ad) {
        var t = ad.timestamp || Date.parse(ad.created_at || '') || 0;
        return isFinite(t) ? t : 0;
    }
    function sortList(list) {
        var out = list.slice();
        var mode = sortEl ? sortEl.value : 'newest';
        out.sort(function (a, b) {
            if (mode === 'oldest') return timeOf(a) - timeOf(b);
            if (mode === 'price_low') return moneyOf(a) - moneyOf(b);
            if (mode === 'price_high') return moneyOf(b) - moneyOf(a);
            if (mode === 'area_high') return areaOf(b) - areaOf(a);
            if (mode === 'area_low') return areaOf(a) - areaOf(b);
            return timeOf(b) - timeOf(a);
        });
        return out;
    }
    function render() {
        var filtered = allProperties.filter(txMatches);
        var sorted = sortList(filtered);
        if (countEl) countEl.textContent = sorted.length + ' آگهی منتشرشده';
        if (!listEl) return;
        if (!sorted.length) {
            listEl.innerHTML = '<div class="properties-empty"><h3>هنوز آگهی منتشرشده‌ای وجود ندارد</h3><p>به‌محض انتشار فایل‌های جدید، ویترین ملکینو در اینجا به‌روزرسانی می‌شود.</p></div>';
            return;
        }
        if (typeof window.mkRenderAdCards === 'function') {
            window.mkRenderAdCards(listEl, sorted);
        }
    }
    function load() {
        fetch('properties-data.php', { cache: 'no-store' })
            .then(function (r) { if (!r.ok) throw new Error('fail'); return r.json(); })
            .then(function (data) {
                if (!Array.isArray(data)) throw new Error('bad');
                allProperties = data.filter(function (ad) {
                    return (ad.status || 'published') === 'published';
                });
                render();
            })
            .catch(function () {
                if (countEl) countEl.textContent = 'خطا در بارگذاری';
                if (listEl) listEl.innerHTML = '<div class="properties-error"><strong>بارگذاری آگهی‌ها انجام نشد</strong><div>اتصال دیتابیس و جدول ads را بررسی کنید.</div></div>';
            });
    }
    if (sortEl) sortEl.addEventListener('change', render);
    load();
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
