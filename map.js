(function () {
    var cfg = window.MELKINO_MAP || {};
    if (!cfg.enabled) {
        var el = document.getElementById('mkMapCanvas');
        if (el) el.innerHTML = '<p style="padding:40px;text-align:center">نقشه املاک فعلاً غیرفعال است.</p>';
        return;
    }
    if (!window.L) return;
    var map = L.map('mkMapCanvas').setView([cfg.center_lat || 36.4182, cfg.center_lng || 54.9763], 13);
    L.tileLayer(cfg.tile_url || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: cfg.tile_attr || '© OSM',
        maxZoom: 19
    }).addTo(map);
    var layer = L.layerGroup();
    map.addLayer(layer);
    var itemsById = {};
    var markersById = {};

    // ---- مارکر طراحی‌پذیر (شکل/رنگ/اندازه از استودیو طراحی) ----
    var THEME = window.MELKINO_MAP_THEME || {};
    var MK = THEME.marker || {};
    var MCARD = THEME.mapCard || {};
    var THEME_COLORS = THEME.colors || {};

    function markerLabel(it) {
        if (MK.label === 'price') return it.price || '';
        if (MK.label === 'type') return it.type || '';
        return '';
    }

    var TYPE_COLORS = (cfg.marker_colors || {});
    var VIP_COLOR = MK.vipColor || '#D4AF37';

    function pinColor(it) {
        if (it.is_vip) return VIP_COLOR;
        var c = TYPE_COLORS[String(it.type || '').trim()];
        if (!c) {
            // تطابق نرم: «ویلایی/ویلا» و کلیدهای مشابه
            var keys = Object.keys(TYPE_COLORS);
            for (var i = 0; i < keys.length; i++) {
                if (keys[i] && String(it.type || '').indexOf(keys[i]) !== -1) { c = TYPE_COLORS[keys[i]]; break; }
            }
        }
        return c || MK.color || it.color || THEME_COLORS.primary || '#0e7c6e';
    }

    function pinIcon(it) {
        var color = pinColor(it);

        // اگر هِلپر مشترک لود نشده بود، همان پین قدیمی نمایش داده می‌شود
        if (!window.MelkinoMarker) {
            return L.divIcon({
                className: 'mk-pin-wrap',
                html: '<span class="mk-pin" style="background:' + color + '"></span>',
                iconSize: [28, 36], iconAnchor: [14, 34], popupAnchor: [0, -28]
            });
        }
        var m = window.MelkinoMarker.metrics(MK.size);
        return L.divIcon({
            className: 'mk-pin-wrap',
            html: window.MelkinoMarker.html({
                shape: MK.shape || 'pin',
                color: color,
                stroke: MK.stroke || '#FFFFFF',
                size: MK.size || 34,
                shadow: MK.shadow !== false,
                pulse: !!MK.pulse,
                label: markerLabel(it)
            }),
            iconSize: m.size,
            iconAnchor: m.anchor,
            popupAnchor: m.popup
        });
    }

    var txFilter = '';
    var ptFilter = '';

    function qs() {
        var b = map.getBounds();
        var p = new URLSearchParams();
        p.set('action', 'properties');
        p.set('north', b.getNorth());
        p.set('south', b.getSouth());
        p.set('east', b.getEast());
        p.set('west', b.getWest());
        if (txFilter) p.set('transaction_type', txFilter);
        if (ptFilter) p.set('property_type', ptFilter);
        return p.toString();
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function amenIcon(on, label, svg) {
        return '<span class="mk-amen' + (on ? ' is-on' : '') + '" title="' + label + (on ? '' : ' ندارد') + '">' + svg + '<i>' + label + '</i></span>';
    }
    function sheetHtml(it) {
        var isApt = String(it.type || '').indexOf('آپارتمان') !== -1;
        var icons = '';
        if (isApt) {
            icons = '<div class="mk-amen-row">' +
                amenIcon(!!it.has_parking, 'پارکینگ', '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M10 16V8h3a2.5 2.5 0 0 1 0 5h-3"/></svg>') +
                amenIcon(!!it.has_elevator, 'آسانسور', '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="m9 10 1.5-2L12 10M15 14l-1.5 2L12 14"/></svg>') +
                amenIcon(!!it.has_storage, 'انباری', '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16v13H4z"/><path d="M4 7 12 3l8 4"/><path d="M12 7v13"/></svg>') +
                '</div>';
        }
        var areaTxt = it.area ? (String(it.area).replace(/\D/g, '') || it.area) : '';
        var areaLine = areaTxt ? esc(areaTxt) + ' متراژ' : '';
        var roomLine = it.rooms ? (esc(String(it.rooms)) + ' اتاق') : '';
        var line3 = [areaLine, roomLine].filter(Boolean).join(' · ');
        return '<button type="button" class="mk-map-sheet-close" id="mkMapSheetClose">بستن</button>' +
            '<div class="mk-map-sheet-body">' +
            (it.thumbnail ? '<img class="mk-map-thumb" src="' + it.thumbnail + '" alt="">' : '') +
            '<div class="mk-map-sheet-copy">' +
            '<span class="mk-sheet-tx">' + esc(it.transaction_type || '—') + (it.is_vip ? ' ⭐' : '') + '</span>' +
            '<strong class="mk-sheet-title">' + esc(it.title || '') + '</strong>' +
            '<p class="mk-sheet-meta">' + line3 + '</p>' +
            icons +
            '<p class="mk-sheet-price">' + (it.price ? esc(it.price) + ' <small>تومان</small>' : '') + '</p>' +
            '<a class="mk-map-btn gold" href="property-details.php?id=' + encodeURIComponent(it.id) + '">' +
            esc(MCARD.buttonText || 'مشاهده فایل') + '</a></div></div>';
    }

    var openItemId = null;
    function closeSheet() {
        var sh = document.getElementById('mkMapSheet');
        if (sh) sh.hidden = true;
        openItemId = null;
    }
    function openItem(id) {
        var it = itemsById[id];
        if (!it) return;
        var sh = document.getElementById('mkMapSheet');
        // کلیک دوباره روی همان مارکر → بستن کارت
        if (openItemId === id && sh && !sh.hidden) {
            closeSheet();
            return;
        }
        openItemId = id;
        sh.setAttribute('data-pos', MCARD.pos || 'bottom');
        sh.setAttribute('data-skin', MCARD.skin || 'classic');
        sh.hidden = false;
        sh.innerHTML = sheetHtml(it);
        var closer = document.getElementById('mkMapSheetClose');
        if (closer) closer.onclick = closeSheet;
        var m = markersById[id];
        if (m && m.getLatLng) map.panTo(m.getLatLng());
    }

    function render(items) {
        layer.clearLayers();
        itemsById = {};
        markersById = {};
        items.forEach(function (it) {
            itemsById[it.id] = it;
            var loc = it.public_location;
            if (!loc || !cfg.show_markers) return;
            var mk = L.marker([loc.latitude, loc.longitude], { icon: pinIcon(it) });
            mk.on('click', function () { openItem(it.id); });
            layer.addLayer(mk);
            markersById[it.id] = mk;
        });
    }

    function load() {
        fetch('map-api.php?' + qs(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.disabled) {
                    document.getElementById('mkMapCanvas').innerHTML = '<p style="padding:40px;text-align:center">نقشه غیرفعال است.</p>';
                    return;
                }
                render((d && d.items) || []);
            })
            .catch(function () {});
    }

    map.on('click', function () { closeSheet(); });

    var t = null;
    map.on('moveend', function () {
        clearTimeout(t);
        t = setTimeout(load, 250);
    });

    function bindChips(root, attr, apply) {
        if (!root) return;
        root.addEventListener('click', function (e) {
            var b = e.target.closest('[' + attr + ']');
            if (!b) return;
            root.querySelectorAll('.mk-chip').forEach(function (x) { x.classList.remove('is-on'); });
            b.classList.add('is-on');
            apply(b.getAttribute(attr) || '');
            load();
        });
    }
    bindChips(document.getElementById('mkMapTx'), 'data-tx', function (v) { txFilter = v; });
    bindChips(document.getElementById('mkMapTypes'), 'data-pt', function (v) {
        ptFilter = v;
        var sm = document.getElementById('mkMapTypesSummary');
        if (sm) sm.textContent = 'نوع ملک: ' + (v || 'همه');
        var box = document.getElementById('mkMapTypes');
        if (box) box.open = false;
    });

    load();
})();
