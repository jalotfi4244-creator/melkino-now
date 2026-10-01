<?php
/*
|--------------------------------------------------------------------------
| melkino-migrate.php — همگام‌سازی ساختار دیتابیس (یک‌بار مصرف)
|--------------------------------------------------------------------------
| کارها:
|   ۱) جدول‌های جاافتاده را می‌سازد (از melkino-database.sql کنارسش؛
|      همه CREATE TABLE IF NOT EXISTS اند، پس داده‌ی موجود دست‌نخورده است).
|   ۲) ستون‌های جاافتاده‌ی جدول‌های موجود را اضافه می‌کند (بر اساس
|      نیازِ کد؛ فقط ستون‌هایی که وجود ندارند).
|   ۳) collation جدول‌های مقایسه را با جدول ads یکسان می‌کند تا خطای
|      «Illegal mix of collations» (1267) دیگر هرگز ظاهر نشود.
|
| مصرف:
|   - این فایل را کنار بقیه‌ی فایل‌های سایت آپلود کن،
|   - وارد پنل ادمین شو (لاگین ادمین)،
|   - آدرس melkino-migrate.php را باز کن،
|   - گزارش را ببین و بعد فایل را از هاست حذف کن.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo 'فقط ادمین می‌تواند این صفحه را اجرا کند. اول وارد پنل ادمین شو.';
    exit;
}

global $pdo;

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">'
    . '<title>مهاجرت دیتابیس ملکینو</title>'
    . '<style>body{font-family:Tahoma,sans-serif;background:#0D1413;color:#F3F4F6;padding:30px;line-height:1.9}'
    . 'li{margin:4px 0}.ok{color:#7ee2b8}.warn{color:#ffd47e}.bad{color:#ff8a8a}</style></head><body>';
echo '<h2>🛠 گزارش مهاجرت دیتابیس ملکینو</h2><ul>';

if (!($pdo instanceof PDO)) {
    echo '<li class="bad">اتصال دیتابیس برقرار نیست؛ اول config.secrets.php را بررسی کن.</li></ul></body></html>';
    exit;
}

/* ---------- ۱) جدول‌های جاافتاده ---------- */
$sqlFile = __DIR__ . '/melkino-database.sql';
if (is_readable($sqlFile)) {
    $sql = (string)file_get_contents($sqlFile);
    // حذف کامنت‌ها و تقسیم به دستور‌ها
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $made = 0;
    foreach ($statements as $st) {
        if (stripos($st, 'CREATE TABLE') === false) {
            continue;
        }
        try {
            $before = $pdo->query('SHOW TABLES')->rowCount();
            $pdo->exec($st);
            preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', $st, $m);
            $made++;
            echo '<li class="ok">بررسی/ساخت جدول: ' . htmlspecialchars($m[1] ?? '?') . '</li>';
            unset($before);
        } catch (Throwable $e) {
            echo '<li class="warn">هنگام ساخت جدول: ' . htmlspecialchars($e->getMessage()) . '</li>';
        }
    }
    echo '<li class="ok">' . $made . ' دستور CREATE بررسی/اجرا شد.</li>';
} else {
    echo '<li class="warn">فایل melkino-database.sql کنار این اسکریپت پیدا نشد؛ مرحله‌ی ساخت جدول‌ها رد شد.</li>';
}

/* ---------- ۲) ستون‌های جاافتاده ---------- */
$wanted = [
    'ads' => [
        'owner_user_id'     => 'INT NULL',
        'consultant_id'     => 'INT NULL',
        'area'              => 'VARCHAR(30) NULL',
        'built_area'        => 'VARCHAR(30) NULL',
        'land_area'         => 'VARCHAR(30) NULL',
        'rooms'             => 'VARCHAR(10) NULL',
        'floor'             => 'VARCHAR(10) NULL',
        'year'              => 'VARCHAR(10) NULL',
        'display_price'     => 'VARCHAR(40) NULL',
        'vip_until'         => 'DATETIME NULL',
        'rejected_at'       => 'DATETIME NULL',
        'archived_at'       => 'DATETIME NULL',
        'bale_message_id'   => 'VARCHAR(40) NULL',
        'bale_channel_id'   => 'VARCHAR(120) NULL',
        'bale_published_at' => 'DATETIME NULL',
        'user_id'           => 'INT NULL',
        'telegram_id'       => 'VARCHAR(64) NULL',
        'views'             => 'INT NOT NULL DEFAULT 0',
        // فیلدهای وام (فروش/پیش‌فروش): مبلغ وام از قیمت کسر و هر دو قیمت نمایش داده می‌شود
        'has_loan'                 => 'TINYINT(1) NOT NULL DEFAULT 0',
        'loan_amount'              => 'VARCHAR(40) NULL',
        'loan_type'                => 'VARCHAR(60) NULL',
        'loan_duration'            => 'VARCHAR(60) NULL',
        'loan_bank'                => 'VARCHAR(120) NULL',
        'loan_installment'         => 'VARCHAR(40) NULL',
        'loan_installments_paid'   => 'VARCHAR(20) NULL',
        'loan_notes'               => 'VARCHAR(500) NULL',
        // نوع سند + معاوضه (راند ۱۴)
        'deed_type'                => 'VARCHAR(40) NULL',
        'deed_notes'               => 'VARCHAR(500) NULL',
        'exchange_types'           => 'VARCHAR(255) NULL',
        // راند ۳۰: هویت تلگرامِ ثبت‌کننده روی رکورد آگهی
        'created_by_telegram_id'   => 'VARCHAR(30) NULL',
    ],
    'users' => [
        'bale_id'         => 'VARCHAR(30) NULL',
        'first_login'     => 'DATETIME NULL',
        'last_login'      => 'DATETIME NULL',
        'login_count'     => 'INT NOT NULL DEFAULT 0',
        'last_ip'         => 'VARCHAR(45) NULL',
        'last_platform'   => 'VARCHAR(20) NULL',
        'user_agent'      => 'VARCHAR(1000) NULL',
        'photo_url'       => 'VARCHAR(500) NULL',
        'language_code'   => 'VARCHAR(10) NULL',
        'bale_username'   => 'VARCHAR(191) NULL',
        'updated_at'      => 'DATETIME NULL',
        // ورود با پیامک (OTP) توکن دسترسی را روی کاربر ذخیره می‌کند
        'access_token'    => 'VARCHAR(64) NULL',
        // راند ۳۰: هویت تلگرام + قفل شمارهٔ تماس
        'telegram_first_name'     => 'VARCHAR(100) NULL',
        'telegram_last_name'      => 'VARCHAR(100) NULL',
        'telegram_username'       => 'VARCHAR(100) NULL',
        'telegram_language_code'  => 'VARCHAR(10) NULL',
        'telegram_is_premium'     => 'TINYINT(1) NOT NULL DEFAULT 0',
        'telegram_photo_url'      => 'VARCHAR(500) NULL',
        'telegram_auth_date'      => 'DATETIME NULL',
        'last_init_data'          => 'TEXT NULL',
        'phone_verified'          => 'TINYINT(1) NOT NULL DEFAULT 0',
        'phone_locked'            => 'TINYINT(1) NOT NULL DEFAULT 0',
        // راند ۳۱: نام/نام‌خانوادگی ثبت‌شده توسط خود کاربر (قفل‌شونده)
        'first_name'              => 'VARCHAR(100) NULL',
        'last_name'               => 'VARCHAR(100) NULL',
        'name_locked'             => 'TINYINT(1) NOT NULL DEFAULT 0',
    ],
    'images' => [
        'storage_path'     => 'VARCHAR(500) NULL',
        'sort_order'       => 'INT NOT NULL DEFAULT 0',
        'is_selected'      => 'TINYINT(1) NOT NULL DEFAULT 1',
        'is_primary'       => 'TINYINT(1) NOT NULL DEFAULT 0',
        'publish_publicly' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'created_at'       => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ],
    'compare_items' => [
        'guest_token' => 'VARCHAR(64) NULL',
    ],
    'compare_groups' => [
        'guest_token' => 'VARCHAR(64) NULL',
    ],
    // برخی نسخه‌های قدیمی، جدول settings را خیلی ساده ساخته بودند
    // (فقط setting_key/setting_value)؛ ولی db-settings.php به ستون‌های
    // زیر نیاز دارد و بدون آن‌ها، صفحه‌ی جزئیات ملک خطای ۵۰۰ می‌گیرد.
    'settings' => [
        'setting_group' => "VARCHAR(64) NOT NULL DEFAULT 'global'",
        'value_type' => "VARCHAR(32) NOT NULL DEFAULT 'string'",
        'updated_by_admin_id' => 'BIGINT UNSIGNED NULL',
    ],
    // صفحه‌ی جزئیات ملک، امکانات را با ORDER BY am.sort_order می‌خواند
    'amenities' => [
        'sort_order' => 'INT NOT NULL DEFAULT 0',
    ],
    // ستون‌هایی که بخش اعلان‌ها (کاربران، برودکست پنل، درصد تطبیق) نیاز دارد
    'notifications' => [
        'url' => 'VARCHAR(500) NULL',
        'broadcast_id' => 'BIGINT UNSIGNED NULL',
        'request_id' => 'INT UNSIGNED NULL',
        'ad_id' => 'VARCHAR(64) NULL',
        'match_percent' => 'DECIMAL(5,2) NULL',
    ],
    'notification_broadcasts' => [
        'created_by' => 'BIGINT UNSIGNED NULL',
    ],
    // راند ۳۰: ستون‌های تطبیق (موتور تطبیق و پنل ادمین این‌ها را می‌خوانند)
    'request_matches' => [
        'match_percent'         => 'DECIMAL(5,2) NOT NULL DEFAULT 0',
        'matched_transaction'   => 'VARCHAR(60) NULL',
        'matched_property_type' => 'VARCHAR(60) NULL',
        'location_score'        => 'INT NOT NULL DEFAULT 0',
        'area_score'            => 'INT NOT NULL DEFAULT 0',
        'budget_score'          => 'INT NOT NULL DEFAULT 0',
        'amenities_score'       => 'INT NOT NULL DEFAULT 0',
        'is_notified'           => 'TINYINT(1) NOT NULL DEFAULT 0',
    ],
    // راند ۳۰: ستون‌هایی که فرم درخواست ملک از قبل می‌نوشت ولی در
    // ساختارِ بعضی دیتابیس‌های قدیمی وجود نداشت (ثبت درخواست خطا می‌گرفت)
    'property_requests' => [
        'tracking_code'    => 'VARCHAR(30) NULL',
        'gender'           => 'VARCHAR(10) NULL',
        'last_name'        => 'VARCHAR(100) NULL',
        'transaction_type' => 'VARCHAR(60) NULL',
        'urgency'          => 'VARCHAR(40) NULL',
        'date_needed'      => 'VARCHAR(40) NULL',
        'rahn_kamal'       => 'VARCHAR(10) NULL',
        'min_area'         => 'VARCHAR(30) NULL',
        'max_area'         => 'VARCHAR(30) NULL',
        'min_price'        => 'VARCHAR(40) NULL',
        'max_price'        => 'VARCHAR(40) NULL',
        'min_deposit'      => 'VARCHAR(40) NULL',
        'max_deposit'      => 'VARCHAR(40) NULL',
        'min_rent'         => 'VARCHAR(40) NULL',
        'max_rent'         => 'VARCHAR(40) NULL',
        'min_age'          => 'VARCHAR(30) NULL',
        'max_age'          => 'VARCHAR(30) NULL',
        'is_not_keyed'     => 'VARCHAR(10) NULL',
        'additional'       => 'TEXT NULL',
        'property_details' => 'LONGTEXT NULL',
    ],
];

foreach ($wanted as $table => $columns) {
    try {
        $existing = [];
        foreach ($pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $existing[strtolower((string)$col['Field'])] = true;
        }
    } catch (Throwable $e) {
        echo '<li class="warn">جدول `' . htmlspecialchars($table) . '` وجود ندارد (مرحله‌ی ۱ باید می‌ساختش).</li>';
        continue;
    }
    foreach ($columns as $column => $definition) {
        if (isset($existing[strtolower($column)])) {
            continue;
        }
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
            echo '<li class="ok">ستون اضافه شد: ' . htmlspecialchars($table . '.' . $column) . '</li>';
        } catch (Throwable $e) {
            echo '<li class="bad">افزودن ستون ' . htmlspecialchars($table . '.' . $column) . ' ناموفق: '
                . htmlspecialchars($e->getMessage()) . '</li>';
        }
    }
}

/* ---------- ۲.۳۵) راند ۳۱: کلید یکتای شماره (یک شماره = یک حساب) ---------- */
try {
    $hasIdx = $pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='uniq_phone'"
    )->fetchColumn();
    if ((int)$hasIdx === 0) {
        $dups = $pdo->query(
            "SELECT COUNT(*) FROM (SELECT phone FROM users WHERE phone IS NOT NULL GROUP BY phone HAVING COUNT(*) > 1) d"
        )->fetchColumn();
        if ((int)$dups === 0) {
            $pdo->exec('ALTER TABLE `users` ADD UNIQUE KEY `uniq_phone` (`phone`)');
            echo '<li class="ok">کلید یکتای uniq_phone به users اضافه شد (یک شماره فقط روی یک حساب).</li>';
        } else {
            echo '<li class="warn">شمارهٔ تکراری در users وجود دارد؛ کلید یکتا اضافه نشد. اول تکراری‌ها را از تب کاربران پنل حذف کنید.</li>';
        }
    }
} catch (Throwable $e) {
    echo '<li class="warn">افزودن کلید یکتای شماره ناموفق: ' . htmlspecialchars($e->getMessage()) . '</li>';
}

/* ---------- ۲.۴) راند ۳۰: کلید یکتای تطبیق (برای ON DUPLICATE KEY) ---------- */
try {
    $hasIdx = $pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='request_matches' AND INDEX_NAME='uniq_req_ad'"
    )->fetchColumn();
    if ((int)$hasIdx === 0) {
        $pdo->exec('ALTER TABLE `request_matches` ADD UNIQUE KEY `uniq_req_ad` (`request_id`, `ad_id`)');
        echo '<li class="ok">کلید یکتای uniq_req_ad به request_matches اضافه شد.</li>';
    }
} catch (Throwable $e) {
    echo '<li class="warn">افزودن کلید یکتای request_matches ناموفق: ' . htmlspecialchars($e->getMessage()) . '</li>';
}

/* ---------- ۲.۵) راند ۳۰: مهاجرت دادهٔ شماره‌های موجود ---------- */
// شماره‌هایی که از قبل ثبت شده‌اند تأییدشده+قفل‌شده علامت می‌خورند تا
// کاربران موجود مسدود نشوند. idempotent است.
try {
    $n = $pdo->exec(
        "UPDATE users SET phone_verified = 1, phone_locked = 1
          WHERE phone IS NOT NULL AND phone <> '' AND phone_locked = 0"
    );
    echo '<li class="ok">مهاجرت شمارهٔ تماس: ' . (int)$n . ' کاربر تأیید/قفل شد.</li>';
} catch (Throwable $e) {
    echo '<li class="warn">مهاجرت شمارهٔ تماس انجام نشد: ' . htmlspecialchars($e->getMessage()) . '</li>';
}

/* ---------- ۳) یکسان‌سازی collation ---------- */
try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $adsColl = $pdo->prepare(
        'SELECT TABLE_COLLATION FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = "ads" LIMIT 1'
    );
    $adsColl->execute([$db]);
    $target = (string)$adsColl->fetchColumn();

    if ($target !== '') {
        foreach (['compare_items', 'compare_groups'] as $t) {
            $q = $pdo->prepare(
                'SELECT TABLE_COLLATION FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1'
            );
            $q->execute([$db, $t]);
            $cur = (string)$q->fetchColumn();
            if ($cur === '' || $cur === $target) {
                continue;
            }
            $charset = explode('_', $target)[0];
            $pdo->exec('ALTER TABLE `' . $t . '` CONVERT TO CHARACTER SET ' . $charset . ' COLLATE ' . $target);
            echo '<li class="ok">collation جدول ' . htmlspecialchars($t) . ' از '
                . htmlspecialchars($cur) . ' به ' . htmlspecialchars($target) . ' تغییر کرد.</li>';
        }
        echo '<li class="ok">collation مرجع (جدول ads): ' . htmlspecialchars($target) . '</li>';
    }
} catch (Throwable $e) {
    echo '<li class="warn">یکسان‌سازی collation انجام نشد: ' . htmlspecialchars($e->getMessage()) . '</li>';
}

echo '<li class="ok">✅ پایان. حالا این فایل و melkino-database.sql را از هاست حذف کن '
    . '(یا بگذار بمانند؛ بدون لاگین ادمین اجرا نمی‌شوند).</li>';
echo '</ul></body></html>';
