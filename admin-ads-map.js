(function () {
    function loadLeaflet(cb) {
        if (window.L) { cb(); return; }
        var s = document.createElement('script');
        s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        s.onload = cb;
        document.head.appendChild(s);
    }
    var overview;
    function drawOverview() {
        var el = document.getElementById('adminAdsMap');
        if (!el || !window.L) return;
        if (overview) { overview.remove(); overview = null; }
        overview = L.map(el).setView([36.4182, 54.9763], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(overview);
        var list = window.adsData || (typeof adsData !== 'undefined' ? adsData : []);
        list.forEach(function (ad) {
            var lat = parseFloat(ad.latitude);
            var lng = parseFloat(ad.longitude);
            if (!isFinite(lat) || !isFinite(lng)) return;
            var m = L.marker([lat, lng]).addTo(overview);
            m.bindTooltip((ad.title || ad.id || '') + '');
            m.on('click', function () {
                if (typeof openAdEditModal === 'function') openAdEditModal(ad.id);
            });
        });
        setTimeout(function () { overview.invalidateSize(); }, 300);
    }
    window.mkInitAdminAdsMap = function () { loadLeaflet(drawOverview); };
    window.mkInitAdminEditMap = function (ad) {
        loadLeaflet(function () {
            var box = document.getElementById('editAdMap');
            var latEl = document.getElementById('editAdLat');
            var lngEl = document.getElementById('editAdLng');
            if (!box || !window.L) return;
            var lat = parseFloat((ad && ad.latitude) || (latEl && latEl.value) || 36.4182);
            var lng = parseFloat((ad && ad.longitude) || (lngEl && lngEl.value) || 54.9763);
            var has = ad && isFinite(parseFloat(ad.latitude)) && isFinite(parseFloat(ad.longitude));
            var map = L.map(box).setView([lat, lng], has ? 16 : 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
            var marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            function set(a, b) {
                marker.setLatLng([a, b]);
                if (latEl) latEl.value = String(a);
                if (lngEl) lngEl.value = String(b);
            }
            if (has) set(lat, lng);
            map.on('click', function (e) { set(e.latlng.lat, e.latlng.lng); });
            marker.on('dragend', function () {
                var p = marker.getLatLng();
                set(p.lat, p.lng);
            });
            setTimeout(function () { map.invalidateSize(); }, 250);
        });
    };
    if (document.getElementById('adminAdsMap')) {
        loadLeaflet(drawOverview);
    }
})();
