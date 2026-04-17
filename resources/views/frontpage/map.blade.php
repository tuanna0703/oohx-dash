@extends('frontpage.layouts.app', ['activeNav' => 'map', 'bodyClass' => 'page-map', 'hideFooter' => true])

@section('title', 'Bản đồ OOH/DOOH | OOHX')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<style>
#leaflet-map{position:absolute;inset:0;z-index:0}
#leaflet-map .leaflet-control-zoom{display:none}

/* ── Pin base ── */
.oohx-pin{background:none;border:none}

/* Tier 1: Dot (zoom < 14) */
.oohx-dot{width:12px;height:12px;border-radius:50%;border:2px solid rgba(255,255,255,.9);box-shadow:0 2px 8px rgba(0,0,0,.25);cursor:pointer;transition:transform 160ms}
.oohx-dot:hover{transform:scale(1.4)}
.oohx-dot--bl{background:var(--bl)}.oohx-dot--grn{background:var(--grn)}.oohx-dot--org{background:var(--org)}.oohx-dot--red{background:var(--red)}

/* Tier 2: Pill (zoom 14-15) — icon + network name */
.oohx-pin-box{border-radius:10px;padding:5px 10px;font-size:11px;font-weight:700;color:#fff;display:flex;align-items:center;gap:4px;white-space:nowrap;box-shadow:0 4px 16px rgba(0,0,0,.2);cursor:pointer;transition:transform 200ms var(--spring);max-width:160px;overflow:hidden}
.oohx-pin-box:hover{transform:scale(1.08)}
.oohx-pin-box svg{width:14px;height:14px;flex-shrink:0}
.oohx-pin-box span{overflow:hidden;text-overflow:ellipsis}
.oohx-pin-arrow{width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;margin:-1px auto 0}
/* Pill colors */
.oohx-pin--bl .oohx-pin-box{background:var(--bl)}.oohx-pin--bl .oohx-pin-arrow{border-top:8px solid var(--bl)}
.oohx-pin--grn .oohx-pin-box{background:var(--grn)}.oohx-pin--grn .oohx-pin-arrow{border-top:8px solid var(--grn)}
.oohx-pin--org .oohx-pin-box{background:var(--org)}.oohx-pin--org .oohx-pin-arrow{border-top:8px solid var(--org)}
.oohx-pin--red .oohx-pin-box{background:var(--red)}.oohx-pin--red .oohx-pin-arrow{border-top:8px solid var(--red)}

/* Tier 3: Rich (zoom >= 16) — logo + name + meta */
.oohx-rich{display:flex;align-items:center;gap:8px;background:#fff;border-radius:12px;padding:6px 10px 6px 6px;box-shadow:0 4px 20px rgba(0,0,0,.18);cursor:pointer;transition:transform 200ms var(--spring);border:1px solid var(--ln2);max-width:200px}
.oohx-rich:hover{transform:scale(1.05);box-shadow:0 6px 24px rgba(0,0,0,.22)}
.oohx-rich-logo{width:32px;height:32px;border-radius:8px;object-fit:contain;background:#fff;flex-shrink:0;border:1px solid var(--ln2);padding:2px}
.oohx-rich-initials{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.oohx-rich-initials--bl{background:rgba(42,79,246,.1);color:var(--bl)}
.oohx-rich-initials--grn{background:rgba(52,199,89,.1);color:#1a7d37}
.oohx-rich-initials--org{background:rgba(255,159,10,.1);color:#c93400}
.oohx-rich-initials--red{background:rgba(255,59,48,.1);color:#d70015}
.oohx-rich-body{min-width:0;flex:1}
.oohx-rich-name{font-size:12px;font-weight:700;color:var(--t1);line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.oohx-rich-meta{font-size:10px;color:var(--t4);line-height:1.2;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
/* Rich border accent by type */
.oohx-rich--bl{border-left:3px solid var(--bl)}.oohx-rich--grn{border-left:3px solid var(--grn)}
.oohx-rich--org{border-left:3px solid var(--org)}.oohx-rich--red{border-left:3px solid var(--red)}
/* Rich count badge (for sites with multiple screens) */
.oohx-rich-badge{min-width:22px;height:22px;padding:0 6px;border-radius:11px;background:var(--bl);color:#fff;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 6px rgba(42,79,246,.3);margin-left:auto}

/* ── Cluster circles ── */
.marker-cluster{background:none !important;border:none !important}
.oohx-cluster{display:flex;align-items:center;justify-content:center;border-radius:50%;color:#fff;font-weight:700;box-shadow:0 4px 20px rgba(42,79,246,.35);cursor:pointer;transition:transform 200ms ease}
.oohx-cluster:hover{transform:scale(1.12)}
.oohx-cluster--sm{width:44px;height:44px;font-size:13px;background:var(--bl);border:3px solid rgba(255,255,255,.9)}
.oohx-cluster--md{width:56px;height:56px;font-size:14px;background:var(--bl);border:3px solid rgba(255,255,255,.9)}
.oohx-cluster--lg{width:68px;height:68px;font-size:15px;background:#1a3af0;border:4px solid rgba(255,255,255,.9)}
.oohx-cluster--xl{width:80px;height:80px;font-size:16px;background:#0f28c7;border:4px solid rgba(255,255,255,.9)}
.oohx-cluster::after{content:'';position:absolute;inset:-6px;border-radius:50%;background:rgba(42,79,246,.15);z-index:-1;animation:cluster-pulse 2s ease-in-out infinite}
@keyframes cluster-pulse{0%,100%{transform:scale(1);opacity:.6}50%{transform:scale(1.15);opacity:.2}}

.mp-card.active{background:var(--bl-lt);border-color:rgba(42,79,246,.15)}
</style>
@endpush

@section('content')
<div class="map-layout">
    {{-- ── LEFT PANEL ── --}}
    <div class="map-panel" id="panel">
        <div class="mp-drag" onclick="togglePanel()"><div class="mp-drag-bar"></div></div>
        {{-- Smart Filter --}}
        @include('frontpage.partials.smart-filter', [
            'filters'          => $filters,
            'locationsByRegion' => $locationsByRegion,
            'target'           => 'map',
        ])

        <div class="mp-count">
            <span>Hiển thị <strong id="pin-count">{{ number_format($pins->count()) }}</strong> kết quả</span>
        </div>
        <div class="mp-list" id="pin-list">
            @php
                // Group pins by site for better hierarchy display
                $pinsBySite = $pins->filter(fn($p) => $p->site?->lat && $p->site?->lon)
                    ->groupBy('site_id')
                    ->sortByDesc(fn($group) => $group->count())
                    ->take(80);
            @endphp
            @foreach($pinsBySite as $siteId => $siteScreens)
                @php $site = $siteScreens->first()->site; @endphp
                @if($siteScreens->count() > 1)
                {{-- Site group header --}}
                <div class="mp-site-group">
                    <div class="mp-site-header" data-lat="{{ $site->lat }}" data-lng="{{ $site->lon }}">
                        <div class="mp-site-name">{{ $site->name }}</div>
                        <div class="mp-site-meta">
                            <span>{{ $site->city ?? '' }}</span>
                            <span class="mp-site-badge">{{ $siteScreens->count() }} screens</span>
                        </div>
                    </div>
                    @foreach($siteScreens->take(5) as $pin)
                    <div class="mp-card mp-card-nested" data-uuid="{{ $pin->slug ?? $pin->uuid }}" data-lat="{{ $site->lat }}" data-lng="{{ $site->lon }}">
                        <div class="mp-card-img">
                            <img src="{{ $pin->spec?->photo ?? 'https://placehold.co/200x200/F5F5F7/6E6E73?text=—' }}" loading="lazy" alt="{{ $pin->name }}">
                        </div>
                        <div class="mp-card-info">
                            <div class="mp-card-nm">{{ $pin->name }}</div>
                            <div class="mp-card-pr">{{ number_format($pin->inventory?->floor_cpm ?? 0, 0, ',', '.') }} ₫<span>/tháng</span></div>
                        </div>
                    </div>
                    @endforeach
                    @if($siteScreens->count() > 5)
                    <div class="mp-site-more">+{{ $siteScreens->count() - 5 }} screens khác</div>
                    @endif
                </div>
                @else
                {{-- Single screen, no group --}}
                @php $pin = $siteScreens->first(); @endphp
                <div class="mp-card" data-uuid="{{ $pin->slug ?? $pin->uuid }}" data-lat="{{ $site->lat }}" data-lng="{{ $site->lon }}">
                    <div class="mp-card-img">
                        <img src="{{ $pin->spec?->photo ?? 'https://placehold.co/200x200/F5F5F7/6E6E73?text=—' }}" loading="lazy" alt="{{ $pin->name }}">
                    </div>
                    <div class="mp-card-info">
                        <div class="mp-card-nm">{{ $pin->name }}</div>
                        <div class="mp-card-lc">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:11px;height:11px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
                            {{ $site->city ?? '' }}
                        </div>
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="mp-card-pr">{{ number_format($pin->inventory?->floor_cpm ?? 0, 0, ',', '.') }} ₫<span>/tháng</span></div>
                            <span class="badge b-grn" style="font-size:11px;padding:3px 8px">Còn trống</span>
                        </div>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- ── MAP AREA ── --}}
    <div class="map-area">
        <div id="leaflet-map"></div>

        <div class="map-toolbar" id="map-toolbar">
            {{-- Tất cả — always first, reset filter --}}
            <button class="mt-btn on" data-city="all">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg> Tất cả
            </button>
            {{-- Top cities — rendered dynamically by JS. Data embedded in script tag. --}}
            <div class="mt-top-chips" id="mt-top-chips"></div>
            {{-- More cities dropdown --}}
            <div class="mt-more" id="mt-more">
                <button class="mt-btn mt-more-trigger" id="mt-more-btn" onclick="document.getElementById('mt-dropdown').classList.toggle('open');event.stopPropagation()">
                    Khác <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;flex-shrink:0"><path d="M7 10l5 5 5-5z"/></svg>
                </button>
                <div class="mt-dropdown" id="mt-dropdown">
                    <div class="mt-dd-head">Chọn tỉnh/thành</div>
                    <div class="mt-dd-list" id="mt-dd-list"></div>
                </div>
            </div>
        </div>

        <div class="map-count-badge"><strong id="map-pin-count">{{ number_format($pinsJson->count()) }}</strong> vị trí đang hiển thị</div>

        <div class="map-controls">
            <button class="mc-btn" id="zoom-in">+</button>
            <button class="mc-btn" id="zoom-out">−</button>
            <button class="mc-btn" id="zoom-fit">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:16px;height:16px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            </button>
        </div>

        {{-- Popup (shared partial) --}}
        @include('frontpage.partials.map-popup', ['pfx' => ''])

        {{-- Mobile panel toggle --}}
        <button class="panel-toggle" onclick="togglePanel()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:16px;height:16px;flex-shrink:0"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
            Danh sách (<span id="toggle-count">{{ number_format($pins->count()) }}</span>)
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
@include('frontpage.partials.map-shared-js')
</script>
<script>
(function(){
    // ── Pin data from server ──
    var PINS = @json($pinsJson);
    var ALL_CITIES = @json(($filters['cities'] ?? collect())->values());

    // ── Init Leaflet ──
    var map = L.map('leaflet-map', {
        zoomControl: false,
        attributionControl: false,
    }).setView([16.0, 106.0], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
    }).addTo(map);

    L.control.attribution({position:'bottomleft',prefix:false})
        .addAttribution('© <a href="https://openstreetmap.org">OSM</a>')
        .addTo(map);

    // ── Shared utilities via OOHXMap (from map-shared-js partial) ──
    var pinColor = OOHXMap.pinColor;
    var fmtPrice = OOHXMap.fmtPrice;
    var fmtCount = OOHXMap.fmtCount;

    // ── Create MarkerClusterGroup ──
    var markers = [];
    var clusterGroup = L.markerClusterGroup({
        maxClusterRadius: function(zoom) {
            if (zoom <= 7) return 120;
            if (zoom <= 10) return 80;
            if (zoom <= 12) return 50;
            return 30;
        },
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        disableClusteringAtZoom: 16,
        chunkedLoading: true,
        chunkInterval: 100,
        chunkDelay: 20,
        iconCreateFunction: OOHXMap.createClusterIcon,
    });
    map.addLayer(clusterGroup);

    // Use shared rich marker builder
    var createPinIcon = function(pin) { return OOHXMap.createPinIcon(pin); };

    function renderPins(pinList) {
        clusterGroup.clearLayers();
        markers = [];

        var markerArray = [];
        pinList.forEach(function(pin) {
            var icon = createPinIcon(pin);
            var marker = L.marker([pin.lat, pin.lng], {icon: icon});
            marker._pinData = pin;
            marker.on('click', function() { selectPin(pin); });
            markers.push({marker: marker, pin: pin});
            markerArray.push(marker);
        });

        clusterGroup.addLayers(markerArray);

        // Update counts
        var siteCount = pinList.length;
        var screenCount = pinList.reduce(function(s, p){ return s + (p.screenCount || 1); }, 0);
        document.getElementById('pin-count').textContent = screenCount.toLocaleString('vi-VN');
        document.getElementById('map-pin-count').textContent = siteCount.toLocaleString('vi-VN');
        document.getElementById('toggle-count').textContent = screenCount.toLocaleString('vi-VN');
    }

    renderPins(PINS);

    // Fit bounds
    if (PINS.length > 0) {
        var bounds = L.latLngBounds(PINS.map(function(p){ return [p.lat, p.lng]; }));
        map.fitBounds(bounds, {padding: [40, 40], maxZoom: 14});
    }

    // ── Pin selection (popup + panel highlight) ──
    var activeCard = null;

    function selectPin(pin) {
        // Delegate popup rendering to shared module
        OOHXMap.showPopup(pin, '');
        var firstScreen = pin.screens && pin.screens.length ? pin.screens[0] : null;

        // Pan map
        map.panTo([pin.lat, pin.lng]);

        // Sync active city chip based on pin's city
        syncCityChip(pin.city || pin.addr || '');

        // Highlight panel card (match by siteId if available, else first screen id)
        if (activeCard) activeCard.classList.remove('active');
        var cardSelector = pin.siteId
            ? '.mp-site-header[data-site-id="' + pin.siteId + '"], .mp-card[data-uuid="' + (firstScreen ? firstScreen.id : pin.siteId) + '"]'
            : (firstScreen ? '.mp-card[data-uuid="' + firstScreen.id + '"]' : '');
        var card = cardSelector ? document.querySelector(cardSelector) : null;
        if (card) {
            card.classList.add('active');
            card.scrollIntoView({behavior:'smooth', block:'nearest'});
            activeCard = card;
        }
    }

    // Override OOHXMap.hidePopup to also clear panel highlight
    var _origHide = OOHXMap.hidePopup;
    OOHXMap.hidePopup = function(pfx) {
        _origHide(pfx);
        if (activeCard) { activeCard.classList.remove('active'); activeCard = null; }
    };

    // ── Sync active city chip based on pin's city ──
    // Match pin's city name → find matching city code from ALL_CITIES
    function normalizeCityName(s) {
        return (s || '').toLowerCase().split('>')[0].trim();
    }
    function syncCityChip(cityName) {
        var key = normalizeCityName(cityName);
        if (!key) return;
        // Find matching city from ALL_CITIES by name
        var match = null;
        for (var i = 0; i < ALL_CITIES.length; i++) {
            if (normalizeCityName(ALL_CITIES[i].name) === key) { match = ALL_CITIES[i]; break; }
        }
        if (!match) return;

        // Clear all active
        document.querySelectorAll('.mt-btn').forEach(function(b){ b.classList.remove('on'); });
        document.querySelectorAll('.mt-dd-item').forEach(function(b){ b.classList.remove('on'); });

        // Check if in top chips
        var topChip = document.querySelector('.mt-top-chips .mt-btn[data-city="' + match.code + '"]');
        if (topChip) {
            topChip.classList.add('on');
        } else {
            // In dropdown — highlight "Khác" + dropdown item
            var moreBtn = document.getElementById('mt-more-btn');
            if (moreBtn) moreBtn.classList.add('on');
            var ddItem = document.querySelector('.mt-dd-item[data-city="' + match.code + '"]');
            if (ddItem) ddItem.classList.add('on');
        }
    }

    // ── Panel card click → fly to pin ──
    // Site-level pins: map screen UUID → containing site pin
    function findPinByScreenId(uuid) {
        for (var i = 0; i < PINS.length; i++) {
            var pin = PINS[i];
            if (pin.screens && pin.screens.some(function(s){ return s.id === uuid; })) return pin;
        }
        return null;
    }
    document.querySelectorAll('.mp-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var uuid = card.dataset.uuid;
            var pin = findPinByScreenId(uuid);
            if (pin) {
                map.flyTo([pin.lat, pin.lng], 15, {duration: 0.8});
                selectPin(pin);
            }
        });
    });

    // ── Zoom controls ──
    document.getElementById('zoom-in').addEventListener('click', function(){ map.zoomIn(); });
    document.getElementById('zoom-out').addEventListener('click', function(){ map.zoomOut(); });
    document.getElementById('zoom-fit').addEventListener('click', function(){
        if (PINS.length > 0) {
            var bounds = L.latLngBounds(PINS.map(function(p){ return [p.lat, p.lng]; }));
            map.fitBounds(bounds, {padding: [40, 40], maxZoom: 14});
        }
    });

    // ═══ Dynamic City Toolbar ═══
    // Default: top N cities by screen count. User can pick from "Khác" → swaps into top.
    // State persisted in localStorage. Top N is responsive (breakpoint-based).

    var TOP_CITY_STORAGE = 'oohx_map_top_cities';
    // City code → approximate center lat/lng for flyTo (fallback to auto-fit bounds)
    var CITY_COORDS = {
        'hanoi': [21.0285, 105.8542, 12], 'hcm': [10.7769, 106.7009, 12],
        'danang': [16.0544, 108.2022, 13], 'haiphong': [20.8449, 106.6881, 13],
        'cantho': [10.0452, 105.7469, 13], 'thainguyen': [21.5928, 105.8442, 13],
        'baria-vungtau': [10.4114, 107.1364, 11], 'namdinh': [20.4388, 106.1621, 13],
        'thanhhoa': [19.8067, 105.7852, 13], 'dongnai': [10.9574, 106.8426, 12],
    };

    // Top N adaptive by breakpoint
    function getTopN() {
        var w = window.innerWidth;
        if (w >= 1280) return 5;
        if (w >= 1024) return 4;
        if (w >= 768) return 3;
        return 2;
    }

    // State: list of city codes in display order for top slots
    function loadTopCityCodes() {
        try {
            var saved = JSON.parse(localStorage.getItem(TOP_CITY_STORAGE) || 'null');
            if (Array.isArray(saved) && saved.length > 0) return saved;
        } catch (e) {}
        // Default: top N by count from ALL_CITIES
        return ALL_CITIES.slice(0, 5).map(function(c){ return c.code; });
    }
    function saveTopCityCodes(codes) {
        try { localStorage.setItem(TOP_CITY_STORAGE, JSON.stringify(codes)); } catch (e) {}
    }

    var topCityCodes = loadTopCityCodes();

    function findCity(code) {
        for (var i = 0; i < ALL_CITIES.length; i++) {
            if (ALL_CITIES[i].code === code) return ALL_CITIES[i];
        }
        return null;
    }

    function renderCityChips() {
        var topWrap = document.getElementById('mt-top-chips');
        var ddList = document.getElementById('mt-dd-list');
        if (!topWrap || !ddList) return;

        var topN = getTopN();
        var visible = topCityCodes.slice(0, topN);
        var visibleSet = {};
        visible.forEach(function(c){ visibleSet[c] = true; });

        // Render top chips
        topWrap.innerHTML = visible.map(function(code){
            var c = findCity(code);
            if (!c) return '';
            return '<button class="mt-btn" data-city="' + c.code + '">'
                + c.name + ' <span class="mt-btn-count">' + c.count.toLocaleString('vi-VN') + '</span>'
                + '</button>';
        }).join('');

        // Render dropdown (remaining cities not in top)
        ddList.innerHTML = ALL_CITIES.filter(function(c){ return !visibleSet[c.code]; })
            .map(function(c){
                return '<button class="mt-dd-item" data-city="' + c.code + '">'
                    + c.name + ' <span class="mt-dd-count">' + c.count.toLocaleString('vi-VN') + '</span>'
                    + '</button>';
            }).join('');

        // Bind click handlers
        topWrap.querySelectorAll('.mt-btn').forEach(function(btn){
            btn.addEventListener('click', function(){ selectCity(btn.dataset.city, false); });
        });
        ddList.querySelectorAll('.mt-dd-item').forEach(function(item){
            item.addEventListener('click', function(){
                document.getElementById('mt-dropdown').classList.remove('open');
                selectCity(item.dataset.city, true);
            });
        });
    }

    function selectCity(code, fromDropdown) {
        // If picked from dropdown → swap to top (position 1 after "Tất cả")
        if (fromDropdown) {
            topCityCodes = [code].concat(topCityCodes.filter(function(c){ return c !== code; }));
            // Keep max 10 in memory (rest re-populated from ALL_CITIES top)
            topCityCodes = topCityCodes.slice(0, 10);
            saveTopCityCodes(topCityCodes);
            renderCityChips();
        }

        // Clear active states
        document.querySelectorAll('.mt-btn').forEach(function(b){ b.classList.remove('on'); });
        document.querySelectorAll('.mt-dd-item').forEach(function(b){ b.classList.remove('on'); });

        if (code === 'all') {
            document.querySelector('.mt-btn[data-city="all"]').classList.add('on');
            renderPins(PINS);
            if (PINS.length > 0) {
                var bounds = L.latLngBounds(PINS.map(function(p){ return [p.lat, p.lng]; }));
                map.fitBounds(bounds, {padding:[40,40], maxZoom: 10});
            }
            return;
        }

        // Mark active chip
        var chip = document.querySelector('.mt-btn[data-city="' + code + '"]');
        if (chip) chip.classList.add('on');
        var ddItem = document.querySelector('.mt-dd-item[data-city="' + code + '"]');
        if (ddItem) ddItem.classList.add('on');

        // Filter pins
        var cityInfo = findCity(code);
        var cityName = cityInfo ? cityInfo.name : '';
        var filtered = PINS.filter(function(p){
            return p.city && p.city.toLowerCase().indexOf(cityName.toLowerCase()) >= 0;
        });
        renderPins(filtered);

        // Fly to
        var coords = CITY_COORDS[code];
        if (coords) {
            map.flyTo([coords[0], coords[1]], coords[2], {duration: 0.8});
        } else if (filtered.length > 0) {
            var bounds = L.latLngBounds(filtered.map(function(p){ return [p.lat, p.lng]; }));
            map.fitBounds(bounds, {padding:[40,40], maxZoom: 13});
        }
    }

    // "Tất cả" handler
    document.querySelector('.mt-btn[data-city="all"]').addEventListener('click', function(){
        selectCity('all', false);
    });

    // Initial render + re-render on resize (for responsive topN)
    renderCityChips();
    var resizeTimer;
    window.addEventListener('resize', function(){
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(renderCityChips, 150);
    });

    // Override syncCityChip (from selectPin) to work with dynamic chips
    window.selectMapCity = function(item) {
        selectCity(item.dataset.city, true);
    };

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        var dd = document.getElementById('mt-dropdown');
        if (dd && !document.getElementById('mt-more').contains(e.target)) {
            dd.classList.remove('open');
        }
    });

    // ── Panel toggle (mobile) ──
    window.togglePanel = function() {
        document.getElementById('panel').classList.toggle('open');
    };

    // ── Site group header click → fly to site ──
    document.querySelectorAll('.mp-site-header').forEach(function(header){
        header.addEventListener('click', function(){
            var lat = parseFloat(header.dataset.lat);
            var lng = parseFloat(header.dataset.lng);
            if (lat && lng) map.flyTo([lat, lng], 16, {duration: 0.8});
        });
    });

    // ── Smart Filter ──
    @include('frontpage.partials.smart-filter-js')
})();
</script>
@endpush
