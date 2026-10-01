<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/eitaa.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bot-settings.php';

if (!melkinoLoginMethodEnabled('eitaa')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'ورود با ایتا توسط مدیر سایت غیرفعال شده است.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'روش مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = function_exists('melkinoEitaaToken') ? melkinoEitaaToken() : '';
if ($token === '' || $token === 'توکن_برنامه_ایتا') {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'توکن برنامهٔ ایتا در پنل تنظیم نشده است.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = [];
}

$initData = (string)($body['init_data'] ?? '');
if ($initData === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'initData ارسال نشد.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$verified = eitaaVerifyInitData($initData);
if ($verified === null) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'امضای ایتا معتبر نیست یا منقضی شده.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim($verified['first_name'] . ' ' . $verified['last_name']);
$identity = melkinoUpsertUser('', '', $name, $verified['username'], null, null, $verified['id']);

melkinoRecordLoginInfo($identity['id'] ?? null, 'eitaa', [
    'eitaa_id' => $verified['id'],
    'username' => $verified['username'],
    'name'     => $name,
]);

if (function_exists('melkinoSyncTelegramIdentity')) {
    melkinoSyncTelegramIdentity($identity['id'] ?? null, $verified, 'eitaa', $initData);
}

if (empty($identity['trusted'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ثبت هویت ناموفق بود.'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_regenerate_id(true);

$_SESSION['reg_eitaa_id'] = $verified['id'];
if ($name !== '') {
    $_SESSION['user_name'] = $name;
}

$phone = '';
try {
    $phoneRow = $pdo->prepare('SELECT phone FROM users WHERE id = ?');
    $phoneRow->execute([$identity['id']]);
    $phone = trim((string)$phoneRow->fetchColumn());
    if ($phone !== '') {
        $_SESSION['user_phone'] = $phone;
    }
} catch (Throwable $e) {
}

$loginToken = function_exists('melkinoMintLoginToken')
    ? melkinoMintLoginToken($identity['id'] ?? null, null, null)
    : '';

echo json_encode([
    'success'     => true,
    'user_id'     => $identity['id'],
    'name'        => $name,
    'phone'       => $phone,
    'login_token' => $loginToken,
], JSON_UNESCAPED_UNICODE);
