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
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/security-lib.php';
global $pdo;

if (isset($_GET['action'])) {
    $identity = melkinoCurrentIdentity($_GET['telegram_id'] ?? null);

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { melkinoCsrfCheck(); }

    if (!$pdo instanceof PDO) {
        melkinoJsonResponse([
            'success' => false,
            'message' => 'اتصال دیتابیس برقرار نیست.'
        ], 500);
    }

    // ===== اکشن count =====
    if ($_GET['action'] === 'count') {
        $conds = [];
        $params = [];

        if (!empty($identity['user_id'])) {
            $conds[] = 'n.user_id = ?';
            $params[] = $identity['user_id'];
        }

        if (($identity['telegram_id'] ?? '') !== '') {
            $conds[] = 'n.telegram_id = ?';
            $params[] = $identity['telegram_id'];
        }

        if (!$conds) {
            melkinoJsonResponse([
                'success' => true,
                'unread' => 0
            ]);
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM notifications n
             WHERE (' . implode(' OR ', $conds) . ')
             AND n.is_read = 0'
        );

        $stmt->execute($params);
        $unread = (int)$stmt->fetchColumn();

        melkinoJsonResponse([
            'success' => true,
            'unread' => $unread
        ]);
    }

    // ===== اکشن send =====
    if ($_GET['action'] === 'send') {
        /*
         * امنیت (CRITICAL): پیش از این هر کاربر واردشده‌ای می‌توانست برای هر
         * user_id/telegram_id دلخواه اعلان بسازد (عنوان، متن و لینک دلخواه)
         * ⇒ جعل پیام «از طرف ملکینو» و فیشینگ. حالا این اندپوینت فقط برای
         * نشست ادمین باز است. همهٔ فراخوانی‌های داخلی برنامه مستقیماً تابع
         * sendNotification() را صدا می‌زنند و از این مسیر HTTP رد نمی‌شوند،
         * پس هیچ قابلیتی از بین نمی‌رود.
         */
        $isAdminActor = !empty($_SESSION['is_admin'])
            || (function_exists('melkinoIsAdminSession') && melkinoIsAdminSession());
        if (!$isAdminActor) {
            if (function_exists('melkinoAudit')) {
                melkinoAudit('notification.send_denied', 'notification', null, [
                    'target_user_id' => isset($input['user_id']) ? (string) $input['user_id'] : '',
                ]);
            }
            melkinoJsonResponse([
                'success' => false,
                'message' => 'دسترسی غیرمجاز.'
            ], 403);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            melkinoJsonResponse(['success' => false, 'message' => 'روش مجاز نیست.'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !is_array($input)) {
            melkinoJsonResponse([
                'success' => false,
                'message' => 'داده نامعتبر'
            ], 400);
        }

        // --- اعتبارسنجی ورودی‌ها ---
        $user_id     = isset($input['user_id']) && $input['user_id'] !== '' ? (int) $input['user_id'] : null;
        $telegram_id = isset($input['telegram_id']) ? preg_replace('/\D/', '', (string) $input['telegram_id']) : '';
        $telegram_id = $telegram_id !== '' ? substr($telegram_id, 0, 32) : null;

        if (($user_id === null || $user_id <= 0) && $telegram_id === null) {
            melkinoJsonResponse(['success' => false, 'message' => 'گیرندهٔ اعلان مشخص نیست.'], 422);
        }

        $allowedTypes = ['system', 'ad', 'request', 'match', 'visit', 'support', 'promo', 'admin'];
        $type = (string) ($input['type'] ?? 'system');
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'system';
        }

        $strip = static function ($v, int $len): string {
            $s = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $v));
            return mb_substr($s, 0, $len);
        };
        $title   = $strip($input['title'] ?? '', 150);
        $message = $strip($input['message'] ?? '', 1000);
        if ($title === '' || $message === '') {
            melkinoJsonResponse(['success' => false, 'message' => 'عنوان و متن اعلان الزامی است.'], 422);
        }

        // لینک فقط نسبی یا هم‌دامنه؛ جلوگیری از open redirect و javascript:
        $url = null;
        $rawUrl = trim((string) ($input['url'] ?? ''));
        if ($rawUrl !== '') {
            $scheme = strtolower((string) (parse_url($rawUrl, PHP_URL_SCHEME) ?? ''));
            $host   = strtolower((string) (parse_url($rawUrl, PHP_URL_HOST) ?? ''));
            $selfHost = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
            if ($scheme === '' && $host === '' && strpos($rawUrl, '//') !== 0) {
                $url = mb_substr($rawUrl, 0, 300);            // مسیر نسبی داخل سایت
            } elseif (in_array($scheme, ['http', 'https'], true) && $host !== '' && $host === $selfHost) {
                $url = mb_substr($rawUrl, 0, 300);            // همان دامنه
            } else {
                melkinoJsonResponse(['success' => false, 'message' => 'لینک اعلان مجاز نیست.'], 422);
            }
        }

        $ad_id      = isset($input['ad_id']) && $input['ad_id'] !== '' ? mb_substr((string) $input['ad_id'], 0, 64) : null;
        $request_id = isset($input['request_id']) && $input['request_id'] !== '' ? (int) $input['request_id'] : null;
        $match_percent = null;
        if (isset($input['match_percent']) && is_numeric($input['match_percent'])) {
            $match_percent = max(0, min(100, (float) $input['match_percent']));
        }

        if (function_exists('melkinoAudit')) {
            melkinoAudit('notification.send', 'notification', $user_id !== null ? (string) $user_id : $telegram_id, [
                'type' => $type,
                'title' => $title,
            ]);
        }

        $result = sendNotification(
            $user_id,
            $telegram_id,
            $type,
            $title,
            $message,
            $url,
            $ad_id,
            $request_id,
            $match_percent
        );

        melkinoJsonResponse([
            'success' => $result
        ]);
    }

    // ===== اکشن list =====
    if ($_GET['action'] === 'list') {
        $conds = [];
        $params = [];

        if (!empty($identity['user_id'])) {
            $conds[] = 'n.user_id = ?';
            $params[] = $identity['user_id'];
        }

        if (($identity['telegram_id'] ?? '') !== '') {
            $conds[] = 'n.telegram_id = ?';
            $params[] = $identity['telegram_id'];
        }

        if (!$conds) {
            melkinoJsonResponse([
                'success' => true,
                'notifications' => [],
                'unread' => 0
            ]);
        }

        $stmt = $pdo->prepare(
            'SELECT n.*, TIMESTAMPDIFF(SECOND, n.created_at, NOW()) AS age_seconds
             FROM notifications n
             WHERE ' . implode(' OR ', $conds) . '
             ORDER BY n.created_at DESC
             LIMIT 100'
        );

        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unread = 0;

        foreach ($rows as $r) {
            if ((int)$r['is_read'] === 0) {
                $unread++;
            }
        }

        melkinoJsonResponse([
            'success' => true,
            'notifications' => $rows,
            'unread' => $unread
        ]);
    }

    // ===== اکشن read =====
    if ($_GET['action'] === 'read') {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

        if (!$id) {
            melkinoJsonResponse([
                'success' => false
            ], 422);
        }

        $owners = [];
        $params = [$id];

        if (!empty($identity['user_id'])) {
            $owners[] = 'user_id = ?';
            $params[] = $identity['user_id'];
        }

        if (($identity['telegram_id'] ?? '') !== '') {
            $owners[] = 'telegram_id = ?';
            $params[] = $identity['telegram_id'];
        }

        if (!$owners) {
            melkinoJsonResponse([
                'success' => false
            ], 403);
        }

        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = NOW()
             WHERE id = ?
             AND (' . implode(' OR ', $owners) . ')'
        );

        $stmt->execute($params);

        melkinoJsonResponse([
            'success' => true
        ]);
    }

    // ===== اکشن delete =====
    if ($_GET['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

        if (!$id) {
            melkinoJsonResponse([
                'success' => false,
                'message' => 'شناسه اعلان نامعتبر است.'
            ], 422);
        }

        $owners = [];
        $params = [$id];

        if (!empty($identity['user_id'])) {
            $owners[] = 'user_id = ?';
            $params[] = $identity['user_id'];
        }

        if (($identity['telegram_id'] ?? '') !== '') {
            $owners[] = 'telegram_id = ?';
            $params[] = $identity['telegram_id'];
        }

        if (!$owners) {
            melkinoJsonResponse([
                'success' => false,
                'message' => 'دسترسی غیرمجاز.'
            ], 403);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM notifications
             WHERE id = ?
             AND (' . implode(' OR ', $owners) . ')'
        );

        $stmt->execute($params);

        /*
         * اگر هیچ ردیفی حذف نشد یعنی این اعلان یا وجود ندارد یا متعلق به
         * حساب دیگری است. قبلاً همین حالت با HTTP 200 و success:false
         * برمی‌گشت که با بقیهٔ اندپوینت‌ها (۴۰۳) ناسازگار بود.
         * هیچ داده‌ای تغییر نمی‌کرد، ولی برای یکدستی پاسخ ۴۰۳ می‌شود.
         */
        if ($stmt->rowCount() < 1) {
            melkinoJsonResponse([
                'success' => false,
                'message' => 'این اعلان متعلق به حساب شما نیست یا قبلاً حذف شده است.'
            ], 403);
        }

        melkinoJsonResponse([
            'success' => true
        ]);
    }

    // ===== اکشن delete_all =====
    if ($_GET['action'] === 'delete_all') {
        $owners = [];
        $params = [];

        if (!empty($identity['user_id'])) {
            $owners[] = 'user_id = ?';
            $params[] = $identity['user_id'];
        }

        if (($identity['telegram_id'] ?? '') !== '') {
            $owners[] = 'telegram_id = ?';
            $params[] = $identity['telegram_id'];
        }

        if (!$owners) {
            melkinoJsonResponse([
                'success' => false
            ], 403);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM notifications
             WHERE ' . implode(' OR ', $owners)
        );

        $stmt->execute($params);

        melkinoJsonResponse([
            'success' => true,
            'deleted' => $stmt->rowCount()
        ]);
    }

    // ===== اکشن mark_all =====
    if ($_GET['action'] === 'mark_all') {
        $owners = [];
        $params = [];

        if (!empty($identity['user_id'])) {
            $owners[] = 'user_id = ?';
            $params[] = $identity['user_id'];
        }

        if (($identity['telegram_id'] ?? '') !== '') {
            $owners[] = 'telegram_id = ?';
            $params[] = $identity['telegram_id'];
        }

        if (!$owners) {
            melkinoJsonResponse([
                'success' => false
            ], 403);
        }

        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1, read_at = NOW()
             WHERE ' . implode(' OR ', $owners)
        );

        $stmt->execute($params);

        melkinoJsonResponse([
            'success' => true
        ]);
    }

    melkinoJsonResponse([
        'success' => false,
        'message' => 'عملیات نامعتبر است.'
    ], 400);
}

require_once __DIR__ . '/header.php';
?>

<style>
/* =========================================================
   Notifications Page
   Responsive + Scroll Fix
   ========================================================= */

* {
    box-sizing: border-box;
}

.notifications-page {
    width: min(100%, 980px);
    margin: 0 auto;
    padding: 22px 18px 130px;

    /*
     * مهم:
     * اجازه اسکرول عمودی صفحه در موبایل و دسکتاپ
     */
    min-height: 100dvh;
    overflow-y: auto;
    overflow-x: hidden;

    -webkit-overflow-scrolling: touch;
    overscroll-behavior-y: contain;
    scroll-behavior: smooth;
}

/* ===== Header ===== */

.notifications-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.notifications-heading {
    min-width: 0;
}

.notifications-title {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-primary);
    line-height: 1.4;
}

.notifications-sub {
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 5px;
    line-height: 1.8;
}

.notifications-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
}

/* ===== Buttons ===== */

.notif-btn {
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-primary);

    min-height: 40px;
    padding: 8px 14px;

    border-radius: 10px;

    font-family: inherit;
    font-size: 12px;
    font-weight: 700;

    cursor: pointer;

    transition:
        transform .18s ease,
        box-shadow .18s ease,
        border-color .18s ease,
        background .18s ease;
}

.notif-btn:hover {
    transform: translateY(-1px);
    border-color: var(--primary);
    box-shadow: 0 5px 14px rgba(0, 0, 0, .06);
}

.notif-btn:active {
    transform: translateY(0);
}

.notif-btn.primary {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

/* ===== List ===== */

.notif-list {
    display: flex;
    flex-direction: column;
    gap: 12px;

    width: 100%;

    /*
     * عمداً ارتفاع محدود ندارد
     * تا همه اعلان‌ها قابل اسکرول باشند.
     */
    max-height: none;
    overflow: visible;
}

/* ===== Notification Card ===== */

.notif-card {
    display: block;
    width: 100%;

    text-decoration: none;
    color: inherit;

    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;

    padding: 17px 18px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        transform .2s ease;
}

.notif-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 7px 24px rgba(0, 0, 0, .07);
}

.notif-card.unread {
    border-color: rgba(11, 93, 91, .38);

    background:
        linear-gradient(
            135deg,
            var(--surface),
            rgba(11, 93, 91, .055)
        );
}

.notif-card.notif-welcome {
    border-color: rgba(212, 175, 55, .45);
    background:
        linear-gradient(
            135deg,
            var(--surface),
            rgba(212, 175, 55, .09)
        );
}

.notif-icon {
    font-size: 26px;
    line-height: 1;
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: var(--bg-secondary, rgba(0,0,0,.04));
    margin-inline-end: 12px;
}

.notif-welcome .notif-icon {
    background: rgba(212, 175, 55, .16);
}

/* ===== Main row ===== */

.notif-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;

    gap: 18px;
}

.notif-content {
    min-width: 0;
    flex: 1 1 auto;
}

.notif-title {
    font-size: 15px;
    font-weight: 900;

    color: var(--text-primary);

    line-height: 1.75;

    overflow-wrap: anywhere;
    word-break: break-word;
}

.notif-message {
    font-size: 13px;
    line-height: 1.95;

    color: var(--text-secondary);

    margin-top: 6px;

    overflow-wrap: anywhere;
    word-break: break-word;
}

.notif-code {
    font-size: 10px;
    color: var(--text-secondary);

    margin-top: 6px;

    overflow-wrap: anywhere;
    word-break: break-word;
}

.notif-chip {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    color: var(--text-secondary);
    background: var(--bg-secondary, rgba(0,0,0,.04));
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 2px 8px;
    margin-bottom: 6px;
}

.notif-percent {
    flex: 0 0 auto;

    font-size: 18px;
    font-weight: 900;

    color: var(--primary);

    white-space: nowrap;

    padding-top: 1px;
}

/* ===== Date ===== */

.notif-date {
    font-size: 10px;
    color: var(--text-secondary);

    margin-top: 9px;
}

/* ===== Card footer ===== */

.notif-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 10px;
    margin-top: 12px;
}

.notif-footer-hint {
    font-size: 10px;
    line-height: 1.7;

    color: var(--text-secondary);
}

.notif-delete {
    flex: 0 0 auto;
}

/* ===== Empty ===== */

.notif-empty {
    width: 100%;

    text-align: center;

    padding: 60px 20px;

    color: var(--text-secondary);

    background: var(--surface);

    border: 1px dashed var(--border);
    border-radius: 16px;

    line-height: 1.9;
}

/* =========================================================
   Desktop
   ========================================================= */

@media (min-width: 900px) {
    .notifications-page {
        padding-left: 24px;
        padding-right: 24px;
    }

    .notifications-title {
        font-size: 26px;
    }

    .notif-card {
        padding: 20px 22px;
    }

    .notif-title {
        font-size: 16px;
    }

    .notif-message {
        font-size: 14px;
    }
}

/* =========================================================
   Tablet
   ========================================================= */

@media (max-width: 700px) {
    .notifications-page {
        padding: 16px 12px 120px;
    }

    .notifications-head {
        align-items: flex-start;
    }

    .notifications-title {
        font-size: 21px;
    }

    .notifications-sub {
        font-size: 12px;
    }

    .notif-card {
        border-radius: 14px;
        padding: 14px;
    }
}

/* =========================================================
   Mobile
   ========================================================= */

@media (max-width: 520px) {
    .notifications-page {
        width: 100%;

        padding:
            14px 10px
            calc(105px + env(safe-area-inset-bottom));
    }

    .notifications-head {
        display: block;
        margin-bottom: 14px;
    }

    .notifications-actions {
        width: 100%;
        margin-top: 12px;
    }

    .notifications-actions .notif-btn {
        flex: 1 1 0;
        min-width: 0;
    }

    .notif-card {
        padding: 14px 13px;
    }

    .notif-row {
        gap: 10px;
    }

    .notif-title {
        font-size: 14px;
    }

    .notif-message {
        font-size: 12px;
        line-height: 1.9;
    }

    .notif-code {
        font-size: 9px;
    }

    .notif-percent {
        font-size: 16px;
    }

    .notif-footer {
        align-items: flex-end;
    }

    .notif-footer-hint {
        font-size: 9px;
    }
}

/* =========================================================
   Very Small Mobile
   ========================================================= */

@media (max-width: 360px) {
    .notif-row {
        display: block;
    }

    .notif-percent {
        display: inline-block;
        margin-top: 10px;
    }

    .notif-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .notif-delete {
        width: 100%;
    }
}

/* =========================================================
   Accessibility
   ========================================================= */

.notif-btn:focus-visible,
.notif-card:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/*
 * اگر قالب اصلی روی body یا html overflow را محدود کرده باشد،
 * این تنظیم کمک می‌کند صفحه اعلان در موبایل/دسکتاپ قابل مشاهده بماند.
 */
html,
body {
    max-width: 100%;
    overflow-x: hidden;
}
</style>

<main class="notifications-page">

    <div class="notifications-head">

        <div class="notifications-heading">
            <div class="notifications-title">
                🔔 اعلان‌ها
            </div>

            <div class="notifications-sub">
                رویدادهای ملک‌های شما، درخواست‌ها و اطلاعیه‌های عمومی ملکینو
            </div>
        </div>

        <div class="notifications-actions">

            <button
                class="notif-btn primary"
                type="button"
                id="markAll"
            >
                خواندن همه
            </button>

            <button
                class="notif-btn"
                type="button"
                id="deleteAll"
            >
                حذف همه
            </button>

        </div>
    </div>

    <div
        id="notificationsList"
        class="notif-list"
    ></div>

</main>

<script>
(function() {

    'use strict';

    const list = document.getElementById('notificationsList');
    const markAllBtn = document.getElementById('markAll');
    const deleteAllBtn = document.getElementById('deleteAll');

    if (!list) {
        return;
    }

    /* =====================================================
       Telegram ID
       ===================================================== */

    const tid = () => {
        return String(
            localStorage.getItem('melkino_telegram_id') ||
            sessionStorage.getItem('reg_telegram_id') ||
            ''
        );
    };

    /* =====================================================
       Escape HTML
       ===================================================== */

    const esc = (value) => {
        return String(value ?? '').replace(
            /[&<>"']/g,
            (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char])
        );
    };

    /* =====================================================
       زمان نسبی فارسی (۵ دقیقه پیش، دیروز، ...)
       ===================================================== */

    const faDigits = (value) => {
        return String(value).replace(
            /[0-9]/g,
            (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]
        );
    };

    const timeAgoFa = (dateStr, ageSeconds) => {
        let sec;
        if (ageSeconds !== undefined && ageSeconds !== null && ageSeconds !== '') {
            const n = Number(ageSeconds);
            if (!Number.isNaN(n)) sec = Math.max(0, n);
        }
        if (sec === undefined) {
            if (!dateStr) return '';
            const raw = String(dateStr).trim().replace(' ', 'T').replace(/\.\d+$/, '');
            const iso = /[zZ]|[+-]\d{2}:?\d{2}$/.test(raw) ? raw : raw + '+03:30';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return String(dateStr);
            sec = Math.max(0, (Date.now() - d.getTime()) / 1000);
        }
        const min = Math.floor(sec / 60);
        if (min < 1) return 'لحظاتی پیش';
        if (min < 60) return faDigits(min) + ' دقیقه پیش';
        const h = Math.floor(min / 60);
        if (h < 24) return faDigits(h) + ' ساعت پیش';
        const days = Math.floor(h / 24);
        if (days === 1) return 'دیروز';
        if (days < 7) return faDigits(days) + ' روز پیش';
        if (days < 30) return faDigits(Math.floor(days / 7)) + ' هفته پیش';
        if (days < 365) return faDigits(Math.floor(days / 30)) + ' ماه پیش';
        return faDigits(Math.floor(days / 365)) + ' سال پیش';
    };

    /* =====================================================
       API URL
       ===================================================== */

    const apiUrl = (action) => {
        return 'notifications.php?action=' +
            encodeURIComponent(action) +
            '&telegram_id=' +
            encodeURIComponent(tid());
    };

    /* =====================================================
       Badge
       ===================================================== */

    function setBadgeCount(count) {

        const badge = document.getElementById('notificationBadge');

        if (!badge) {
            return;
        }

        count = Number(count) || 0;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'inline-block';
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
        }
    }

    function updateBadgeCount() {

        fetch(apiUrl('count'), {
            cache: 'no-store'
        })
        .then(res => res.json())
        .then(data => {

            const count = Number(data?.unread || 0);

            setBadgeCount(count);

            window.dispatchEvent(
                new CustomEvent(
                    'melkino:notificationUpdate',
                    {
                        detail: {
                            unread: count
                        }
                    }
                )
            );

        })
        .catch(() => {});
    }

    /* =====================================================
       Load Notifications
       ===================================================== */

    async function load() {

        try {

            const res = await fetch(
                apiUrl('list'),
                {
                    cache: 'no-store'
                }
            );

            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }

            const data = await res.json();

            const rows = Array.isArray(data.notifications)
                ? data.notifications
                : [];

            /* ===== Badge ===== */

            const unreadCount = Number(data.unread || 0);

            setBadgeCount(unreadCount);

            window.dispatchEvent(
                new CustomEvent(
                    'melkino:notificationUpdate',
                    {
                        detail: {
                            unread: unreadCount
                        }
                    }
                )
            );

            /* ===== Empty ===== */

            if (!rows.length) {

                list.innerHTML = `
                    <div class="notif-empty">
                        🔕
                        <br>
                        <br>
                        هنوز اعلانی نداری.
                        <br>
                        رویدادهای ملک‌ها، درخواست‌ها و اطلاعیه‌های
                        عمومی ملکینو اینجا نمایش داده می‌شن.
                    </div>
                `;

                return;
            }

            /* ===== Render ===== */

            list.innerHTML = rows.map((n) => {

                const propertyUrl =
                    n.url ||
                    (
                        n.ad_id
                            ? 'property-details.php?id=' + encodeURIComponent(n.ad_id || '')
                            : 'home.php'
                    );

                const hintByType = {
                    welcome: 'برای مشاهده‌ی آگهی‌ها کلیک کنید',
                    match: 'برای مشاهده آگهی کلیک کنید',
                    property_match: 'برای مشاهده آگهی کلیک کنید',
                    broadcast: 'اطلاعیه عمومی ملکینو',
                    ad_submitted: 'برای پیگیری آگهی کلیک کنید',
                    ad_published: 'برای مشاهده آگهی کلیک کنید',
                    ad_rejected: 'برای پیگیری آگهی کلیک کنید',
                    ad_status: 'برای پیگیری آگهی کلیک کنید',
                    request_submitted: 'برای مشاهده درخواست‌ها کلیک کنید',
                    request_status: 'برای مشاهده درخواست‌ها کلیک کنید',
                    visit_submitted: 'برای مشاهده درخواست بازدید کلیک کنید',
                    visit_scheduled: 'برای مشاهده درخواست بازدید کلیک کنید',
                    visit_owner_rejected: 'برای مشاهده درخواست بازدید کلیک کنید',
                    visit_user_notified: 'برای مشاهده درخواست بازدید کلیک کنید',
                    visit_done: 'برای مشاهده درخواست بازدید کلیک کنید',
                    visit_cancelled: 'برای مشاهده درخواست بازدید کلیک کنید',
                    visit_time_changed: 'برای مشاهده درخواست بازدید کلیک کنید',
                    visit_no_response: 'برای مشاهده درخواست بازدید کلیک کنید',
                    visit_visited: 'برای مشاهده درخواست بازدید کلیک کنید',
                    ad_revision_approved: 'برای مشاهده آگهی‌هایتان کلیک کنید',
                    ad_revision_rejected: 'برای مشاهده آگهی‌هایتان کلیک کنید',
                support_reply: 'برای مشاهده گفتگو کلیک کنید',
                    support_closed: 'برای مشاهده تیکت‌ها کلیک کنید',
                };
                const footerHint = hintByType[n.type] || 'برای مشاهده کلیک کنید';

                const isUnread = Number(n.is_read) === 0;

                const matchPercent =
                    n.match_percent !== null &&
                    n.match_percent !== '' &&
                    !Number.isNaN(Number(n.match_percent))
                        ? `
                            <div class="notif-percent">
                                ${Number(n.match_percent)}٪
                            </div>
                          `
                        : '';

                const isWelcome = n.type === 'welcome';

                // راند ۴۹: آیکون‌های کارت اعلان = SVG یکدست (بدون ایموجی)
                const N_IC = {
                    star:    '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 2.7 5.8 6.3.8-4.6 4.3 1.2 6.1L12 17l-5.6 3 1.2-6.1L3 9.6l6.3-.8z"/></svg>',
                    home:    '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11l8-7 8 7"/><path d="M6 9.5V21h12V9.5"/></svg>',
                    mega:    '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10v4l11 5V5z"/><path d="M14 8a4 4 0 0 1 0 8"/><path d="M6 14v5"/></svg>',
                    edit:    '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>',
                    check:   '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/></svg>',
                    x:       '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg>',
                    refresh: '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.3 6.3"/><path d="M20 5v6h-6"/></svg>',
                    list:    '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>',
                    bell:    '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>',
                };
                N_IC.headset = '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/></svg>';
                N_IC.lock = '<svg class="mk-icon mk-icon--sm" width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>';
                const iconByType = {
                    welcome: N_IC.star,
                    match: N_IC.home,
                    property_match: N_IC.home,
                    broadcast: N_IC.mega,
                    ad_submitted: N_IC.edit,
                    ad_published: N_IC.check,
                    ad_rejected: N_IC.x,
                    ad_status: N_IC.refresh,
                    request_submitted: N_IC.list,
                    request_status: N_IC.refresh,
                    visit_submitted: N_IC.home,
                    visit_scheduled: N_IC.check,
                    visit_owner_rejected: N_IC.x,
                    visit_user_notified: N_IC.check,
                    visit_done: N_IC.check,
                    visit_cancelled: N_IC.x,
                    visit_time_changed: N_IC.refresh,
                    visit_no_response: N_IC.bell,
                    visit_visited: N_IC.check,
                    ad_revision_approved: N_IC.check,
                    ad_revision_rejected: N_IC.x,
                    support_reply: N_IC.headset,
                    support_closed: N_IC.lock,
                    system: N_IC.bell,
                };
                const cardIcon = iconByType[n.type] || N_IC.bell;

                const labelByType = {
                    welcome: 'خوش‌آمد',
                    match: 'فایل مناسب',
                    property_match: 'فایل مناسب',
                    broadcast: 'اطلاعیه عمومی',
                    ad_submitted: 'ثبت آگهی',
                    ad_published: 'انتشار آگهی',
                    ad_rejected: 'رد آگهی',
                    ad_status: 'وضعیت آگهی',
                    request_submitted: 'ثبت درخواست',
                    request_status: 'وضعیت درخواست',
                    visit_submitted: 'درخواست بازدید',
                    visit_scheduled: 'هماهنگ با مالک',
                    visit_owner_rejected: 'رد تاریخ بازدید',
                    visit_user_notified: 'اطلاع بازدید',
                    visit_done: 'بازدید انجام شد',
                    visit_cancelled: 'لغو بازدید',
                    visit_time_changed: 'تغییر زمان بازدید',
                    visit_no_response: 'عدم پاسخگویی',
                    visit_visited: 'بازدید شده',
                    ad_revision_approved: 'تأیید ویرایش',
                    ad_revision_rejected: 'رد ویرایش',
                    support_reply: 'پاسخ پشتیبانی',
                    support_closed: 'بسته شدن تیکت',
                    system: 'سیستمی',
                };
                const typeChip = labelByType[n.type]
                    ? `<span class="notif-chip">${esc(labelByType[n.type])}</span>`
                    : '';

                const codeLine = (!isWelcome && n.request_id)
                    ? `
                        <div class="notif-code">
                            کد رهگیری درخواست:
                            ${esc(n.request_id)}
                        </div>
                      `
                    : '';

                return `
                    <a
                        class="notif-card ${isUnread ? 'unread' : ''} ${isWelcome ? 'notif-welcome' : ''}"
                        href="${esc(propertyUrl)}"
                        data-id="${esc(n.id)}"
                    >

                        <div class="notif-row">

                            <div class="notif-icon">${cardIcon}</div>

                            <div class="notif-content">

                                ${typeChip}

                                <div class="notif-title">
                                    ${esc(
                                        n.title ||
                                        'ملک مناسب برای شما پیدا شد'
                                    )}
                                </div>

                                <div class="notif-message">
                                    ${esc(n.message || '')}
                                </div>

                                ${codeLine}

                            </div>

                            ${matchPercent}

                        </div>

                        <div class="notif-date" title="${esc(n.created_at || '')}">
                            ${timeAgoFa(n.created_at, n.age_seconds)}
                        </div>

                        <div class="notif-footer">

                            <span class="notif-footer-hint">
                                ${footerHint}
                            </span>

                            <button
                                type="button"
                                class="notif-btn notif-delete"
                                data-delete="${esc(n.id)}"
                            >
                                حذف
                            </button>

                        </div>

                    </a>
                `;

            }).join('');

            bindEvents();

        } catch (error) {

            console.error('Notifications load error:', error);

            list.innerHTML = `
                <div class="notif-empty">
                    ❌
                    <br>
                    <br>
                    خطا در دریافت اعلان‌ها
                </div>
            `;
        }
    }

    /* =====================================================
       Bind Events
       ===================================================== */

    function bindEvents() {

        /* ===== Card click ===== */

        list
            .querySelectorAll('.notif-card')
            .forEach((card) => {

                card.addEventListener(
                    'click',
                    async function(e) {

                        const deleteButton =
                            e.target.closest('[data-delete]');

                        if (deleteButton) {
                            return;
                        }

                        if (!card.classList.contains('unread')) {
                            return;
                        }

                        const id = card.dataset.id;

                        if (!id) {
                            return;
                        }

                        try {

                            await fetch(
                                apiUrl('read'),
                                {
                                    method: 'POST',

                                    headers: {
                                        'Content-Type':
                                            'application/x-www-form-urlencoded'
                                    },

                                    body:
                                        'id=' +
                                        encodeURIComponent(id)
                                }
                            );

                            card.classList.remove('unread');

                            updateBadgeCount();

                        } catch (error) {
                            console.error(
                                'Mark notification read error:',
                                error
                            );
                        }

                    }
                );
            });

        /* ===== Delete buttons ===== */

        list
            .querySelectorAll('[data-delete]')
            .forEach((button) => {

                button.addEventListener(
                    'click',
                    async function(e) {

                        e.preventDefault();
                        e.stopPropagation();

                        const id = button.dataset.delete;

                        if (!id) {
                            return;
                        }

                        if (
                            !window.confirm(
                                'این اعلان حذف شود؟'
                            )
                        ) {
                            return;
                        }

                        button.disabled = true;

                        try {

                            const response = await fetch(
                                apiUrl('delete'),
                                {
                                    method: 'POST',

                                    headers: {
                                        'Content-Type':
                                            'application/x-www-form-urlencoded'
                                    },

                                    body:
                                        'id=' +
                                        encodeURIComponent(id)
                                }
                            );

                            if (!response.ok) {
                                throw new Error(
                                    'HTTP ' +
                                    response.status
                                );
                            }

                            await load();

                            updateBadgeCount();

                        } catch (error) {

                            console.error(
                                'Delete notification error:',
                                error
                            );

                            button.disabled = false;

                        }

                    }
                );

            });
    }

    /* =====================================================
       Mark All
       ===================================================== */

    if (markAllBtn) {

        markAllBtn.addEventListener(
            'click',
            async function() {

                markAllBtn.disabled = true;

                try {

                    const response = await fetch(
                        apiUrl('mark_all'),
                        {
                            method: 'POST'
                        }
                    );

                    if (!response.ok) {
                        throw new Error(
                            'HTTP ' + response.status
                        );
                    }

                    await load();

                    updateBadgeCount();

                } catch (error) {

                    console.error(
                        'Mark all notification error:',
                        error
                    );

                } finally {

                    markAllBtn.disabled = false;

                }
            }
        );
    }

    /* =====================================================
       Delete All
       ===================================================== */

    if (deleteAllBtn) {

        deleteAllBtn.addEventListener(
            'click',
            async function() {

                if (
                    !window.confirm(
                        'همه اعلان‌ها حذف شوند؟'
                    )
                ) {
                    return;
                }

                deleteAllBtn.disabled = true;

                try {

                    const response = await fetch(
                        apiUrl('delete_all'),
                        {
                            method: 'POST'
                        }
                    );

                    if (!response.ok) {
                        throw new Error(
                            'HTTP ' + response.status
                        );
                    }

                    await load();

                    updateBadgeCount();

                } catch (error) {

                    console.error(
                        'Delete all notifications error:',
                        error
                    );

                } finally {

                    deleteAllBtn.disabled = false;

                }
            }
        );
    }

    /* =====================================================
       External Badge Update
       ===================================================== */

    window.addEventListener(
        'melkino:notificationUpdate',
        function(event) {

            const count = Number(
                event?.detail?.unread || 0
            );

            setBadgeCount(count);
        }
    );

    /* =====================================================
       Initial Load
       ===================================================== */

    load();

    setTimeout(
        updateBadgeCount,
        300
    );

})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
```
