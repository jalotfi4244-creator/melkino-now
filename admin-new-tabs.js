/* =========================================================
   تب‌های جدید پنل ادمین ملکینو
   - ربات و کانال
   - تبلیغات
   - تم و رنگ
   - پشتیبان‌گیری
   - اعلان‌ها
   ========================================================= */

(function () {
    'use strict';

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[c]));

    function setStatus(id, message, ok) {
        const el = document.getElementById(id);
        if (!el) return;
        // راند ۳۶: بدون ایموجی؛ آیکون SVG مشترک
        const clean = String(message || '').replace(/^[\u2705\u274C\u2714\u26D4]\s*/u, '');
        el.innerHTML = (typeof mkStatusHtml === 'function') ? mkStatusHtml(ok, clean) : clean;
        el.style.color = ok === true ? 'var(--success)' : (ok === false ? 'var(--danger)' : 'var(--text-secondary)');
    }

    async function postJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload || {})
        });
        return res.json();
    }

    async function getJson(url) {
        const res = await fetch(url, { cache: 'no-store' });
        return res.json();
    }

    /* =====================================================
       ربات و کانال
       ===================================================== */

    window.loadBotSettings = async function () {
        try {
            const data = await getJson('admin-bots.php?action=get');
            if (!data.success) return;
            const s = data.settings || {};
            const set = (id, val) => { const e = document.getElementById(id); if (e) e.value = val || ''; };
            set('botTelegramChannel', s.telegram_channel);
            set('botTelegramUsername', s.telegram_bot_username);
            set('botBaleChannel', s.bale_channel);
            set('botBaleUsername', s.bale_bot_username);
            set('botEitaaUsername', s.eitaa_bot_username);
            set('botProxy', s.http_proxy);
            set('smsApiUrl', s.sms_api_url);
            set('smsSenderLine', s.sms_sender_line);
            set('smsProvider', s.sms_provider || 'melipayamak');
            set('smsOtpLine', s.sms_otp_line);
            set('smsPromoLine', s.sms_promo_line);
            set('smsOtpBodyId', s.sms_otp_body_id);
            set('smsOtpTemplate', s.sms_otp_template_masked);
            const sp = document.getElementById('smsApiPassword');
            if (sp) sp.placeholder = s.sms_password_masked || 'تنظیم نشده';
            const tg = document.getElementById('botTelegramToken');
            const ba = document.getElementById('botBaleToken');
            const ei = document.getElementById('botEitaaToken');
            const sk = document.getElementById('smsApiKey');
            if (tg) tg.placeholder = s.telegram_token_masked || 'تنظیم نشده';
            if (ba) ba.placeholder = s.bale_token_masked || 'تنظیم نشده';
            if (ei) ei.placeholder = s.eitaa_token_masked || 'تنظیم نشده';
            if (sk) sk.placeholder = s.sms_api_key_masked || 'تنظیم نشده';
            const se = document.getElementById('smsEnabled');
            if (se) se.checked = String(s.sms_enabled) === '1';
            // روش‌های ورود (پیش‌فرض: تلگرام/بله فعال، پیامک غیرفعال)
            const setChk = (id, key, def) => {
                const e = document.getElementById(id);
                if (e) e.checked = s[key] === undefined ? def : String(s[key]) === '1';
            };
            setChk('loginTelegramEnabled', 'login_telegram_enabled', true);
            setChk('loginBaleEnabled', 'login_bale_enabled', true);
            setChk('loginEitaaEnabled', 'login_eitaa_enabled', true);
            setChk('loginSmsEnabled', 'login_sms_enabled', false);
        } catch (e) {
            setStatus('botSettingsStatus', 'خطا در دریافت تنظیمات.', false);
        }
        try { loadPublishSettings('telegram'); } catch (e) {}
        try { loadPublishSettings('bale'); } catch (e) {}
    };

    window.saveBotSettings = async function () {
        const val = id => { const e = document.getElementById(id); return e ? e.value.trim() : ''; };
        const chk = id => { const e = document.getElementById(id); return !!(e && e.checked); };
        const se = document.getElementById('smsEnabled');
        const loginTg = chk('loginTelegramEnabled');
        const loginBale = chk('loginBaleEnabled');
        const loginEitaa = chk('loginEitaaEnabled');
        const loginSms = chk('loginSmsEnabled');
        if (!loginTg && !loginBale && !loginEitaa && !loginSms) {
            setStatus('botSettingsStatus', 'حداقل یکی از روش‌های ورود باید فعال بماند.', false);
            return;
        }
        setStatus('botSettingsStatus', 'در حال ذخیره...', null);
        try {
            const data = await postJson('admin-bots.php?action=save', {
                telegram_token: val('botTelegramToken'),
                telegram_channel: val('botTelegramChannel'),
                telegram_bot_username: val('botTelegramUsername'),
                bale_token: val('botBaleToken'),
                bale_channel: val('botBaleChannel'),
                bale_bot_username: val('botBaleUsername'),
                eitaa_token: val('botEitaaToken'),
                eitaa_bot_username: val('botEitaaUsername'),
                http_proxy: val('botProxy'),
                sms_enabled: se && se.checked ? 1 : 0,
                sms_api_key: val('smsApiKey'),
                sms_api_url: val('smsApiUrl'),
                sms_sender_line: val('smsSenderLine'),
                sms_provider: val('smsProvider') || 'melipayamak',
                sms_password: val('smsApiPassword'),
                sms_otp_line: val('smsOtpLine'),
                sms_promo_line: val('smsPromoLine'),
                sms_otp_body_id: val('smsOtpBodyId'),
                sms_otp_template: val('smsOtpTemplate'),
                login_telegram_enabled: loginTg ? 1 : 0,
                login_bale_enabled: loginBale ? 1 : 0,
                login_eitaa_enabled: loginEitaa ? 1 : 0,
                login_sms_enabled: loginSms ? 1 : 0
            });
            setStatus('botSettingsStatus', data.message || '', data.success);
            if (data.success) loadBotSettings();
        } catch (e) {
            setStatus('botSettingsStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    window.testSmsSend = async function () {
        const phoneEl = document.getElementById('smsTestPhone');
        const phone = phoneEl ? phoneEl.value.trim() : '';
        setStatus('botSettingsStatus', 'در حال ارسال پیامک تست...', null);
        try {
            const data = await postJson('admin-bots.php?action=test_sms', { phone });
            setStatus('botSettingsStatus', data.message || '', data.success);
        } catch (e) {
            setStatus('botSettingsStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    /* =====================================================
       محتوای انتشار در کانال (فیلدها + متن ثابت بالا/پایین)
       ===================================================== */

    function publishIds(platform) {
        const cap = platform === 'bale' ? 'Bale' : 'Telegram';
        return {
            box: 'publishFields' + cap,
            header: 'publishHeader' + cap,
            footer: 'publishFooter' + cap,
            status: 'publishStatus' + cap,
            preview: 'publishPreview' + cap,
            type: 'publishType' + cap,
            trans: 'publishTrans' + cap,
            reset: 'publishReset' + cap,
            comboStatus: 'publishComboStatus' + cap
        };
    }

    function publishCombo(platform) {
        const ids = publishIds(platform);
        const t = document.getElementById(ids.type);
        const r = document.getElementById(ids.trans);
        return {
            property_type: t ? t.value : '',
            transaction_type: r ? r.value : ''
        };
    }

    function fillComboSelect(sel, items, allLabel) {
        if (!sel) return;
        const cur = sel.value;
        sel.innerHTML = '<option value="">' + allLabel + '</option>'
            + items.map(v => '<option value="' + esc(v) + '">' + esc(v) + '</option>').join('');
        if (cur && items.indexOf(cur) !== -1) sel.value = cur;
    }

    window.melkinoReloadPublish = function (platform) {
        loadPublishSettings(platform === 'bale' ? 'bale' : 'telegram');
    };

    async function loadPublishSettings(platform) {
        const ids = publishIds(platform);
        const box = document.getElementById(ids.box);
        try {
            const combo = publishCombo(platform);
            const data = await postJson('admin-bots.php?action=publish_get', Object.assign({ platform }, combo));
            if (!data || !data.success) {
                if (box) box.innerHTML = '<span style="font-size:12px;color:var(--danger);">خطا در بارگذاری.</span>';
                return;
            }

            // پر کردن سلکت‌های ترکیب (فقط مقادیر؛ انتخاب فعلی حفظ می‌شود)
            const combos = data.combos || {};
            fillComboSelect(document.getElementById(ids.type), combos.property_types || [], 'همهٔ انواع ملک');
            fillComboSelect(document.getElementById(ids.trans), combos.transactions || [], 'همهٔ معاملات');

            // وضعیتِ ترکیبِ انتخاب‌شده
            const cs = document.getElementById(ids.comboStatus);
            const resetBtn = document.getElementById(ids.reset);
            const isComboSel = !!(combo.property_type || combo.transaction_type);
            const overridden = !!(data.settings || {}).is_override;
            if (cs) {
                if (!isComboSel) {
                    cs.textContent = 'تنظیمات عمومی (برای همهٔ آگهی‌هایی که ترکیبشان تنظیم اختصاصی ندارد)';
                } else if (overridden) {
                    cs.textContent = '✔ این ترکیب تنظیم اختصاصی دارد';
                } else {
                    cs.textContent = 'این ترکیب تنظیم اختصاصی ندارد — از تنظیمات عمومی استفاده می‌کند؛ با «ذخیره» تنظیم اختصاصی ساخته می‌شود';
                }
            }
            if (resetBtn) resetBtn.style.display = (isComboSel && overridden) ? '' : 'none';

            const defs = data.defs || {};
            const on = Array.isArray((data.settings || {}).fields) ? (data.settings.fields) : Object.keys(defs);
            if (box) {
                box.innerHTML = Object.keys(defs).map(key => {
                    const d = defs[key] || {};
                    const checked = on.indexOf(key) !== -1 ? 'checked' : '';
                    return '<label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-primary);cursor:pointer;border:1px solid var(--border);border-radius:10px;padding:8px 10px;">'
                        + '<input type="checkbox" data-publish-field="' + esc(key) + '" ' + checked + ' style="width:16px;height:16px;accent-color:var(--primary);">'
                        + '<span>' + esc(d.emoji || '') + ' ' + esc(d.label || key) + '</span>'
                        + '</label>';
                }).join('');
            }
            const h = document.getElementById(ids.header);
            const f = document.getElementById(ids.footer);
            if (h) h.value = (data.settings || {}).header || '';
            if (f) f.value = (data.settings || {}).footer || '';
        } catch (e) {
            if (box) box.innerHTML = '<span style="font-size:12px;color:var(--danger);">خطا در بارگذاری.</span>';
        }
    }

    window.savePublishSettings = async function (platform) {
        const ids = publishIds(platform === 'bale' ? 'bale' : 'telegram');
        platform = platform === 'bale' ? 'bale' : 'telegram';
        const box = document.getElementById(ids.box);
        const fields = [];
        if (box) {
            box.querySelectorAll('[data-publish-field]:checked').forEach(el => {
                fields.push(el.getAttribute('data-publish-field'));
            });
        }
        const h = document.getElementById(ids.header);
        const f = document.getElementById(ids.footer);
        setStatus(ids.status, 'در حال ذخیره...', null);
        try {
            const combo = publishCombo(platform);
            const data = await postJson('admin-bots.php?action=publish_save', Object.assign({
                platform,
                fields,
                header: h ? h.value : '',
                footer: f ? f.value : ''
            }, combo));
            setStatus(ids.status, data.message || '', data.success);
            if (data.success) loadPublishSettings(platform);
        } catch (e) {
            setStatus(ids.status, 'خطا در ارتباط با سرور.', false);
        }
    };

    window.resetPublishCombo = async function (platform) {
        platform = platform === 'bale' ? 'bale' : 'telegram';
        const ids = publishIds(platform);
        const combo = publishCombo(platform);
        if (!combo.property_type && !combo.transaction_type) return;
        setStatus(ids.status, 'در حال حذف تنظیم این ترکیب...', null);
        try {
            const data = await postJson('admin-bots.php?action=publish_save', Object.assign({
                platform,
                reset_combo: true
            }, combo));
            setStatus(ids.status, data.message || '', data.success);
            if (data.success) loadPublishSettings(platform);
        } catch (e) {
            setStatus(ids.status, 'خطا در ارتباط با سرور.', false);
        }
    };

    window.previewPublish = async function (platform) {
        const ids = publishIds(platform === 'bale' ? 'bale' : 'telegram');
        platform = platform === 'bale' ? 'bale' : 'telegram';
        const pre = document.getElementById(ids.preview);
        setStatus(ids.status, 'در حال ساخت پیش‌نمایش...', null);
        try {
            const data = await postJson('admin-bots.php?action=publish_preview', Object.assign({ platform }, publishCombo(platform)));
            if (!data || !data.success) {
                setStatus(ids.status, (data && data.message) || 'خطا در ساخت پیش‌نمایش.', false);
                return;
            }
            setStatus(ids.status, '', null);
            if (pre) {
                pre.style.display = 'block';
                pre.textContent = (data.is_sample ? '— پیش‌نمایش روی آگهی نمونه (هنوز آگهی منتشرشده‌ای نیست) —\n\n' : '')
                    + (data.text || '(متن خالی)');
            }
        } catch (e) {
            setStatus(ids.status, 'خطا در ارتباط با سرور.', false);
        }
    };

    /**
     * تست اتصال به تلگرام/بله
     *
     * ابتدا از «مرورگرِ ادمین» امتحان می‌شود؛ این برای هاست‌هایی که خروجیِ
     * سرورشان به api.telegram.org مسدود است (مثل InfinityFree) ضروری است،
     * چون در آن حالت تستِ سمتِ سرور همیشه خطا می‌دهد هرچند توکن سالم باشد.
     * اگر مرورگر هم موفق نشد، در نهایت از سرور امتحان می‌شود.
     */
    window.testBotConnection = async function (which) {
        const platform = which === 'telegram' ? 'telegram' : (which === 'eitaa' ? 'eitaa' : 'bale');
        const label = platform === 'telegram' ? 'تلگرام' : (platform === 'eitaa' ? 'ایتا' : 'بله');
        const tokenId = which === 'telegram' ? 'botTelegramToken' : (which === 'eitaa' ? 'botEitaaToken' : 'botBaleToken');
        const tokenEl = document.getElementById(tokenId);
        const typedToken = tokenEl ? tokenEl.value.trim() : '';

        setStatus('botSettingsStatus', 'در حال تست اتصال...', null);

        // ایتا getMe مرورگر ندارد؛ مستقیم سمت سرور
        if (platform === 'eitaa') {
            try {
                const data = await postJson('admin-bots.php?action=test_eitaa', { token: typedToken });
                setStatus('botSettingsStatus', data.message || '', data.success);
            } catch (e) {
                setStatus('botSettingsStatus', 'خطا در ارتباط با سرور.', false);
            }
            return;
        }

        // ۱) تلاش از مرورگر
        if (typeof window.melkinoApiCall === 'function') {
            try {
                const res = await window.melkinoApiCall(platform, 'getMe', {}, {
                    token: typedToken || ''
                });

                if (res && res.ok && res.result) {
                    const username = res.result.username ? ('@' + res.result.username) : '';
                    const via = res.via === 'browser' ? 'از طریق مرورگر شما' : 'از طریق سرور';
                    let msg = `✅ اتصال به ${label} برقرار است — ربات: ${username} (${via})`;
                    if (typedToken) {
                        msg += ' — توکن واردشده معتبر است؛ آن را ذخیره کن.';
                    }
                    setStatus('botSettingsStatus', msg, true);
                    return;
                }

                if (res && res.description) {
                    setStatus('botSettingsStatus', `❌ ${label}: ${res.description}`, false);
                    return;
                }
            } catch (e) {
                // ادامه می‌دهیم به مسیر سرور
            }
        }

        // ۲) تلاش از سرور (مسیر پشتیبان)
        const action = which === 'telegram' ? 'test_telegram' : 'test_bale';
        try {
            const data = await postJson('admin-bots.php?action=' + action, { token: typedToken });
            setStatus('botSettingsStatus', data.message || '', data.success);
        } catch (e) {
            setStatus('botSettingsStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    /**
     * تست دسترسی به کانالِ بله
     *
     * ابتدا از مرورگرِ ادمین تلاش می‌شود (برای هاست‌هایی که ارتباطِ
     * خروجی‌شان با پیام‌رسان‌ها مسدود است) و در صورت عدم موفقیت از سرور.
     *
     * نکته: شناسه‌ی خالی یا پیش‌فرض پیش از ارسال بررسی می‌شود، چون در غیر
     * این صورت بله پاسخِ گمراه‌کننده‌ی
     * «chat_id: must be a valid value» را برمی‌گرداند.
     */
    /* =========================================================
   یافتنِ شناسه‌ی کانال بله
   =========================================================
   وقتی انتشار با خطای «no such group or user» شکست می‌خورد، یعنی
   شناسه‌ای که ادمین وارد کرده برای ربات قابلِ شناسایی نیست. این تابع
   از سرور می‌خواهد گفتگوهایی را که ربات اخیراً دیده است فهرست کند تا
   ادمین شناسه‌ی عددیِ درست را مستقیماً انتخاب کند.
========================================================= */
/* =========================================================
   بررسیِ توکنِ بله
   =========================================================
   خطای «Unauthorized» مربوط به خودِ توکن است، نه شناسه‌ی کانال.
   این تابع از سرور می‌پرسد آیا بله این توکن را قبول دارد یا نه، و
   کدام ربات به آن وصل است.
========================================================= */
window.baleWhoAmI = async function () {
    try {
        var r = await fetch('admin-bots.php?action=bale_whoami', { cache: 'no-store' });
        var j = await r.json();
        var msg = (j && j.message) || 'پاسخ نامعتبر از سرور';
        if (j && j.masked) {
            msg += '\n' + 'توکنِ ذخیره‌شده: ' + j.masked;
        }
        alert(msg);
    } catch (e) {
        alert('خطا در ارتباط با سرور.');
    }
};

window.findBaleChats = async function () {

    var box = document.getElementById('baleChatsBox');
    if (!box) { return; }

    box.style.display = 'block';
    box.innerHTML = '<div style="font-size:12px;color:#666">در حال دریافتِ گفتگوها از بله…</div>';

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function render(list, note) {
        if (!list || !list.length) {
            box.innerHTML = '<div style="font-size:12px;line-height:1.9">' + esc(note || 'موردی پیدا نشد.') + '</div>';
            return;
        }

        var html = '<div style="font-size:12px;margin-bottom:8px">'
                 + esc(note || 'روی شناسه‌ی عددی بزن تا در فیلدِ بالا قرار بگیرد:')
                 + '</div>';

        list.forEach(function (c) {
            var label = esc(c.title || (c.username ? '@' + c.username : c.id));
            html += '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:7px 0;border-bottom:1px solid #eee">'
                  +   '<code dir="ltr" data-id="' + esc(c.id) + '" style="background:#eef3f2;border:1px solid #cfdedc;'
                  +   'border-radius:6px;padding:3px 8px;font-size:12px;cursor:pointer">' + esc(c.id) + '</code>'
                  +   '<span style="font-size:12px">' + label + '</span>'
                  +   (c.type ? '<span style="font-size:11px;color:#888">' + esc(c.type) + '</span>' : '')
                  +   (c.username ? '<span dir="ltr" style="font-size:11px;color:#888">@' + esc(c.username) + '</span>' : '')
                  + '</div>';
        });

        box.innerHTML = html;

        var codes = box.querySelectorAll('code[data-id]');
        Array.prototype.forEach.call(codes, function (el) {
            el.onclick = function () {
                var input = document.getElementById('botBaleChannel');
                if (input) { input.value = el.getAttribute('data-id'); }
                var done = document.createElement('div');
                done.style.cssText = 'font-size:12px;color:#0b5d59;margin-top:8px';
                done.textContent = 'شناسه در فیلد قرار گرفت. حالا دکمه‌ی «ذخیره» را بزن، سپس دوباره انتشار را امتحان کن.';
                box.appendChild(done);
            };
        });
    }

    try {
        var r = await fetch('admin-bots.php?action=bale_find_chats', { cache: 'no-store' });
        var j = await r.json();
        render(j && j.chats, (j && j.message) || '');
    } catch (e) {
        box.innerHTML = '<div style="font-size:12px;color:#b00020">خطا در ارتباط با سرور.</div>';
    }
};

window.testBaleChannelConnection = async function () {
        const el = document.getElementById('botBaleChannel');
        const channel = el ? el.value.trim() : '';

        const placeholders = ['', '@\u0622\u06cc\u062f\u06cc_\u06a9\u0627\u0646\u0627\u0644', '\u0622\u06cc\u062f\u06cc_\u06a9\u0627\u0646\u0627\u0644', '@', '-', '0'];
        if (placeholders.indexOf(channel) !== -1) {
            setStatus('botSettingsStatus', 'ابتدا شناسه کانال بله را وارد و ذخیره کن.', false);
            return;
        }

        setStatus('botSettingsStatus', 'در حال بررسی کانال بله...', null);

        // ۱) تلاش از مرورگر
        if (typeof window.melkinoApiCall === 'function') {
            try {
                const res = await window.melkinoApiCall('bale', 'getChat', { chat_id: channel });
                if (res && res.ok && res.result) {
                    const title = res.result.title || channel;
                    const via = res.via === 'browser' ? 'از طریق مرورگر شما' : 'از طریق سرور';
                    setStatus('botSettingsStatus', `✅ کانال بله در دسترس است: ${title} (${via})`, true);
                    return;
                }
                if (res && res.description) {
                    setStatus('botSettingsStatus', `❌ کانال بله: ${res.description}`, false);
                    return;
                }
            } catch (e) {
                // ادامه می‌دهیم به مسیر سرور
            }
        }

        // ۲) تلاش از سرور
        try {
            const data = await postJson('admin-bots.php?action=test_bale_channel', { channel: channel });
            setStatus('botSettingsStatus', data.message || '', data.success);
        } catch (e) {
            setStatus('botSettingsStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    window.testChannelConnection = async function () {
        const el = document.getElementById('botTelegramChannel');
        const channel = el ? el.value.trim() : '';
        if (!channel) {
            setStatus('botSettingsStatus', 'ابتدا شناسه کانال را وارد کن.', false);
            return;
        }
        setStatus('botSettingsStatus', 'در حال بررسی کانال...', null);

        // ۱) تلاش از مرورگر
        if (typeof window.melkinoApiCall === 'function') {
            try {
                const res = await window.melkinoApiCall('telegram', 'getChat', { chat_id: channel });
                if (res && res.ok && res.result) {
                    const title = res.result.title || channel;
                    const via = res.via === 'browser' ? 'از طریق مرورگر شما' : 'از طریق سرور';
                    setStatus('botSettingsStatus', `✅ کانال در دسترس است: ${title} (${via})`, true);
                    return;
                }
                if (res && res.description) {
                    setStatus('botSettingsStatus', `❌ کانال: ${res.description}`, false);
                    return;
                }
            } catch (e) {
                // ادامه می‌دهیم به مسیر سرور
            }
        }

        // ۲) تلاش از سرور
        try {
            const data = await postJson('admin-bots.php?action=test_channel', { channel: channel });
            setStatus('botSettingsStatus', data.message || '', data.success);
        } catch (e) {
            setStatus('botSettingsStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    /* =====================================================
       تبلیغات
       ===================================================== */

    window.loadPromotions = async function () {
        const box = document.getElementById('promotionsListContainer');
        if (!box) return;
        box.innerHTML = '<div class="mk-empty"><span class="mk-spinner"></span> در حال بارگذاری...</div>';

        try {
            const data = await getJson('admin-promotions.php?action=list');
            const rows = data.promotions || [];

            if (!rows.length) {
                box.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">هنوز تبلیغی ثبت نشده است.</div>';
                return;
            }

            const placementLabel = {
                all: 'همه صفحه‌ها', home: 'صفحه اصلی', properties: 'فهرست املاک',
                search: 'نتایج جستجو', vip: 'ملک‌های ویژه'
            };

            box.innerHTML = rows.map(p => `
                <div style="border:1px solid var(--border);border-radius:14px;padding:13px;margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
                        <div style="flex:1;min-width:180px;">
                            ${p.image_url ? `<img src="${esc(p.image_url)}" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:10px;display:block;margin-bottom:8px;">` : ''}
                            <div style="font-size:14px;font-weight:800;color:var(--text-primary);">${esc(p.title || (p.link_url ? 'تبلیغ با لینک' : 'تبلیغ تصویری'))}</div>
                            <div style="font-size:12px;color:var(--text-secondary);margin-top:4px;line-height:1.8;">
                                محل: ${esc(placementLabel[p.placement] || p.placement)} ·
                                بعد از کارت ${esc(p.position_after)}${Number(p.repeat_every) > 0 ? ' · تکرار هر ' + esc(p.repeat_every) + ' کارت' : ''}
                            </div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                                بازدید: ${esc(p.views || 0)} · کلیک: ${esc(p.clicks || 0)}
                                ${p.start_date ? ' · شروع: ' + esc(p.start_date) : ''}
                                ${p.end_date ? ' · پایان: ' + esc(p.end_date) : ''}
                            </div>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <button type="button" class="mk-btn mk-btn--sm mk-btn--outline" data-promo-toggle="${esc(p.id)}" data-active="${p.is_active == 1 ? 1 : 0}">
                                ${p.is_active == 1 ? MK_IC.pause + ' غیرفعال' : MK_IC.play + ' فعال'}
                            </button>
                            <button type="button" class="mk-btn mk-btn--sm mk-btn--outline" data-promo-edit="${esc(p.id)}">${MK_IC.edit} ویرایش</button>
                            <button type="button" class="mk-btn mk-btn--sm mk-btn--danger" data-promo-delete="${esc(p.id)}">${MK_IC.trash} حذف</button>
                        </div>
                    </div>
                </div>
            `).join('');
        } catch (e) {
            box.innerHTML = '<div style="color:var(--danger);font-size:13px;">خطا در بارگذاری تبلیغ‌ها.</div>';
        }
    };

    document.addEventListener('click', async function (e) {
        const t = e.target;

        const del = t.closest('[data-promo-delete]');
        if (del) {
            if (!confirm('این تبلیغ حذف شود؟')) return;
            await postJson('admin-promotions.php?action=delete', { id: del.getAttribute('data-promo-delete') });
            loadPromotions();
            return;
        }

        const tog = t.closest('[data-promo-toggle]');
        if (tog) {
            const active = tog.getAttribute('data-active') === '1' ? 0 : 1;
            await postJson('admin-promotions.php?action=toggle', { id: tog.getAttribute('data-promo-toggle'), is_active: active });
            loadPromotions();
            return;
        }

        const ed = t.closest('[data-promo-edit]');
        if (ed) {
            const data = await getJson('admin-promotions.php?action=list');
            const promo = (data.promotions || []).find(x => String(x.id) === String(ed.getAttribute('data-promo-edit')));
            if (promo) openPromotionEditor(promo);
        }
    });

    /* بستنِ مطمئنِ مودال تبلیغ:
       هم کلاس active را برمی‌دارد و هم display را مستقیماً none می‌کند،
       تا به هیچ تابع یا استایل بیرونی وابسته نباشد. */
    window.closePromotionModal = function () {
        const modal = document.getElementById('promotionModal');
        if (!modal) return;
        modal.classList.remove('active');
        modal.style.display = 'none';
    };

    window.openPromotionEditor = function (promo) {
        const modal = document.getElementById('promotionModal');
        if (!modal) return;
        // مودال‌های این پنل با کلاس active نمایش داده می‌شوند (نه style.display)
        modal.style.display = 'flex';
        modal.classList.add('active');

        const set = (id, v) => { const e = document.getElementById(id); if (e) e.value = v ?? ''; };
        set('promoId', promo ? promo.id : '');
        set('promoTitle', promo ? promo.title : '');
        set('promoDescription', promo ? promo.description : '');
        set('promoImageUrl', promo ? promo.image_url : '');
        set('promoLinkUrl', promo ? promo.link_url : '');
        set('promoButtonText', promo ? promo.button_text || 'مشاهده' : 'مشاهده');
        set('promoPlacement', promo ? promo.placement : 'all');
        set('promoPosition', promo ? promo.position_after : 3);
        set('promoRepeat', promo ? promo.repeat_every : 0);
        set('promoActive', promo ? String(promo.is_active) : '1');

        const toLocal = v => v ? String(v).replace(' ', 'T').slice(0, 16) : '';
        set('promoStart', promo ? toLocal(promo.start_date) : '');
        set('promoEnd', promo ? toLocal(promo.end_date) : '');

        const title = document.getElementById('promotionModalTitle');
        if (title) title.textContent = promo ? 'ویرایش تبلیغ' : 'تبلیغ جدید';
        setStatus('promoFormStatus', '', null);
    };

    window.savePromotion = async function () {
        const val = id => { const e = document.getElementById(id); return e ? e.value.trim() : ''; };
        setStatus('promoFormStatus', 'در حال ذخیره...', null);

        try {
            const data = await postJson('admin-promotions.php?action=save', {
                id: val('promoId'),
                title: val('promoTitle'),
                description: val('promoDescription'),
                image_url: val('promoImageUrl'),
                link_url: val('promoLinkUrl'),
                button_text: val('promoButtonText'),
                placement: val('promoPlacement'),
                position_after: val('promoPosition'),
                repeat_every: val('promoRepeat'),
                start_date: val('promoStart').replace('T', ' '),
                end_date: val('promoEnd').replace('T', ' '),
                is_active: val('promoActive') === '1'
            });
            setStatus('promoFormStatus', data.message || '', data.success);
            if (data.success) {
                loadPromotions();
                setTimeout(function () {
                    window.closePromotionModal();
                    setStatus('promoFormStatus', '', null);
                }, 500);
            }
        } catch (e) {
            setStatus('promoFormStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    window.uploadPromotionImage = async function () {
        const input = document.getElementById('promoImageFile');
        if (!input || !input.files || !input.files[0]) {
            setStatus('promoFormStatus', 'ابتدا یک تصویر انتخاب کن.', false);
            return;
        }
        setStatus('promoFormStatus', 'در حال آپلود...', null);

        const form = new FormData();
        form.append('image', input.files[0]);
        form.append('action', 'upload');

        try {
            const res = await fetch('admin-promotions.php?action=upload', { method: 'POST', body: form });
            const data = await res.json();
            if (data.success && data.url) {
                const urlEl = document.getElementById('promoImageUrl');
                if (urlEl) urlEl.value = data.url;
                setStatus('promoFormStatus', 'تصویر آپلود شد.', true);
            } else {
                setStatus('promoFormStatus', data.message || 'آپلود ناموفق بود.', false);
            }
        } catch (e) {
            setStatus('promoFormStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    /* =====================================================
       تم و رنگ
       ===================================================== */

    const THEME_KEYS = [
        ['--primary', 'رنگ اصلی'],
        ['--primary-dark', 'اصلی تیره'],
        ['--primary-light', 'اصلی روشن'],
        ['--secondary', 'رنگ دوم'],
        ['--gold', 'طلایی'],
        ['--bg', 'پس‌زمینه'],
        ['--bg-secondary', 'پس‌زمینه دوم'],
        ['--surface', 'سطح کارت‌ها'],
        ['--text-primary', 'متن اصلی'],
        ['--text-secondary', 'متن فرعی'],
        ['--border', 'حاشیه'],
        ['--success', 'موفق'],
        ['--danger', 'خطا'],
        ['--warning', 'هشدار'],
        ['--info', 'اطلاعات']
    ];

    let themeData = null;
    let themeEditMode = 'light';

    const THEME_PRESETS = {
        default: null, // از فایل theme.json خوانده می‌شود
        ocean: {
            light: { '--primary': '#0369A1', '--primary-dark': '#075985', '--primary-light': '#0EA5E9', '--secondary': '#0284C7', '--gold': '#D4AF37', '--bg': '#F8FAFC', '--bg-secondary': '#EFF6FF', '--surface': '#FFFFFF', '--text-primary': '#0F172A', '--text-secondary': '#64748B', '--border': '#E2E8F0', '--success': '#16A34A', '--danger': '#DC2626', '--warning': '#D97706', '--info': '#0284C7' },
            dark: { '--primary': '#38BDF8', '--primary-dark': '#0EA5E9', '--primary-light': '#7DD3FC', '--secondary': '#0EA5E9', '--gold': '#E5B842', '--bg': '#0B1220', '--bg-secondary': '#111C2E', '--surface': '#142033', '--text-primary': '#E2E8F0', '--text-secondary': '#94A3B8', '--border': '#1E293B', '--success': '#4ADE80', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#38BDF8' }
        },
        royal: {
            light: { '--primary': '#6D28D9', '--primary-dark': '#5B21B6', '--primary-light': '#8B5CF6', '--secondary': '#7C3AED', '--gold': '#D4AF37', '--bg': '#FAF8FF', '--bg-secondary': '#F3EEFF', '--surface': '#FFFFFF', '--text-primary': '#1E1B3A', '--text-secondary': '#6B6480', '--border': '#E9E4F5', '--success': '#16A34A', '--danger': '#DC2626', '--warning': '#D97706', '--info': '#2563EB' },
            dark: { '--primary': '#A78BFA', '--primary-dark': '#8B5CF6', '--primary-light': '#C4B5FD', '--secondary': '#8B5CF6', '--gold': '#E5B842', '--bg': '#120E1F', '--bg-secondary': '#1A1429', '--surface': '#211A33', '--text-primary': '#EDE9FE', '--text-secondary': '#A79DBF', '--border': '#2E2545', '--success': '#4ADE80', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#60A5FA' }
        },
        emerald: {
            light: { '--primary': '#065F46', '--primary-dark': '#064E3B', '--primary-light': '#0F766E', '--secondary': '#047857', '--gold': '#C9A227', '--bg': '#FAFAF7', '--bg-secondary': '#EEF4F1', '--surface': '#FFFFFF', '--text-primary': '#0F1F1A', '--text-secondary': '#5F6F68', '--border': '#DFE8E3', '--success': '#16A34A', '--danger': '#DC2626', '--warning': '#D97706', '--info': '#0EA5E9' },
            dark:  { '--primary': '#34D399', '--primary-dark': '#10B981', '--primary-light': '#6EE7B7', '--secondary': '#10B981', '--gold': '#E5C558', '--bg': '#08130F', '--bg-secondary': '#0E1C17', '--surface': '#12241E', '--text-primary': '#E6F5EF', '--text-secondary': '#9FB3AC', '--border': '#1E322A', '--success': '#4ADE80', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#38BDF8' }
        },
        onyx: {
            light: { '--primary': '#1F2937', '--primary-dark': '#111827', '--primary-light': '#374151', '--secondary': '#4B5563', '--gold': '#C9A227', '--bg': '#FAFAF9', '--bg-secondary': '#F1F1EF', '--surface': '#FFFFFF', '--text-primary': '#111827', '--text-secondary': '#6B7280', '--border': '#E5E7EB', '--success': '#16A34A', '--danger': '#DC2626', '--warning': '#D97706', '--info': '#2563EB' },
            dark:  { '--primary': '#D4AF37', '--primary-dark': '#B98F19', '--primary-light': '#E6C766', '--secondary': '#9CA3AF', '--gold': '#E5C558', '--bg': '#0B0B0C', '--bg-secondary': '#141416', '--surface': '#1A1A1D', '--text-primary': '#F5F5F4', '--text-secondary': '#A8A29E', '--border': '#2A2A2E', '--success': '#4ADE80', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#60A5FA' }
        },
        sapphire: {
            light: { '--primary': '#0F2E5C', '--primary-dark': '#0B2447', '--primary-light': '#1E4F8F', '--secondary': '#1D4ED8', '--gold': '#C9A227', '--bg': '#F8FAFC', '--bg-secondary': '#EDF2F9', '--surface': '#FFFFFF', '--text-primary': '#0B1B2B', '--text-secondary': '#5A6B80', '--border': '#DDE5EF', '--success': '#16A34A', '--danger': '#DC2626', '--warning': '#D97706', '--info': '#0284C7' },
            dark:  { '--primary': '#60A5FA', '--primary-dark': '#3B82F6', '--primary-light': '#93C5FD', '--secondary': '#3B82F6', '--gold': '#E5C558', '--bg': '#070F1C', '--bg-secondary': '#0D1729', '--surface': '#131F33', '--text-primary': '#E8F0FA', '--text-secondary': '#9AAEC4', '--border': '#1E2E47', '--success': '#4ADE80', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#38BDF8' }
        },
        champagne: {
            light: { '--primary': '#8A6A3B', '--primary-dark': '#6F5430', '--primary-light': '#A98A57', '--secondary': '#B08D57', '--gold': '#D4AF37', '--bg': '#FCFAF5', '--bg-secondary': '#F5F0E6', '--surface': '#FFFFFF', '--text-primary': '#2A2419', '--text-secondary': '#736853', '--border': '#EDE4D3', '--success': '#4D7C0F', '--danger': '#B91C1C', '--warning': '#B45309', '--info': '#0369A1' },
            dark:  { '--primary': '#E0C88C', '--primary-dark': '#C9A227', '--primary-light': '#EFDCB0', '--secondary': '#C9A227', '--gold': '#E5C558', '--bg': '#141108', '--bg-secondary': '#1D1810', '--surface': '#241E13', '--text-primary': '#F7EFDD', '--text-secondary': '#BCAA8A', '--border': '#382F1E', '--success': '#A3E635', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#7DD3FC' }
        },
        silver: {
            light: { '--primary': '#334155', '--primary-dark': '#1E293B', '--primary-light': '#475569', '--secondary': '#64748B', '--gold': '#BFA46F', '--bg': '#F8FAFC', '--bg-secondary': '#EEF2F6', '--surface': '#FFFFFF', '--text-primary': '#0F172A', '--text-secondary': '#64748B', '--border': '#E2E8F0', '--success': '#16A34A', '--danger': '#DC2626', '--warning': '#D97706', '--info': '#0EA5E9' },
            dark:  { '--primary': '#CBD5E1', '--primary-dark': '#94A3B8', '--primary-light': '#E2E8F0', '--secondary': '#94A3B8', '--gold': '#D6BE8B', '--bg': '#0A0F16', '--bg-secondary': '#101823', '--surface': '#16202B', '--text-primary': '#E9EEF5', '--text-secondary': '#9AA9BC', '--border': '#253141', '--success': '#4ADE80', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#38BDF8' }
        },
        sunset: {
            light: { '--primary': '#C2410C', '--primary-dark': '#9A3412', '--primary-light': '#EA580C', '--secondary': '#DB2777', '--gold': '#D4AF37', '--bg': '#FFFAF5', '--bg-secondary': '#FFF1E7', '--surface': '#FFFFFF', '--text-primary': '#2B1B14', '--text-secondary': '#7C5A4A', '--border': '#F5E1D3', '--success': '#16A34A', '--danger': '#DC2626', '--warning': '#D97706', '--info': '#0891B2' },
            dark: { '--primary': '#FB923C', '--primary-dark': '#EA580C', '--primary-light': '#FDBA74', '--secondary': '#F472B6', '--gold': '#E5B842', '--bg': '#1A1008', '--bg-secondary': '#241610', '--surface': '#2C1C13', '--text-primary': '#FEEBC8', '--text-secondary': '#C4A48F', '--border': '#3A2519', '--success': '#4ADE80', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#22D3EE' }
        },
        forest: {
            light: { '--primary': '#15803D', '--primary-dark': '#166534', '--primary-light': '#22C55E', '--secondary': '#0F766E', '--gold': '#D4AF37', '--bg': '#F7FAF7', '--bg-secondary': '#EAF5EC', '--surface': '#FFFFFF', '--text-primary': '#10261A', '--text-secondary': '#5B6E60', '--border': '#DCE9DE', '--success': '#16A34A', '--danger': '#DC2626', '--warning': '#D97706', '--info': '#0EA5E9' },
            dark: { '--primary': '#4ADE80', '--primary-dark': '#22C55E', '--primary-light': '#86EFAC', '--secondary': '#34B7A9', '--gold': '#E5B842', '--bg': '#0B1410', '--bg-secondary': '#111C17', '--surface': '#16241D', '--text-primary': '#E7F5EC', '--text-secondary': '#9DB3A5', '--border': '#25382D', '--success': '#4ADE80', '--danger': '#F87171', '--warning': '#FBBF24', '--info': '#38BDF8' }
        }
    };

    window.initThemeManager = function () {
        if (themeData) return;
        loadThemeSettings();
    };

    window.loadThemeSettings = async function () {
        try {
            const data = await getJson('save_theme.php');
            if (data.success && data.themes) {
                themeData = JSON.parse(JSON.stringify(data.themes));
                renderThemeInputs();
            }
        } catch (e) {
            setStatus('themeStatus', 'خطا در دریافت رنگ‌ها.', false);
        }
    };

    function renderThemeInputs() {
        const grid = document.getElementById('themeColorGrid');
        if (!grid || !themeData) return;

        const mode = themeData[themeEditMode] || {};
        grid.innerHTML = THEME_KEYS.map(([key, label]) => {
            const value = mode[key] || '#000000';
            const hex = /^#([0-9a-fA-F]{3,8})$/.test(value) ? value : '#000000';
            return `
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <label style="font-size:12px;color:var(--text-secondary);">${esc(label)}</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="color" value="${esc(hex)}" data-theme-key="${esc(key)}" style="width:46px;height:38px;border:1px solid var(--border);border-radius:8px;cursor:pointer;padding:2px;background:var(--surface);">
                        <input type="text" value="${esc(value)}" data-theme-text="${esc(key)}" dir="ltr" class="admin-input" style="flex:1;font-size:12px;">
                    </div>
                </div>`;
        }).join('');

        const lightBtn = document.getElementById('themeModeLight');
        const darkBtn = document.getElementById('themeModeDark');
        if (lightBtn) lightBtn.style.opacity = themeEditMode === 'light' ? '1' : '.6';
        if (darkBtn) darkBtn.style.opacity = themeEditMode === 'dark' ? '1' : '.6';
    }

    document.addEventListener('input', function (e) {
        const colorEl = e.target.closest('[data-theme-key]');
        if (colorEl) {
            const key = colorEl.getAttribute('data-theme-key');
            const textEl = document.querySelector('[data-theme-text="' + key + '"]');
            if (textEl) textEl.value = colorEl.value;
            if (themeData && themeData[themeEditMode]) themeData[themeEditMode][key] = colorEl.value;
            return;
        }
        const textEl = e.target.closest('[data-theme-text]');
        if (textEl) {
            const key = textEl.getAttribute('data-theme-text');
            const colorEl2 = document.querySelector('[data-theme-key="' + key + '"]');
            if (colorEl2 && /^#([0-9a-fA-F]{3,8})$/.test(textEl.value.trim())) {
                colorEl2.value = textEl.value.trim();
            }
            if (themeData && themeData[themeEditMode]) themeData[themeEditMode][key] = textEl.value.trim();
        }
    });

    window.setThemeEditMode = function (mode) {
        themeEditMode = mode === 'dark' ? 'dark' : 'light';
        renderThemeInputs();
    };

    window.previewThemeColors = function () {
        if (!themeData) return;
        const root = document.documentElement;
        const current = themeEditMode;
        const values = themeData[current] || {};
        // پیش‌نمایش فقط روی همان حالتی که کاربر الان در آن است
        const isDarkNow = root.getAttribute('data-theme') === 'dark';
        if ((current === 'dark') !== isDarkNow) {
            setStatus('themeStatus', 'برای دیدن این حالت، ابتدا حالت روشن/تاریک سایت را عوض کن.', null);
            return;
        }
        Object.keys(values).forEach(k => root.style.setProperty(k, values[k]));
        setStatus('themeStatus', 'پیش‌نمایش اعمال شد (ذخیره نشده).', true);
    };

    window.saveThemeColors = async function () {
        if (!themeData) return;
        setStatus('themeStatus', 'در حال ذخیره...', null);
        try {
            const res = await fetch('save_theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ themes: themeData })
            });
            const data = await res.json();
            setStatus('themeStatus', data.message || (data.success ? 'رنگ‌ها ذخیره شد.' : 'ذخیره ناموفق بود.'), data.success);
            if (data.success) setTimeout(() => location.reload(), 800);
        } catch (e) {
            setStatus('themeStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    window.applyThemePreset = async function (name) {
        if (!confirm('تم انتخاب‌شده جایگزین رنگ‌های فعلی شود؟')) return;

        let preset = THEME_PRESETS[name];
        if (name === 'default') {
            try {
                const res = await fetch('theme.json', { cache: 'no-store' });
                const json = await res.json();
                preset = json.themes || null;
            } catch (e) {
                preset = null;
            }
        }
        if (!preset) {
            setStatus('themeStatus', 'این تم در دسترس نیست.', false);
            return;
        }

        // فقط کلیدهایی که در ویرایشگر هستند جایگزین می‌شوند
        const merged = { light: {}, dark: {} };
        ['light', 'dark'].forEach(mode => {
            merged[mode] = Object.assign({}, themeData ? themeData[mode] : {});
            THEME_KEYS.forEach(([key]) => {
                if (preset[mode] && preset[mode][key]) merged[mode][key] = preset[mode][key];
            });
        });

        themeData = merged;
        renderThemeInputs();

        try {
            const res = await fetch('save_theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ themes: themeData })
            });
            const data = await res.json();
            setStatus('themeStatus', data.success ? 'تم اعمال شد.' : 'اعمال تم ناموفق بود.', data.success);
            if (data.success) setTimeout(() => location.reload(), 800);
        } catch (e) {
            setStatus('themeStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    window.resetThemeColors = async function () {
        if (!confirm('رنگ‌ها به پیش‌فرض ملکینو برگردند؟')) return;
        await applyThemePreset('default');
    };

    /* =====================================================
       پشتیبان‌گیری
       ===================================================== */

    // راند ۳۴: آیکون‌های SVG یکدست سامانهٔ طراحی (بدون ایموجی در UI)
    const MK_IC = {
    chat: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v11H9l-5 4z"/></svg>',
    plus: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>',
    search: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    send: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 3 3 10.5l7 3 3 7z"/><path d="M21 3 10 13.5"/></svg>',
    star: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 2.7 5.8 6.3.8-4.6 4.3 1.2 6.1L12 17l-5.6 3 1.2-6.1L3 9.6l6.3-.8z"/></svg>',
    bank: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10h18M5 10v8M9 10v8M15 10v8M19 10v8M3 21h18M12 3 3 10h18z"/></svg>',
    coins: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6"/><path d="M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/></svg>',
    home: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11l8-7 8 7"/><path d="M6 9.5V21h12V9.5"/></svg>',
        download: '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>',
        restore:  '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>',
        trash:    '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/></svg>',
        shield:   '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.2 7.8-8 9-4.8-1.2-8-4.5-8-9V6Z"/></svg>',
        db:       '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5.5" rx="8" ry="2.8"/><path d="M4 5.5v13c0 1.5 3.6 2.8 8 2.8s8-1.3 8-2.8v-13"/><path d="M4 12c0 1.5 3.6 2.8 8 2.8s8-1.3 8-2.8"/></svg>',
        folder:   '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></svg>',
        rows:     '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18"/></svg>',
        warn:     '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.8 2.3 17.8A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.2l-8-14a2 2 0 0 0-3.4 0Z"/><path d="M12 9v5"/><path d="M12 17.5h.01"/></svg>',
        edit:     '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>',
        play:     '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4.5 19 12 7 19.5Z"/></svg>',
        pause:    '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5v14M15 5v14"/></svg>',
        pin:      '<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" style="width:12px;height:12px;vertical-align:-2px;" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>',
        megaphone:'<svg class="mk-icon mk-icon--sm" width="16" height="16" viewBox="0 0 24 24" style="vertical-align:-3px;margin-inline-end:4px;" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 14-5v12L3 13v-2Z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>'
    };
    window.MK_IC = Object.assign(window.MK_IC || {}, MK_IC);

    window.loadBackups = async function () {
        const box = document.getElementById('backupsListContainer');
        if (!box) return;
        box.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-secondary);font-size:13px;">در حال بارگذاری...</div>';

        try {
            const data = await getJson('admin-backup.php?action=list');
            const rows = data.backups || [];

            if (!rows.length) {
                box.innerHTML = '<div class="mk-empty">هیچ فایل پشتیبانی وجود ندارد. برای ساخت نخستین پشتیبان روی «پشتیبان کامل» بزنید.</div>';
                return;
            }

            // راند ۳۳: هشدار قدیمی بودن آخرین پشتیبان (بیش از ۷ روز)
            let warnHtml = '';
            const newest = rows[0];
            if (newest && Number(newest.age_days || 0) >= 7) {
                warnHtml = '<div class="mk-warn" style="margin-bottom:12px;">' + MK_IC.warn + ' آخرین پشتیبان ' + esc(newest.age_days) + ' روز پیش ساخته شده است. پیشنهاد می‌شود همین حالا یک پشتیبان تازه بسازید.</div>';
            }

            box.innerHTML = warnHtml + rows.map(b => {
                let badges = '';
                if (b.is_safety) badges += '<span class="mk-badge mk-badge--gold">' + MK_IC.shield + ' نسخه ایمنی</span>';
                if (b.meta) {
                    if (b.meta.with_db) badges += '<span class="mk-badge mk-badge--ok">' + MK_IC.db + ' دیتابیس (' + (b.meta.tables || 0) + ' جدول)</span>';
                    else badges += '<span class="mk-badge mk-badge--muted">' + MK_IC.db + ' بدون دیتابیس</span>';
                    if (b.meta.with_files) badges += '<span class="mk-badge mk-badge--muted">' + MK_IC.folder + ' ' + (b.meta.file_count || 0) + ' فایل</span>';
                    // راند ۳۳: مجموع رکوردهای دیتابیس در زمان پشتیبان‌گیری
                    if (b.meta.rows_total > 0) badges += '<span class="mk-badge mk-badge--info">' + MK_IC.rows + ' ' + (b.meta.rows_total) + ' رکورد</span>';
                }
                return `
                <div class="mk-card mk-row-between" style="margin-bottom:10px;">
                    <div>
                        <div style="font-size:var(--fs-md);font-weight:var(--fw-bold);color:var(--text-primary);" dir="ltr">${esc(b.name)}</div>
                        <div style="font-size:var(--fs-xs);color:var(--text-muted);margin-top:4px;">${esc(b.created_at)} · ${esc(b.size_human)} · ${(b.age_days === 0 ? 'امروز' : (b.age_days || 0) + ' روز پیش')}</div>
                        ${badges ? '<div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">' + badges + '</div>' : '' }
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button type="button" class="mk-btn mk-btn--sm mk-btn--outline" data-backup-download="${esc(b.name)}">${MK_IC.download} دانلود</button>
                        <button type="button" class="mk-btn mk-btn--sm mk-btn--outline" data-backup-restore="${esc(b.name)}">${MK_IC.restore} بازیابی</button>
                        <button type="button" class="mk-btn mk-btn--sm mk-btn--danger" data-backup-delete="${esc(b.name)}">${MK_IC.trash} حذف</button>
                    </div>
                </div>
            `;
            }).join('');
        } catch (e) {
            box.innerHTML = '<div class="mk-error">خطا در بارگذاری فهرست پشتیبان‌ها.</div>';
        }
    };

    window.createBackup = async function () {
        const withDb = document.getElementById('backupWithDb');
        const withFiles = document.getElementById('backupWithFiles');
        const includeDb = withDb ? (withDb.checked ? '1' : '0') : '1';
        const includeFiles = withFiles ? (withFiles.checked ? '1' : '0') : '1';

        if (includeDb === '0' && includeFiles === '0') {
            alert('حداقل یکی از «فایل‌ها» یا «دیتابیس» باید انتخاب شود.');
            return;
        }
        if (!confirm('ساخت فایل پشتیبان ممکن است کمی طول بکشد. ادامه بدهیم؟')) return;

        const status = document.getElementById('backupsListContainer');
        if (status) status.innerHTML = '<div class="mk-empty"><span class="mk-spinner"></span> در حال ساخت پشتیبان...</div>';

        try {
            const data = await getJson('admin-backup.php?action=create&with_db=' + includeDb + '&with_files=' + includeFiles);
            if (data.success) {
                alert(data.message || 'پشتیبان ساخته شد.');
                loadBackups();
            } else {
                alert(data.message || 'ساخت پشتیبان ناموفق بود.');
                loadBackups();
            }
        } catch (e) {
            alert('خطا در ارتباط با سرور.');
            loadBackups();
        }
    };

    window.loadMelkinoExcelUi = async function () {
        const box = document.getElementById('melkinoRatingList');
        if (!box) return; // امتیاز داخل ویرایش آگهی است؛ این جعبه دیگر در تب پشتیبان نیست
        try {
            const data = await getJson('admin-ads-excel.php?action=rating_state');
            const en = document.getElementById('melkinoRatingEnabled');
            if (en) en.checked = !!data.enabled;
            const rows = data.ads || [];
            if (!rows.length) {
                box.innerHTML = '<div class="mk-empty">آگهی‌ای برای امتیازدهی نیست.</div>';
                return;
            }
            box.innerHTML = rows.map(a => `
                <div class="mk-card" style="margin-bottom:8px;padding:10px 12px;">
                    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:center;">
                        <div>
                            <div style="font-weight:700;font-size:13px;">${esc(a.title || a.id)}</div>
                            <div style="font-size:11px;color:var(--text-muted);" dir="ltr">${esc(a.id)} · ${esc(a.status || '')}</div>
                        </div>
                        <label style="font-size:12px;display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" data-mk-visited="${esc(a.id)}" ${Number(a.melkino_visited) ? 'checked' : ''}> بازدید ملکینو
                        </label>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center;">
                        <label style="font-size:12px;">امتیاز
                            <select data-mk-rating="${esc(a.id)}" class="admin-input" style="width:auto;padding:4px 8px;font-size:12px;">
                                ${[0,1,2,3,4,5].map(n => `<option value="${n}" ${Number(a.melkino_rating) === n ? 'selected' : ''}>${n === 0 ? '—' : n + ' ستاره'}</option>`).join('')}
                            </select>
                        </label>
                    </div>
                    <textarea data-mk-review="${esc(a.id)}" class="admin-input" rows="2" placeholder="نظر ملکینو (فقط در صفحه جزئیات)" style="width:100%;margin-top:8px;font-size:12px;">${esc(a.melkino_review || '')}</textarea>
                    <button type="button" class="btn-secondary" style="padding:5px 12px;font-size:12px;margin-top:6px;" data-mk-save="${esc(a.id)}">ذخیره امتیاز</button>
                </div>
            `).join('');
        } catch (e) {
            box.innerHTML = '<div class="mk-error">بارگذاری فهرست امتیاز ممکن نشد.</div>';
        }
    };

    window.melkinoExcelExport = function () {
        window.location.href = 'admin-ads-excel.php?action=export';
    };
    window.melkinoExcelTemplate = function () {
        window.location.href = 'admin-ads-excel.php?action=template';
    };
    window.melkinoExcelImport = async function (input) {
        const st = document.getElementById('melkinoExcelStatus');
        if (!input || !input.files || !input.files[0]) return;
        if (st) st.textContent = 'در حال بارگذاری اکسل...';
        const fd = new FormData();
        fd.append('excel_file', input.files[0]);
        fd.append('action', 'import');
        try {
            const res = await fetch('admin-ads-excel.php?action=import', { method: 'POST', body: fd });
            const data = await res.json();
            const extra = (data.errors && data.errors.length) ? (' ' + data.errors.join(' | ')) : '';
            if (st) st.textContent = (data.message || '') + extra;
            if (data.success) loadMelkinoExcelUi();
        } catch (e) {
            if (st) st.textContent = 'خطا در ارتباط با سرور.';
        }
        input.value = '';
    };
    window.melkinoRatingToggle = async function (on) {
        const st = document.getElementById('melkinoRatingStatus');
        try {
            const data = await postJson('admin-ads-excel.php?action=rating_toggle', { enabled: !!on });
            if (st) st.textContent = data.message || '';
        } catch (e) {
            if (st) st.textContent = 'ذخیره تنظیمات ناموفق بود.';
        }
    };

    document.addEventListener('click', async function (e) {
        const saveBtn = e.target.closest('[data-mk-save]');
        if (saveBtn) {
            const id = saveBtn.getAttribute('data-mk-save');
            const visited = document.querySelector('[data-mk-visited="' + id + '"]');
            const rating = document.querySelector('[data-mk-rating="' + id + '"]');
            const review = document.querySelector('[data-mk-review="' + id + '"]');
            const st = document.getElementById('melkinoRatingStatus');
            try {
                const data = await postJson('admin-ads-excel.php?action=rating_save', {
                    id,
                    visited: !!(visited && visited.checked),
                    rating: rating ? Number(rating.value) : 0,
                    review: review ? review.value : ''
                });
                if (st) st.textContent = data.message || '';
            } catch (err) {
                if (st) st.textContent = 'ذخیره امتیاز ناموفق بود.';
            }
            return;
        }
    });

    document.addEventListener('click', async function (e) {
        const t = e.target;

        const dl = t.closest('[data-backup-download]');
        if (dl) {
            window.location.href = 'admin-backup.php?action=download&file=' + encodeURIComponent(dl.getAttribute('data-backup-download'));
            return;
        }

        const del = t.closest('[data-backup-delete]');
        if (del) {
            if (!confirm('این فایل پشتیبان حذف شود؟')) return;
            await postJson('admin-backup.php?action=delete', { file: del.getAttribute('data-backup-delete') });
            loadBackups();
            return;
        }

        const rs = t.closest('[data-backup-restore]');
        if (rs) {
            const withDb = document.getElementById('restoreWithDb');
            const withFiles = document.getElementById('restoreWithFiles');
            const restoreDb = withDb ? withDb.checked : false;
            const restoreFiles = withFiles ? withFiles.checked : true;
            if (!restoreDb && !restoreFiles) {
                alert('حداقل یکی از «فایل‌ها» یا «دیتابیس» باید انتخاب شود.');
                return;
            }
            let warning;
            if (restoreDb && restoreFiles) {
                warning = 'بازیابی فایل‌ها و دیتابیس انجام شود؟\nاطلاعات فعلی دیتابیس با اطلاعات درون پشتیبان جایگزین می‌شود.\n(پیش از بازیابی یک نسخه‌ی ایمنی ساخته می‌شود)';
            } else if (restoreDb) {
                warning = 'فقط دیتابیس از این پشتیبان بازیابی شود؟\nاطلاعات فعلی دیتابیس جایگزین می‌شود.\n(پیش از بازیابی یک نسخه‌ی ایمنی ساخته می‌شود)';
            } else {
                warning = 'فایل‌های پروژه از این پشتیبان بازیابی شوند؟\n(پیش از بازیابی یک نسخه‌ی ایمنی ساخته می‌شود)';
            }
            if (!confirm(warning)) return;
            if (restoreDb && !confirm('مطمئنی؟ بازیابی دیتابیس قابل بازگشت نیست (جز با نسخه‌ی ایمنی).')) return;

            try {
                const data = await postJson('admin-backup.php?action=restore', {
                    file: rs.getAttribute('data-backup-restore'),
                    restore_db: restoreDb,
                    restore_files: restoreFiles
                });
                alert(data.message || (data.success ? 'بازیابی انجام شد.' : 'بازیابی ناموفق بود.'));
                if (data.success) setTimeout(() => location.reload(), 1200);
            } catch (err) {
                alert('خطا در ارتباط با سرور.');
            }
        }
    });


    /* =====================================================
       مدیریت تصاویر آگهی‌ها
       ===================================================== */

    window.loadAdminImages = async function () {
        const box = document.getElementById('adminImagesContainer');
        if (!box) return;
        box.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-secondary);font-size:13px;">در حال بارگذاری تصاویر...</div>';

        const q = (document.getElementById('imagesSearch') || {}).value || '';
        const missing = (document.getElementById('imagesMissingOnly') || {}).value || '0';
        const url = 'admin-images.php?action=list&q=' + encodeURIComponent(q) + '&missing=' + encodeURIComponent(missing);

        try {
            const data = await getJson(url);
            const rows = data.images || [];

            if (!rows.length) {
                box.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">تصویری با این فیلتر پیدا نشد.</div>';
                return;
            }

            const esc2 = esc;
            box.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;">' +
                rows.map(im => `
                    <div style="border:1px solid var(--border);border-radius:14px;overflow:hidden;background:var(--surface);display:flex;flex-direction:column;">
                        <div style="position:relative;height:110px;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;overflow:hidden;">
                            ${im.exists
                                ? `<img src="${esc2(im.url)}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;">`
                                : '<span style="font-size:11px;color:var(--danger);padding:8px;text-align:center;">فایل روی سرور نیست</span>'}
                            ${im.is_primary == 1 ? '<span style="position:absolute;top:6px;right:6px;background:var(--gold-gradient, #D4AF37);color:#111827;font-size:10px;font-weight:800;padding:3px 8px;border-radius:999px;">اصلی</span>' : ''}
                        </div>
                        <div style="padding:9px 10px 11px;display:flex;flex-direction:column;gap:4px;flex:1;">
                            <div style="font-size:11px;color:var(--text-primary);font-weight:700;line-height:1.6;word-break:break-word;">${esc2(im.ad_title || ('آگهی ' + im.ad_id))}</div>
                            <div style="font-size:10px;color:var(--text-muted);word-break:break-all;" dir="ltr">${esc2(im.filename)}</div>
                            <div style="font-size:10px;color:var(--text-muted);">${esc2(im.size_human)}${im.exists ? '' : ' · ' + 'بدون فایل'}</div>
                            <button type="button" class="mk-btn mk-btn--sm mk-btn--danger" style="margin-top:auto;" data-image-delete="${esc2(im.id)}">
                                ${MK_IC.trash} حذف تصویر
                            </button>
                        </div>
                    </div>
                `).join('') + '</div>';
        } catch (e) {
            box.innerHTML = '<div style="color:var(--danger);font-size:13px;">خطا در بارگذاری تصاویر.</div>';
        }
    };

    window.scanOrphanImages = async function () {
        const box = document.getElementById('orphanImagesContainer');
        if (!box) return;
        box.innerHTML = '<div class="mk-empty"><span class="mk-spinner"></span> در حال اسکن پوشه uploads...</div>';

        try {
            const data = await getJson('admin-images.php?action=orphans');
            const rows = data.orphans || [];

            if (!rows.length) {
                box.innerHTML = '<div class="mk-empty" style="color:var(--success);">هیچ فایل بدون استفاده‌ای پیدا نشد.</div>';
                return;
            }

            box.innerHTML = `
                <div style="border:1px solid var(--border);border-radius:14px;padding:12px;margin-bottom:10px;">
                    <div style="font-size:13px;font-weight:800;color:var(--text-primary);margin-bottom:4px;">${rows.length} فایل بدون استفاده (${esc(data.total_size_human)})</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px;">این فایل‌ها به هیچ آگهی‌ای وصل نیستند.</div>
                    <div style="max-height:190px;overflow:auto;border:1px solid var(--border-light);border-radius:10px;padding:8px;margin-bottom:10px;">
                        ${rows.slice(0, 200).map(f => `<div style="font-size:11px;color:var(--text-secondary);padding:2px 0;" dir="ltr">${esc(f.name)} <span style="color:var(--text-muted);">(${Math.round(f.size/1024)} کیلوبایت)</span></div>`).join('')}
                    </div>
                    <button type="button" class="mk-btn mk-btn--sm mk-btn--danger" onclick="deleteOrphanImages()">
                        ${MK_IC.trash} حذف همه (${rows.length} فایل)
                    </button>
                </div>`;

            window.__melkinoOrphans = rows.map(f => f.name);
        } catch (e) {
            box.innerHTML = '<div style="color:var(--danger);font-size:13px;">خطا در اسکن پوشه.</div>';
        }
    };

    window.deleteOrphanImages = async function () {
        const files = window.__melkinoOrphans || [];
        if (!files.length) return;
        if (!confirm(files.length + ' فایل برای همیشه حذف شود؟ این کار قابل بازگشت نیست.')) return;
        if (!confirm('تأیید دوباره: فایل‌ها از روی سرور پاک می‌شوند. ادامه بدهیم؟')) return;

        try {
            const data = await postJson('admin-images.php?action=delete_orphans', { files: files });
            alert(data.message || (data.success ? 'پاکسازی انجام شد.' : 'پاکسازی انجام نشد.'));
            window.__melkinoOrphans = [];
            scanOrphanImages();
        } catch (e) {
            alert('خطا در ارتباط با سرور.');
        }
    };

    document.addEventListener('click', async function (e) {
        const del = e.target.closest('[data-image-delete]');
        if (!del) return;
        if (!confirm('این تصویر از دیتابیس و از روی سرور حذف شود؟')) return;

        const btn = del;
        btn.disabled = true;
        btn.textContent = '⏳ در حال حذف...';

        try {
            const data = await postJson('admin-images.php?action=delete', { id: del.getAttribute('data-image-delete') });
            alert(data.message || (data.success ? 'حذف شد.' : 'حذف انجام نشد.'));
            loadAdminImages();
        } catch (err) {
            alert('خطا در ارتباط با سرور.');
            loadAdminImages();
        }
    });

    /* =====================================================
       اعلان‌ها (ارسال عمومی + مدیریت)
       ===================================================== */

    const notifTypeFa = {
        broadcast: 'عمومی',
        welcome: 'خوش‌آمد',
        match: 'تطبیق',
        property_match: 'تطبیق',
        ad_submitted: 'ثبت آگهی',
        ad_published: 'انتشار',
        ad_rejected: 'رد آگهی',
        ad_revision_approved: 'تأیید ویرایش',
        ad_revision_rejected: 'رد ویرایش',
        ad_status: 'وضعیت آگهی',
        request_submitted: 'ثبت درخواست',
        request_status: 'وضعیت درخواست',
        system: 'سیستمی'
    };

    window.loadAdminNotifications = async function () {
        loadNotifStats();
        loadBroadcasts();
        loadRecentNotifs();
        if (typeof window.loadAdminNotifEvents === 'function') {
            window.loadAdminNotifEvents();
        }
    };

    /* ==============================================
       رویدادهای اعلان سیستمی (راند ۲۰)
       ============================================== */
    const notifEvEsc = (x) => String(x == null ? '' : x)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    window.loadAdminNotifEvents = async function () {
        const box = document.getElementById('notifEventsContainer');
        if (!box) return;
        try {
            const data = await getJson('admin-notifications.php?action=events_get');
            if (!data || !data.success) {
                box.innerHTML = '<div class="admin-field-help">دریافت رویدادها ناموفق بود.</div>';
                return;
            }
            const note = document.getElementById('notifEventsMasterNote');
            if (note) {
                note.innerHTML = data.master_enabled
                    ? '(سوئیچ اصلی «اعلان‌ها» روشن است.)'
                    : ((window.MK_IC ? MK_IC.warn : '') + ' سوئیچ اصلی «اعلان‌ها» در تنظیمات عمومی خاموش است — هیچ اعلان خودکاری ارسال نمی‌شود!');
                note.style.color = data.master_enabled ? '' : '#dc2626';
            }
            box.innerHTML = (data.events || []).map(ev => `
                <div class="security-switch" style="margin-bottom:8px;">
                    <div>
                        <span>${ev.emoji} ${notifEvEsc(ev.title)} <small style="display:inline;opacity:.75">— ${notifEvEsc(ev.channel)}</small></span>
                        <small>${notifEvEsc(ev.desc)}<br>${MK_IC.pin} محل ارسال: ${notifEvEsc(ev.where)}</small>
                    </div>
                    ${ev.toggleable
                        ? `<input type="checkbox" data-notif-event="${ev.id}" ${ev.enabled ? 'checked' : ''} title="فعال/غیرفعال">`
                        : `<span style="font-size:10px;color:var(--text-secondary);white-space:nowrap;">${ev.id === 'sms_otp' ? 'مدیریت در کارت پیامک' : 'ارسال دستی'}</span>`}
                </div>`).join('');
        } catch (e) {
            box.innerHTML = '<div class="admin-field-help">دریافت رویدادها ناموفق بود.</div>';
        }
    };

    window.saveAdminNotifEvents = async function () {
        const status = document.getElementById('notifEventsStatus');
        const events = {};
        document.querySelectorAll('[data-notif-event]').forEach(el => {
            events[el.getAttribute('data-notif-event')] = el.checked;
        });
        try {
            if (status) status.textContent = 'در حال ذخیره…';
            const res = await postJson('admin-notifications.php?action=events_save', { events });
            if (status) {
                status.innerHTML = (typeof mkStatusHtml === 'function') ? mkStatusHtml(!!(res && res.success), (res && res.success) ? 'ذخیره شد.' : 'ذخیره ناموفق بود.') : ((res && res.success) ? 'ذخیره شد.' : 'ذخیره ناموفق بود.');
                setTimeout(() => { status.innerHTML = ''; }, 4000);
            }
        } catch (e) {
            if (status) status.innerHTML = (typeof mkStatusHtml === 'function') ? mkStatusHtml(false, 'ذخیره ناموفق بود.') : 'ذخیره ناموفق بود.';
        }
    };

    async function loadNotifStats() {
        const box = document.getElementById('notifStatsRow');
        if (!box) return;
        try {
            const data = await getJson('admin-notifications.php?action=stats');
            if (!data || !data.success) return;
            const s = data.stats || {};
            const card = (num, label) =>
                '<div style="border:1px solid var(--border);border-radius:12px;padding:10px;text-align:center;">' +
                '<div style="font-size:20px;font-weight:800;color:var(--text-primary);">' + esc(s[num] ?? 0) + '</div>' +
                '<div style="font-size:11px;color:var(--text-secondary);margin-top:4px;">' + label + '</div></div>';
            box.innerHTML =
                card('users', 'کاربران') +
                card('total', 'کل اعلان‌ها') +
                card('unread', 'خوانده‌نشده') +
                card('broadcasts', 'اعلان عمومی');
        } catch (e) { /* ignore */ }
    }

    async function loadBroadcasts() {
        const box = document.getElementById('broadcastsListContainer');
        if (!box) return;
        box.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-secondary);font-size:13px;">در حال بارگذاری...</div>';
        try {
            const data = await getJson('admin-notifications.php?action=broadcast_list');
            const rows = (data && data.broadcasts) || [];
            if (!rows.length) {
                box.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">هنوز اعلان عمومی ارسال نشده است.</div>';
                return;
            }
            box.innerHTML = rows.map(b =>
                '<div style="border:1px solid var(--border);border-radius:14px;padding:12px;margin-bottom:10px;">' +
                '<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">' +
                '<div style="min-width:0;flex:1;">' +
                '<div style="font-size:13px;font-weight:800;color:var(--text-primary);">' + MK_IC.megaphone + esc(b.title) + '</div>' +
                '<div style="font-size:12px;color:var(--text-secondary);margin-top:6px;line-height:1.9;">' + esc(b.message) + '</div>' +
                (b.url ? '<div style="font-size:11px;margin-top:4px;" dir="ltr"><a href="' + esc(b.url) + '" target="_blank" rel="noopener">' + esc(b.url) + '</a></div>' : '') +
                '<div style="font-size:11px;color:var(--text-muted);margin-top:6px;">' + esc(b.created_at) + ' · ارسال به ' + esc(b.sent_count) + ' کاربر · ' + esc(b.unread_count) + ' خوانده‌نشده</div>' +
                '</div>' +
                '<button type="button" class="btn-secondary" style="padding:5px 10px;font-size:11px;color:var(--danger);flex-shrink:0;" onclick="deleteBroadcast(' + Number(b.id) + ')">' + MK_IC.trash + ' حذف</button>' +
                '</div></div>'
            ).join('');
        } catch (e) {
            box.innerHTML = '<div style="color:var(--danger);font-size:13px;">خطا در بارگذاری.</div>';
        }
    }

    window.sendBroadcast = async function () {
        const t = document.getElementById('broadcastTitle');
        const m = document.getElementById('broadcastMessage');
        const u = document.getElementById('broadcastUrl');
        const title = t ? t.value.trim() : '';
        const message = m ? m.value.trim() : '';
        const url = u ? u.value.trim() : '';
        if (!title || !message) {
            setStatus('broadcastStatus', 'عنوان و متن اعلان الزامی است.', false);
            return;
        }
        if (!confirm('این اعلان برای همه کاربران ارسال شود؟')) return;
        setStatus('broadcastStatus', 'در حال ارسال...', null);
        try {
            const data = await postJson('admin-notifications.php?action=broadcast_send', { title, message, url });
            setStatus('broadcastStatus', data.message || '', data.success);
            if (data.success) {
                if (t) t.value = '';
                if (m) m.value = '';
                if (u) u.value = '';
                loadNotifStats();
                loadBroadcasts();
            }
        } catch (e) {
            setStatus('broadcastStatus', 'خطا در ارتباط با سرور.', false);
        }
    };

    window.deleteBroadcast = async function (id) {
        if (!confirm('این اعلان عمومی و همه نسخه‌هایش حذف شود؟')) return;
        try {
            const data = await postJson('admin-notifications.php?action=broadcast_delete', { id });
            alert(data.message || (data.success ? 'حذف شد.' : 'خطا.'));
            if (data.success) { loadNotifStats(); loadBroadcasts(); }
        } catch (e) {
            alert('خطا در ارتباط با سرور.');
        }
    };

    /* فهرست آخرین اعلان‌ها + نوار ابزار حذف گروهی/بر اساس نوع */
    let recentNotifsCache = [];
    let recentNotifFilterValue = '';

    const recentNotifTypeOptions = [
        ['', 'همهٔ انواع'],
        ['welcome', 'خوش‌آمد'],
        ['match', 'فایل مناسب (تطبیق)'],
        ['broadcast', 'اطلاعیه عمومی'],
        ['ad_submitted', 'ثبت آگهی'],
        ['ad_published', 'انتشار آگهی'],
        ['ad_rejected', 'رد آگهی'],
        ['ad_revision_approved', 'تأیید ویرایش مالک'],
        ['ad_revision_rejected', 'رد ویرایش مالک'],
        ['ad_status', 'وضعیت آگهی'],
        ['request_submitted', 'ثبت درخواست'],
        ['request_status', 'وضعیت درخواست'],
        ['system', 'سیستمی'],
    ];

    window.setRecentNotifFilter = function (value) {
        recentNotifFilterValue = String(value || '');
        renderRecentNotifs();
    };

    function renderRecentNotifs() {
        const box = document.getElementById('recentNotifsContainer');
        if (!box) return;
        const filter = recentNotifFilterValue;
        const rows = filter
            ? recentNotifsCache.filter(n => n.type === filter)
            : recentNotifsCache;

        if (!recentNotifsCache.length) {
            box.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">اعلانی وجود ندارد.</div>';
            return;
        }

        const selectHtml =
            '<select id="recentNotifTypeFilter" class="admin-input" style="width:auto;padding:6px 10px;font-size:12px;" onchange="setRecentNotifFilter(this.value)">' +
            recentNotifTypeOptions.map(o =>
                '<option value="' + esc(o[0]) + '"' + (o[0] === filter ? ' selected' : '') + '>' + esc(o[1]) + '</option>'
            ).join('') +
            '</select>';

        if (!rows.length) {
            box.innerHTML =
                '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;padding:10px;border:1px dashed var(--border);border-radius:12px;">' +
                selectHtml +
                '<button type="button" class="btn-secondary" style="padding:6px 12px;font-size:12px;color:var(--danger);" onclick="deleteNotifsByType()">' + MK_IC.trash + ' حذف همهٔ نوع «' + esc(notifTypeFa[filter] || filter) + '»</button>' +
                '<button type="button" class="btn-secondary" style="padding:6px 12px;font-size:12px;color:var(--danger);" onclick="deleteAllNotifs()">' + MK_IC.trash + '</button>' +
                '</div>' +
                '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px;">اعلانی از این نوع در ۵۰ اعلان اخیر نیست (اما ممکن است در کلِ جدول باشد؛ دکمهٔ حذف نوع همان‌ها را پاک می‌کند).</div>';
            return;
        }

        box.innerHTML =
            '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;padding:10px;border:1px dashed var(--border);border-radius:12px;">' +
            '<label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-secondary);cursor:pointer;">' +
            '<input type="checkbox" id="recentNotifSelectAll" style="width:16px;height:16px;accent-color:var(--primary);" onchange="toggleAllRecentNotifs(this.checked)"> انتخاب همه</label>' +
            selectHtml +
            '<button type="button" class="btn-secondary" style="padding:6px 12px;font-size:12px;color:var(--danger);" onclick="deleteSelectedNotifs()">' + MK_IC.trash + '</button>' +
            '<button type="button" class="btn-secondary" style="padding:6px 12px;font-size:12px;color:var(--danger);" onclick="deleteNotifsByType()">' + MK_IC.trash + ' حذف همهٔ نوع «' + esc(notifTypeFa[filter] || filter || '—') + '»</button>' +
            '<button type="button" class="btn-secondary" style="padding:6px 12px;font-size:12px;color:var(--danger);" onclick="deleteAllNotifs()">' + MK_IC.trash + '</button>' +
            '</div>' +
            rows.map(n => {
                const who = n.user_name || n.user_phone
                    ? esc(n.user_name || '') + (n.user_phone ? ' · ' + esc(n.user_phone) : '')
                    : (n.telegram_id ? 'تلگرام: ' + esc(n.telegram_id) : 'کاربر #' + esc(n.user_id || '?'));
                return '<div style="border:1px solid var(--border);border-radius:14px;padding:12px;margin-bottom:10px;' +
                    (Number(n.is_read) === 0 ? 'border-color:rgba(11,93,91,.35);' : '') + '">' +
                    '<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">' +
                    '<div style="min-width:0;flex:1;">' +
                    '<div style="display:flex;align-items:center;gap:8px;font-size:12px;">' +
                    '<input type="checkbox" class="recent-notif-check" data-id="' + Number(n.id) + '" style="width:16px;height:16px;accent-color:var(--primary);flex-shrink:0;">' +
                    '<span>' + (notifTypeFa[n.type] || esc(n.type)) +
                    ' <span style="color:var(--text-muted);">· ' + who + '</span>' +
                    (Number(n.is_read) === 0 ? ' <span style="color:var(--primary);font-weight:800;">· خوانده‌نشده</span>' : '') + '</span></div>' +
                    '<div style="font-size:13px;font-weight:800;color:var(--text-primary);margin-top:4px;">' + esc(n.title) + '</div>' +
                    '<div style="font-size:12px;color:var(--text-secondary);margin-top:4px;line-height:1.8;">' + esc((n.message || '').substring(0, 200)) + '</div>' +
                    '<div style="font-size:11px;color:var(--text-muted);margin-top:4px;">' + esc(n.created_at) + '</div>' +
                    '</div>' +
                    '<button type="button" class="btn-secondary" style="padding:5px 10px;font-size:11px;color:var(--danger);flex-shrink:0;" onclick="deleteAdminNotif(' + Number(n.id) + ')">🗑</button>' +
                    '</div></div>';
            }).join('');
    }

    async function loadRecentNotifs() {
        const box = document.getElementById('recentNotifsContainer');
        if (!box) return;
        box.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-secondary);font-size:13px;">در حال بارگذاری...</div>';
        try {
            const data = await getJson('admin-notifications.php?action=recent_list');
            recentNotifsCache = (data && data.notifications) || [];
            renderRecentNotifs();
        } catch (e) {
            box.innerHTML = '<div style="color:var(--danger);font-size:13px;">خطا در بارگذاری.</div>';
        }
    }

    window.toggleAllRecentNotifs = function (checked) {
        document.querySelectorAll('.recent-notif-check').forEach(c => { c.checked = !!checked; });
    };

    function selectedRecentNotifIds() {
        const ids = [];
        document.querySelectorAll('.recent-notif-check:checked').forEach(c => {
            const id = Number(c.getAttribute('data-id'));
            if (id > 0) ids.push(id);
        });
        return ids;
    }

    window.deleteSelectedNotifs = async function () {
        const ids = selectedRecentNotifIds();
        if (!ids.length) { alert('ابتدا یک یا چند اعلان را انتخاب کن.'); return; }
        if (!confirm(ids.length + ' اعلان انتخاب‌شده حذف شود؟')) return;
        try {
            const data = await postJson('admin-notifications.php?action=notif_delete_bulk', { ids });
            alert(data.message || (data.success ? 'حذف شد.' : 'خطا.'));
            if (data.success) { loadNotifStats(); loadRecentNotifs(); }
        } catch (e) { alert('خطا در ارتباط با سرور.'); }
    };

    window.deleteNotifsByType = async function () {
        const t = recentNotifFilterValue;
        if (!t) {
            alert('ابتدا از فهرست کنار دکمه، نوع اعلان را انتخاب کن (مثلاً خوش‌آمد یا فایل مناسب).');
            return;
        }
        const faName = (notifTypeFa[t] || t);
        if (!confirm('همهٔ اعلان‌های نوع «' + faName + '» (در کلِ جدول، نه فقط ۵۰ تای اخیر) حذف شود؟')) return;
        try {
            const data = await postJson('admin-notifications.php?action=notif_delete_filtered', { type: t });
            alert(data.message || (data.success ? 'حذف شد.' : 'خطا.'));
            if (data.success) { loadNotifStats(); loadRecentNotifs(); }
        } catch (e) { alert('خطا در ارتباط با سرور.'); }
    };

    window.deleteAllNotifs = async function () {
        if (!confirm('همهٔ اعلان‌های همهٔ کاربران حذف شود؟ این عمل برگشت‌پذیر نیست.')) return;
        if (!confirm('مطمئنی؟ تمام اعلان‌های کاربران برای همیشه پاک می‌شوند.')) return;
        try {
            const data = await postJson('admin-notifications.php?action=notif_delete_filtered', { type: 'all' });
            alert(data.message || (data.success ? 'حذف شد.' : 'خطا.'));
            if (data.success) { loadNotifStats(); loadRecentNotifs(); }
        } catch (e) { alert('خطا در ارتباط با سرور.'); }
    };

    window.deleteAdminNotif = async function (id) {
        if (!confirm('این اعلان حذف شود؟')) return;
        try {
            const data = await postJson('admin-notifications.php?action=notif_delete', { id });
            if (data.success) { loadNotifStats(); loadRecentNotifs(); }
            else alert(data.message || 'خطا.');
        } catch (e) {
            alert('خطا در ارتباط با سرور.');
        }
    };

    /* =====================================================
       بارگذاری اولیه
       ===================================================== */
    // بستن مودال تبلیغ با کلیک روی پس‌زمینه یا کلید Escape
    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'promotionModal') {
            window.closePromotionModal();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('promotionModal');
            if (modal && (modal.classList.contains('active') || modal.style.display === 'flex')) {
                window.closePromotionModal();
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        // بارگذاری تنبل: فقط تب باز شده داده می‌گیرد (سرعت لود پنل)
        if (typeof switchTab === 'function') {
            const current = document.querySelector('.tab-content.active');
            if (current && current.id === 'tab-bots') loadBotSettings();
            if (current && current.id === 'tab-promotions') loadPromotions();
            if (current && current.id === 'tab-backup') loadBackups();
            if (current && current.id === 'tab-notifications') loadAdminNotifications();
        }
    });

})();




/* ==============================================================
   تب «نمایش» — مدیریت میدان‌به‌میدان کارت‌های آگهی (راند ۲۱)
   هر فیلد آگهی: متن (چیپ) / حباب (پیل) / مخفی
   ============================================================== */
(function () {
    'use strict';

    const cdEsc = (x) => String(x == null ? '' : x)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    let CD_DEFS = null;
    let CD_SETTINGS = {};
    let CD_FILTER = '';

    const CD_SCOPE_LABELS = { home: 'صفحهٔ اصلی', list: 'فهرست آگهی‌ها', both: 'هر دو صفحه' };
    const CD_SPECIAL = { loan: ' loan', exchange: ' exchange', key_not_turned: ' key' };

    /* مقادیر نمونه برای پیش‌نمایش زنده */
    const CD_SAMPLE = {
        title: 'آپارتمان ۱۲۰ متری در مرکز شهر',
        code: 'AD-14050628-1234',
        price: '۵٬۰۰۰٬۰۰۰٬۰۰۰ تومان',
        loan_price_line: 'نقد: ۳٬۵۰۰٬۰۰۰٬۰۰۰ + وام: ۱٬۵۰۰٬۰۰۰٬۰۰۰ تومان',
        location: 'شاهرود، خیابان امام، کوچهٔ گل',
        date: '1405/06/28',
        transaction: 'خرید و فروش',
        property_type: 'آپارتمان',
        area: '۱۲۰ متر',
        rooms: '۳ اتاق',
        floor: 'طبقهٔ ۲',
        year: 'ساخت ۱۴۰۰',
        parking: 'پارکینگ',
        elevator: 'آسانسور',
        key_not_turned: 'کلید نخورده',
        loan: 'وام',
        exchange: 'مایل به معاوضه',
        tags: ['ویژه', 'بازسازی‌شده'],
        deed: 'طلق',
        deposit: 'رهن ۵۰۰٬۰۰۰٬۰۰۰ تومان',
        rent_monthly: 'اجاره ۱۵٬۰۰۰٬۰۰۰ تومان',
        full_rent: 'رهن کامل ۸۰۰٬۰۰٬۰۰۰ تومان'
    };

    const cdModeOf = (k) => {
        const v = CD_SETTINGS[k];
        if (v === true || v === 'on') return 'on';
        if (v === false || v === undefined || v === null) return 'off';
        return String(v);
    };
    const cdShowOf = (k) => cdModeOf(k) !== 'off';

    function cdSampleValue(k, def) {
        if (CD_SAMPLE[k] !== undefined) return CD_SAMPLE[k];
        if (k.indexOf('pd.') === 0) {
            const dk = k.slice(3);
            if (dk.indexOf('has_') === 0) return 'دارد';
            if (def && def.suffix) return '۱۲' + def.suffix;
        }
        return 'نمونهٔ ' + ((def && def.label) || k);
    }

    /* فهرست آیتم‌های مشخصات برای یک scope — آینهٔ رندر واقعی کارت */
    function cdItemsFor(scope) {
        const out = [];
        const items = (CD_DEFS && CD_DEFS.specs && CD_DEFS.specs.items) || {};
        Object.keys(items).forEach((k) => {
            const def = items[k] || {};
            const ds = def.scope || 'both';
            if (ds !== 'both' && ds !== scope) return;
            const mode = cdModeOf(k);
            if (mode === 'off' || mode === 'on') { if (mode !== 'text' && mode !== 'pill') return; }
            if (k === 'tags') {
                (CD_SAMPLE.tags || []).forEach((t) => out.push({ key: k, mode: mode, emoji: '', label: 'برچسب', value: t, cls: '' }));
                return;
            }
            out.push({ key: k, mode: mode, emoji: def.emoji || '', label: def.label || k, value: cdSampleValue(k, def), cls: CD_SPECIAL[k] || '' });
        });
        return out;
    }

    function cdCardHtml(scope, horizontal) {
        const mainItems = (CD_DEFS && CD_DEFS.main && CD_DEFS.main.items) || {};
        const show = (k) => {
            const def = mainItems[k];
            if (def) {
                const ds = def.scope || 'both';
                if (ds !== 'both' && ds !== scope) return false;
            }
            return cdShowOf(k);
        };
        const items = cdItemsFor(scope);
        const pills = items.filter((i) => i.mode === 'pill');
        const chips = items.filter((i) => i.mode === 'text');
        const pillsHtml = pills.map((i) =>
            '<span class="cdp-badge' + i.cls + '">' + cdEsc((i.emoji ? i.emoji + ' ' : '') + i.value) + '</span>').join('');
        const chipsHtml = chips.map((i) =>
            '<span class="cdp-chip">' + cdEsc((i.emoji ? i.emoji + ' ' : '') + (String(i.key).indexOf('pd.') === 0 ? i.label + ': ' : '') + i.value) + '</span>').join('');
        const titleHtml = show('title') ? '<div class="cdp-title">' + cdEsc(CD_SAMPLE.title) + '</div>' : '';
        const locHtml = show('location') ? '<div class="cdp-loc">📍 ' + cdEsc(CD_SAMPLE.location) + '</div>' : '';
        const chipsBlock = chipsHtml ? '<div class="cdp-details">' + chipsHtml + '</div>' : '';
        const pillsBlock = pillsHtml ? '<div class="cdp-badges"' + (horizontal ? ' style="padding:8px 0 0"' : '') + '>' + pillsHtml + '</div>' : '';
        const priceHtml = show('price') ? '<div class="cdp-price">' + cdEsc(CD_SAMPLE.price) + '</div>' : '';

        if (horizontal) {
            return '<div class="cdp-card cdp-h">' +
                '<div class="cdp-img">🖼️</div>' +
                '<div style="flex:1;min-width:0"><div class="cdp-body">' +
                (show('code') ? '<div style="font-size:10px;color:var(--text-secondary);margin-bottom:2px">' + cdEsc(CD_SAMPLE.code) + '</div>' : '') +
                titleHtml + locHtml + pillsBlock + chipsBlock + priceHtml +
                '</div></div></div>';
        }
        return '<div class="cdp-card">' +
            '<div class="cdp-img">🖼️ تصویر آگهی</div>' +
            pillsBlock +
            '<div class="cdp-body">' + titleHtml + locHtml + chipsBlock + priceHtml +
            (show('loan_price_line') ? '<div class="cdp-loanline">' + MK_IC.bank + ' ' + cdEsc(CD_SAMPLE.loan_price_line) + '</div>' : '') +
            '</div>' +
            '<div class="cdp-footer"><span>' + (show('date') ? cdEsc(CD_SAMPLE.date) : '') + '</span><span>🔍 مشاهده جزئیات</span></div>' +
            '</div>';
    }

    function renderPreview() {
        const box = document.getElementById('cardDisplayPreview');
        if (!box || !CD_DEFS) return;
        box.innerHTML =
            '<div class="cd-preview-col"><h4>کارت صفحهٔ اصلی</h4>' + cdCardHtml('home', false) + '</div>' +
            '<div class="cd-preview-col"><h4>کارت فهرست آگهی‌ها</h4>' + cdCardHtml('list', true) + '</div>';
    }

    function renderControls() {
        const box = document.getElementById('cardDisplayControls');
        if (!box || !CD_DEFS) return;
        const f = CD_FILTER.trim().toLowerCase();
        let html = '<input type="text" id="cdSearch" class="cd-search" placeholder="جست‌وجوی فیلد… (مثلاً: طبقه، سند، وام، متراژ)" value="' + cdEsc(CD_FILTER) + '">';
        Object.keys(CD_DEFS).forEach((gname) => {
            const group = CD_DEFS[gname] || {};
            const items = group.items || {};
            const isMain = gname === 'main';
            let rows = '';
            let shown = 0;
            Object.keys(items).forEach((k) => {
                const def = items[k] || {};
                const label = String(def.label || k);
                if (f && (label + ' ' + k).toLowerCase().indexOf(f) === -1) return;
                shown++;
                const cur = cdModeOf(k);
                const segs = isMain
                    ? [['on', 'نمایش'], ['off', 'مخفی']]
                    : [['text', 'متن'], ['pill', 'حباب'], ['off', 'مخفی']];
                const segHtml = segs.map((sg) =>
                    '<button type="button" class="' + (cur === sg[0] ? 'on' : '') + '" data-cd-key="' + cdEsc(k) + '" data-cd-mode="' + sg[0] + '">' + sg[1] + '</button>').join('');
                const scopeL = CD_SCOPE_LABELS[def.scope || 'both'] || '';
                const help = def.help || (k.indexOf('pd.') === 0
                    ? 'فیلد تخصصی فرم ثبت آگهی — فقط وقتی پر شده باشد روی کارت نمایش داده می‌شود.'
                    : '');
                rows += '<div class="cd-item">' +
                    '<div class="cd-item-info"><span>' + (def.emoji ? cdEsc(def.emoji) + ' ' : '') + cdEsc(label) +
                    ' <span class="cd-scope">' + cdEsc(scopeL) + '</span></span>' +
                    (help ? '<small>' + cdEsc(help) + '</small>' : '') +
                    '</div>' +
                    '<div class="cd-seg">' + segHtml + '</div>' +
                    '</div>';
            });
            if (!shown && f) return;
            html += '<div class="cd-group">' +
                '<div class="cd-group-title">' + cdEsc(group.label || gname) + ' <span class="cd-count">' + shown + ' فیلد</span></div>' +
                (group.help ? '<div class="cd-group-help">' + cdEsc(group.help) + '</div>' : '') +
                (rows || '<div class="admin-field-help">موردی یافت نشد.</div>') +
                '</div>';
        });
        box.innerHTML = html;

        const si = document.getElementById('cdSearch');
        if (si) {
            si.addEventListener('input', () => {
                CD_FILTER = si.value;
                renderControls();
                renderPreview();
                const n = document.getElementById('cdSearch');
                if (n) { n.focus(); try { n.setSelectionRange(n.value.length, n.value.length); } catch (e) {} }
            });
        }
        box.querySelectorAll('[data-cd-key]').forEach((b) => b.addEventListener('click', () => {
            const k = b.getAttribute('data-cd-key');
            const m = b.getAttribute('data-cd-mode');
            const isMain = !!(CD_DEFS.main && CD_DEFS.main.items && CD_DEFS.main.items[k]);
            CD_SETTINGS[k] = isMain ? (m === 'on') : m;
            renderControls();
            renderPreview();
        }));
    }

    function cdStatus(msg, ok) {
        const st = document.getElementById('cardDisplayStatus');
        if (!st) return;
        const clean = String(msg || '').replace(/^[\u2705\u274C\u2714\u26D4]\s*/u, '');
        st.innerHTML = (typeof mkStatusHtml === 'function') ? mkStatusHtml(ok, clean) : clean;
        st.style.color = ok === true ? 'var(--success)' : (ok === false ? 'var(--danger)' : 'var(--text-secondary)');
        st.dataset.mkMsg = clean;
        if (clean && ok !== undefined) setTimeout(() => { if (st.dataset.mkMsg === clean) st.innerHTML = ''; }, 4500);
    }

    window.melkinoSaveCardDisplay = async function () {
        if (!CD_DEFS) return;
        cdStatus('در حال ذخیره…');
        try {
            const payload = {};
            const all = Object.assign({}, (CD_DEFS.main || {}).items || {}, (CD_DEFS.specs || {}).items || {});
            Object.keys(all).forEach((k) => {
                const v = CD_SETTINGS[k];
                payload[k] = (v === undefined || v === null) ? all[k].default : v;
            });
            const res = await fetch('admin-card-display.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ settings: payload })
            });
            const data = await res.json();
            if (data && data.success) {
                CD_SETTINGS = payload;
                cdStatus('✅ تنظیمات نمایش ذخیره شد.', true);
            } else {
                cdStatus('❌ ذخیره ناموفق بود.' + ((data && data.message) ? ' ' + data.message : ''), false);
            }
        } catch (e) {
            cdStatus('❌ خطا در ذخیره: ' + (e.message || e), false);
        }
    };

    window.melkinoResetCardDisplay = function () {
        if (!CD_DEFS) return;
        const fresh = {};
        Object.keys(CD_DEFS).forEach((g) => {
            const items = (CD_DEFS[g] || {}).items || {};
            Object.keys(items).forEach((k) => { fresh[k] = items[k].default; });
        });
        CD_SETTINGS = fresh;
        renderControls();
        renderPreview();
        cdStatus('↺ به پیش‌فرض برگشت — برای اعمال، «ذخیره» را بزنید.');
    };

    window.melkinoInitDisplayTab = async function () {
        const box = document.getElementById('cardDisplayControls');
        try {
            const res = await fetch('admin-card-display.php?action=get', { cache: 'no-store' });
            const data = await res.json();
            if (!data || !data.success || !data.defs) throw new Error((data && data.message) || 'پاسخ نامعتبر');
            CD_DEFS = data.defs;
            CD_SETTINGS = data.settings || {};
            renderControls();
            renderPreview();
        } catch (e) {
            if (box) box.innerHTML = '<div class="admin-field-help">دریافت تنظیمات نمایش ناموفق بود: ' + cdEsc(e.message || e) + '</div>';
        }
    };
})();
