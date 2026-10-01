<?php
/**
|--------------------------------------------------------------------------
| محافظ مشترکِ endpointهای پنل ادمین
|--------------------------------------------------------------------------
| هر فایل API جدیدِ پنل، ابتدا این فایل را require می‌کند.
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security-lib.php';

if (!function_exists('melkinoRequireAdminJson')) {
    function melkinoRequireAdminJson(): void
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // محافظت CSRF: همه‌ی درخواست‌های تغییردهنده (POST/PUT/PATCH/DELETE)
        // باید هدر X-CSRF-Token (یا csrf_token در بدنه) را با توکن سشن
        // تطبیق بدهند تا فرم‌های cross-origin از سایت‌های متخلف رد شوند.
        if (melkinoIsMutatingRequest()) {
            melkinoCsrfCheck();
        }
    }
}

if (!function_exists('melkinoAdminJson')) {
    function melkinoAdminJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('melkinoAdminJsonBody')) {
    function melkinoAdminJsonBody(): array
    {
        if (function_exists('melkinoReadRequestBody')) {
            $data = melkinoReadRequestBody();
            if (is_array($data) && $data) {
                return $data;
            }
        }
        $raw = (string) @file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
        return is_array($_POST) ? $_POST : [];
    }
}
