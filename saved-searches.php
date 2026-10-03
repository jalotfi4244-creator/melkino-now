<?php
/*
|--------------------------------------------------------------------------
| saved-searches.php — «جستجوهای ذخیره‌شده» کاربر
|--------------------------------------------------------------------------
| مدیریت جستجوهای ذخیره‌شده و اطلاع‌رسانی پیامکی ملک جدید:
|   • فهرست جستجوها با خلاصهٔ فیلترها + وضعیت اطلاع‌رسانی (روشن/خاموش)
|   • حذف جستجو / قطع و وصل اطلاع‌رسانی (POST ساده)
|   • لغو دریافت همهٔ پیامک‌های اطلاع‌رسانی (ثبت در sms_optouts — مقررات ۲۷۰)
| گیت ورود و ترتیب include دقیقاً مثل my-request-matches.php.
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
}
$_mkPage = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')));
$_mkAllow = ['login.php', 'logout.php', 'auth.php', 'auth-telegram.php', 'auth-bale.php', 'auth-eitaa.php', 'request-otp.php', 'verify-otp.php', 'admin-login.php', 'admin-logout.php', 'telegram.php', 'bale.php', 'eitaa.php', 'telegram-relay.php', 'identity-sync.php', 'bale-ok.php', 'r.php'];
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

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/config.php';
$pdo = melkinoInitDbGlobal();
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/db-settings.php';
require_once __DIR__ . '/sms-program.php';

smsProgramEnsureSchema($pdo);

global $pdo;
$identity = melkinoCurrentIdentity(
    $_GET['telegram_id'] ?? $_POST['telegram_id'] ?? null
);

$userId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$phone = smsProgramNormPhone((string)($identity['phone'] ?? ''));
if ($phone === '' && !empty($_SESSION['user_phone'])) {
    $phone = smsProgramNormPhone((string)$_SESSION['user_phone']);
}

// ---------- POST actions ----------
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    $sid = (int)($_POST['id'] ?? 0);
    if ($act === 'toggle' && $sid > 0) {
        $st = $pdo->prepare('UPDATE saved_searches SET notify = 1 - notify WHERE id = ? AND (user_id = ? OR phone = ?)');
        $st->execute([$sid, $userId, $phone]);
        $flash = 'وضعیت اطلاع‌رسانی تغییر کرد.';
    } elseif ($act === 'delete' && $sid > 0) {
        $st = $pdo->prepare('DELETE FROM saved_searches WHERE id = ? AND (user_id = ? OR phone = ?)');
        $st->execute([$sid, $userId, $phone]);
        $flash = 'جستجو حذف شد.';
    } elseif ($act === 'unsub_all') {
        try {
            $st = $pdo->prepare('INSERT IGNORE INTO sms_optouts (phone, scope, source) VALUES (?, \'alerts\', \'panel\')');
            $st->execute([$phone]);
            $pdo->prepare('UPDATE saved_searches SET notify = 0 WHERE phone = ?')->execute([$phone]);
            $flash = 'دریافت پیامک‌های اطلاع‌رسانی برای شمارهٔ شما لغو شد.';
        } catch (Throwable $e) {
            $flash = 'انجام نشد؛ دوباره تلاش کنید.';
        }
    } elseif ($act === 'resub') {
        $pdo->prepare("DELETE FROM sms_optouts WHERE phone = ? AND scope = 'alerts'")->execute([$phone]);
        $pdo->prepare('UPDATE saved_searches SET notify = 1 WHERE phone = ?')->execute([$phone]);
        $flash = 'دریافت پیامک‌های اطلاع‌رسانی دوباره فعال شد.';
    }
}

$searches = [];
$optedOut = false;
if ($phone !== '') {
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM sms_optouts WHERE phone = ? AND scope IN (?, \'all\')');
        $st->execute([$phone, 'alerts']);
        $optedOut = (int)$st->fetchColumn() > 0;
        $st = $pdo->prepare('SELECT * FROM saved_searches WHERE phone = ? OR user_id = ? ORDER BY id DESC');
        $st->execute([$phone, $userId]);
        $searches = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $searches = [];
    }
}

function ssFa($n): string
{
    return strtr((string)$n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}
function ssSummary(array $s): string
{
    $bits = [];
    $txMap = ['فروش' => 'فروش', 'پیش فروش' => 'پیش فروش', 'اجاره' => 'اجاره و رهن'];
    $tx = trim((string)$s['tx']);
    if ($tx !== '') {
        $bits[] = $txMap[$tx] ?? $tx;
    }
    if (trim((string)$s['property_type']) !== '') {
        $bits[] = trim((string)$s['property_type']);
    }
    if (trim((string)$s['district']) !== '') {
        $bits[] = trim((string)$s['district']);
    }
    $minA = (int)smsProgramDigits((string)$s['min_area']);
    $maxA = (int)smsProgramDigits((string)$s['max_area']);
    if ($minA > 0 || $maxA > 0) {
        $bits[] = 'متراژ ' . ssFa($minA ?: '؟') . ' تا ' . ssFa($maxA ?: '؟');
    }
    $minP = (int)smsProgramDigits((string)$s['min_price']);
    $maxP = (int)smsProgramDigits((string)$s['max_price']);
    if ($minP > 0 || $maxP > 0) {
        $bits[] = 'بودجه ' . ssFa(number_format($minP ?: 0)) . ' تا ' . ssFa(number_format($maxP ?: 0));
    }
    if (trim((string)$s['rooms']) !== '' && trim((string)$s['rooms']) !== '0') {
        $bits[] = ssFa($s['rooms']) . ' خواب';
    }
    return $bits ? implode(' · ', $bits) : 'بدون فیلتر (همهٔ آگهی‌های جدید)';
}

require_once __DIR__ . '/header.php';
?>

<style>
    .ss-shell { max-width: 760px; margin: 0 auto; padding: 22px 14px 40px; width: 100%; }
    .ss-head { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
    .ss-title { font-size: 21px; font-weight: 900; color: var(--text-primary); }
    .ss-sub { color: var(--text-secondary); font-size: 12.5px; line-height: 2; margin-bottom: 18px; }
    .ss-card {
        background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md, 14px);
        padding: 15px 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    }
    .ss-emoji { font-size: 24px; }
    .ss-body { flex: 1; min-width: 200px; }
    .ss-filter { font-size: 13.5px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px; }
    .ss-meta { font-size: 11.5px; color: var(--text-secondary); }
    .ss-on { color: #0E7C6E; font-weight: 800; }
    .ss-off { color: #d97706; font-weight: 800; }
    .ss-actions { display: flex; gap: 8px; }
    .ss-btn {
        border: 1px solid var(--border); background: var(--bg-secondary, rgba(127,127,127,.08)); color: var(--text-primary);
        border-radius: 10px; padding: 8px 14px; font: inherit; font-size: 12px; font-weight: 800; cursor: pointer; text-decoration: none;
    }
    .ss-btn.danger { color: #dc2626; }
    .ss-empty { text-align: center; padding: 48px 16px; color: var(--text-secondary); }
    .ss-empty .big { font-size: 44px; margin-bottom: 12px; }
    .ss-notice {
        border: 1px solid rgba(217,119,6,.35); background: rgba(217,119,6,.08); color: #b45309;
        border-radius: 12px; padding: 12px 14px; font-size: 12.5px; line-height: 2; margin-bottom: 16px;
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap; justify-content: space-between;
    }
    .ss-flash {
        border: 1px solid rgba(14,124,110,.35); background: rgba(14,124,110,.08); color: #0E7C6E;
        border-radius: 12px; padding: 10px 14px; font-size: 12.5px; font-weight: 800; margin-bottom: 16px;
    }
</style>

<div class="ss-shell">
    <div class="ss-head">
        <span style="font-size:26px">🔍</span>
        <span class="ss-title">جستجوهای ذخیره‌شده</span>
    </div>
    <div class="ss-sub">
        هر ملک جدیدی که با فیلترهای شما بسازد، همان لحظه پیامک اطلاع‌رسانی می‌گیرید.
        <a href="properties.php" style="color:#0E7C6E;font-weight:800;text-decoration:none;">ثبت جستجوی جدید از صفحهٔ همه آگهی‌ها ←</a>
    </div>

    <?php if ($flash !== ''): ?><div class="ss-flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <?php if ($optedOut): ?>
        <div class="ss-notice">
            <span>⛔ دریافت پیامک‌های اطلاع‌رسانی برای شمارهٔ شما لغو شده است.</span>
            <form method="post" style="display:inline;"><input type="hidden" name="action" value="resub"><button class="ss-btn" type="submit">فعال‌سازی دوباره</button></form>
        </div>
    <?php endif; ?>

    <?php if (!$searches): ?>
        <div class="ss-empty">
            <div class="big">🗂</div>
            هنوز جستجویی ذخیره نکرده‌اید.<br>
            از صفحهٔ «همه آگهی‌ها» فیلترها را بزنید و «ذخیرهٔ این جستجو» را بزنید.
        </div>
    <?php else: ?>
        <?php foreach ($searches as $s): ?>
            <div class="ss-card">
                <span class="ss-emoji"><?= !empty($s['notify']) && !$optedOut ? '🔔' : '🔕' ?></span>
                <div class="ss-body">
                    <div class="ss-filter"><?= htmlspecialchars(ssSummary($s)) ?></div>
                    <div class="ss-meta">
                        ثبت: <?= ssFa(substr((string)$s['created_at'], 0, 10)) ?>
                        <?php if (!empty($s['last_notified_at'])): ?> · آخرین اطلاع‌رسانی: <?= ssFa(substr((string)$s['last_notified_at'], 0, 16)) ?><?php endif; ?>
                        · <span class="<?= !empty($s['notify']) && !$optedOut ? 'ss-on' : 'ss-off' ?>"><?= !empty($s['notify']) && !$optedOut ? 'اطلاع‌رسانی روشن' : 'اطلاع‌رسانی خاموش' ?></span>
                    </div>
                </div>
                <div class="ss-actions">
                    <form method="post"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="ss-btn" type="submit"><?= !empty($s['notify']) ? 'قطع' : 'وصل' ?></button></form>
                    <form method="post"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="ss-btn danger" type="submit">حذف</button></form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$optedOut): ?>
            <div class="ss-notice" style="margin-top:18px;">
                <span>نمی‌خواهید دیگر پیامک اطلاع‌رسانی بگیرید؟</span>
                <form method="post"><input type="hidden" name="action" value="unsub_all"><button class="ss-btn danger" type="submit">لغو دریافت همهٔ پیامک‌های اطلاع‌رسانی</button></form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/footer.php';
