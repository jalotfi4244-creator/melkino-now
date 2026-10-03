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
/**
 * راند ۲۵: صفحهٔ انتخاب نوع ثبت
 * دکمهٔ وسط فوتر («ثبت») به این صفحه می‌آید؛ کاربر بین دو مسیر انتخاب می‌کند:
 *   ۱) ثبت ملک    → register-step1.php (مالک/مشاور، آگهی خودش را منتشر می‌کند)
 *   ۲) ثبت درخواست → property-request.php (متقاضی، دنبال ملک می‌گردد)
 * (مسیر «مشارکت در ساخت» طبق تصمیم محصول از این صفحه حذف شد؛
 *  فرم آن در register-partnership.php همچنان موجود است.)
 */
require_once __DIR__ . '/header.php';
?>
<style>
    .main-content { flex: 1; overflow-y: auto; padding-bottom: 90px; background: var(--bg); }
    .choose-wrap { padding: var(--space-3); display: flex; flex-direction: column; gap: var(--space-3); max-width: 560px; margin: 0 auto; }
    .choose-head { text-align: center; padding-top: var(--space-2); }
    .choose-head h1 { font-size: 22px; font-weight: 900; color: var(--text-primary); margin: 0 0 6px; }
    .choose-head p { font-size: 14px; color: var(--text-secondary); margin: 0; line-height: 1.9; }
    .choose-card {
        display: block; text-decoration: none;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius-md, 16px); padding: 20px 18px;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .choose-card:active { transform: scale(0.98); }
    .choose-card:hover { border-color: var(--primary); box-shadow: 0 6px 18px rgba(0,0,0,0.07); }
    .choose-card .cc-top { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
    .choose-card .cc-icon {
        width: 46px; height: 46px; border-radius: 14px; flex: 0 0 46px;
        display: flex; align-items: center; justify-content: center; font-size: 24px;
        background: rgba(6, 78, 78, 0.10);
    }
    .choose-card .cc-title { font-size: 17px; font-weight: 800; color: var(--text-primary); }
    .choose-card .cc-sub { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
    .choose-card .cc-desc { font-size: 13px; color: var(--text-secondary); line-height: 2; margin: 0 0 12px; }
    .choose-card .cc-go { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: var(--primary); }
</style>

<div class="main-content">
    <div class="choose-wrap">
        <div class="choose-head">
            <h1>چه چیزی می‌خواهید ثبت کنید؟</h1>
            <p>یکی از مسیرهای زیر را انتخاب کنید تا به فرم مربوطه هدایت شوید.</p>
        </div>

        <a class="choose-card" href="register-step1.php">
            <div class="cc-top">
                <div class="cc-icon"><?= melkinoSvgIcon('home', 'mk-icon mk-icon--lg') ?></div>
                <div>
                    <div class="cc-title">ثبت ملک</div>
                    <div class="cc-sub">برای مالکین و مشاورین املاک</div>
                </div>
            </div>
            <p class="cc-desc">
                اگر ملکی برای فروش، رهن یا اجاره دارید، مشخصات آن را ثبت کنید تا
                آگهی شما ساخته شود و بتوانید آن را در سایت، کانال تلگرام و بله
                منتشر کنید.
            </p>
            <span class="cc-go">ادامهٔ ثبت ملک ←</span>
        </a>

        <a class="choose-card" href="property-request.php">
            <div class="cc-top">
                <div class="cc-icon"><?= melkinoSvgIcon('list', 'mk-icon mk-icon--lg') ?></div>
                <div>
                    <div class="cc-title">ثبت درخواست</div>
                    <div class="cc-sub">برای خریداران و مستأجرین</div>
                </div>
            </div>
            <p class="cc-desc">
                اگر دنبال ملک هستید، ویژگی‌های ملک مورد نظرتان (نوع، بودجه،
                محله و …) را ثبت کنید تا فایل‌های مناسب شما پیدا شوند و وقتی
                ملک مطابق درخواستتان ثبت شد، باخبر شوید.
            </p>
            <span class="cc-go">ادامهٔ ثبت درخواست ←</span>
        </a>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
