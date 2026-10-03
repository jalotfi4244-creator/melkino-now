<?php
/**
|--------------------------------------------------------------------------
| ملکینو — مدیریت «درخواست‌های مشارکت در ساخت» (پنل ادمین)
|--------------------------------------------------------------------------
| صفحهٔ مستقل محافظت‌شده. آدرس: admin-partnership.php
| API: ?action=list|get|status|delete|note  (JSON + CSRF + گارد ادمین)
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/admin-guard.php';

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
}
if (empty($_SESSION['is_admin'])) {
    header('Location: admin-login.php?redirect=' . rawurlencode('admin-partnership.php'), true, 302);
    exit;
}

require_once __DIR__ . '/partnership-lib.php';
melkinoEnsurePartnershipSchema();
global $pdo;

/* ---------------- API ---------------- */
$mkAction = (string)($_GET['action'] ?? $_POST['action'] ?? '');
if ($mkAction !== '') {
    melkinoRequireAdminJson();

    switch ($mkAction) {
        case 'list':
            $status = trim((string)($_GET['status'] ?? ''));
            $q = trim((string)($_GET['q'] ?? ''));
            $sql = "SELECT id, code, status, title, property_type, area, current_status, city, neighborhood,
                           owner_share, builder_share, balaghz, balaghz_amount, value_from, value_to,
                           owner_name, phone, completeness, created_at
                    FROM partnership_requests";
            $where = [];
            $params = [];
            if ($status !== '' && array_key_exists($status, melkinoPartStatuses())) {
                $where[] = "status = ?";
                $params[] = $status;
            }
            if ($q !== '') {
                $where[] = "(code LIKE ? OR title LIKE ? OR city LIKE ? OR neighborhood LIKE ? OR phone LIKE ?)";
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like, $like, $like);
            }
            if ($where) {
                $sql .= " WHERE " . implode(' AND ', $where);
            }
            $sql .= " ORDER BY created_at DESC, id DESC LIMIT 300";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            $counts = [];
            foreach ($pdo->query("SELECT status, COUNT(*) c FROM partnership_requests GROUP BY status") as $r) {
                $counts[$r['status']] = (int)$r['c'];
            }
            melkinoAdminJson(['success' => true, 'rows' => $rows, 'counts' => $counts]);

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $st = $pdo->prepare("SELECT * FROM partnership_requests WHERE id = ? LIMIT 1");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                melkinoAdminJson(['success' => false, 'message' => 'یافت نشد.'], 404);
            }
            melkinoAdminJson(['success' => true, 'row' => $row]);

        case 'status':
            $data = function_exists('melkinoAdminJsonBody') ? melkinoAdminJsonBody() : $_POST;
            $id = (int)($data['id'] ?? 0);
            $status = trim((string)($data['status'] ?? ''));
            if (!array_key_exists($status, melkinoPartStatuses())) {
                melkinoAdminJson(['success' => false, 'message' => 'وضعیت نامعتبر است.'], 422);
            }
            $st = $pdo->prepare("UPDATE partnership_requests SET status = ?, updated_at = NOW() WHERE id = ?");
            $st->execute([$status, $id]);
            melkinoAdminJson(['success' => true]);

        case 'site_publish':
            // انتشار «مشارکت در ساخت» در سایت: آینهٔ آگهی در جدول ads ساخته/به‌روز می‌شود
            // on=1 → انتشار (UPSERT آینه با status=published) | on=0 → قطع (status=archived)
            // بدون body → فقط بررسی وضعیت فعلی
            $data = function_exists('melkinoAdminJsonBody') ? melkinoAdminJsonBody() : $_POST;
            $pid = (int)($data['id'] ?? 0);
            if ($pid <= 0) {
                melkinoAdminJson(['success' => false, 'message' => 'شناسه نامعتبر است.'], 422);
            }
            $st = $pdo->prepare('SELECT * FROM partnership_requests WHERE id = ? LIMIT 1');
            $st->execute([$pid]);
            $pr = $st->fetch(PDO::FETCH_ASSOC);
            if (!$pr) {
                melkinoAdminJson(['success' => false, 'message' => 'درخواست یافت نشد.'], 404);
            }
            $mirrorId = 'PRT-' . $pid;
            $chk = $pdo->prepare("SELECT status FROM ads WHERE id = ? LIMIT 1");
            $chk->execute([$mirrorId]);
            $mirrorStatus = (string)($chk->fetchColumn() ?: '');

            if (!array_key_exists('on', $data)) {
                melkinoAdminJson(['success' => true, 'published' => $mirrorStatus === 'published']);
            }

            $on = !empty($data['on']);
            if ($on) {
                $loc = trim(($pr['city'] ?? '') . (($pr['neighborhood'] ?? '') !== '' ? '، ' . $pr['neighborhood'] : ''));
                $descParts = [];
                if (!empty($pr['current_status'])) $descParts[] = 'وضعیت فعلی: ' . $pr['current_status'];
                if (!empty($pr['br_count'])) $descParts[] = 'بر: ' . $pr['br_count'];
                if (!empty($pr['direction'])) $descParts[] = 'جهت: ' . $pr['direction'] ?? '';
                if (!empty($pr['buildable_floors'])) $descParts[] = 'تراکم قابل ساخت: ' . $pr['buildable_floors'] . ' طبقه';
                if (!empty($pr['buildable_units'])) $descParts[] = 'تعداد واحد قابل ساخت: ' . $pr['buildable_units'];
                if (!empty($pr['owner_share'])) $descParts[] = 'سهم مالک: ' . $pr['owner_share'];
                if (!empty($pr['duration'])) $descParts[] = 'مدت: ' . $pr['duration'];
                $desc = 'مشارکت در ساخت — ' . implode(' · ', array_filter($descParts));
                if (!empty($pr['notes'])) $desc .= "\n" . mb_substr((string)$pr['notes'], 0, 800);
                $fields = [
                    ':id' => $mirrorId,
                    ':title' => mb_substr((string)($pr['title'] ?? 'مشارکت در ساخت'), 0, 180),
                    ':pt' => (string)($pr['property_type'] ?? ''),
                    ':area' => (string)($pr['area'] ?? ''),
                    ':loc' => mb_substr($loc !== '' ? $loc : 'مشارکت در ساخت', 0, 250),
                    ':addr' => mb_substr((string)($pr['address'] ?? ''), 0, 500),
                    ':phone' => (string)($pr['phone'] ?? ''),
                    ':owner' => mb_substr((string)($pr['owner_name'] ?? ''), 0, 120),
                    ':desc' => $desc,
                    ':lat' => ($pr['latitude'] ?? null) !== null && $pr['latitude'] !== '' ? (float)$pr['latitude'] : null,
                    ':lng' => ($pr['longitude'] ?? null) !== null && $pr['longitude'] !== '' ? (float)$pr['longitude'] : null,
                ];
                $exists = $mirrorStatus !== '';
                if ($exists) {
                    $pdo->prepare("UPDATE ads SET title=:title, property_type=:pt, area=:area, location=:loc, address=:addr, phone=:phone, last_name=:owner, description=:desc, latitude=:lat, longitude=:lng, status='published', updated_at=NOW() WHERE id=:id")
                        ->execute($fields);
                } else {
                    $pdo->prepare("INSERT INTO ads (id, title, status, transaction_type, property_type, area, location, address, phone, last_name, description, latitude, longitude, created_at, updated_at)
                                   VALUES (:id, :title, 'published', 'مشارکت در ساخت', :pt, :area, :loc, :addr, :phone, :owner, :desc, :lat, :lng, NOW(), NOW())")
                        ->execute($fields);
                }
                if (function_exists('melkinoLogAdHistory')) {
                    melkinoLogAdHistory($pdo, $mirrorId, 'انتشار مشارکت در سایت', 'درخواست مشارکت #' . $pid);
                }
                melkinoAdminJson(['success' => true, 'published' => true, 'ad_id' => $mirrorId, 'message' => 'مشارکت در سایت منتشر شد.']);
            }
            $pdo->prepare("UPDATE ads SET status='archived', updated_at=NOW() WHERE id = ?")->execute([$mirrorId]);
            if (function_exists('melkinoLogAdHistory')) {
                melkinoLogAdHistory($pdo, $mirrorId, 'قطع انتشار مشارکت', 'درخواست مشارکت #' . $pid);
            }
            melkinoAdminJson(['success' => true, 'published' => false, 'message' => 'انتشار مشارکت از سایت قطع شد.']);

        case 'note':
            $data = function_exists('melkinoAdminJsonBody') ? melkinoAdminJsonBody() : $_POST;
            $id = (int)($data['id'] ?? 0);
            $note = mb_substr(trim(strip_tags((string)($data['note'] ?? ''))), 0, 1000);
            $st = $pdo->prepare("UPDATE partnership_requests SET admin_note = ?, updated_at = NOW() WHERE id = ?");
            $st->execute([$note, $id]);
            melkinoAdminJson(['success' => true]);

        case 'update':
            // ویرایش ادمینی — همان فیلدهای فرم + وضعیت (فراخوانی از مودال ویرایش آگهی‌ها)
            $mkBody = melkinoAdminJsonBody();
            $mkId = (int)($mkBody['id'] ?? 0);
            if ($mkId <= 0) {
                melkinoAdminJson(['success' => false, 'message' => 'شناسه نامعتبر است.'], 400);
            }
            $mkSt = $pdo->prepare("SELECT * FROM partnership_requests WHERE id = ? LIMIT 1");
            $mkSt->execute([$mkId]);
            $mkRow = $mkSt->fetch(PDO::FETCH_ASSOC);
            if (!$mkRow) {
                melkinoAdminJson(['success' => false, 'message' => 'درخواست یافت نشد.'], 404);
            }
            $mkOpt = melkinoPartOptions();
            // فیلدی که در بدنه ارسال نشده باشد دست‌نخورده می‌ماند
            // (آپدیتِ جزئی نباید بقیهٔ فیلدها را خالی کند).
            $mkTxt = static function (string $k, int $max = 190) use ($mkBody, $mkRow): string {
                if (!array_key_exists($k, $mkBody)) {
                    return (string)($mkRow[$k] ?? '');
                }
                $v = trim((string)$mkBody[$k]);
                $v = strip_tags($v);
                return (function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max));
            };
            $mkEnm = static function (string $k, array $list) use ($mkBody, $mkRow): string {
                $v = trim((string)($mkBody[$k] ?? ''));
                return in_array($v, $list, true) ? $v : (string)($mkRow[$k] ?? '');
            };
            $mkFields = [
                'title'            => $mkTxt('title', 180),
                'city'             => $mkTxt('city', 80),
                'neighborhood'     => $mkTxt('neighborhood', 120),
                'address'          => $mkTxt('address', 500),
                'property_type'    => $mkEnm('property_type', $mkOpt['property_types']),
                'current_status'   => $mkEnm('current_status', $mkOpt['current_statuses']),
                'area'             => $mkTxt('area', 20),
                'br_count'         => $mkEnm('br_count', $mkOpt['br_counts']),
                'direction'        => $mkEnm('direction', $mkOpt['directions']),
                'passage_width'    => $mkTxt('passage_width', 30),
                'land_width'       => $mkTxt('land_width', 30),
                'permit_status'    => $mkEnm('permit_status', $mkOpt['permit_statuses']),
                'density'          => $mkTxt('density', 30),
                'occupancy_rate'   => $mkTxt('occupancy_rate', 30),
                'buildable_floors' => $mkTxt('buildable_floors', 30),
                'buildable_area'   => $mkTxt('buildable_area', 30),
                'owner_name'       => $mkTxt('owner_name', 120),
                'phone'            => $mkTxt('phone', 30),
                'deed_status'      => $mkEnm('deed_status', $mkOpt['deed_statuses']),
                'deed_kind'        => $mkEnm('deed_kind', $mkOpt['deed_kinds']),
                'owners_count'     => $mkTxt('owners_count', 30),
                'occupancy'        => $mkTxt('occupancy', 80),
                'legal_status'     => $mkTxt('legal_status', 190),
                'notes'            => $mkTxt('notes', 1000),
                'latitude'         => $mkTxt('latitude', 30),
                'longitude'        => $mkTxt('longitude', 30),
                'location_source'  => $mkTxt('location_source', 20),
                'status'           => $mkEnm('status', array_keys(melkinoPartStatuses())),
            ];
            $mkSet = implode(', ', array_map(static fn($k) => "{$k} = ?", array_keys($mkFields)));
            $mkUpd = $pdo->prepare("UPDATE partnership_requests SET {$mkSet} WHERE id = ?");
            $mkUpd->execute(array_merge(array_values($mkFields), [$mkId]));
            melkinoAdminJson(['success' => true, 'message' => 'ذخیره شد']);

        case 'delete':
            $data = function_exists('melkinoAdminJsonBody') ? melkinoAdminJsonBody() : $_POST;
            $id = (int)($data['id'] ?? 0);
            $st = $pdo->prepare("DELETE FROM partnership_requests WHERE id = ?");
            $st->execute([$id]);
            melkinoAdminJson(['success' => true]);

        default:
            melkinoAdminJson(['success' => false, 'message' => 'عملیات ناشناخته.'], 400);
    }
}

/* ---------------- HTML ---------------- */
$mkStatuses = melkinoPartStatuses();
$mkCsrf = function_exists('melkinoCsrfToken') ? melkinoCsrfToken() : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars($mkCsrf, ENT_QUOTES, 'UTF-8') ?>">
<title>مدیریت درخواست‌های مشارکت در ساخت — ملکینو</title>
<style>
    :root { --p: #064E4E; --p2: #0F766E; --gold: #A98416; --bg: #F6F7F4; --sf: #fff; --bd: #E5E7EB; --tx: #111827; --tx2: #6B7280; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--tx); font-family: Vazirmatn, Tahoma, sans-serif; font-size: 13.5px; }
    .top { background: var(--sf); border-bottom: 1px solid var(--bd); padding: 14px 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; position: sticky; top: 0; z-index: 20; }
    .top h1 { font-size: 16px; margin: 0; font-weight: 900; color: var(--p); }
    .top .spacer { flex: 1; }
    input, select, textarea, button { font-family: inherit; }
    .inp { border: 1.5px solid var(--bd); border-radius: 10px; padding: 8px 12px; font-size: 13px; background: #fff; }
    .btn { border: 0; border-radius: 10px; padding: 9px 16px; font-size: 13px; font-weight: 800; cursor: pointer; }
    .btn-p { background: var(--p); color: #fff; }
    .btn-g { background: #fff; border: 1.5px solid var(--bd); color: var(--tx2); }
    .btn-d { background: rgba(192,57,43,.08); color: #C0392B; }
    .wrap { padding: 18px 20px; max-width: 1200px; margin: 0 auto; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; align-items: center; }
    .chip { border: 1.5px solid var(--bd); background: #fff; border-radius: 99px; padding: 7px 14px; font-size: 12.5px; font-weight: 700; color: var(--tx2); cursor: pointer; }
    .chip.on { background: var(--p); border-color: var(--p); color: #fff; }
    table { width: 100%; border-collapse: collapse; background: var(--sf); border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
    th { background: #F0F2ED; font-size: 11.5px; color: var(--tx2); padding: 10px 12px; text-align: right; white-space: nowrap; }
    td { padding: 11px 12px; border-top: 1px solid var(--bd); vertical-align: middle; }
    tr.row { cursor: pointer; transition: background .15s; }
    tr.row:hover { background: #FBFCFA; }
    .code { font-weight: 900; color: var(--p); letter-spacing: .5px; white-space: nowrap; }
    .st { display: inline-block; border-radius: 99px; padding: 4px 11px; font-size: 11px; font-weight: 800; white-space: nowrap; }
    .st-pending { background: #FEF3C7; color: #92610c; }
    .st-approved { background: #D1FAE5; color: #047857; }
    .st-reviewing { background: #DBEAFE; color: #1d4ed8; }
    .st-contacted { background: #E0E7FF; color: #4338ca; }
    .st-offer { background: #FCE7F3; color: #be185d; }
    .st-done { background: #DCFCE7; color: #15803d; }
    .st-rejected { background: #FEE2E2; color: #b91c1c; }
    .bar { width: 90px; height: 7px; background: #ECEEE8; border-radius: 99px; overflow: hidden; }
    .bar i { display: block; height: 100%; background: linear-gradient(90deg, var(--p2), var(--p)); }
    .empty { text-align: center; padding: 46px 12px; color: var(--tx2); }
    /* مودال */
    .modal-bg { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 50; display: none; align-items: flex-start; justify-content: center; padding: 26px 14px; overflow-y: auto; }
    .modal-bg.open { display: flex; }
    .modal { background: #fff; border-radius: 18px; width: 100%; max-width: 760px; padding: 22px; }
    .modal h2 { margin: 0 0 4px; font-size: 16px; }
    .modal .sub { color: var(--tx2); font-size: 12px; margin-bottom: 14px; }
    .sec { border: 1px solid var(--bd); border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; }
    .sec b { display: block; font-size: 12px; color: var(--gold); margin-bottom: 8px; }
    .kv { display: flex; flex-wrap: wrap; gap: 6px; }
    .kv span { background: #F6F7F4; border: 1px solid var(--bd); border-radius: 8px; padding: 4px 10px; font-size: 12px; }
    .kv span i { color: var(--tx2); font-style: normal; }
    .photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
    .photos img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 10px; border: 1px solid var(--bd); }
    .modal-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 14px; }
    .close-x { position: sticky; float: left; border: 0; background: #F3F4EF; width: 32px; height: 32px; border-radius: 99px; cursor: pointer; font-size: 15px; }
    a { color: var(--p); }
    .tel { font-weight: 900; }
    @media (max-width: 720px) { th:nth-child(n+5), td:nth-child(n+5) { display: none; } }
</style>
</head>
<body>
    <div id="mkpApiErr" style="display:none;max-width:1100px;margin:10px auto 0;padding:12px 16px;border-radius:12px;background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;font-size:13px;line-height:2;"></div>
<div class="top">
        <h1>🤝 درخواست‌های مشارکت در ساخت <small style="font-weight:600;font-size:11px;color:var(--text-secondary,#667);opacity:.8;">MKP-ADM/2026-09-30b</small></h1>
        <span class="spacer"></span>
        <input class="inp" id="q" placeholder="جستجو: کد، عنوان، شهر، تلفن...">
        <button class="btn btn-p" onclick="loadList()">جستجو</button>
        <a class="btn btn-g" href="admin-panel.php">بازگشت به پنل</a>
    </div>

    <div class="wrap">
        <div class="filters" id="filters">
            <button class="chip on" data-st="">همه</button>
            <?php foreach ($mkStatuses as $k => $label): ?>
                <button class="chip" data-st="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <span id="cnt-<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"></span></button>
            <?php endforeach; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>کد</th><th>عنوان</th><th>موقعیت</th><th>سهم (مالک/سازنده)</th>
                    <th>تلفن</th><th>کامل بودن</th><th>وضعیت</th><th>تاریخ ثبت</th>
                </tr>
            </thead>
            <tbody id="rows"><tr><td colspan="8" class="empty">در حال بارگذاری...</td></tr></tbody>
        </table>
    </div>

    <div class="modal-bg" id="modalBg" onclick="if(event.target===this)closeModal()">
        <div class="modal" id="modalBox"></div>
    </div>

<script>
(function () {
    'use strict';
    var statuses = <?= json_encode($mkStatuses, JSON_UNESCAPED_UNICODE) ?>;
    var currentStatus = '';
    var fa = function (n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; }); };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function token() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return window.MELKINO_CSRF || (m && m.content) || '';
    }
    function api(params, body) {
        var headers = { 'X-CSRF-Token': token() };
        var opts = { method: body ? 'POST' : 'GET', credentials: 'same-origin', headers: headers };
        if (body) { opts.body = JSON.stringify(body); headers['Content-Type'] = 'application/json'; }
        return fetch('admin-partnership.php' + (params ? '?' + params : ''), opts).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).catch(function (err) {
            var b = document.getElementById('mkpApiErr');
            if (b) {
                b.style.display = 'block';
                b.innerHTML = '⚠️ ارتباط با سرور برقرار نشد (' + (err && err.message ? err.message : 'خطای نامشخص') + '). ' +
                    'صفحه را با Ctrl+F5 رفرش کنید؛ اگر تکرار شد یعنی فایل <b>admin-partnership.php</b> روی هاست قدیمی یا ناقص است.';
            }
            return null;
        });
    }
    function humanToman(v) {
        v = parseFloat(v);
        if (!isFinite(v) || v <= 0) return '';
        if (v >= 1e9) return fa(String(Math.round(v / 1e8) / 10).replace(/\.0$/, '')) + ' میلیارد تومان';
        if (v >= 1e6) return fa(String(Math.round(v / 1e6))) + ' میلیون تومان';
        return fa(Math.round(v).toLocaleString('en-US')) + ' تومان';
    }
    function jlist(v) { try { var a = JSON.parse(v || '[]'); return Array.isArray(a) ? a : []; } catch (e) { return []; } }
    function dateFa(dt) {
        if (!dt) return '—';
        try { return new Date(dt.replace(' ', 'T')).toLocaleDateString('fa-IR'); } catch (e) { return dt; }
    }

    function loadList() {
        var q = document.getElementById('q').value.trim();
        var qs = 'action=list&status=' + encodeURIComponent(currentStatus) + '&q=' + encodeURIComponent(q);
        api(qs).then(function (res) {
            if (!res || !res.success) return;
            renderCounts(res.counts || {});
            var tb = document.getElementById('rows');
            var rows = res.rows || [];
            if (!rows.length) {
                tb.innerHTML = '<tr><td colspan="8" class="empty">هنوز درخواستی ثبت نشده است.</td></tr>';
                return;
            }
            tb.innerHTML = rows.map(function (r) {
                var st = statuses[r.status] || r.status;
                return '<tr class="row" onclick="window.__mkpOpen(' + parseInt(r.id, 10) + ')">' +
                    '<td class="code">' + esc(r.code || '—') + '</td>' +
                    '<td>' + esc((r.title || '').slice(0, 46)) + '</td>' +
                    '<td>' + esc([r.city, r.neighborhood].filter(Boolean).join('، ')) + '</td>' +
                    '<td>' + (r.owner_share ? fa(r.owner_share) + '/' + fa(r.builder_share || Math.round(100 - parseFloat(r.owner_share))) : '—') + '</td>' +
                    '<td class="tel" dir="ltr">' + esc(r.phone || '—') + '</td>' +
                    '<td><div class="bar"><i style="width:' + parseInt(r.completeness || 0, 10) + '%"></i></div></td>' +
                    '<td><span class="st st-' + esc(r.status) + '">' + esc(st) + '</span></td>' +
                    '<td>' + dateFa(r.created_at) + '</td></tr>';
            }).join('');
        });
    }
    function renderCounts(counts) {
        Object.keys(statuses).forEach(function (k) {
            var el = document.getElementById('cnt-' + k);
            if (el) el.textContent = counts[k] ? '(' + fa(counts[k]) + ')' : '';
        });
    }
    document.querySelectorAll('#filters .chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.querySelectorAll('#filters .chip').forEach(function (c) { c.classList.remove('on'); });
            chip.classList.add('on');
            currentStatus = chip.getAttribute('data-st');
            loadList();
        });
    });

    window.__mkpOpen = function (id) {
        api('action=get&id=' + id).then(function (res) {
            if (!res || !res.success) return;
            renderModal(res.row);
        });
    };

    function sec(title, items) {
        var lis = items.filter(function (x) { return x.v; })
            .map(function (x) { return '<span>' + (x.k ? '<i>' + x.k + ': </i>' : '') + esc(x.v) + '</span>'; }).join('');
        return lis ? '<div class="sec"><b>' + title + '</b><div class="kv">' + lis + '</div></div>' : '';
    }

    function renderModal(r) {
        var photos = jlist(r.photos);
        var legal = jlist(r.legal_status);
        var units = jlist(r.unit_shares);
        var others = jlist(r.doc_other);
        var docs = [];
        if (r.doc_deed) docs.push('<a href="' + esc(r.doc_deed) + '" target="_blank">سند 📎</a>');
        if (r.doc_permit) docs.push('<a href="' + esc(r.doc_permit) + '" target="_blank">جواز 📎</a>');
        if (r.doc_endjob) docs.push('<a href="' + esc(r.doc_endjob) + '" target="_blank">پایان‌کار 📎</a>');
        others.forEach(function (p, i) { docs.push('<a href="' + esc(p) + '" target="_blank">سایر ' + fa(i + 1) + ' 📎</a>'); });

        var stOpts = Object.keys(statuses).map(function (k) {
            return '<option value="' + k + '"' + (k === r.status ? ' selected' : '') + '>' + esc(statuses[k]) + '</option>';
        }).join('');

        document.getElementById('modalBox').innerHTML =
            '<button class="close-x" onclick="closeModal()">✕</button>' +
            '<h2>' + esc(r.title || 'بدون عنوان') + '</h2>' +
            '<div class="sub">کد ' + esc(r.code || '—') + ' · ثبت: ' + dateFa(r.created_at) +
            ' · مالک: ' + esc(r.owner_name || '—') + ' · <a class="tel" dir="ltr" href="tel:' + esc(r.phone) + '">' + esc(r.phone || '') + '</a></div>' +
            sec('ملک', [
                { k: 'نوع', v: r.property_type }, { k: 'مساحت', v: r.area ? fa(r.area) + ' متر' : '' },
                { k: 'وضعیت', v: r.current_status }, { k: 'بر', v: r.br_count },
                { k: 'سن بنا', v: r.building_age ? fa(r.building_age) + ' سال' : '' },
                { k: 'طبقات فعلی', v: r.current_floors ? fa(r.current_floors) : '' },
                { k: 'واحد فعلی', v: r.current_units ? fa(r.current_units) : '' },
                { k: 'پارکینگ فعلی', v: r.current_parkings ? fa(r.current_parkings) : '' }
            ]) +
            sec('موقعیت', [
                { k: 'شهر', v: r.city }, { k: 'محله', v: r.neighborhood },
                { k: 'آدرس', v: r.address }, { k: 'عرض گذر', v: r.passage_width ? fa(r.passage_width) + ' متر' : '' },
                { k: 'عرض زمین', v: r.land_width ? fa(r.land_width) + ' متر' : '' },
                { k: 'جهت ملک', v: r.direction },
                (r.latitude ? { k: 'مختصات', v: r.latitude + ' , ' + r.longitude } : { v: '' })
            ]) +
            sec('ظرفیت ساخت', [
                (String(r.capacity_known) === '0' ? { v: 'پروانه ندارد — ظرفیت ساخت نیاز به بررسی دارد 🔎' } : { v: '' }),
                { k: 'تراکم', v: r.density ? fa(r.density) + '٪' : '' },
                { k: 'اشغال', v: r.occupancy_rate ? fa(r.occupancy_rate) + '٪' : '' },
                { k: 'طبقات قابل ساخت', v: r.buildable_floors ? fa(r.buildable_floors) : '' },
                { k: 'زیربنا', v: r.buildable_area ? fa(r.buildable_area) + ' متر' : '' },
                { k: 'واحد قابل ساخت', v: r.buildable_units ? fa(r.buildable_units) : '' }
            ]) +
            sec('جواز', [
                { v: r.permit_status },
                { k: 'شماره', v: r.permit_number }, { k: 'تاریخ', v: r.permit_date },
                { k: 'طبقات', v: r.permit_floors ? fa(r.permit_floors) : '' },
                { k: 'زیربنا', v: r.permit_area ? fa(r.permit_area) : '' }
            ]) +
            sec('شرایط مشارکت', [
                { k: 'سهم', v: r.owner_share ? 'مالک ' + fa(r.owner_share) + '٪ / سازنده ' + fa(r.builder_share || Math.round(100 - parseFloat(r.owner_share))) + '٪' : '' },
                { k: 'بلاعوض', v: r.balaghz ? (r.balaghz === 'بله' ? humanToman(r.balaghz_amount) || fa(r.balaghz_amount) : r.balaghz) : '' },
                { k: 'تقسیم', v: r.division_method }, { k: 'مدت', v: r.duration }, { k: 'تأمین هزینه', v: r.funding },
                { k: 'پارکینگ مالک', v: r.partner_parkings ? fa(r.partner_parkings) : '' },
                { k: 'انباری', v: r.partner_storage ? fa(r.partner_storage) : '' },
                { k: 'بازه ارزش', v: (r.value_from || r.value_to) ? (humanToman(r.value_from) + (r.value_to ? ' تا ' + humanToman(r.value_to) : '')) : '' },
                { k: 'واحدهای مالک', v: units.length ? units.join(' · ') : '' },
                { k: 'توضیحات', v: r.notes }
            ]) +
            sec('مالکیت و وضعیت', [
                { k: 'سند', v: r.deed_status }, { k: 'نوع سند', v: r.deed_kind }, { k: 'تعداد مالکین', v: r.owners_count ? fa(r.owners_count) : '' },
                { k: 'سکونت', v: r.occupancy },
                { k: 'وضعیت حقوقی', v: legal.length ? legal.join(' · ') : '—' }
            ]) +
            (photos.length ? '<div class="sec"><b>عکس‌ها (' + fa(photos.length) + ')</b><div class="photos">' +
                photos.map(function (p) { return '<a href="' + esc(p) + '" target="_blank"><img src="' + esc(p) + '" alt="" loading="lazy"></a>'; }).join('') + '</div></div>' : '') +
            (docs.length ? '<div class="sec"><b>مدارک</b><div class="kv">' + docs.join('') + '</div></div>' : '') +
            '<div class="sec"><b>یادداشت ادمین</b><textarea id="mkpNote" class="inp" style="width:100%;min-height:60px">' + esc(r.admin_note || '') + '</textarea></div>' +
            '<div class="modal-actions">' +
            '<select id="mkpStatusSel" class="inp">' + stOpts + '</select>' +
            '<button class="btn btn-p" onclick="saveStatus(' + parseInt(r.id, 10) + ')">ذخیره وضعیت</button>' +
            '<button class="btn btn-g" onclick="saveNote(' + parseInt(r.id, 10) + ')">ذخیره یادداشت</button>' +
            '<button class="btn btn-d" onclick="delRow(' + parseInt(r.id, 10) + ')">حذف درخواست</button>' +
            '</div>';
        document.getElementById('modalBg').classList.add('open');
    }

    window.saveStatus = function (id) {
        var status = document.getElementById('mkpStatusSel').value;
        api('', { action: 'status', id: id, status: status }).then(function (res) {
            if (res && res.success) { closeModal(); loadList(); }
        });
    };
    window.saveNote = function (id) {
        var note = document.getElementById('mkpNote').value;
        api('', { action: 'note', id: id, note: note }).then(function (res) {
            if (res && res.success) alert('یادداشت ذخیره شد.');
        });
    };
    window.delRow = function (id) {
        if (!confirm('این درخواست برای همیشه حذف شود؟')) return;
        api('', { action: 'delete', id: id }).then(function (res) {
            if (res && res.success) { closeModal(); loadList(); }
        });
    };
    window.closeModal = function () {
        document.getElementById('modalBg').classList.remove('open');
    };
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    document.getElementById('q').addEventListener('keydown', function (e) { if (e.key === 'Enter') loadList(); });
    loadList();

    // [PARTNERSHIP] باز کردن خودکار یک درخواست (?open=ID) — از جدول آگهی‌ها
    var mkpAutoOpen = parseInt('<?= (int)($_GET['open'] ?? 0) ?>', 10);
    if (mkpAutoOpen > 0 && typeof window.__mkpOpen === 'function') {
        try { window.__mkpOpen(mkpAutoOpen); } catch (e) {}
    }
})();
</script>
</body>
</html>
