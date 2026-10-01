<?php
/**
 * ساخت / بازنشانی حساب ادمین — فقط از خط فرمان (CLI)
 * ------------------------------------------------------------------
 * جایگزین امنِ «رمز پشتیبان ثابت» که از admin-login.php حذف شد.
 * این فایل از طریق وب قابل اجرا نیست؛ اگر کسی آن را در مرورگر باز کند
 * با ۴۰۴ روبه‌رو می‌شود و هیچ کاری انجام نمی‌دهد.
 *
 * استفاده:
 *   php tools/create-admin.php --username=admin
 *   php tools/create-admin.php --username=admin --password='...'   (توصیه نمی‌شود: در history شل می‌ماند)
 *
 * اگر --password ندهید، رمز به‌صورت مخفی از ورودی پرسیده می‌شود.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';

global $pdo;
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "اتصال به پایگاه داده برقرار نشد.\n");
    exit(1);
}

$opts = getopt('', ['username::', 'password::', 'deactivate::']);
$username = trim((string) ($opts['username'] ?? 'admin'));
if ($username === '' || !preg_match('/^[A-Za-z0-9_.\-]{3,64}$/', $username)) {
    fwrite(STDERR, "نام کاربری نامعتبر است (۳ تا ۶۴ کاراکتر، حروف/عدد/._-).\n");
    exit(1);
}

if (isset($opts['deactivate'])) {
    $st = $pdo->prepare('UPDATE admins SET is_active = 0 WHERE username = ?');
    $st->execute([$username]);
    fwrite(STDOUT, $st->rowCount() > 0 ? "حساب «$username» غیرفعال شد.\n" : "حسابی با این نام پیدا نشد.\n");
    exit(0);
}

$password = (string) ($opts['password'] ?? '');
if ($password === '') {
    fwrite(STDOUT, "رمز جدید برای «$username» را وارد کنید (نمایش داده نمی‌شود): ");
    if (function_exists('shell_exec') && stripos(PHP_OS, 'WIN') !== 0) {
        @shell_exec('stty -echo 2>/dev/null');
    }
    $password = trim((string) fgets(STDIN));
    if (function_exists('shell_exec') && stripos(PHP_OS, 'WIN') !== 0) {
        @shell_exec('stty echo 2>/dev/null');
    }
    fwrite(STDOUT, "\n");
}

if (strlen($password) < 12) {
    fwrite(STDERR, "رمز باید حداقل ۱۲ کاراکتر باشد.\n");
    exit(1);
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    fwrite(STDERR, "رمز باید حداقل یک حرف و یک رقم داشته باشد.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
unset($password);

try {
    $st = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $id = $st->fetchColumn();

    if ($id) {
        $pdo->prepare('UPDATE admins SET password_hash = ?, is_active = 1, updated_at = NOW() WHERE id = ?')
            ->execute([$hash, (int) $id]);
        fwrite(STDOUT, "رمز حساب «$username» به‌روزرسانی شد (id=$id).\n");
    } else {
        $pdo->prepare('INSERT INTO admins (username, password_hash, is_active) VALUES (?, ?, 1)')
            ->execute([$username, $hash]);
        fwrite(STDOUT, "حساب ادمین «$username» ساخته شد (id=" . $pdo->lastInsertId() . ").\n");
    }
} catch (Throwable $e) {
    // پیام خام استثنا چاپ نمی‌شود تا نام جدول/ستون لو نرود
    error_log('[melkino][create-admin] ' . $e->getMessage());
    fwrite(STDERR, "ذخیره انجام نشد؛ جزئیات در لاگ سرور ثبت شد.\n");
    exit(1);
}

fwrite(STDOUT, "هشدار: این رمز را در جای امن نگه دارید. هیچ رمز پشتیبانی در کد وجود ندارد.\n");
exit(0);
