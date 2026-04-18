{{-- Shared map utilities (constants + helpers + marker + popup + city chips)
     Must be included INSIDE an existing <script> block.
     Expects global `L` (Leaflet) to be loaded.
     Usage:
       Before include: (nothing required — attaches to window.OOHXMap)
       After include:  Call OOHXMap.createPinIcon(pin), OOHXMap.selectPin(pin, ctx), etc.
--}}
(function(){
    if (window.OOHXMap) return; // Load once
    var M = {};

    // ── Constants ──────────────────────────────────────────────────────────────
    M.PIN_ICONS = {
        'mall':          '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M18.36 9l.6 3H5.04l.6-3h12.72M20 4H4v2h16V4zm0 3H4l-1 5v2h1v6h10v-6h4v6h2v-6h1v-2l-1-5zM6 18v-4h6v4H6z"/></svg>',
        'retail':        '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M18.36 9l.6 3H5.04l.6-3h12.72M20 4H4v2h16V4zm0 3H4l-1 5v2h1v6h10v-6h4v6h2v-6h1v-2l-1-5zM6 18v-4h6v4H6z"/></svg>',
        'transit':       '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M4 16c0 .88.39 1.67 1 2.22V20c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h8v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1.78c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z"/></svg>',
        'roadside':      '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
        'office':        '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>',
        'residential':   '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M17 11V3H7v4H3v14h8v-4h2v4h8V11h-4zM7 19H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm4 4H9v-2h2v2zm0-4H9V9h2v2zm0-4H9V5h2v2zm4 8h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm4 12h-2v-2h2v2zm0-4h-2v-2h2v2z"/></svg>',
        'food-beverage': '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z"/></svg>',
        'healthcare':    '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M19 3H5c-1.1 0-1.99.9-1.99 2L3 19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-1 11h-4v4h-4v-4H6v-4h4V6h4v4h4v4z"/></svg>',
        'education':     '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>',
        'hospitality':   '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2V10c0-2.21-1.79-4-4-4z"/></svg>',
        'entertainment': '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M18 3v2h-2V3H8v2H6V3H4v18h2v-2h2v2h8v-2h2v2h2V3h-2zM8 17H6v-2h2v2zm0-4H6v-2h2v2zm0-4H6V7h2v2zm10 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V7h2v2z"/></svg>',
        'sports-fitness':'<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M20.57 14.86L22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 2.71 2.71 4.14l1.43 1.43L2 7.71l1.43 1.43L2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22l1.43-1.43L16.29 22l2.14-2.14 1.43 1.43 1.43-1.43-1.43-1.43L22 16.29z"/></svg>',
    };
    M.DEFAULT_ICON = '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h7v2H8v2h8v-2h-2v-2h7c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12z"/></svg>';

    M.CITY_COORDS = {
        'hanoi': [21.0285, 105.8542, 12], 'hcm': [10.7769, 106.7009, 12],
        'danang': [16.0544, 108.2022, 13], 'haiphong': [20.8449, 106.6881, 13],
        'cantho': [10.0452, 105.7469, 13], 'thainguyen': [21.5928, 105.8442, 13],
        'baria-vungtau': [10.4114, 107.1364, 11], 'namdinh': [20.4388, 106.1621, 13],
        'thanhhoa': [19.8067, 105.7852, 13], 'dongnai': [10.9574, 106.8426, 12],
    };

    M.TOP_CITY_STORAGE = 'oohx_map_top_cities';

    // ── Helpers ────────────────────────────────────────────────────────────────
    M.pinColor = function(type) {
        if (!type) return 'bl';
        if (type === 'mall' || type === 'retail') return 'grn';
        if (type === 'transit') return 'org';
        if (type === 'healthcare' || type === 'education') return 'red';
        if (type === 'food-beverage' || type === 'entertainment') return 'org';
        return 'bl';
    };

    M.fmtPrice = function(p) {
        if (p >= 1e9) return (p / 1e9).toFixed(1).replace('.0','') + 'B';
        if (p >= 1e6) return Math.round(p / 1e6) + 'M';
        if (p > 0) return p.toLocaleString('vi-VN');
        return '—';
    };

    M.fmtCount = function(n) {
        if (n >= 1000) return (n / 1000).toFixed(1).replace('.0','') + 'K';
        return n.toString();
    };

    M.clusterSize = function(count) {
        if (count >= 100) return 'xl';
        if (count >= 50)  return 'lg';
        if (count >= 20)  return 'md';
        return 'sm';
    };

    // ── Marker icon builder (rich marker with logo + count badge) ─────────────
    M.createPinIcon = function(pin) {
        var col = M.pinColor(pin.type);
        var logoHtml = pin.ownerLogo
            ? '<img src="' + pin.ownerLogo + '" class="oohx-rich-logo" alt="">'
            : '<div class="oohx-rich-initials oohx-rich-initials--' + col + '">' + (pin.ownerInitials || '?') + '</div>';
        var primary = pin.siteName || pin.networkName || pin.ownerName || pin.name || '';
        var meta = (pin.screenCount && pin.screenCount > 1)
            ? (pin.screenCount + ' màn hình · ' + (pin.networkName || pin.typeLabel || ''))
            : (pin.networkName || pin.typeLabel || '');
        var badge = (pin.screenCount && pin.screenCount > 1)
            ? '<div class="oohx-rich-badge">' + pin.screenCount + '</div>'
            : '';
        return L.divIcon({
            className: 'oohx-pin oohx-pin--rich',
            html: '<div class="oohx-rich oohx-rich--' + col + '">'
                + logoHtml
                + '<div class="oohx-rich-body">'
                + '<div class="oohx-rich-name">' + primary + '</div>'
                + '<div class="oohx-rich-meta">' + meta + '</div>'
                + '</div>'
                + badge
                + '</div>',
            iconSize: [200, 48],
            iconAnchor: [16, 48],
        });
    };

    // ── Cluster icon builder ──────────────────────────────────────────────────
    M.createClusterIcon = function(cluster) {
        var count = cluster.getChildCount();
        var size = M.clusterSize(count);
        var px = size === 'xl' ? 80 : size === 'lg' ? 68 : size === 'md' ? 56 : 44;
        return L.divIcon({
            className: 'marker-cluster',
            html: '<div class="oohx-cluster oohx-cluster--' + size + '">' + M.fmtCount(count) + '</div>',
            iconSize: L.point(px, px),
        });
    };

    // ── Popup show/hide (expects a popup element with given prefix IDs) ──────
    // pfx: ID prefix for popup elements, e.g. '' → #popup, 'hp-' → #hp-popup
    M.showPopup = function(pin, pfx) {
        pfx = pfx || '';
        var popup = document.getElementById(pfx + 'popup');
        if (!popup) return;
        var isMulti = (pin.screenCount || 1) > 1;
        var firstScreen = pin.screens && pin.screens.length ? pin.screens[0] : null;

        // Photo
        var photo = (firstScreen && firstScreen.photo) || pin.photo || 'https://placehold.co/600x400/F5F5F7/6E6E73?text=No+Photo';
        var imgEl = document.getElementById(pfx + 'popup-img');
        if (imgEl) imgEl.src = photo;

        // Breadcrumb (network name)
        var bcEl = document.getElementById(pfx + 'popup-bc');
        if (bcEl) bcEl.innerHTML = pin.networkName || '';

        // Name (site name or fallback)
        var nameEl = document.getElementById(pfx + 'popup-name');
        if (nameEl) nameEl.textContent = pin.siteName || pin.name || '';

        // City
        var cityEl = document.getElementById(pfx + 'popup-city');
        if (cityEl) cityEl.textContent = pin.city || pin.addr || '';

        // Specs: owner · venue type · size/count
        var specParts = [];
        if (pin.ownerName) specParts.push(pin.ownerName);
        if (pin.typeLabel) specParts.push(pin.typeLabel);
        if (!isMulti && firstScreen && firstScreen.size) specParts.push(firstScreen.size);
        if (isMulti) specParts.push(pin.screenCount + ' màn hình');
        var specsEl = document.getElementById(pfx + 'popup-specs');
        if (specsEl) specsEl.textContent = specParts.join(' · ');

        // Product badge (single mode only)
        var prodEl = document.getElementById(pfx + 'popup-product');
        if (prodEl) {
            if (!isMulti && firstScreen && firstScreen.product) {
                var p = firstScreen.product;
                var txt = p.name + ' (' + p.total_units + ' MH)';
                if (p.can_buy_individual) txt += ' · Mua lẻ được';
                var txtEl = document.getElementById(pfx + 'popup-product-text');
                var linkEl = document.getElementById(pfx + 'popup-product-link');
                if (txtEl) txtEl.textContent = txt;
                if (linkEl) linkEl.href = '/products/' + p.slug;
                prodEl.style.display = '';
            } else {
                prodEl.style.display = 'none';
            }
        }

        // Mode switch: single vs multi
        var footSingle = document.getElementById(pfx + 'popup-foot-single');
        var listEl = document.getElementById(pfx + 'popup-list');

        if (isMulti) {
            if (footSingle) footSingle.style.display = 'none';
            if (listEl) {
                listEl.style.display = '';
                var titleEl = document.getElementById(pfx + 'popup-list-title');
                if (titleEl) titleEl.textContent = pin.screenCount + ' màn hình tại đây';
                var bodyHtml = pin.screens.map(function(s) {
                    var unit = s.priceUnit || 'tháng';
                    var priceHtml = (s.price && s.price > 0)
                        ? '<div class="mpop-list-price">' + M.fmtPrice(s.price) + '₫<span>/' + unit + '</span></div>'
                        : '<div class="mpop-list-price mpop-list-price-quote">Liên hệ</div>';
                    return '<a href="/explore/' + s.id + '" class="mpop-list-item">'
                        + '<div class="mpop-list-thumb">' + (s.photo ? '<img src="' + s.photo + '" alt="" loading="lazy">' : '') + '</div>'
                        + '<div class="mpop-list-info">'
                        + '<div class="mpop-list-name">' + s.name + '</div>'
                        + '<div class="mpop-list-meta">' + (s.size ? s.size + ' · ' : '') + (s.typeLabel || '') + '</div>'
                        + '</div>'
                        + priceHtml
                        + '<svg viewBox="0 0 24 24" fill="var(--t4)" class="mpop-list-arr"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>'
                        + '</a>';
                }).join('');
                var listBody = document.getElementById(pfx + 'popup-list-body');
                if (listBody) listBody.innerHTML = bodyHtml;
            }
        } else if (firstScreen) {
            if (footSingle) footSingle.style.display = '';
            if (listEl) listEl.style.display = 'none';
            var priceEl = document.getElementById(pfx + 'popup-price');
            var unitEl = document.getElementById(pfx + 'popup-price-unit');
            var quoteEl = document.getElementById(pfx + 'popup-price-quote');
            var linkEl = document.getElementById(pfx + 'popup-link');
            var hasPrice = firstScreen.price && firstScreen.price > 0;
            if (priceEl) { priceEl.textContent = hasPrice ? M.fmtPrice(firstScreen.price) + ' ₫' : ''; priceEl.style.display = hasPrice ? '' : 'none'; }
            if (unitEl)  { unitEl.textContent  = hasPrice ? '/ ' + (firstScreen.priceUnit || 'tháng') : ''; unitEl.style.display = hasPrice ? '' : 'none'; }
            if (quoteEl) { quoteEl.style.display = hasPrice ? 'none' : ''; }
            if (linkEl) linkEl.href = '/explore/' + firstScreen.id;
        }

        popup.style.display = 'block';
    };

    M.hidePopup = function(pfx) {
        pfx = pfx || '';
        var popup = document.getElementById(pfx + 'popup');
        if (popup) popup.style.display = 'none';
    };

    // ── City chips (dynamic top N + localStorage) ─────────────────────────────
    M.loadTopCityCodes = function(allCities) {
        try {
            var saved = JSON.parse(localStorage.getItem(M.TOP_CITY_STORAGE) || 'null');
            if (Array.isArray(saved) && saved.length > 0) return saved;
        } catch (e) {}
        return (allCities || []).slice(0, 5).map(function(c){ return c.code; });
    };

    M.saveTopCityCodes = function(codes) {
        try { localStorage.setItem(M.TOP_CITY_STORAGE, JSON.stringify(codes)); } catch (e) {}
    };

    M.findCity = function(code, allCities) {
        for (var i = 0; i < (allCities || []).length; i++) {
            if (allCities[i].code === code) return allCities[i];
        }
        return null;
    };

    window.OOHXMap = M;
})();
