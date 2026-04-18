@php
    /** @var \App\Models\PoiSnapshot $record */
    $record = $getRecord();
    $centerLat = (float) $record->lat_key;
    $centerLon = (float) $record->lon_key;
    $rawPois   = is_array($record->pois) ? $record->pois : [];

    // ── Group POIs theo category VN-friendly ────────────────────────────
    // Map raw OSM tag → [group key, label VN, Material Symbols icon name, color]
    $groupMap = [
        // Food & beverage
        'cafe'           => ['food_bev',  'Cafe',                'local_cafe',         '#8B4513'],
        'restaurant'     => ['food_bev',  'Nhà hàng',            'restaurant',         '#E67E22'],
        'fast_food'      => ['food_bev',  'Đồ ăn nhanh',         'fastfood',           '#E74C3C'],
        'bar'            => ['food_bev',  'Bar',                 'local_bar',          '#9B59B6'],
        'pub'            => ['food_bev',  'Pub',                 'sports_bar',         '#9B59B6'],
        'food_court'     => ['food_bev',  'Food court',          'restaurant_menu',    '#E67E22'],
        'bakery'         => ['food_bev',  'Bakery',              'bakery_dining',      '#D35400'],

        // Education
        'school'         => ['education', 'Trường học',          'school',             '#3498DB'],
        'university'     => ['education', 'Đại học',             'cast_for_education', '#2980B9'],
        'college'        => ['education', 'Cao đẳng',            'cast_for_education', '#2980B9'],
        'kindergarten'   => ['education', 'Mầm non',             'child_care',         '#3498DB'],
        'language_school'=> ['education', 'Trung tâm ngoại ngữ', 'translate',          '#3498DB'],

        // Health
        'hospital'       => ['health',    'Bệnh viện',           'local_hospital',     '#E74C3C'],
        'clinic'         => ['health',    'Phòng khám',          'medical_services',   '#E91E63'],
        'pharmacy'       => ['health',    'Hiệu thuốc',          'medication',         '#27AE60'],
        'doctors'        => ['health',    'Bác sĩ',              'stethoscope',        '#E91E63'],

        // Finance
        'bank'           => ['finance',   'Ngân hàng',           'account_balance',    '#16A085'],
        'atm'            => ['finance',   'ATM',                 'local_atm',          '#16A085'],

        // Auto
        'fuel'           => ['auto',      'Cây xăng',            'local_gas_station',  '#F39C12'],
        'parking'        => ['auto',      'Bãi đậu xe',          'local_parking',      '#7F8C8D'],

        // Entertainment
        'cinema'         => ['entertain', 'Rạp chiếu phim',      'movie',              '#9B59B6'],
        'theatre'        => ['entertain', 'Nhà hát',             'theater_comedy',     '#9B59B6'],
        'nightclub'      => ['entertain', 'Nightclub',           'nightlife',          '#9B59B6'],

        // Lodging
        'hotel'          => ['lodging',   'Khách sạn',           'hotel',              '#34495E'],
        'guest_house'    => ['lodging',   'Guest house',         'house',              '#34495E'],
        'hostel'         => ['lodging',   'Hostel',              'bed',                '#34495E'],

        // Retail
        'mall'           => ['retail',    'Trung tâm thương mại','local_mall',         '#E67E22'],
        'department_store'=>['retail',    'Department store',    'store',              '#E67E22'],
        'supermarket'    => ['retail',    'Siêu thị',            'local_grocery_store','#27AE60'],
        'convenience'    => ['retail',    'Cửa hàng tiện lợi',   'convenience_store',  '#27AE60'],
        'marketplace'    => ['retail',    'Chợ',                 'storefront',         '#E67E22'],
        'clothes'        => ['retail',    'Thời trang',          'checkroom',          '#E91E63'],
        'shoes'          => ['retail',    'Giày dép',            'do_not_step',        '#E91E63'],
        'electronics'    => ['retail',    'Điện tử',             'devices',            '#34495E'],
        'mobile_phone'   => ['retail',    'Điện thoại',          'smartphone',         '#34495E'],
        'beauty'         => ['retail',    'Làm đẹp',             'face_retouching_natural', '#E91E63'],
        'cosmetics'      => ['retail',    'Mỹ phẩm',             'brush',              '#E91E63'],
        'hairdresser'    => ['retail',    'Tóc',                 'content_cut',        '#E91E63'],

        // Sports & leisure
        'gym'            => ['sports',    'Gym',                 'fitness_center',     '#16A085'],
        'fitness_centre' => ['sports',    'Fitness',             'fitness_center',     '#16A085'],
        'park'           => ['sports',    'Công viên',           'park',               '#27AE60'],
        'sports_centre'  => ['sports',    'Trung tâm thể thao',  'sports_soccer',      '#16A085'],
        'stadium'        => ['sports',    'Sân vận động',        'stadium',            '#16A085'],

        // Transit
        'bus_station'    => ['transit',   'Bến xe bus',          'directions_bus',     '#3498DB'],
        'station'        => ['transit',   'Trạm',                'train',              '#3498DB'],

        // Tourism
        'attraction'     => ['tourism',   'Điểm tham quan',      'attractions',        '#9B59B6'],
        'museum'         => ['tourism',   'Bảo tàng',            'museum',             '#9B59B6'],
        'gallery'        => ['tourism',   'Gallery',             'photo_library',      '#9B59B6'],
        'place_of_worship'=>['tourism',   'Nơi thờ tự',          'church',             '#7F8C8D'],

        // Office
        'office'         => ['office',    'Văn phòng',           'business',           '#34495E'],
        'company'        => ['office',    'Công ty',             'business_center',    '#34495E'],
        'coworking'      => ['office',    'Coworking',           'groups',             '#34495E'],
        'government'     => ['office',    'Cơ quan nhà nước',    'gavel',              '#7F8C8D'],
    ];

    // Group → [Material icon, label VN]
    $groupLabels = [
        'food_bev'  => ['restaurant',      'Ăn uống'],
        'education' => ['school',          'Giáo dục'],
        'health'    => ['local_hospital',  'Y tế'],
        'finance'   => ['account_balance', 'Tài chính'],
        'auto'      => ['local_gas_station','Giao thông xe'],
        'entertain' => ['movie',           'Giải trí'],
        'lodging'   => ['hotel',           'Lưu trú'],
        'retail'    => ['local_mall',      'Mua sắm'],
        'sports'    => ['fitness_center',  'Thể thao'],
        'transit'   => ['directions_bus',  'Vận tải công cộng'],
        'tourism'   => ['attractions',     'Du lịch'],
        'office'    => ['business',        'Văn phòng'],
        'other'     => ['place',           'Khác'],
    ];

    // Project + filter: chỉ POIs có name + lat/lon hợp lệ
    $projected = [];
    $idx = 0;
    foreach ($rawPois as $p) {
        $tags = $p['tags'] ?? [];
        $name = $tags['name'] ?? $tags['name:vi'] ?? null;
        $pLat = $p['lat'] ?? ($p['center']['lat'] ?? null);
        $pLon = $p['lon'] ?? ($p['center']['lon'] ?? null);
        if (! $name || ! $pLat || ! $pLon) continue;

        $rawTag = $tags['amenity']
            ?? $tags['shop']
            ?? $tags['leisure']
            ?? $tags['tourism']
            ?? $tags['office']
            ?? $tags['public_transport']
            ?? $tags['building']
            ?? null;

        $meta = $groupMap[$rawTag] ?? ['other', $rawTag ?: 'POI', 'place', '#7F8C8D'];

        // Distance from center
        $R = 6371000;
        $dLat = deg2rad($pLat - $centerLat);
        $dLon = deg2rad($pLon - $centerLon);
        $a = sin($dLat/2)**2 + cos(deg2rad($centerLat)) * cos(deg2rad($pLat)) * sin($dLon/2)**2;
        $dist = (int) round(2 * $R * asin(sqrt($a)));

        $projected[] = [
            'id'    => 'p' . $idx++,
            'name'  => $name,
            'lat'   => (float) $pLat,
            'lon'   => (float) $pLon,
            'group' => $meta[0],
            'gLabel'=> $meta[1],
            'icon'  => $meta[2],
            'color' => $meta[3],
            'tag'   => $rawTag,
            'dist'  => $dist,
        ];
    }

    // Sort by distance asc
    usort($projected, fn ($a, $b) => $a['dist'] <=> $b['dist']);

    // Group
    $grouped = [];
    foreach ($projected as $p) {
        $grouped[$p['group']][] = $p;
    }

    // Order groups by count desc
    uksort($grouped, fn ($a, $b) => count($grouped[$b] ?? []) <=> count($grouped[$a] ?? []));

    $totalNamed = count($projected);
    $totalRaw   = count($rawPois);
@endphp

{{-- Leaflet + Material Symbols (load once per page; harmless if duplicated) --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">

<style>
.material-symbols-outlined{font-family:'Material Symbols Outlined';font-weight:normal;font-style:normal;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:'liga';-webkit-font-smoothing:antialiased;font-variation-settings:'FILL' 1, 'wght' 500, 'GRAD' 0, 'opsz' 24}
.poi-browser{display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:16px;height:680px}
@media(max-width:1024px){.poi-browser{grid-template-columns:1fr;height:auto}.poi-browser-map{height:480px}}
.poi-browser-list{overflow-y:auto;border:1px solid rgb(229 231 235 / var(--tw-border-opacity, 1));border-radius:8px;background:#fff}
.dark .poi-browser-list{border-color:rgb(55 65 81);background:rgb(17 24 39)}
.poi-browser-summary{padding:10px 12px;border-bottom:1px solid rgb(229 231 235);font-size:12px;color:rgb(75 85 99);background:rgb(249 250 251);position:sticky;top:0;z-index:5}
.dark .poi-browser-summary{background:rgb(31 41 55);border-color:rgb(55 65 81);color:rgb(156 163 175)}
.poi-browser-grp-h{padding:8px 12px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.4px;background:rgb(243 244 246);color:rgb(55 65 81);border-top:1px solid rgb(229 231 235);position:sticky;top:36px;z-index:4}
.dark .poi-browser-grp-h{background:rgb(31 41 55);color:rgb(209 213 219);border-color:rgb(55 65 81)}
.poi-browser-grp-h .ct{float:right;font-size:11px;background:rgb(229 231 235);color:rgb(55 65 81);padding:1px 6px;border-radius:980px;font-weight:600;letter-spacing:0}
.dark .poi-browser-grp-h .ct{background:rgb(55 65 81);color:rgb(229 231 235)}
.poi-browser-item{display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;border-top:1px solid rgb(243 244 246);transition:background 120ms}
.dark .poi-browser-item{border-color:rgb(55 65 81)}
.poi-browser-item:hover{background:rgb(239 246 255)}
.dark .poi-browser-item:hover{background:rgb(30 58 138 / .25)}
.poi-browser-item.is-active{background:rgb(219 234 254);box-shadow:inset 3px 0 0 rgb(37 99 235)}
.dark .poi-browser-item.is-active{background:rgb(30 58 138 / .4);box-shadow:inset 3px 0 0 rgb(96 165 250)}
.poi-browser-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.poi-browser-dot .material-symbols-outlined{font-size:16px}
.poi-browser-grp-h .material-symbols-outlined{font-size:16px;vertical-align:-3px;margin-right:6px;color:rgb(75 85 99)}
.dark .poi-browser-grp-h .material-symbols-outlined{color:rgb(156 163 175)}
.poi-browser-meta{min-width:0;flex:1}
.poi-browser-name{font-weight:600;font-size:13px;color:rgb(17 24 39);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dark .poi-browser-name{color:rgb(243 244 246)}
.poi-browser-sub{font-size:11px;color:rgb(107 114 128);display:flex;align-items:center;gap:6px;margin-top:1px}
.poi-browser-sub b{color:rgb(37 99 235);font-weight:600}
.dark .poi-browser-sub b{color:rgb(96 165 250)}
.poi-browser-empty{padding:40px 20px;text-align:center;color:rgb(107 114 128);font-size:13px}
.poi-browser-map{width:100%;height:100%;border-radius:8px;border:1px solid rgb(229 231 235);background:#f3f4f6;overflow:hidden}
.dark .poi-browser-map{border-color:rgb(55 65 81)}
.psm-pin{background:none;border:none}
.psm-pin-inner{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.25);cursor:pointer;transition:transform 160ms}
.psm-pin-inner .material-symbols-outlined{font-size:16px}
.psm-pin-inner:hover{transform:scale(1.3);z-index:1000}
.psm-center{background:none;border:none;position:relative}
.psm-center-inner{position:absolute;top:0;left:0;width:36px;height:36px;border-radius:50%;background:rgb(37 99 235);color:#fff;display:flex;align-items:center;justify-content:center;border:3px solid #fff;box-shadow:0 4px 12px rgba(37,99,235,.4);z-index:2}
.psm-center-inner .material-symbols-outlined{font-size:20px}
.psm-center-pulse{position:absolute;top:-4px;left:-4px;width:44px;height:44px;border-radius:50%;background:rgb(37 99 235 / .25);animation:psm-pulse 2s ease-in-out infinite;z-index:1}
@keyframes psm-pulse{0%,100%{transform:scale(1);opacity:.6}50%{transform:scale(1.4);opacity:.1}}
</style>

<div class="poi-browser">
    {{-- ── LEFT: grouped list ─────────────────────────────────────────── --}}
    <div class="poi-browser-list" id="poi-browser-list">
        <div class="poi-browser-summary">
            <strong>{{ $totalNamed }}</strong> POIs có tên / {{ $totalRaw }} raw ·
            <strong>{{ count($grouped) }}</strong> nhóm
        </div>

        @if($totalNamed === 0)
            <div class="poi-browser-empty">
                Snapshot không có POI nào có tên (raw: {{ $totalRaw }}).
            </div>
        @endif

        @foreach($grouped as $groupKey => $items)
            @php
                [$grpIcon, $grpLabel] = $groupLabels[$groupKey] ?? ['place', $groupKey];
            @endphp
            <div class="poi-browser-grp-h">
                <span class="material-symbols-outlined">{{ $grpIcon }}</span>{{ $grpLabel }}
                <span class="ct">{{ count($items) }}</span>
            </div>
            @foreach($items as $p)
                <div class="poi-browser-item" data-poi-id="{{ $p['id'] }}">
                    <div class="poi-browser-dot" style="background:{{ $p['color'] }}">
                        <span class="material-symbols-outlined">{{ $p['icon'] }}</span>
                    </div>
                    <div class="poi-browser-meta">
                        <div class="poi-browser-name">{{ $p['name'] }}</div>
                        <div class="poi-browser-sub">
                            <span>{{ $p['gLabel'] }}</span>
                            <span>·</span>
                            <b>{{ $p['dist'] }}m</b>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    {{-- ── RIGHT: map ─────────────────────────────────────────────────── --}}
    <div>
        <div id="poi-snap-map" class="poi-browser-map"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function(){
    var POIS = @json($projected);
    var CENTER = { lat: {{ $centerLat }}, lon: {{ $centerLon }}, radius: {{ (int) $record->radius }} };
    var mapEl = document.getElementById('poi-snap-map');
    if (!mapEl || typeof L === 'undefined') return;

    // Avoid double-init nếu Livewire re-render
    if (mapEl._psmInited) return;
    mapEl._psmInited = true;

    var map = L.map(mapEl, {
        center: [CENTER.lat, CENTER.lon],
        zoom: 17,
        zoomControl: true,
        scrollWheelZoom: true,
        preferCanvas: true,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    // Center pin (snapshot location)
    var centerIcon = L.divIcon({
        className: 'psm-center',
        html: '<div class="psm-center-pulse"></div>'
            + '<div class="psm-center-inner"><span class="material-symbols-outlined">my_location</span></div>',
        iconSize: [36, 36], iconAnchor: [18, 18],
    });
    L.marker([CENTER.lat, CENTER.lon], { icon: centerIcon, zIndexOffset: 1000 })
        .addTo(map)
        .bindPopup('<div style="font-weight:700;font-size:13px">Tâm snapshot</div>'
                 + '<div style="font-size:11px;color:#888">Bán kính ' + CENTER.radius + 'm</div>');

    // Radius circle for visual context
    L.circle([CENTER.lat, CENTER.lon], {
        radius: CENTER.radius,
        color: '#2563eb',
        weight: 1,
        fillOpacity: 0.04,
        dashArray: '4 4',
    }).addTo(map);

    // POI markers — store handles by id để click list pan/popup
    var markers = {};
    POIS.forEach(function(p){
        var icon = L.divIcon({
            className: 'psm-pin',
            html: '<div class="psm-pin-inner" style="background:'+p.color+'">'
                + '<span class="material-symbols-outlined">'+p.icon+'</span></div>',
            iconSize: [28, 28], iconAnchor: [14, 14],
        });
        var m = L.marker([p.lat, p.lon], { icon: icon, zIndexOffset: 100 })
            .addTo(map)
            .bindPopup(
                '<div style="font-weight:600;font-size:13px">'+p.name+'</div>'
              + '<div style="font-size:11px;color:#666;margin-top:2px">'+p.gLabel+' · '+p.dist+'m từ tâm</div>'
            );
        markers[p.id] = m;
    });

    // Wire click on left list → pan + popup
    var list = document.getElementById('poi-browser-list');
    if (list) {
        list.addEventListener('click', function(e){
            var item = e.target.closest('.poi-browser-item');
            if (!item) return;
            var id = item.getAttribute('data-poi-id');
            var m = markers[id];
            if (!m) return;

            // Highlight active row
            list.querySelectorAll('.poi-browser-item.is-active').forEach(function(el){ el.classList.remove('is-active'); });
            item.classList.add('is-active');

            map.flyTo(m.getLatLng(), 18, { duration: 0.6 });
            setTimeout(function(){ m.openPopup(); }, 650);
        });
    }

    // Fix tile load on hidden container
    setTimeout(function(){ map.invalidateSize(); }, 100);
})();
</script>
