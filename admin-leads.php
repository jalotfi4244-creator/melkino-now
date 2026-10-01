<?php
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/config.php';
if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>پیگیری لید و پیامک — ملکینو</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="design-pro.css">
    <?php require_once __DIR__ . '/csrf-shim.php'; ?>
    <style>
        body { background: var(--bg, #f4f6f5); margin: 0; font-family: inherit; }
        .leads-wrap { max-width: 1100px; margin: 0 auto; padding: 18px 14px 48px; }
        .leads-head { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
        .leads-head h1 { margin: 0; font-size: 20px; }
        .admin-card { background: var(--surface, #fff); border: 1px solid var(--border, #e3e8e6); border-radius: 16px; margin-bottom: 16px; overflow: hidden; }
        .card-header { display: flex; justify-content: space-between; gap: 10px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--border, #e3e8e6); }
        .card-title { font-weight: 800; }
        .leads-body { padding: 14px 16px; }
        .leads-row { display: grid; grid-template-columns: 1.4fr .8fr .6fr .9fr auto; gap: 8px; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border, #eee); font-size: 13px; }
        .leads-row:last-child { border-bottom: 0; }
        .leads-row small { color: var(--text-secondary, #667); display: block; }
        .btn-sms { border: 0; background: #0E7C6E; color: #fff; border-radius: 10px; padding: 8px 12px; font: inherit; font-size: 12px; font-weight: 800; cursor: pointer; }
        .btn-sms:disabled { opacity: .45; cursor: not-allowed; }
        .btn-sec { border: 1px solid var(--border, #ccc); background: #fff; border-radius: 10px; padding: 8px 12px; font: inherit; font-size: 12px; cursor: pointer; }
        .leads-tools { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 10px; }
        .leads-tools select, .leads-tools input { font: inherit; padding: 7px 10px; border-radius: 10px; border: 1px solid var(--border, #ccc); }
        .muted { color: var(--text-secondary, #667); font-size: 12px; line-height: 1.8; }
        .ok { color: #0E7C6E; font-size: 12px; }
        .err { color: #c0392b; font-size: 12px; }
        @media (max-width: 720px) {
            .leads-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="leads-wrap">
    <div class="leads-head">
        <h1>پیگیری لید و پیامک</h1>
        <a class="btn-sec" href="admin-panel.php">بازگشت به پنل ادمین</a>
    </div>
    <p class="muted">از همین پنل پیامک سیستم، فایل‌های منطبق با درخواست را برای مشتری بفرست یا فقط تعداد تطبیق را اطلاع بده. بازدید تکراری آگهی (۳ بار، ۴ بار، …) هم برای پیگیری اینجاست.</p>

    <div class="admin-card">
        <div class="card-header"><span class="card-title">درخواست‌های دارای تطبیق</span><span id="matchCount" class="muted"></span></div>
        <div class="leads-body" id="matchList">در حال بارگذاری…</div>
    </div>

    <div class="admin-card">
        <div class="card-header"><span class="card-title">بازدید تکراری آگهی</span></div>
        <div class="leads-body">
            <div class="leads-tools">
                <label>حداقل بازدید
                    <select id="minViews">
                        <option value="2">۲ بار</option>
                        <option value="3" selected>۳ بار</option>
                        <option value="4">۴ بار</option>
                        <option value="5">۵ بار</option>
                        <option value="8">۸ بار</option>
                    </select>
                </label>
                <button type="button" class="btn-sec" id="reloadViewers">بارگذاری دوباره</button>
            </div>
            <div id="viewerList">در حال بارگذاری…</div>
        </div>
    </div>

    <div class="admin-card">
        <div class="card-header"><span class="card-title">آخرین پیامک‌های لید</span></div>
        <div class="leads-body" id="logList">در حال بارگذاری…</div>
    </div>
</div>
<script src="admin-leads.js"></script>
</body>
</html>
