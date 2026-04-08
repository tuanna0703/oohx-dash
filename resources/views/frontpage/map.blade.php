@extends('frontpage.layouts.app', ['activeNav' => 'map', 'bodyClass' => 'page-map', 'hideFooter' => true])

@section('title', 'Bản đồ OOH/DOOH | OOHX')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>
#leaflet-map{position:absolute;inset:0;z-index:0}
#leaflet-map .leaflet-control-zoom{display:none}
.oohx-pin{background:none;border:none}
.oohx-pin-box{border-radius:10px;padding:5px 10px;font-size:12px;font-weight:700;color:#fff;display:flex;align-items:center;gap:4px;white-space:nowrap;box-shadow:0 4px 16px rgba(0,0,0,.2);cursor:pointer;transition:transform 200ms var(--spring)}
.oohx-pin-box:hover{transform:scale(1.1)}
.oohx-pin-arrow{width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;margin:-1px auto 0}
.oohx-pin--bl .oohx-pin-box{background:var(--bl)}.oohx-pin--bl .oohx-pin-arrow{border-top:8px solid var(--bl)}
.oohx-pin--grn .oohx-pin-box{background:var(--grn)}.oohx-pin--grn .oohx-pin-arrow{border-top:8px solid var(--grn)}
.oohx-pin--org .oohx-pin-box{background:var(--org)}.oohx-pin--org .oohx-pin-arrow{border-top:8px solid var(--org)}
.oohx-pin--red .oohx-pin-box{background:var(--red)}.oohx-pin--red .oohx-pin-arrow{border-top:8px solid var(--red)}
.mp-card.active{background:var(--bl-lt);border-color:rgba(42,79,246,.15)}
</style>
@endpush

@section('content')
<div class="map-layout">
    {{-- ── LEFT PANEL ── --}}
    <div class="map-panel" id="panel">
        <div class="mp-head">
            <div class="mp-search">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t4)" style="width:16px;height:16px;flex-shrink:0"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input placeholder="Tìm khu vực, loại biển…" id="map-search">
                <button class="btn btn-p btn-xs" style="border-radius:6px" onclick="searchMap()">OK</button>
            </div>
            <div class="mp-chips">
                <div class="mpc-chip on" data-type="all">Tất cả</div>
                @foreach(($filters['formats'] ?? collect())->take(3) as $f)
                <div class="mpc-chip" data-type="{{ $f['type'] }}">{{ $f['label'] }}</div>
                @endforeach
                <div class="mpc-chip" data-type="available">Available</div>
            </div>
        </div>
        <div class="mp-count">Hiển thị <strong id="pin-count">{{ number_format($pins->count()) }}</strong> kết quả</div>
        <div class="mp-list" id="pin-list">
            @foreach($pins->take(50) as $pin)
            @if($pin->site?->lat && $pin->site?->lon)
            <div class="mp-card" data-uuid="{{ $pin->uuid }}" data-lat="{{ $pin->site->lat }}" data-lng="{{ $pin->site->lon }}">
                <div class="mp-card-img">
                    <img src="{{ $pin->spec?->photo_url ?? 'https://placehold.co/200x200/F5F5F7/6E6E73?text=No+Photo' }}" loading="lazy" alt="{{ $pin->name }}">
                </div>
                <div>
                    <div class="mp-card-nm">{{ $pin->name }}</div>
                    <div class="mp-card-lc">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:11px;height:11px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
                        {{ $pin->site?->city ?? '' }}
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <div class="mp-card-pr">{{ number_format($pin->inventory?->floor_cpm ?? 0, 0, ',', '.') }} ₫<span>/tháng</span></div>
                        <span class="badge b-grn" style="font-size:11px;padding:3px 8px">Available</span>
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

        <div class="map-toolbar">
            <button class="mt-btn on" data-city="hanoi" data-lat="21.0285" data-lng="105.8542" data-zoom="12">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:14px;height:14px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> Hà Nội
            </button>
            <button class="mt-btn" data-city="hcm" data-lat="10.7769" data-lng="106.7009" data-zoom="12">TP.HCM</button>
            <button class="mt-btn" data-city="danang" data-lat="16.0544" data-lng="108.2022" data-zoom="13">Đà Nẵng</button>
            <button class="mt-btn" data-city="all" data-lat="16.0" data-lng="106.0" data-zoom="6">Tất cả</button>
        </div>

        <div class="map-count-badge"><strong id="map-pin-count">{{ number_format($pins->count()) }}</strong> vị trí đang hiển thị</div>

        <div class="map-controls">
            <button class="mc-btn" id="zoom-in">+</button>
            <button class="mc-btn" id="zoom-out">−</button>
            <button class="mc-btn" id="zoom-fit">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:16px;height:16px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            </button>
        </div>

        {{-- Popup --}}
        <div class="map-popup" id="popup" style="display:none">
            <div class="mpop-close" onclick="hidePopup()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:14px;height:14px;flex-shrink:0"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </div>
            <div class="mpop-img"><img id="popup-img" src="" alt=""></div>
            <div class="mpop-body">
                <div class="mpop-name" id="popup-name"></div>
                <div class="mpop-meta" id="popup-meta">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:12px;height:12px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    <span id="popup-city"></span>
                </div>
                <span class="badge b-grn" style="display:inline-flex;margin-bottom:8px">Available</span>
                <div class="mpop-price" id="popup-price"></div>
                <div class="mpop-actions">
                    <a href="#" id="popup-link" class="btn btn-p btn-sm" style="flex:1;justify-content:center">Xem chi tiết</a>
                    <button class="btn btn-s btn-sm btn-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:14px;height:14px;flex-shrink:0"><path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Mobile panel toggle --}}
    <button class="panel-toggle" onclick="togglePanel()">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:16px;height:16px;flex-shrink:0"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
        Danh sách (<span id="toggle-count">{{ number_format($pins->count()) }}</span>)
    </button>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function(){
    // ── Pin data from server ──
    var PINS = @json($pinsJson);

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

    // ── Pin color by type ──
    function pinColor(type) {
        if (!type) return 'bl';
        if (type.indexOf('outdoor') >= 0) return 'bl';
        if (type.indexOf('indoor') >= 0 || type.indexOf('mall') >= 0) return 'grn';
        if (type.indexOf('transit') >= 0 || type.indexOf('airport') >= 0) return 'org';
        return 'bl';
    }

    // ── Format price ──
    function fmtPrice(p) {
        if (p >= 1e9) return (p / 1e9).toFixed(1).replace('.0','') + 'B';
        if (p >= 1e6) return Math.round(p / 1e6) + 'M';
        if (p > 0) return p.toLocaleString('vi-VN');
        return '—';
    }

    // ── Create markers ──
    var markers = [];
    var markerLayer = L.layerGroup().addTo(map);

    function renderPins(pinList) {
        markerLayer.clearLayers();
        markers = [];

        pinList.forEach(function(pin) {
            var col = pinColor(pin.type);
            var icon = L.divIcon({
                className: 'oohx-pin oohx-pin--' + col,
                html: '<div class="oohx-pin-box">' + fmtPrice(pin.price) + '</div><div class="oohx-pin-arrow"></div>',
                iconSize: [70, 36],
                iconAnchor: [35, 36],
            });

            var marker = L.marker([pin.lat, pin.lng], {icon: icon}).addTo(markerLayer);
            marker._pinData = pin;
            marker.on('click', function() { selectPin(pin); });
            markers.push({marker: marker, pin: pin});
        });

        // Update counts
        var cnt = pinList.length.toLocaleString('vi-VN');
        document.getElementById('pin-count').textContent = cnt;
        document.getElementById('map-pin-count').textContent = cnt;
        document.getElementById('toggle-count').textContent = cnt;
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
        // Popup
        var popup = document.getElementById('popup');
        document.getElementById('popup-name').textContent = pin.name;
        document.getElementById('popup-city').textContent = pin.city || pin.addr || '';
        document.getElementById('popup-price').innerHTML = fmtPrice(pin.price) + ' ₫<span> / tháng</span>';
        document.getElementById('popup-img').src = pin.photo || 'https://placehold.co/600x400/F5F5F7/6E6E73?text=No+Photo';
        document.getElementById('popup-link').href = '/explore/' + pin.id;
        popup.style.display = 'block';

        // Pan map
        map.panTo([pin.lat, pin.lng]);

        // Highlight panel card
        if (activeCard) activeCard.classList.remove('active');
        var card = document.querySelector('.mp-card[data-uuid="' + pin.id + '"]');
        if (card) {
            card.classList.add('active');
            card.scrollIntoView({behavior:'smooth', block:'nearest'});
            activeCard = card;
        }
    }

    window.hidePopup = function() {
        document.getElementById('popup').style.display = 'none';
        if (activeCard) { activeCard.classList.remove('active'); activeCard = null; }
    };

    // ── Panel card click → fly to pin ──
    document.querySelectorAll('.mp-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var uuid = card.dataset.uuid;
            var pin = PINS.find(function(p){ return p.id === uuid; });
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

    // ── City toolbar ──
    document.querySelectorAll('.mt-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.mt-btn').forEach(function(b){ b.classList.remove('on'); });
            btn.classList.add('on');

            var lat = parseFloat(btn.dataset.lat);
            var lng = parseFloat(btn.dataset.lng);
            var zoom = parseInt(btn.dataset.zoom);
            map.flyTo([lat, lng], zoom, {duration: 0.8});
        });
    });

    // ── Filter chips ──
    document.querySelectorAll('.mpc-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            document.querySelectorAll('.mpc-chip').forEach(function(x){ x.classList.remove('on'); });
            chip.classList.add('on');

            var type = chip.dataset.type;
            var filtered = type === 'all' ? PINS : PINS.filter(function(p) {
                if (type === 'available') return true;
                return p.type && p.type.indexOf(type) >= 0;
            });
            renderPins(filtered);

            if (filtered.length > 0) {
                var bounds = L.latLngBounds(filtered.map(function(p){ return [p.lat, p.lng]; }));
                map.fitBounds(bounds, {padding: [40, 40], maxZoom: 14});
            }

            hidePopup();
        });
    });

    // ── Panel toggle (mobile) ──
    window.togglePanel = function() {
        document.getElementById('panel').classList.toggle('open');
    };

    // ── Search ──
    window.searchMap = function() {
        var q = document.getElementById('map-search').value.trim().toLowerCase();
        if (!q) { renderPins(PINS); return; }
        var filtered = PINS.filter(function(p) {
            return (p.name && p.name.toLowerCase().indexOf(q) >= 0) ||
                   (p.city && p.city.toLowerCase().indexOf(q) >= 0) ||
                   (p.addr && p.addr.toLowerCase().indexOf(q) >= 0);
        });
        renderPins(filtered);
        if (filtered.length > 0) {
            var bounds = L.latLngBounds(filtered.map(function(p){ return [p.lat, p.lng]; }));
            map.fitBounds(bounds, {padding: [40, 40], maxZoom: 14});
        }
    };

    document.getElementById('map-search').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') searchMap();
    });
})();
</script>
@endpush
