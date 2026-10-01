<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/melkino-logo.php';

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'روش مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (function_exists('melkinoCsrfCheck')) {
    melkinoCsrfCheck();
}

if (empty($_FILES['onboarding_logo']) || ($_FILES['onboarding_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'فایلی ارسال نشده یا خطا در آپلود'], JSON_UNESCAPED_UNICODE);
    exit;
}

$f = $_FILES['onboarding_logo'];
if (($f['size'] ?? 0) > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'حجم فایل بیشتر از ۵ مگابایت است'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fi = finfo_open(FILEINFO_MIME_TYPE);
$mime = $fi ? finfo_file($fi, $f['tmp_name']) : '';
if ($fi) {
    finfo_close($fi);
}
$ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
if (!$ext || @getimagesize($f['tmp_name']) === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'فرمت فایل مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

$brandDir = __DIR__ . '/uploads/branding';
$upDir = __DIR__ . '/uploads';
if (!is_dir($upDir)) {
    @mkdir($upDir, 0755, true);
}
if (!is_dir($brandDir)) {
    @mkdir($brandDir, 0755, true);
}
if (!is_dir($brandDir) || !is_writable($brandDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'پوشهٔ uploads/branding قابل نوشتن نیست.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$oldMeta = dbSettingGet($pdo, 'branding', 'logos', []);
if (!is_array($oldMeta)) {
    $oldMeta = [];
}

$name = 'site-logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
$path = $brandDir . '/' . $name;
if (!move_uploaded_file($f['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در ذخیره‌سازی فایل'], JSON_UNESCAPED_UNICODE);
    exit;
}
@touch($path);

$stable = $upDir . '/onboarding-logo.' . $ext;
foreach (glob($upDir . '/onboarding-logo.*') ?: [] as $old) {
    if (is_file($old)) {
        @unlink($old);
    }
}
@copy($path, $stable);

$prev = (string) ($oldMeta['primary_logo']['url'] ?? '');
$prev = function_exists('melkinoLogoStripQuery') ? melkinoLogoStripQuery($prev) : $prev;
foreach (glob($brandDir . '/site-logo-*.*') ?: [] as $old) {
    if (is_file($old) && basename($old) !== $name) {
        @unlink($old);
    }
}
foreach (glob($brandDir . '/onboarding-logo.*') ?: [] as $old) {
    if (is_file($old)) {
        @unlink($old);
    }
}

$adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
if (!$adminId) {
    $adminId = null;
}

$meta = [
    'url' => 'uploads/branding/' . $name,
    'filename' => $name,
    'mime' => $mime,
    'updated_at' => date('Y-m-d H:i:s'),
];
$oldMeta['primary_logo'] = $meta;
dbSettingSet($pdo, 'branding', 'logos', $oldMeta, 'json', $adminId);

$public = function_exists('melkinoSiteLogoUrl') ? melkinoSiteLogoUrl() : ('uploads/branding/' . $name);
echo json_encode([
    'success' => true,
    'message' => 'لوگو با موفقیت آپلود شد',
    'logo_url' => $public,
    'file' => $meta['url'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
