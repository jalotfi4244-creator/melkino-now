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
    if (!headers_sent()) {
        header('Location: login.php?redirect=' . rawurlencode($here), true, 302);
    }
    exit;
}
unset($_mkPage, $_mkAllow);

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/map-lib.php';
require_once __DIR__ . '/map-markers.php';
require_once __DIR__ . '/design-studio-lib.php';
global $pdo;
$mapSettings = ($pdo instanceof PDO) ? melkinoMapPublicSettings($pdo) : melkinoMapDefaults();

/*
 * تم نقشه (کارت بازشونده + شکل مارکر) از همان تم استودیو خوانده می‌شود.
 * پیش‌نویس اولویت دارد تا ادمین تغییرش را بلافاصله ببیند — دقیقاً همان
 * منطقی که ad-cards-bootstrap.php برای کارت‌های سایت دارد.
 */
$mkStudioTheme = melkinoStudioDefaultTheme();
try {
    if ($pdo instanceof PDO && function_exists('dbSettingGet')) {
        $rawDraft = dbSettingGet($pdo, 'theme', 'studio_draft', null);
        $rawPub   = dbSettingGet($pdo, 'theme', 'studio_published', null);
        $rawTheme = (is_array($rawDraft) && !empty($rawDraft['layout'])) ? $rawDraft : $rawPub;
        if (is_array($rawTheme)) {
            $mkStudioTheme = melkinoStudioSanitize($rawTheme);
        }
    }
} catch (Throwable $e) {
    // تم پیش‌فرض کافی است؛ نقشه هرگز به‌خاطر تم نباید بشکند
}
$mkMapTheme = [
    'mapCard' => $mkStudioTheme['mapCard'],
    'marker'  => $mkStudioTheme['marker'],
    'colors'  => ['primary' => $mkStudioTheme['colors']['primary'], 'gold' => $mkStudioTheme['colors']['gold']],
];
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="map.css?v=<?= (int) @filemtime(__DIR__ . '/map.css') ?>">
<div class="mk-map-page">
    <div class="mk-map-toolbar">
        <div class="mk-map-title">نقشه املاک</div>
        <div class="mk-map-tx" id="mkMapTx">
            <button type="button" class="mk-chip is-on" data-tx="">همه</button>
            <button type="button" class="mk-chip" data-tx="فروش">فروش</button>
            <button type="button" class="mk-chip" data-tx="اجاره">اجاره</button>
            <button type="button" class="mk-chip" data-tx="پیش فروش">پیش‌فروش</button>
        </div>
        <details class="mk-map-types" id="mkMapTypes">
            <summary id="mkMapTypesSummary">نوع ملک: همه</summary>
            <div class="mk-map-types-list">
                <button type="button" class="mk-chip is-on" data-pt="">همه</button>
                <button type="button" class="mk-chip" data-pt="آپارتمان">آپارتمان</button>
                <button type="button" class="mk-chip" data-pt="ویلایی">ویلایی</button>
                <button type="button" class="mk-chip" data-pt="باغ">باغ</button>
                <button type="button" class="mk-chip" data-pt="زمین">زمین</button>
                <button type="button" class="mk-chip" data-pt="تجاری">تجاری</button>
                <button type="button" class="mk-chip" data-pt="اداری">اداری</button>
            </div>
        </details>
    </div>
    <div class="mk-map-body">
        <div id="mkMapCanvas"></div>
    </div>
    <div id="mkMapSheet" class="mk-map-sheet" hidden></div>
</div>
<style id="mk-map-studio-css"><?= str_ireplace('</style', '<\/style', melkinoStudioCss($mkStudioTheme)) ?></style>
<script>
window.MELKINO_MAP = <?= json_encode($mapSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.MELKINO_MAP_THEME = <?= json_encode($mkMapTheme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.MELKINO_MARKER_SHAPES = <?= json_encode(melkinoMarkerShapes(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="map-markers.js?v=<?= (int) @filemtime(__DIR__ . '/map-markers.js') ?>"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="map.js?v=<?= (int) @filemtime(__DIR__ . '/map.js') ?>"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
