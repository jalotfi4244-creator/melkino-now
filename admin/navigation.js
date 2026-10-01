(function () {
    'use strict';

    var IA = [
        { id: 'dash', title: 'داشبورد', tab: 'dashboard', icon: 'grid' },
        {
            id: 'ads', title: 'آگهی‌ها', icon: 'home', children: [
                { tab: 'ads', title: 'همه آگهی‌ها' },
                { tab: 'ads', filter: 'pending', title: 'در انتظار بررسی' },
                { tab: 'ads', filter: 'published', title: 'منتشرشده' },
                { tab: 'ads', filter: 'sold', title: 'فروخته‌شده / غیرفعال' },
                { tab: 'images', title: 'تصاویر' },
                { tab: 'map', title: 'نقشه و انتشار' },
                { tab: 'revisions', title: 'ویرایش‌های کاربران' }
            ]
        },
        {
            id: 'customers', title: 'مشتریان و درخواست‌ها', icon: 'users', children: [
                { tab: 'users', title: 'کاربران' },
                { tab: 'requests', title: 'درخواست ملک' },
                { tab: 'visits', title: 'بازدیدها' },
                { tab: 'leads', title: 'لیدها' }
            ]
        },
        {
            id: 'comm', title: 'ارتباطات', icon: 'chat', children: [
                { tab: 'support', title: 'پشتیبانی' },
                { tab: 'notifications', title: 'اعلان‌ها' },
                { tab: 'comm', title: 'پیامک، قالب و کمپین' },
                { tab: 'assistant', title: 'اتوماسیون / دستیار' },
                { tab: 'bots', title: 'ربات و کانال' },
                { tab: 'contact', title: 'ارتباط با ما' }
            ]
        },
        {
            id: 'ui', title: 'ظاهر و محتوا', icon: 'palette', children: [
                // ادغام «تم و رنگ» با «استودیو طراحی» در یک صفحه با سوییچ بالا
                { tab: 'studio', title: 'تم و استودیو طراحی', merge: ['studio', 'theme'], mergeTitles: ['استودیو طراحی', 'تم و رنگ'] },
                { tab: 'display', title: 'نمایش کارت و فیلد' },
                { tab: 'forms', title: 'فرم‌ها' }
            ]
        },
        {
            id: 'mkt', title: 'بازاریابی', icon: 'megaphone', children: [
                { tab: 'promotions', title: 'تبلیغات' },
                { tab: 'onboarding', title: 'صفحات هدایت' }
            ]
        },
        {
            id: 'sys', title: 'سیستم', icon: 'gear', children: [
                { tab: 'global', title: 'تنظیمات عمومی' },
                { tab: 'password', title: 'امنیت و ادمین‌ها' },
                { tab: 'diagnostics', title: 'عیب‌یابی و پشتیبان', merge: ['diagnostics', 'backup'], mergeTitles: ['عیب‌یابی سیستم', 'پشتیبان‌گیری'] }
            ]
        }
    ];

    var ICO = {
        grid: '<svg class="mk-nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        home: '<svg class="mk-nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h14v-9.5"/></svg>',
        users: '<svg class="mk-nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3"/><path d="M3 19c.6-3 3-5 6-5s5.4 2 6 5"/><circle cx="17" cy="9" r="2.4"/><path d="M16 19c.4-2 1.8-3.4 3.8-4"/></svg>',
        chat: '<svg class="mk-nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v11H8l-4 3V5z"/></svg>',
        palette: '<svg class="mk-nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="8" cy="10" r="1"/><circle cx="12" cy="8" r="1"/><circle cx="16" cy="10" r="1"/></svg>',
        megaphone: '<svg class="mk-nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10v4h3l8 4V6L7 10H4z"/><path d="M19 10v4"/></svg>',
        gear: '<svg class="mk-nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.7 1 1.1 1.5 1.1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>'
    };

    var TITLES = {};
    IA.forEach(function (g) {
        if (g.tab) TITLES[g.tab] = { group: g.title, page: g.title };
        (g.children || []).forEach(function (c) {
            var key = c.filter ? (c.tab + ':' + c.filter) : c.tab;
            TITLES[key] = { group: g.title, page: c.title };
            if (!TITLES[c.tab]) TITLES[c.tab] = { group: g.title, page: c.title };
        });
    });

    /* ---------- تب‌های ادغام‌شده ----------
     * دو تب موجود (مثلاً «استودیو طراحی» + «تم») در منو یک ورودی می‌شوند و
     * بالای محتوا یک نوار سوییچ ساخته می‌شود. هیچ محتوایی بازنویسی یا حذف
     * نمی‌شود؛ فقط بین همان تب‌های موجود جابه‌جا می‌کند.
     */
    var MERGES = {};
    IA.forEach(function (g) {
        (g.children || []).forEach(function (c) {
            if (c.merge && c.merge.length > 1) {
                c.merge.forEach(function (tb, i) {
                    MERGES[tb] = { tabs: c.merge, titles: c.mergeTitles || c.merge, main: c.merge[0], title: c.title, index: i };
                });
            }
        });
    });

    function mountMergeBar(tab, retry) {
        var cfg = MERGES[tab];
        if (!cfg) return;
        var panel = document.getElementById('tab-' + tab);
        if (!panel) return;
        var old = panel.querySelector(':scope > .mk-merge-bar');
        if (old) old.remove();
        var bar = document.createElement('div');
        bar.className = 'mk-merge-bar';
        bar.innerHTML = '<span class="mk-merge-title">' + cfg.title + '</span>' +
            cfg.tabs.map(function (tb, i) {
                return '<button type="button" class="mk-merge-chip' + (tb === tab ? ' is-on' : '') +
                    '" data-merge-go="' + tb + '">' + (cfg.titles[i] || tb) + '</button>';
            }).join('');
        bar.addEventListener('click', function (e) {
            var b = e.target.closest('[data-merge-go]');
            if (!b) return;
            window.mkGoto(b.getAttribute('data-merge-go'), '');
        });
        panel.insertBefore(bar, panel.firstChild);

        // محتوای بعضی تب‌ها با تأخیر (lazy) لود می‌شود و innerHTML را
        // بازنویسی می‌کند؛ چند بار دوباره نصب می‌کنیم تا نوار گم نشود.
        if (!retry) {
            [300, 900, 2000].forEach(function (ms) {
                setTimeout(function () {
                    var pn = document.getElementById('tab-' + tab);
                    if (pn && pn.classList.contains('active') && !pn.querySelector(':scope > .mk-merge-bar')) {
                        mountMergeBar(tab, true);
                    }
                }, ms);
            });
        }
    }

    function groupOf(tab) {
        if (MERGES[tab]) tab = MERGES[tab].main;
        for (var i = 0; i < IA.length; i++) {
            var g = IA[i];
            if (g.tab === tab) return g.id;
            if ((g.children || []).some(function (c) { return c.tab === tab; })) return g.id;
        }
        return 'dash';
    }

    function svgCaret() {
        return '<svg class="mk-caret mk-nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>';
    }

    function renderNav() {
        var root = document.getElementById('mkAsideNav');
        if (!root) return;
        root.innerHTML = IA.map(function (g) {
            if (!g.children) {
                return '<button type="button" class="mk-nav-item" data-tab="' + g.tab + '" data-group="' + g.id + '">' +
                    (ICO[g.icon] || '') + '<span class="mk-nav-label">' + g.title + '</span></button>';
            }
            var kids = g.children.map(function (c) {
                var f = c.filter ? ' data-filter="' + c.filter + '"' : '';
                return '<button type="button" class="mk-nav-link" data-tab="' + c.tab + '"' + f + '>' +
                    '<span class="mk-nav-label">' + c.title + '</span></button>';
            }).join('');
            return '<div class="mk-nav-group" data-group="' + g.id + '">' +
                '<button type="button" class="mk-nav-group-btn" data-group="' + g.id + '">' +
                (ICO[g.icon] || '') + '<span class="mk-nav-label">' + g.title + '</span>' + svgCaret() +
                '</button><div class="mk-nav-children">' + kids + '</div></div>';
        }).join('');
    }

    function setOpenGroup(id, keepOthers) {
        document.querySelectorAll('#mkAsideNav .mk-nav-group').forEach(function (el) {
            if (el.getAttribute('data-group') === id) el.classList.add('is-open');
            else if (!keepOthers) el.classList.remove('is-open');
        });
    }

    function setHead(tab, filter) {
        var key = filter ? (tab + ':' + filter) : tab;
        var meta = TITLES[key] || TITLES[tab] || { group: 'مدیریت', page: tab };
        if (MERGES[tab]) {
            var mc = MERGES[tab];
            meta = { group: (TITLES[mc.main] || meta).group, page: mc.titles[mc.index] || mc.title };
        }
        var t = document.getElementById('mkPageTitle');
        var c = document.getElementById('mkPageCrumb');
        if (t) t.textContent = meta.page;
        if (c) c.textContent = meta.group + ' / ' + meta.page;
        document.title = meta.page + ' — ملکینو';
        var navTab = MERGES[tab] ? MERGES[tab].main : tab;
        document.querySelectorAll('#mkAsideNav .mk-nav-item, #mkAsideNav .mk-nav-link').forEach(function (b) {
            var on = b.getAttribute('data-tab') === navTab &&
                (filter ? b.getAttribute('data-filter') === filter : !b.getAttribute('data-filter') || b.getAttribute('data-filter') === 'all');
            if (!filter && b.getAttribute('data-tab') === navTab && !b.getAttribute('data-filter')) on = true;
            if (filter && b.getAttribute('data-tab') === navTab && b.getAttribute('data-filter') === filter) on = true;
            if (!filter && b.getAttribute('data-filter')) on = false;
            b.classList.toggle('is-on', !!on);
        });
        setOpenGroup(groupOf(tab), false);
    }

    function closeDrawer() {
        document.body.classList.remove('mk-nav-open');
        var bd = document.getElementById('mkNavBackdrop');
        if (bd) { bd.hidden = true; }
    }
    function openDrawer() {
        document.body.classList.add('mk-nav-open');
        var bd = document.getElementById('mkNavBackdrop');
        if (bd) { bd.hidden = false; }
    }

    function writeHash(tab, filter) {
        var path = '#/' + encodeURIComponent(tab);
        if (filter && filter !== 'all') path += '/' + encodeURIComponent(filter);
        if (location.hash !== path) {
            history.replaceState(null, '', path);
        }
    }

    function parseHash() {
        var h = String(location.hash || '').replace(/^#\/?/, '');
        if (!h) return null;
        var parts = h.split('/').filter(Boolean);
        return { tab: decodeURIComponent(parts[0] || ''), filter: decodeURIComponent(parts[1] || '') };
    }

    function applyFilter(filter) {
        if (!filter || typeof filterAds !== 'function') return;
        var btn = document.querySelector('#tab-ads .btn-filter[onclick*="' + filter + '"]');
        filterAds(filter, btn || null);
    }

    window.mkGoto = function (tab, filter) {
        if (tab === 'revisions') {
            var fab = document.getElementById('openUserRevisions');
            if (fab) fab.click();
            writeHash('ads', 'revisions');
            setHead('ads', 'revisions');
            closeDrawer();
            return;
        }
        if (typeof switchTab === 'function') switchTab(tab);
        if (tab === 'ads' && filter) {
            setTimeout(function () { applyFilter(filter); }, 40);
        }
        writeHash(tab, filter);
        setHead(tab, filter);
        mountMergeBar(tab);
        closeDrawer();
    };

    function wrapSwitchTab() {
        if (typeof window.switchTab !== 'function' || window.switchTab.__mkWrapped) return;
        var orig = window.switchTab;
        window.switchTab = function (tabId) {
            orig(tabId);
            if (tabId === 'leads') {
                var fr = document.getElementById('mkLeadsFrame');
                if (fr && !fr.getAttribute('src')) fr.setAttribute('src', fr.getAttribute('data-src'));
            }
            var h = parseHash();
            var filter = (h && h.tab === tabId) ? h.filter : '';
            writeHash(tabId, filter);
            setHead(tabId, filter);
            mountMergeBar(tabId);
            try { if (typeof revealAdminNavGroup === 'function') revealAdminNavGroup(adminNavGroupOf(tabId)); } catch (e) {}
        };
        window.switchTab.__mkWrapped = true;
    }

    function bind() {
        renderNav();
        wrapSwitchTab();
        var nav = document.getElementById('mkAsideNav');
        if (nav) {
            nav.addEventListener('click', function (e) {
                var groupBtn = e.target.closest('.mk-nav-group-btn');
                if (groupBtn) {
                    var gid = groupBtn.getAttribute('data-group');
                    var box = groupBtn.closest('.mk-nav-group');
                    var was = box && box.classList.contains('is-open');
                    setOpenGroup(was ? '' : gid, false);
                    if (document.body.classList.contains('mk-aside-collapsed')) {
                        document.body.classList.remove('mk-aside-collapsed');
                        try { localStorage.removeItem('mk_aside_collapsed'); } catch (err) {}
                    }
                    return;
                }
                var item = e.target.closest('[data-tab]');
                if (!item) return;
                window.mkGoto(item.getAttribute('data-tab'), item.getAttribute('data-filter') || '');
            });
        }
        var open = document.getElementById('mkNavOpen');
        var back = document.getElementById('mkNavBackdrop');
        var col = document.getElementById('mkAsideCollapse');
        if (open) open.addEventListener('click', openDrawer);
        if (back) back.addEventListener('click', closeDrawer);
        if (col) col.addEventListener('click', function () {
            document.body.classList.toggle('mk-aside-collapsed');
            try {
                localStorage.setItem('mk_aside_collapsed', document.body.classList.contains('mk-aside-collapsed') ? '1' : '0');
            } catch (e) {}
        });
        try {
            if (localStorage.getItem('mk_aside_collapsed') === '1' && window.innerWidth > 980) {
                document.body.classList.add('mk-aside-collapsed');
            }
        } catch (e) {}

        document.querySelectorAll('#mkAside [title]').forEach(function (el) {
            el.setAttribute('aria-label', el.getAttribute('title'));
        });

        var parsed = parseHash();
        if (parsed && parsed.tab) {
            window.mkGoto(parsed.tab, parsed.filter);
        } else {
            var active = document.querySelector('.tab-content.active');
            var id = active ? String(active.id || '').replace(/^tab-/, '') : 'dashboard';
            setHead(id, '');
            writeHash(id, '');
            mountMergeBar(id);
        }

        window.addEventListener('hashchange', function () {
            var p = parseHash();
            if (p && p.tab) window.mkGoto(p.tab, p.filter);
        });

        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeDrawer();
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
    else bind();
})();
