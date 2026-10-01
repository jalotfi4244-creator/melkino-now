<?php
/**
 * صفحهٔ تشخیص مینی‌اپ بله — بدون هدر امنیتی، بدون SDK اضافه
 * اگر همین صفحه هم در بله وب «blocked» شد، مشکل از هاست است نه از ملکینو.
 */
if (!headers_sent()) {
    header_remove('X-Frame-Options');
    header_remove('Content-Security-Policy');
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://tapi.bale.ai/miniapp.js?3"></script>
<script>
(function () {
    function go() {
        try {
            var w = window.Bale && window.Bale.WebApp;
            if (w) {
                try { w.ready(); } catch (e) {}
                try { w.expand(); } catch (e) {}
            }
        } catch (e) {}
    }
    go();
    setTimeout(go, 50);
    setTimeout(go, 300);
})();
</script>
<title>تست مینی‌اپ بله</title>
<style>
body{font-family:Tahoma,sans-serif;background:#122320;color:#fff;margin:0;padding:24px;line-height:1.9}
.ok{background:#1F8A70;padding:12px 16px;border-radius:12px}
.bad{background:#8A1F1F;padding:12px 16px;border-radius:12px}
code{direction:ltr;display:block;background:#0D1413;padding:8px;border-radius:8px;margin:8px 0}
</style>
</head>
<body>
<h1>اگر این متن را می‌بینی، iframe باز شده</h1>
<div id="box" class="bad">در حال بررسی SDK بله…</div>
<p>اگر در بله وب به‌جای این صفحه آیکون سند شکسته و «This content is blocked» می‌بینی، هاست InfinityFree اجازهٔ iframe به دامنهٔ دیگر را نمی‌دهد و با .htaccess درست نمی‌شود.</p>
<script>
(function () {
    var box = document.getElementById('box');
    var w = null;
    try { w = window.Bale && window.Bale.WebApp; } catch (e) {}
    if (w) {
        box.className = 'ok';
        box.innerHTML = 'SDK بله وصل است<br>platform: ' + (w.platform || '—')
            + '<br>initData: ' + ((w.initData && w.initData.length) ? (w.initData.length + ' کاراکتر') : 'خالی');
        try { w.ready(); } catch (e) {}
    } else {
        box.innerHTML = 'صفحه لود شد ولی Bale.WebApp نیست (این تب مرورگر معمولی است، نه مینی‌اپ).';
    }
})();
</script>
</body>
</html>
