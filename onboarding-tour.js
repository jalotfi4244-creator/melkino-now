/* ملکینو — موتور Guided / Product Tour (بدون وابستگی خارجی) */
(function (w, d) {
    'use strict';
    if (w.MelkinoTour) return;

    var CFG = w.MELKINO_TOUR || {};
    var STORE = 'melkino_tour_' + (CFG.tour || 'user') + '_v2';
    var RESUME = 'melkino_tour_resume';
    var root, spot, card, confirmEl, liveEl;
    var steps = [];
    var index = 0;
    var running = false;
    var lastFocus = null;

    function pageName() {
        var p = (location.pathname.split('/').pop() || 'home.php').toLowerCase();
        return p || 'home.php';
    }
    function enabledSteps() {
        return (CFG.steps || []).filter(function (s) {
            return s && s.enabled !== false;
        });
    }
    function qs(sel, ctx) {
        if (!sel) return null;
        try { return (ctx || d).querySelector(sel); } catch (e) { return null; }
    }
    function pageOk(step) {
        if (!step || !step.page || step.page === '*' ) return true;
        var cur = pageName();
        if (Array.isArray(step.page)) return step.page.indexOf(cur) !== -1;
        return String(step.page) === cur;
    }
    function findTarget(step) {
        if (!step || !step.target) return null;
        var sels = String(step.target).split(',');
        var fallback = null;
        for (var i = 0; i < sels.length; i++) {
            var el = qs(sels[i].trim());
            if (!el) continue;
            if (!fallback) fallback = el;
            if (el.getClientRects().length > 0) return el;
        }
        return fallback;
    }

    function build() {
        if (root) return;
        root = d.createElement('div');
        root.className = 'mk-tour-root';
        root.setAttribute('dir', 'rtl');
        root.innerHTML =
            '<div class="mk-tour-spot" aria-hidden="true"></div>' +
            '<div class="mk-tour-card" role="dialog" aria-modal="true" aria-labelledby="mkTourTitle" aria-describedby="mkTourDesc" tabindex="-1">' +
                '<div class="mk-tour-kicker" id="mkTourKicker">راهنمای ملکینو</div>' +
                '<h2 class="mk-tour-title" id="mkTourTitle"></h2>' +
                '<p class="mk-tour-desc" id="mkTourDesc"></p>' +
                '<div class="mk-tour-progress">' +
                    '<span class="mk-tour-count" id="mkTourCount"></span>' +
                    '<div class="mk-tour-dots" id="mkTourDots"></div>' +
                '</div>' +
                '<div class="mk-tour-actions" id="mkTourActions"></div>' +
                '<div class="mk-tour-skiprow"><button type="button" class="mk-tour-skip" id="mkTourSkip">رد کردن راهنما</button></div>' +
            '</div>' +
            '<div class="mk-tour-confirm" id="mkTourConfirm" role="alertdialog" aria-labelledby="mkTourConfirmT">' +
                '<div class="mk-tour-confirm-box">' +
                    '<p id="mkTourConfirmT">راهنما متوقف شود؟</p>' +
                    '<div class="mk-tour-actions">' +
                        '<button type="button" class="mk-tour-btn mk-tour-btn-primary" id="mkTourKeep">ادامه راهنما</button>' +
                        '<button type="button" class="mk-tour-btn mk-tour-btn-soft" id="mkTourExit">خروج از راهنما</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="mk-tour-live" id="mkTourLive" aria-live="polite"></div>';
        d.body.appendChild(root);
        spot = root.querySelector('.mk-tour-spot');
        card = root.querySelector('.mk-tour-card');
        confirmEl = root.querySelector('#mkTourConfirm');
        liveEl = root.querySelector('#mkTourLive');
        root.querySelector('#mkTourSkip').addEventListener('click', askSkip);
        root.querySelector('#mkTourKeep').addEventListener('click', function () { confirmEl.classList.remove('is-on'); });
        root.querySelector('#mkTourExit').addEventListener('click', function () { finish(true); });
        d.addEventListener('keydown', onKey);
        w.addEventListener('resize', onWin, { passive: true });
        w.addEventListener('scroll', onWin, { passive: true });
    }

    function onKey(e) {
        if (!running) return;
        if (e.key === 'Escape') {
            e.preventDefault();
            if (confirmEl.classList.contains('is-on')) confirmEl.classList.remove('is-on');
            else askSkip();
            return;
        }
        if (confirmEl.classList.contains('is-on')) return;
        if (e.key === 'ArrowLeft') { e.preventDefault(); next(); }
        if (e.key === 'ArrowRight') { e.preventDefault(); prev(); }
        if (e.key === 'Enter' && e.target && e.target.tagName !== 'BUTTON') { e.preventDefault(); next(); }
    }
    function onWin() {
        if (!running) return;
        layout(steps[index]);
    }

    function askSkip() {
        confirmEl.classList.add('is-on');
        var b = root.querySelector('#mkTourKeep');
        if (b) b.focus();
    }

    function markDone() {
        try { localStorage.setItem(STORE, 'done'); } catch (e) {}
        try { sessionStorage.removeItem(RESUME); } catch (e) {}
    }
    function isDone() {
        try { return localStorage.getItem(STORE) === 'done'; } catch (e) { return false; }
    }

    function saveResume(i) {
        try {
            sessionStorage.setItem(RESUME, JSON.stringify({ tour: CFG.tour || 'user', i: i }));
        } catch (e) {}
    }
    function readResume() {
        try {
            var raw = sessionStorage.getItem(RESUME);
            if (!raw) return null;
            var o = JSON.parse(raw);
            if (!o || o.tour !== (CFG.tour || 'user')) return null;
            return o;
        } catch (e) { return null; }
    }

    function goPage(step, i) {
        var dest = Array.isArray(step.page) ? step.page[0] : step.page;
        if (!dest || dest === '*') return false;
        saveResume(i);
        location.href = dest;
        return true;
    }

    function runAction(step) {
        if (!step || !step.action) return;
        var a = String(step.action);
        if (a.indexOf('switchTab:') === 0 && typeof w.switchTab === 'function') {
            try { w.switchTab(a.slice(10)); } catch (e) {}
        }
    }

    function waitTarget(step, tries, cb) {
        var el = findTarget(step);
        if (el || tries <= 0 || !step.target) { cb(el); return; }
        setTimeout(function () { waitTarget(step, tries - 1, cb); }, 180);
    }

    function showStep(i) {
        steps = enabledSteps();
        if (!steps.length) { finish(true); return; }
        if (i < 0) i = 0;
        if (i >= steps.length) { finish(true); return; }
        var step = steps[i];
        if (!pageOk(step) && step.page && step.page !== '*') {
            if (goPage(step, i)) return;
        }
        runAction(step);
        waitTarget(step, step.target ? 10 : 0, function (el) {
            if (step.target && !el) {
                showStep(i + 1);
                return;
            }
            index = i;
            saveResume(i);
            render(step, el);
        });
    }

    function render(step, el) {
        running = true;
        root.classList.add('is-on');
        d.getElementById('mkTourTitle').textContent = step.title || '';
        d.getElementById('mkTourDesc').textContent = step.description || '';
        d.getElementById('mkTourKicker').textContent = step.kicker || 'راهنمای ملکینو';
        liveEl.textContent = (step.title || '') + ' — ' + (step.description || '');
        var n = steps.length;
        d.getElementById('mkTourCount').textContent = 'مرحله ' + toFa(index + 1) + ' از ' + toFa(n);
        var dots = d.getElementById('mkTourDots');
        dots.innerHTML = '';
        for (var i = 0; i < n; i++) {
            var b = d.createElement('button');
            b.type = 'button';
            b.className = 'mk-tour-dot' + (i === index ? ' is-on' : (i < index ? ' is-done' : ''));
            b.setAttribute('aria-label', 'مرحله ' + (i + 1));
            b.setAttribute('data-i', String(i));
            b.addEventListener('click', function () { showStep(parseInt(this.getAttribute('data-i'), 10)); });
            dots.appendChild(b);
        }
        var actions = d.getElementById('mkTourActions');
        actions.innerHTML = '';
        var last = index === n - 1;
        var first = index === 0;
        if (!first && !step.hidePrev) {
            actions.appendChild(btn('قبلی', 'mk-tour-btn mk-tour-btn-soft', prev));
        }
        if (first && !step.target) {
            actions.appendChild(btn(step.cta || 'شروع کنیم', 'mk-tour-btn mk-tour-btn-gold', next));
            actions.appendChild(btn('رد کردن', 'mk-tour-btn mk-tour-btn-ghost', askSkip));
            d.getElementById('mkTourSkip').style.display = 'none';
        } else if (last) {
            actions.appendChild(btn(step.cta || 'شروع استفاده از ملکینو', 'mk-tour-btn mk-tour-btn-gold', function () { finish(true); }));
            d.getElementById('mkTourSkip').style.display = 'none';
        } else {
            actions.appendChild(btn('مرحله بعد', 'mk-tour-btn mk-tour-btn-primary', next));
            d.getElementById('mkTourSkip').style.display = '';
        }

        if (el) {
            try { el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' }); } catch (e) {}
            setTimeout(function () { layout(step, el); }, 280);
        } else {
            layout(step, null);
        }
        setTimeout(function () {
            var f = card.querySelector('.mk-tour-btn-gold, .mk-tour-btn-primary');
            if (f) f.focus();
        }, 40);
    }

    function btn(label, cls, fn) {
        var b = d.createElement('button');
        b.type = 'button';
        b.className = cls;
        b.textContent = label;
        b.addEventListener('click', fn);
        return b;
    }

    function toFa(n) {
        return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
    }

    function layout(step, el) {
        el = el || findTarget(step);
        var pad = 8;
        if (el) {
            var r = el.getBoundingClientRect();
            spot.style.top = Math.max(0, r.top - pad) + 'px';
            spot.style.left = Math.max(0, r.left - pad) + 'px';
            spot.style.width = Math.min(w.innerWidth, r.width + pad * 2) + 'px';
            spot.style.height = Math.min(w.innerHeight, r.height + pad * 2) + 'px';
            spot.classList.add('is-on');
        } else {
            spot.classList.remove('is-on');
            spot.style.width = '0';
            spot.style.height = '0';
        }
        card.classList.add('is-on');
        placeCard(el, step && step.position);
    }

    function placeCard(el, preferred) {
        var cw = card.offsetWidth || 360;
        var ch = card.offsetHeight || 220;
        var vw = w.innerWidth, vh = w.innerHeight;
        var gap = 12, edge = 10;
        var x, y, pos = preferred || 'auto';
        if (!el) {
            x = Math.max(edge, (vw - cw) / 2);
            y = Math.max(edge, (vh - ch) / 2);
        } else {
            var r = el.getBoundingClientRect();
            var below = vh - r.bottom;
            var above = r.top;
            if (pos === 'auto') {
                if (below >= ch + 16) pos = 'bottom';
                else if (above >= ch + 16) pos = 'top';
                else if (r.left > vw / 2) pos = 'left';
                else pos = 'right';
            }
            if (pos === 'bottom') {
                y = r.bottom + gap;
                x = r.left + r.width / 2 - cw / 2;
            } else if (pos === 'top') {
                y = r.top - ch - gap;
                x = r.left + r.width / 2 - cw / 2;
            } else if (pos === 'left') {
                x = r.left - cw - gap;
                y = r.top + r.height / 2 - ch / 2;
            } else {
                x = r.right + gap;
                y = r.top + r.height / 2 - ch / 2;
            }
        }
        x = Math.min(Math.max(edge, x), vw - cw - edge);
        y = Math.min(Math.max(edge, y), vh - ch - edge);
        if (x + cw > vw - edge) x = vw - cw - edge;
        if (y + ch > vh - edge) y = vh - ch - edge;
        card.style.left = x + 'px';
        card.style.top = y + 'px';
    }

    function next() { showStep(index + 1); }
    function prev() { showStep(index - 1); }

    function finish(save) {
        running = false;
        if (save) markDone();
        try { sessionStorage.removeItem(RESUME); } catch (e) {}
        if (root) {
            root.classList.remove('is-on');
            card.classList.remove('is-on');
            spot.classList.remove('is-on');
            confirmEl.classList.remove('is-on');
        }
        if (lastFocus && lastFocus.focus) {
            try { lastFocus.focus(); } catch (e) {}
        }
    }

    function start(from) {
        build();
        lastFocus = d.activeElement;
        steps = enabledSteps();
        var i = typeof from === 'number' ? from : 0;
        showStep(i);
    }

    function reset() {
        try { localStorage.removeItem(STORE); } catch (e) {}
        try { sessionStorage.removeItem(RESUME); } catch (e) {}
    }

    w.MelkinoTour = {
        start: start,
        next: next,
        prev: prev,
        stop: function () { finish(true); },
        reset: reset,
        replay: function () { reset(); start(0); },
        isDone: isDone
    };

    function bindReplay() {
        var el = d.getElementById('mkTourReplayBtn');
        if (!el) return;
        el.addEventListener('click', function (e) {
            e.preventDefault();
            reset();
            start(0);
        });
    }

    function readInit() {
        var tg = '', bl = '', ei = '';
        try { if (w.Eitaa && w.Eitaa.WebApp) ei = w.Eitaa.WebApp.initData || ''; } catch (e) {}
        try { if (w.Telegram && w.Telegram.WebApp) tg = w.Telegram.WebApp.initData || ''; } catch (e) {}
        try { if (w.Bale && w.Bale.WebApp) bl = w.Bale.WebApp.initData || ''; } catch (e) {}
        if (ei) return { platform: 'eitaa', data: ei };
        if (tg) return { platform: 'telegram', data: tg };
        if (bl) return { platform: 'bale', data: bl };
        try {
            var h = (w.location.hash || '').replace(/^#/, '');
            if (h) {
                var p = new URLSearchParams(h);
                var raw = p.get('tgWebAppData') || '';
                if (raw) {
                    var ua = navigator.userAgent || '';
                    var plat = /eitaa/i.test(ua) ? 'eitaa' : (/bale/i.test(ua) ? 'bale' : 'telegram');
                    return { platform: plat, data: raw };
                }
            }
        } catch (e) {}
        return { platform: '', data: '' };
    }
    function isMiniApp() {
        if (readInit().data) return true;
        var ua = '';
        try { ua = String(navigator.userAgent || ''); } catch (e) {}
        return /Telegram|Bale|Eitaa|WebApp/i.test(ua);
    }
    function isLoggedIn() {
        if (CFG.logged_in) return true;
        try { if (w.MELKINO_PROFILE && w.MELKINO_PROFILE.logged_in) return true; } catch (e) {}
        return false;
    }
    function showLoginWait() {
        if (d.getElementById('mkTourLoginWait')) return;
        var el = d.createElement('div');
        el.id = 'mkTourLoginWait';
        el.setAttribute('dir', 'rtl');
        el.innerHTML = '<div style="text-align:center;max-width:300px"><div style="color:#f5c518;font-weight:800;font-size:20px;margin-bottom:8px">در حال ورود</div><div style="color:#fff;font-size:13px;line-height:1.8;margin-bottom:18px">صبر کنید تا حساب پیام‌رسان ثبت شود؛ بعد پروفایل را می‌بینید.</div><div style="height:4px;border-radius:99px;background:#1c2a26;overflow:hidden"><i id="mkTourLoginBar" style="display:block;height:100%;width:18%;background:#f5c518"></i></div></div>';
        el.style.cssText = 'position:fixed;inset:0;z-index:2147483000;background:#0c1412;display:flex;align-items:center;justify-content:center;font-family:Tahoma,sans-serif';
        d.body.appendChild(el);
        var n = 18;
        el._t = setInterval(function () {
            n = Math.min(90, n + 4);
            var b = d.getElementById('mkTourLoginBar');
            if (b) b.style.width = n + '%';
        }, 250);
    }
    function hideLoginWait() {
        var el = d.getElementById('mkTourLoginWait');
        if (!el) return;
        try { clearInterval(el._t); } catch (e) {}
        el.remove();
    }
    function loginThen(ok, fail) {
        var got = readInit();
        if (!got.data) return false;
        var ep = got.platform === 'eitaa' ? 'auth-eitaa.php' : (got.platform === 'bale' ? 'auth-bale.php' : 'auth-telegram.php');
        fetch(ep, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ init_data: got.data })
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (j && j.success) {
                CFG.logged_in = true;
                if (w.MELKINO_PROFILE) w.MELKINO_PROFILE.logged_in = true;
                if (j.login_token) {
                    try {
                        var u = new URL(location.href);
                        u.searchParams.set('t', j.login_token);
                        hideLoginWait();
                        location.replace(u.toString());
                        return;
                    } catch (e) {}
                }
                ok();
                return;
            }
            fail();
        }).catch(function () { fail(); });
        return true;
    }
    function waitLogin(fn) {
        if (CFG.tour === 'admin') { fn(); return; }
        if (isLoggedIn()) { fn(); return; }
        if (!isMiniApp() && !readInit().data) { fn(); return; }
        showLoginWait();
        var tries = 0;
        var busy = false;
        var timer = setInterval(function () {
            tries++;
            if (isLoggedIn()) {
                clearInterval(timer);
                hideLoginWait();
                fn();
                return;
            }
            if (!busy) {
                busy = true;
                var started = loginThen(function () {
                    clearInterval(timer);
                    hideLoginWait();
                    fn();
                }, function () { busy = false; });
                if (!started) busy = false;
            }
            if (tries > 45) {
                clearInterval(timer);
                hideLoginWait();
            }
        }, 250);
    }

    function boot() {
        CFG = w.MELKINO_TOUR || CFG;
        bindReplay();
        if (CFG.enabled === false) return;
        var params = {};
        try { params = Object.fromEntries(new URLSearchParams(location.search)); } catch (e) {}
        if (params.mk_tour === 'reset') { reset(); }
        var force = params.mk_tour === '1' || params.mk_tour === 'start' || params.mk_tour === (CFG.tour || 'user');
        var resume = readResume();
        if (resume && typeof resume.i === 'number') {
            waitLogin(function () { setTimeout(function () { start(resume.i); }, 400); });
            return;
        }
        if (force) {
            reset();
            waitLogin(function () { setTimeout(function () { start(0); }, 400); });
            return;
        }
        if (CFG.auto && !isDone()) {
            var pg = pageName();
            var ok = (CFG.tour === 'admin')
                ? (pg === 'admin-panel.php')
                : (pg === 'home.php' || pg === 'profile.php');
            if (ok) waitLogin(function () { setTimeout(function () { start(0); }, 500); });
        }
    }

    if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot);
    else boot();
})(window, document);
