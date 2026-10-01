<?php
/**
 * بدون ورود، هیچ صفحهٔ عمومی ملکینو دیده نمی‌شود.
 * وب‌هوک ربات، ورود، و پنل ادمین مستثنا هستند.
 */
if (!function_exists('melkinoEnforceSiteLogin')) {
    function melkinoEnforceSiteLogin(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (defined('PHP_SAPI') && PHP_SAPI === 'cli') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_start();
        }

        $page = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        if ($page === '') {
            $page = strtolower(basename((string) ($_SERVER['PHP_SELF'] ?? 'home.php')));
        }

        $allow = [
            'login.php',
            'logout.php',
            'auth.php',
            'auth-telegram.php',
            'auth-bale.php',
            'auth-eitaa.php',
            'request-otp.php',
            'verify-otp.php',
            'admin-login.php',
            'admin-logout.php',
            'telegram.php',
            'bale.php',
            'eitaa.php',
            'telegram-relay.php',
            'identity-sync.php',
            'bale-ok.php',
            'r.php',
        ];
        if (in_array($page, $allow, true)) {
            return;
        }
        if (substr($page, 0, 6) === 'admin-') {
            return;
        }

        $logged = !empty($_SESSION['user_id'])
            || !empty($_SESSION['reg_telegram_id'])
            || !empty($_SESSION['reg_bale_id'])
            || !empty($_SESSION['reg_eitaa_id'])
            || !empty($_SESSION['user_phone'])
            || !empty($_SESSION['is_admin']);

        if (!$logged && function_exists('melkinoCurrentIdentity')) {
            $idn = melkinoCurrentIdentity();
            $logged = !empty($idn['user_id']);
        }
        if ($logged) {
            return;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $isApi = (substr($page, -8) === '-api.php')
            || isset($_GET['action'])
            || isset($_POST['action'])
            || strpos($accept, 'application/json') !== false
            || $xhr === 'xmlhttprequest';

        if ($isApi) {
            if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'ok' => false,
                'message' => 'برای دیدن این بخش باید وارد ملکینو شوی.',
                'login' => 'login.php',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $here = (string) ($_SERVER['REQUEST_URI'] ?? $page);
        $here = preg_replace('#^/+#', '', $here) ?? $page;
        if ($here === '' || strpos($here, 'login.php') === 0) {
            $here = 'home.php';
        }
        $to = 'login.php?redirect=' . rawurlencode($here);
        if (!headers_sent()) {
            header('Location: ' . $to, true, 302);
        } else {
            echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">'
                . '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '">'
                . '<script>location.replace(' . json_encode($to, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');</script>'
                . '</head><body>برای دیدن ملکینو باید وارد شوید. <a href="'
                . htmlspecialchars($to, ENT_QUOTES, 'UTF-8') . '">ورود</a></body></html>';
        }
        exit;
    }
}

if (!function_exists('melkinoRequireLogin')) {
    function melkinoRequireLogin(?string $returnTo = null)
    {
        melkinoEnforceSiteLogin();
        return function_exists('melkinoCurrentIdentity') ? melkinoCurrentIdentity() : [];
    }
}

melkinoEnforceSiteLogin();
