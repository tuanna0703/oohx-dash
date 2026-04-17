@extends('frontpage.layouts.app', ['activeNav' => 'map', 'bodyClass' => 'page-map', 'hideFooter' => true])

@section('title', 'Bản đồ OOH/DOOH | OOHX')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<style>
#leaflet-map{position:absolute;inset:0;z-index:0}
#leaflet-map .leaflet-control-zoom{display:none}

/* ── Pin: icon + label mode (zoom ≥ 15) ── */
.oohx-pin{background:none;border:none}
.oohx-pin-box{border-radius:10px;padding:5px 10px;font-size:11px;font-weight:700;color:#fff;display:flex;align-items:center;gap:4px;white-space:nowrap;box-shadow:0 4px 16px rgba(0,0,0,.2);cursor:pointer;transition:transform 200ms var(--spring)}
.oohx-pin-box:hover{transform:scale(1.1)}
.oohx-pin-box svg{width:14px;height:14px;flex-shrink:0}
.oohx-pin-arrow{width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;margin:-1px auto 0}
/* Pin: icon-only mode (zoom < 15) */
.oohx-pin-icon{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 12px rgba(0,0,0,.25);cursor:pointer;transition:transform 200ms var(--spring);border:2px solid rgba(255,255,255,.9)}
.oohx-pin-icon:hover{transform:scale(1.15)}
.oohx-pin-icon svg{width:14px;height:14px;flex-shrink:0}
/* Colors for both modes */
.oohx-pin--bl .oohx-pin-box,.oohx-pin--bl .oohx-pin-icon{background:var(--bl)}.oohx-pin--bl .oohx-pin-arrow{border-top:8px solid var(--bl)}
.oohx-pin--grn .oohx-pin-box,.oohx-pin--grn .oohx-pin-icon{background:var(--grn)}.oohx-pin--grn .oohx-pin-arrow{border-top:8px solid var(--grn)}
.oohx-pin--org .oohx-pin-box,.oohx-pin--org .oohx-pin-icon{background:var(--org)}.oohx-pin--org .oohx-pin-arrow{border-top:8px solid var(--org)}
.oohx-pin--red .oohx-pin-box,.oohx-pin--red .oohx-pin-icon{background:var(--red)}.oohx-pin--red .oohx-pin-arrow{border-top:8px solid var(--red)}

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
        {{-- Collapsible Smart Filter --}}
        @php
            $activeFilterCount = count(array_filter(request()->input('region', []))) + count(array_filter(request()->input('city', []))) + count(array_filter(request()->input('network', []))) + count(array_filter(request()->input('venue_type', [])));
            $hasQ = !empty(request()->input('q'));
        @endphp
        <div class="mp-filter-toggle" id="mp-filter-toggle">
            <div class="mp-filter-toggle-left">
                <svg viewBox="0 0 24 24" fill="var(--t3)" style="width:16px;height:16px;flex-shrink:0"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                <span>Bộ lọc</span>
                @if($activeFilterCount > 0 || $hasQ)<span class="mp-filter-badge">{{ $activeFilterCount + ($hasQ ? 1 : 0) }}</span>@endif
            </div>
            <svg class="mp-filter-chevron" viewBox="0 0 24 24" fill="var(--t4)" style="width:18px;height:18px;flex-shrink:0"><path d="M7 10l5 5 5-5z"/></svg>
        </div>
        <div class="mp-filter-body" id="mp-filter-body" style="display:none">
            @include('frontpage.partials.smart-filter', [
                'filters'          => $filters,
                'locationsByRegion' => $locationsByRegion,
                'target'           => 'map',
            ])
        </div>

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

        <div class="map-toolbar">
            <button class="mt-btn" data-city="all" data-lat="16.0" data-lng="106.0" data-zoom="6">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg> Tất cả
            </button>
            <button class="mt-btn on" data-city="hanoi" data-lat="21.0285" data-lng="105.8542" data-zoom="12">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> Hà Nội
            </button>
            <button class="mt-btn" data-city="hcm" data-lat="10.7769" data-lng="106.7009" data-zoom="12">TP.HCM</button>
            <button class="mt-btn" data-city="danang" data-lat="16.0544" data-lng="108.2022" data-zoom="13">Đà Nẵng</button>
            <button class="mt-btn" data-city="haiphong" data-lat="20.8449" data-lng="106.6881" data-zoom="13">Hải Phòng</button>
            {{-- More cities dropdown --}}
            <div class="mt-more" id="mt-more">
                <button class="mt-btn mt-more-trigger" id="mt-more-btn" onclick="document.getElementById('mt-dropdown').classList.toggle('open');event.stopPropagation()">
                    Khác <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;flex-shrink:0"><path d="M7 10l5 5 5-5z"/></svg>
                </button>
                <div class="mt-dropdown" id="mt-dropdown">
                    <div class="mt-dd-head">Chọn tỉnh/thành</div>
                    <div class="mt-dd-list">
                        @foreach(($filters['cities'] ?? collect()) as $city)
                            @if(!in_array($city['code'], ['hanoi', 'hcm', 'danang', 'haiphong']))
                            <button class="mt-dd-item" data-city="{{ $city['code'] }}" onclick="selectMapCity(this);document.getElementById('mt-dropdown').classList.remove('open')">
                                {{ $city['name'] }} <span class="mt-dd-count">{{ number_format($city['count']) }}</span>
                            </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
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
                <span class="badge b-grn" style="display:inline-flex;margin-bottom:8px">Còn trống</span>
                <div id="popup-product" style="display:none;margin-bottom:8px;padding:6px 10px;background:rgba(42,79,246,.05);border:1px solid rgba(42,79,246,.12);border-radius:8px">
                    <a href="#" id="popup-product-link" style="font-size:11px;font-weight:700;color:var(--bl);text-decoration:none;display:flex;align-items:center;gap:4px">
                        <svg viewBox="0 0 24 24" fill="currentColor" style="width:11px;height:11px;flex-shrink:0"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                        <span id="popup-product-text"></span>
                    </a>
                </div>
                <div class="mpop-price" id="popup-price"></div>
                <div class="mpop-actions">
                    <a href="#" id="popup-link" class="btn btn-p btn-sm" style="flex:1;justify-content:center">Xem chi tiết</a>
                    <button class="btn btn-s btn-sm btn-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:14px;height:14px;flex-shrink:0"><path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/></svg>
                    </button>
                </div>
            </div>
        </div>

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
        if (type === 'roadside') return 'bl';
        if (type === 'mall' || type === 'retail') return 'grn';
        if (type === 'transit') return 'org';
        if (type === 'healthcare' || type === 'education') return 'red';
        if (type === 'food-beverage' || type === 'entertainment') return 'org';
        return 'bl';
    }

    // ── SVG icons per venue type (inline, 16x16) ──
    var PIN_ICONS = {
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
    var DEFAULT_ICON = '<svg viewBox="0 0 24 24" fill="#fff" width="14" height="14"><path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h7v2H8v2h8v-2h-2v-2h7c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12z"/></svg>';

    // Short labels for pins
    var PIN_LABELS = {
        'mall': 'TTTM', 'retail': 'Bán lẻ', 'transit': 'Giao thông', 'roadside': 'Outdoor',
        'office': 'VP', 'residential': 'Khu DC', 'food-beverage': 'F&B', 'healthcare': 'Y tế',
        'education': 'GD', 'hospitality': 'KS', 'entertainment': 'Giải trí', 'sports-fitness': 'Gym',
    };

    // ── Format price (for popup only) ──
    function fmtPrice(p) {
        if (p >= 1e9) return (p / 1e9).toFixed(1).replace('.0','') + 'B';
        if (p >= 1e6) return Math.round(p / 1e6) + 'M';
        if (p > 0) return p.toLocaleString('vi-VN');
        return '—';
    }

    // ── Cluster size helper ──
    function clusterSize(count) {
        if (count >= 500) return 'xl';
        if (count >= 100) return 'lg';
        if (count >= 20) return 'md';
        return 'sm';
    }

    function fmtCount(n) {
        if (n >= 1000) return (n / 1000).toFixed(1).replace('.0','') + 'K';
        return n.toString();
    }

    // ── Create MarkerClusterGroup ──
    var markers = [];
    var clusterGroup = L.markerClusterGroup({
        maxClusterRadius: function(zoom) {
            // Larger radius at low zoom → more aggressive grouping
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
        iconCreateFunction: function(cluster) {
            var count = cluster.getChildCount();
            var size = clusterSize(count);
            return L.divIcon({
                className: 'marker-cluster',
                html: '<div class="oohx-cluster oohx-cluster--' + size + '">' + fmtCount(count) + '</div>',
                iconSize: L.point(size === 'xl' ? 80 : size === 'lg' ? 68 : size === 'md' ? 56 : 44,
                                  size === 'xl' ? 80 : size === 'lg' ? 68 : size === 'md' ? 56 : 44),
            });
        }
    });
    map.addLayer(clusterGroup);

    // ── Build pin icon based on zoom level ──
    function createPinIcon(pin, zoom) {
        var col = pinColor(pin.type);
        var svg = PIN_ICONS[pin.type] || DEFAULT_ICON;

        if (zoom >= 15) {
            // Icon + short label
            var label = PIN_LABELS[pin.type] || pin.typeLabel || '';
            return L.divIcon({
                className: 'oohx-pin oohx-pin--' + col,
                html: '<div class="oohx-pin-box">' + svg + '<span>' + label + '</span></div><div class="oohx-pin-arrow"></div>',
                iconSize: [90, 36],
                iconAnchor: [45, 36],
            });
        } else {
            // Icon only (compact)
            return L.divIcon({
                className: 'oohx-pin oohx-pin--' + col,
                html: '<div class="oohx-pin-icon">' + svg + '</div>',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
            });
        }
    }

    var currentZoom = map.getZoom();
    var lastPinMode = currentZoom >= 15 ? 'label' : 'icon';

    function renderPins(pinList) {
        clusterGroup.clearLayers();
        markers = [];
        currentZoom = map.getZoom();

        var markerArray = [];
        pinList.forEach(function(pin) {
            var icon = createPinIcon(pin, currentZoom);

            var marker = L.marker([pin.lat, pin.lng], {icon: icon});
            marker._pinData = pin;
            marker.on('click', function() { selectPin(pin); });
            markers.push({marker: marker, pin: pin});
            markerArray.push(marker);
        });

        // Bulk add for performance (chunkedLoading handles the rest)
        clusterGroup.addLayers(markerArray);

        // Update counts
        var cnt = pinList.length.toLocaleString('vi-VN');
        document.getElementById('pin-count').textContent = cnt;
        document.getElementById('map-pin-count').textContent = cnt;
        document.getElementById('toggle-count').textContent = cnt;
    }

    renderPins(PINS);

    // Re-render pin icons when zoom crosses threshold (icon ↔ icon+label)
    map.on('zoomend', function() {
        var zoom = map.getZoom();
        var mode = zoom >= 15 ? 'label' : 'icon';
        if (mode !== lastPinMode) {
            lastPinMode = mode;
            markers.forEach(function(m) {
                m.marker.setIcon(createPinIcon(m.pin, zoom));
            });
        }
    });

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

        // Product badge
        var prodEl = document.getElementById('popup-product');
        if (pin.product) {
            var txt = pin.product.name + ' (' + pin.product.total_units + ' MH)';
            if (pin.product.can_buy_individual) txt += ' · Mua lẻ được';
            document.getElementById('popup-product-text').textContent = txt;
            document.getElementById('popup-product-link').href = '/products/' + pin.product.slug;
            prodEl.style.display = 'block';
        } else {
            prodEl.style.display = 'none';
        }

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
    document.querySelectorAll('.mt-btn:not(.mt-more-trigger)').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.mt-btn').forEach(function(b){ b.classList.remove('on'); });
            document.querySelectorAll('.mt-dd-item').forEach(function(b){ b.classList.remove('on'); });
            btn.classList.add('on');

            var lat = parseFloat(btn.dataset.lat);
            var lng = parseFloat(btn.dataset.lng);
            var zoom = parseInt(btn.dataset.zoom);
            map.flyTo([lat, lng], zoom, {duration: 0.8});
        });
    });

    // ── More cities dropdown ──
    window.selectMapCity = function(item) {
        var city = item.dataset.city;
        // Clear all active states
        document.querySelectorAll('.mt-btn').forEach(function(b){ b.classList.remove('on'); });
        document.querySelectorAll('.mt-dd-item').forEach(function(b){ b.classList.remove('on'); });
        item.classList.add('on');
        document.getElementById('mt-more-btn').classList.add('on');

        // Filter pins by city name
        var cityName = item.textContent.trim().replace(/\d[\d,.]*$/,'').trim();
        var filtered = PINS.filter(function(p) {
            return p.city && p.city.indexOf(cityName) >= 0;
        });
        renderPins(filtered);
        if (filtered.length > 0) {
            var bounds = L.latLngBounds(filtered.map(function(p){ return [p.lat, p.lng]; }));
            map.fitBounds(bounds, {padding: [40, 40], maxZoom: 14});
        }
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

    // ── Filter collapse/expand ──
    (function(){
        var toggle = document.getElementById('mp-filter-toggle');
        var body = document.getElementById('mp-filter-body');
        if (!toggle || !body) return;
        // Auto-expand if filters are active
        var hasActive = {{ ($activeFilterCount > 0 || $hasQ) ? 'true' : 'false' }};
        if (hasActive) { body.style.display = ''; toggle.classList.add('open'); }
        toggle.addEventListener('click', function(){
            var isOpen = body.style.display !== 'none';
            body.style.display = isOpen ? 'none' : '';
            toggle.classList.toggle('open', !isOpen);
        });
    })();

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
