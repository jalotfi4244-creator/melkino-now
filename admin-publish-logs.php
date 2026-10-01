<?php
/**
|--------------------------------------------------------------------------
| لاگ انتشار آگهی در کانال‌ها (راند ۱۸)
|--------------------------------------------------------------------------
| GET ?action=list&ad_id=...  → فهرست تلاش‌های انتشار یک آگهی (جدیدترین اول)
| GET ?action=counts          → تعداد انتشار موفق هر آگهی (برای بج‌ها)
| جدول channel_publish_logs در صورت نبود، خودکار ساخته می‌شود.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/security-lib.php';

melkinoRequireAdminJson();

global $pdo;
if (!($pdo instanceof PDO)) {
    melkinoAdminJson(['success' => false, 'message' => 'پایگاه داده در دسترس نیست.'], 500);
}

// اطمینان از وجود جدول لاگ
try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS channel_publish_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ad_id VARCHAR(40) NOT NULL,
            platform VARCHAR(10) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            message_id VARCHAR(60) NULL,
            note VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cpl_ad (ad_id),
            INDEX idx_cpl_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    melkinoAdminJson(['success' => false, 'message' => melkinoSafeError($e, 'publish-logs.schema', 'جدول لاگ در دسترس نیست.')], 500);
}

$action = strtolower(trim((string)($_GET['action'] ?? 'list')));

if ($action === 'counts') {
    try {
        $rows = $pdo->query(
            'SELECT ad_id, platform, COUNT(*) AS total,
                    MAX(CASE WHEN success = 1 THEN created_at END) AS last_success
             FROM channel_publish_logs
             GROUP BY ad_id, platform'
        )->fetchAll(PDO::FETCH_ASSOC);
        melkinoAdminJson(['success' => true, 'counts' => $rows]);
    } catch (Throwable $e) {
        melkinoAdminJson(['success' => false, 'message' => melkinoSafeError($e, 'publish-logs.list', 'خواندن لاگ انجام نشد.')], 500);
    }
}

$adId = trim((string)($_GET['ad_id'] ?? ''));
if ($adId === '' || strlen($adId) > 40) {
    melkinoAdminJson(['success' => false, 'message' => 'شناسهٔ آگهی معتبر نیست.'], 422);
}

try {
    $st = $pdo->prepare(
        'SELECT id, platform, success, message_id, note, created_at
         FROM channel_publish_logs
         WHERE ad_id = ?
         ORDER BY id DESC
         LIMIT 100'
    );
    $st->execute([$adId]);
    melkinoAdminJson(['success' => true, 'logs' => $st->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    melkinoAdminJson(['success' => false, 'message' => melkinoSafeError($e, 'publish-logs.op', 'عملیات انجام نشد.')], 500);
}
