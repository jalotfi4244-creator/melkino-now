/*
| admin-sms.js — تب «برنامهٔ پیامک» (ملی‌پیامک)
| سبک رندر: مثل بقیهٔ تب‌های پنل — همه‌چیز از API همین دامنه
*/
(function () {
    'use strict';

    function apiGet(action) {
        return fetch('admin-sms-api.php?action=' + encodeURIComponent(action), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); });
    }
    function apiPost(action, data) {
        data = data || {};
        data.action = action;
        return fetch('admin-sms-api.php?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(data)
        }).then(function (r) { return r.json(); });
    }
    function el(id) { return document.getElementById(id); }
    function setChk(id, v) { var e = el(id); if (e) e.checked = !!Number(v); }
    function setVal(id, v) { var e = el(id); if (e) e.value = v === null || v === undefined ? '' : String(v); }

    var GOALS = {
        saved_search: 'جستجوی ذخیره',
        request_match: 'انطباق درخواست',
        admin_alert: 'هشدار ادمین',
        campaign: 'کمپین',
        manual: 'دستی'
    };
    var STATUS_FA = {
        sent: ['✅ ارسال‌شد', 'color:#0E7C6E;font-weight:800;'],
        queued: ['⏳ در صف', 'color:#d97706;font-weight:800;'],
        failed: ['❌ ناموفق', 'color:#dc2626;font-weight:800;'],
        held: ['🧷 نگه‌داری‌شده (تا حد نصاب)', 'color:#2563eb;font-weight:800;'],
        merged: ['🔗 ادغام‌شده در پیامک خلاصه', 'color:#6b7280;font-weight:800;'],
        skipped: ['⏭ ردشده', 'color:#6b7280;font-weight:800;']
    };

    /* ---------------- status ---------------- */
    window.smsProgLoad = async function () {
        if (!el('tab-sms')) return;
        try {
            var d = await apiGet('status');
            if (!d.success) return;
            var s = d.settings || {};
            var p = d.provider || {};
            var c = d.counts || {};

            setChk('smsProgEnabled', s.enabled);
            setChk('ssOn', (s.saved_search || {}).on);
            setVal('ssCap', (s.saved_search || {}).cap_day);
            setVal('ssMinNew', (s.saved_search || {}).min_new || 1);
            setVal('ssTemplate', (s.saved_search || {}).template);
            setVal('ssDigestTemplate', (s.saved_search || {}).digest_template);
            setChk('rmOn', (s.request_match || {}).on);
            setVal('rmCap', (s.request_match || {}).cap_day);
            setVal('rmScore', (s.request_match || {}).score_min);
            setVal('rmMinNew', (s.request_match || {}).min_new || 1);
            setVal('rmTemplate', (s.request_match || {}).template);
            setChk('aaOn', (s.admin_alert || {}).on);
            setVal('aaMode', (s.admin_alert || {}).mode || 'over');
            setVal('aaEvery', (s.admin_alert || {}).every_n || 20);
            setVal('aaEveryAds', (s.admin_alert || {}).every_req_n || '');
            setVal('aaAds', (s.admin_alert || {}).ads_thr);
            setVal('aaReq', (s.admin_alert || {}).req_thr);
            setVal('aaPhone', (s.admin_alert || {}).phone);
            setVal('aaDeb', (s.admin_alert || {}).debounce_h);
            setVal('aaTemplate', (s.admin_alert || {}).template);
            smsProgSyncAlertMode();
            setChk('mkOn', (s.marketing || {}).on);
            setChk('cfgLagoo', s.lagoo11);
            setVal('cfgQuietStart', s.quiet_start);
            setVal('cfgQuietEnd', s.quiet_end);
            setVal('cfgMaxTick', s.max_per_tick);
            setVal('cfgSiteUrl', s.site_url);
            el('cfgCronToken').placeholder = s.cron_token_set ? 'توکن ثبت شده (برای تغییر مقدار جدید بنویس)' : 'مثلاً یک رشتهٔ تصادفی';

            var chips = [];
            chips.push(chip(p.enabled ? '✅ پنل پیامک فعال' : '⛔ پنل پیامک غیرفعال (تب ربات و کانال)', !p.enabled));
            chips.push(chip('👤 ' + p.name + (p.username_set ? ' (کاربر ثبت‌شده)' : ' (بدون نام کاربری!)'), !p.username_set));
            chips.push(chip('📡 خط خدماتی: ' + (p.service_line || '—'), !p.service_line));
            chips.push(chip('📣 خط تبلیغاتی: ' + (p.promo_line || '—'), !p.promo_line));
            chips.push(chip('⏳ در صف: ' + (c.queued || 0), false));
            chips.push(chip('✅ ارسال‌شده: ' + (c.sent || 0), false));
            chips.push(chip('❌ ناموفق: ' + (c.failed || 0), (c.failed || 0) > 0));
            chips.push(chip('🔍 جستجوها: ' + (c.saved_searches || 0), false));
            chips.push(chip('🚫 لغو عضویت: ' + (c.optouts || 0), false));
            chips.push(chip('📋 آگهی در انتظار: ' + (c.pending_ads || 0), (c.pending_ads || 0) > 10));
            chips.push(chip('📥 درخواست جدید: ' + (c.pending_requests || 0), (c.pending_requests || 0) > 10));
            if (s.last_tick) chips.push(chip('🕐 آخرین اجرا: ' + s.last_tick, false));
            el('smsProgChips').innerHTML = chips.join('');
            el('smsProgStatusMsg').textContent = p.otp_body_id ? ('الگوی OTP فعال است (bodyId ' + p.otp_body_id + ').') : 'کد ورود با متن آزاد از خط خدماتی ارسال می‌شود.';
        } catch (e) { /* بی‌صدا */ }
    };

    function chip(text, warn) {
        return '<span style="display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:999px;font-size:11.5px;font-weight:800;border:1px solid ' + (warn ? 'rgba(217,119,6,.4)' : 'var(--border)') + ';background:' + (warn ? 'rgba(217,119,6,.08)' : 'rgba(127,127,127,.06)') + ';color:' + (warn ? '#b45309' : 'var(--text-primary)') + ';">' + text + '</span>';
    }

    /* ---------------- save ---------------- */
    window.smsProgSave = async function () {
        var num = function (id, d) { var v = parseInt(el(id) && el(id).value, 10); return isFinite(v) ? v : d; };
        var data = {
            enabled: el('smsProgEnabled').checked ? 1 : 0,
            lagoo11: el('cfgLagoo').checked ? 1 : 0,
            quiet_start: num('cfgQuietStart', 22),
            quiet_end: num('cfgQuietEnd', 8),
            max_per_tick: num('cfgMaxTick', 15),
            site_url: (el('cfgSiteUrl').value || '').trim(),
            cron_token: (el('cfgCronToken').value || '').trim(),
            saved_search: {
                on: el('ssOn').checked ? 1 : 0,
                cap_day: num('ssCap', 2),
                min_new: num('ssMinNew', 1),
                template: el('ssTemplate').value,
                digest_template: el('ssDigestTemplate').value
            },
            request_match: {
                on: el('rmOn').checked ? 1 : 0,
                cap_day: num('rmCap', 1),
                score_min: num('rmScore', 0),
                min_new: num('rmMinNew', 1),
                template: el('rmTemplate').value
            },
            admin_alert: {
                on: el('aaOn').checked ? 1 : 0,
                mode: el('aaMode').value,
                every_n: num('aaEvery', 20),
                every_req_n: (el('aaEveryAds').value || '').trim() === '' ? 0 : num('aaEveryAds', 20),
                ads_thr: num('aaAds', 10),
                req_thr: num('aaReq', 10),
                debounce_h: num('aaDeb', 6),
                phone: (el('aaPhone').value || '').trim(),
                template: el('aaTemplate').value
            },
            marketing: { on: el('mkOn').checked ? 1 : 0 }
        };
        try {
            var d = await apiPost('save', data);
            var m = el('smsProgSaveMsg');
            m.textContent = d.message || (d.success ? 'ذخیره شد.' : 'خطا');
            m.style.color = d.success ? '#0E7C6E' : '#dc2626';
            setTimeout(function () { m.textContent = ''; }, 4000);
            smsProgLoad();
        } catch (e) {
            el('smsProgSaveMsg').textContent = 'خطا در ارتباط با سرور.';
        }
    };

    /* ---------------- actions ---------------- */
    window.smsProgSyncAlertMode = function () {
        var m = el('aaMode') ? el('aaMode').value : 'over';
        var overBox = el('aaOverBox'), everyBox = el('aaEveryBox');
        if (overBox) overBox.style.display = m === 'over' ? 'flex' : 'none';
        if (everyBox) everyBox.style.display = m === 'every' ? 'flex' : 'none';
    };
    if (el('aaMode')) el('aaMode').addEventListener('change', smsProgSyncAlertMode);

    window.smsProgGenToken = function () {
        var s = '';
        for (var i = 0; i < 24; i++) s += 'abcdefghjkmnpqrstuvwxyz23456789'.charAt(Math.floor(Math.random() * 31));
        el('cfgCronToken').value = s;
    };
    function actionMsg(text, ok) {
        var m = el('smsProgActionMsg');
        m.textContent = text;
        m.style.color = ok ? '#0E7C6E' : '#dc2626';
        setTimeout(function () { m.textContent = ''; }, 8000);
    }
    window.smsProgRunNow = async function () {
        actionMsg('در حال اجرا…', true);
        try {
            var d = await apiPost('tick', {});
            if (d.success) {
                var r = d.result || {};
                actionMsg('اجرا شد — ارسال: ' + (r.sent || 0) + ' · ناموفق: ' + (r.failed || 0) + ' · در صف: ' + (r.queued || 0) + (r.skipped ? ' · ' + r.skipped : ''), true);
                smsProgLoad();
                smsProgLoadOutbox();
            } else actionMsg(d.message || 'اجرا نشد.', false);
        } catch (e) { actionMsg('خطای ارتباط.', false); }
    };
    window.smsProgBalance = async function () {
        actionMsg('در حال استعلام اعتبار…', true);
        try {
            var d = await apiPost('balance', {});
            actionMsg(d.success ? ('اعتبار پنل پیامک: ' + d.credit) : (d.message || 'استعلام نشد.'), d.success);
        } catch (e) { actionMsg('خطای ارتباط.', false); }
    };
    window.smsProgTest = async function () {
        var phone = (el('smsProgTestPhone').value || '').trim();
        if (!/^09\d{9}$/.test(phone)) { actionMsg('شماره معتبر نیست.', false); return; }
        actionMsg('در حال ارسال…', true);
        try {
            var d = await apiPost('test', { phone: phone });
            actionMsg(d.message || (d.success ? 'ارسال شد.' : 'ارسال نشد.'), d.success);
        } catch (e) { actionMsg('خطای ارتباط.', false); }
    };

    /* ---------------- outbox ---------------- */
    window.smsProgLoadOutbox = async function () {
        var tbody = el('obRows');
        if (!tbody) return;
        try {
            var d = await apiPost('outbox', {
                goal: el('obGoal').value,
                status: el('obStatus').value
            });
            if (!d.success) return;
            tbody.innerHTML = (d.rows || []).map(function (r) {
                var src = '';
                if (r.ad_id) src = 'ملک ' + r.ad_id;
                if (r.request_id) src = (src ? src + ' · ' : '') + 'درخواست #' + r.request_id;
                if (r.search_id) src = (src ? src + ' · ' : '') + 'جستجو #' + r.search_id;
                if (r.campaign_id) src = (src ? src + ' · ' : '') + 'کمپین #' + r.campaign_id;
                if (r.alert) src = 'هشدار: ' + r.alert;
                var st = STATUS_FA[r.status] || [r.status, ''];
                return '<tr style="border-top:1px solid var(--border);">'
                    + '<td style="padding:6px 8px;color:var(--text-secondary);">' + r.created_at + '</td>'
                    + '<td style="padding:6px 8px;">' + (GOALS[r.goal] || r.goal) + '</td>'
                    + '<td style="padding:6px 8px;direction:ltr;text-align:right;">' + r.phone + '</td>'
                    + '<td style="padding:6px 8px;direction:ltr;text-align:right;font-size:11px;">' + (src || '—') + '</td>'
                    + '<td style="padding:6px 8px;color:var(--text-secondary);">' + r.body + '</td>'
                    + '<td style="padding:6px 8px;"><span style="' + st[1] + '">' + st[0] + '</span></td>'
                    + '<td style="padding:6px 8px;color:#dc2626;">' + (r.fail_reason || '—') + '</td>'
                    + '</tr>';
            }).join('') || '<tr><td colspan="7" style="padding:18px;text-align:center;color:var(--text-secondary);">موردی نیست.</td></tr>';
        } catch (e) { /* بی‌صدا */ }
    };

    /* ---------------- optouts ---------------- */
    window.smsProgOptoutAdd = async function () {
        var phone = (el('optPhone').value || '').trim();
        if (!/^09\d{9}$/.test(phone)) { alert('شماره معتبر نیست.'); return; }
        await apiPost('optout_add', { phone: phone, scope: el('optScope').value });
        el('optPhone').value = '';
        smsProgLoadOptouts();
    };
    window.smsProgOptoutDel = async function (id) {
        await apiPost('optout_del', { id: id });
        smsProgLoadOptouts();
    };
    window.smsProgLoadOptouts = async function () {
        var box = el('optRows');
        if (!box) return;
        try {
            var d = await apiPost('optouts', {});
            if (!d.success) return;
            var SCOPE = { alerts: 'اطلاع‌رسانی', promo: 'تبلیغاتی', all: 'همه' };
            box.innerHTML = '<div style="display:flex;flex-wrap:wrap;gap:8px;">' + (d.rows || []).map(function (r) {
                return '<span style="display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:10px;border:1px solid var(--border);font-size:11.5px;">'
                    + '<span dir="ltr">' + r.phone + '</span> <span style="color:var(--text-secondary);">(' + (SCOPE[r.scope] || r.scope) + ')</span>'
                    + '<button type="button" onclick="smsProgOptoutDel(' + r.id + ')" style="all:unset;cursor:pointer;color:#dc2626;font-weight:900;">×</button></span>';
            }).join('') + '</div>' + ((d.rows || []).length ? '' : '<div style="color:var(--text-secondary);font-size:12px;">فهرست خالی است — هیچ شماره‌ای لغو عضویت نداده است.</div>');
        } catch (e) { /* بی‌صدا */ }
    };

    /* ---------------- saved searches ---------------- */
    window.smsProgSearchDel = async function (id) {
        await apiPost('search_del', { id: id });
        smsProgLoadSearches();
    };
    window.smsProgLoadSearches = async function () {
        var box = el('ssRows');
        if (!box) return;
        try {
            var d = await apiPost('searches', {});
            if (!d.success) return;
            box.innerHTML = (d.rows || []).map(function (r) {
                var bits = [];
                if (r.tx) bits.push(r.tx === 'اجاره' ? 'اجاره و رهن' : r.tx);
                if (r.property_type) bits.push(r.property_type);
                if (r.district) bits.push(r.district);
                if (r.rooms && r.rooms !== '0') bits.push(r.rooms + ' خواب');
                return '<div style="display:flex;align-items:center;gap:10px;border:1px solid var(--border);border-radius:12px;padding:10px 12px;margin-bottom:8px;flex-wrap:wrap;">'
                    + '<span style="font-weight:800;font-size:12.5px;">' + (bits.join(' · ') || 'بدون فیلتر') + '</span>'
                    + '<span style="font-size:11.5px;color:var(--text-secondary);direction:ltr;">' + r.phone + '</span>'
                    + (r.user_name ? '<span style="font-size:11.5px;color:var(--text-secondary);">(' + r.user_name + ')</span>' : '')
                    + '<span style="font-size:11px;color:' + (Number(r.notify) ? '#0E7C6E' : '#d97706') + ';font-weight:800;">' + (Number(r.notify) ? 'روشن' : 'خاموش') + '</span>'
                    + '<button type="button" onclick="smsProgSearchDel(' + r.id + ')" style="margin-inline-start:auto;all:unset;cursor:pointer;color:#dc2626;font-size:12px;font-weight:800;">حذف</button>'
                    + '</div>';
            }).join('') || '<div style="color:var(--text-secondary);font-size:12px;">هنوز هیچ کاربری جستجو ذخیره نکرده است.</div>';
        } catch (e) { /* بی‌صدا */ }
    };

    /* ---------------- boot با تب ---------------- */
    var _origSwitch = window.switchTab;
    if (typeof _origSwitch === 'function') {
        window.switchTab = function (t) {
            _origSwitch(t);
            if (t === 'sms') {
                smsProgLoad();
                smsProgLoadOutbox();
                smsProgLoadOptouts();
                smsProgLoadSearches();
            }
        };
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            if (el('tab-sms') && el('tab-sms').classList.contains('active')) {
                smsProgLoad();
                smsProgLoadOutbox();
                smsProgLoadOptouts();
                smsProgLoadSearches();
            }
        });
    }
})();
