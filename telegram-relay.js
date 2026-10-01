/**
|--------------------------------------------------------------------------
| رله سمت مرورگر برای تلگرام و بله
|--------------------------------------------------------------------------
| InfinityFree خروجی سرور به api.telegram.org را می‌بندد.
| مرورگر ادمین باید مستقیم به API بزند. api.telegram.org هدر CORS می‌دهد،
| پس اگر fetch شکست بخورد یعنی شبکه/فیلتر است — نه «موفقیت خوانده‌نشده».
| موفقیت جعلی (no-cors / فرم مخفی) عمداً حذف شده است.
|--------------------------------------------------------------------------
*/
(function () {
    'use strict';

    var tokenCache = {};
    var API_BASES = {
        telegram: 'https://api.telegram.org/bot',
        bale: 'https://tapi.bale.ai/bot'
    };

    window.melkinoPlatformLabel = function (platform) {
        return platform === 'bale' ? 'بله' : 'تلگرام';
    };

    window.melkinoRelayToken = async function (platform) {
        if (tokenCache[platform]) return tokenCache[platform];
        try {
            const res = await fetch('telegram-relay.php?action=token&platform=' + encodeURIComponent(platform), {
                cache: 'no-store',
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (data && data.success && data.token) {
                tokenCache[platform] = data;
                return data;
            }
            return null;
        } catch (e) {
            return null;
        }
    };

    function abortableTimeout(ms) {
        var ctrl = new AbortController();
        var t = setTimeout(function () { try { ctrl.abort(); } catch (e) {} }, ms);
        return { signal: ctrl.signal, cancel: function () { clearTimeout(t); } };
    }

    function networkFail(platform, extra) {
        var host = platform === 'bale' ? 'tapi.bale.ai' : 'api.telegram.org';
        return {
            ok: false,
            via: 'browser',
            assumed: false,
            description: (extra ? extra + ' ' : '')
                + 'مرورگر به ' + host + ' وصل نشد. فیلترشکن را روشن کنید و دوباره «تأیید و انتشار» را بزنید.'
        };
    }

    async function postAndRead(url, body, signal) {
        var res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            signal: signal
        });
        var data = await res.json();
        return data;
    }

    window.melkinoClientCall = async function (platform, method, params, options) {
        options = options || {};
        var token = options.token || '';
        var apiBase = API_BASES[platform] || API_BASES.telegram;

        if (!token) {
            var tokenData = await window.melkinoRelayToken(platform);
            if (!tokenData || !tokenData.token) {
                return { ok: false, description: 'توکن ' + window.melkinoPlatformLabel(platform) + ' در دسترس نیست.', via: 'none' };
            }
            token = tokenData.token;
            if (tokenData.api_base) apiBase = tokenData.api_base;
        }

        var url = apiBase + token + '/' + method;
        var clean = {};
        Object.keys(params || {}).forEach(function (k) {
            if (params[k] != null && params[k] !== '') clean[k] = String(params[k]);
        });
        var body = new URLSearchParams(clean).toString();
        var timer = abortableTimeout(20000);

        try {
            var data = await postAndRead(url, body, timer.signal);
            data.via = 'browser';
            data.assumed = false;
            return data;
        } catch (e1) {
            try {
                var fd = new FormData();
                Object.keys(clean).forEach(function (k) { fd.append(k, clean[k]); });
                var res2 = await fetch(url, { method: 'POST', body: fd, signal: timer.signal });
                var data2 = await res2.json();
                data2.via = 'browser';
                data2.assumed = false;
                return data2;
            } catch (e2) {
                var aborted = (e1 && e1.name === 'AbortError') || (e2 && e2.name === 'AbortError');
                return networkFail(platform, aborted ? 'زمان اتصال تمام شد.' : '');
            }
        } finally {
            timer.cancel();
        }
    };

    window.melkinoServerCall = async function (platform, method, params) {
        try {
            const res = await fetch('telegram-relay.php?action=server_call', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    platform: platform,
                    method: method,
                    params: params || {},
                    csrf_token: window.MELKINO_CSRF || ''
                })
            });
            const data = await res.json();
            return {
                ok: !!(data && data.success),
                result: data ? data.result : null,
                description: data ? (data.message || '') : 'پاسخ نامعتبر',
                via: 'server',
                assumed: false
            };
        } catch (e) {
            return { ok: false, via: 'server', assumed: false, description: 'خطا در ارتباط با سرور سایت.' };
        }
    };

    window.melkinoApiCall = async function (platform, method, params, options) {
        options = options || {};
        var clientDescription = '';

        if (!options.serverOnly) {
            var clientResult = await window.melkinoClientCall(platform, method, params, options);
            if (clientResult && clientResult.ok && !clientResult.assumed) return clientResult;
            if (clientResult && clientResult.description) clientDescription = clientResult.description;
            if (options.clientOnly) {
                return clientResult && clientResult.assumed
                    ? { ok: false, via: 'browser', description: 'پاسخ تلگرام تأیید نشد؛ ارسال موفق فرض نمی‌شود.' }
                    : (clientResult || { ok: false, via: 'browser', description: 'ارسال از مرورگر ناموفق بود.' });
            }
        }

        if (options.clientOnly) {
            return { ok: false, via: 'browser', description: clientDescription || 'ارسال از مرورگر ناموفق بود.' };
        }

        var serverResult = await window.melkinoServerCall(platform, method, params);
        if (serverResult && !serverResult.ok && clientDescription && !options.serverOnly) {
            serverResult.description =
                (serverResult.description || 'خطای سرور')
                + ' | مرورگر: ' + clientDescription
                + ' — فیلترشکن را روشن کنید یا در تب «ربات و کانال» پروکسی سرور بگذارید.';
        }
        return serverResult;
    };

    window.melkinoRecordPublish = async function (platform, adId, messageId) {
        try {
            await fetch('telegram-relay.php?action=record', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    platform: platform,
                    ad_id: adId,
                    message_id: messageId || '',
                    csrf_token: window.MELKINO_CSRF || ''
                })
            });
        } catch (e) {}
    };

    window.melkinoClearRelayCache = function () {
        tokenCache = {};
    };
})();

(function bootMelkinoAssistant() {
    function load() {
        if (document.getElementById('melkino-assistant-boot')) return;
        if (!document.querySelector('.tabs-container')) return;
        var s = document.createElement('script');
        s.id = 'melkino-assistant-boot';
        s.src = 'admin-assistant.js?v=ast-btns-4';
        s.defer = true;
        document.head.appendChild(s);
        if (!document.getElementById('melkino-comm-boot')) {
            var c = document.createElement('script');
            c.id = 'melkino-comm-boot';
            c.src = 'admin-comm.js?v=cm4';
            c.defer = true;
            document.head.appendChild(c);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
