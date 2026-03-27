@once
<style>
.nvm-list-tab .fi-ta-ctn { border-radius: 0 !important; box-shadow: none !important; }
</style>
@endonce

@php
    $screensData = $this->screensMapData;

    $siteOpts = collect($screensData)
        ->filter(fn($s) => $s['site_id'] !== '')
        ->mapWithKeys(fn($s) => [$s['site_id'] => $s['site_name']])
        ->sortBy(fn($v) => $v)
        ->all();

    $provinceOpts = collect($screensData)
        ->filter(fn($s) => $s['province_id'] !== '')
        ->mapWithKeys(fn($s) => [$s['province_id'] => $s['province_name']])
        ->filter()
        ->sortBy(fn($v) => $v)
        ->all();

    $communeOpts = collect($screensData)
        ->filter(fn($s) => $s['commune_id'] !== '')
        ->mapWithKeys(fn($s) => [$s['commune_id'] => [
            'name'        => $s['commune_name'],
            'province_id' => $s['province_id'],
        ]])
        ->filter(fn($v) => $v['name'] !== '')
        ->sortBy(fn($v) => $v['name'])
        ->all();

    // ── Mock campaign data (replace with real query when model is ready) ───────
    $mockCampaigns = [
        ['name' => 'Tết Sale 2025',         'advertiser' => 'Vinamilk',      'start' => '2025-01-10', 'end' => '2025-02-10', 'budget' => 120_000_000, 'impressions' => 485_000, 'status' => 'completed'],
        ['name' => 'Brand Awareness Q1',    'advertiser' => 'Samsung VN',    'start' => '2025-01-01', 'end' => '2025-03-31', 'budget' => 200_000_000, 'impressions' => 750_000, 'status' => 'completed'],
        ['name' => 'Summer Blast',          'advertiser' => 'Pepsi Vietnam', 'start' => '2025-04-01', 'end' => '2025-05-31', 'budget' => 85_000_000,  'impressions' => 320_000, 'status' => 'active'],
        ['name' => 'Mid-Year Promo',        'advertiser' => 'Shopee',        'start' => '2025-06-01', 'end' => '2025-06-30', 'budget' => 60_000_000,  'impressions' => 180_000, 'status' => 'paused'],
        ['name' => 'Back to School',        'advertiser' => 'Bitis',         'start' => '2025-08-01', 'end' => '2025-09-15', 'budget' => 45_000_000,  'impressions' => 95_000,  'status' => 'active'],
        ['name' => 'Year-End Grand Sale',   'advertiser' => 'Lazada VN',     'start' => '2024-11-01', 'end' => '2024-12-31', 'budget' => 175_000_000, 'impressions' => 620_000, 'status' => 'completed'],
    ];

    // Chart: last 12 months performance (revenue triệu VND, impressions nghìn)
    $mockChartLabels      = ['T4/24','T5/24','T6/24','T7/24','T8/24','T9/24','T10/24','T11/24','T12/24','T1/25','T2/25','T3/25'];
    $mockChartRevenue     = [42, 58, 74, 51, 80, 88, 105, 122, 175, 128, 95, 112];
    $mockChartImpressions = [168, 232, 296, 204, 320, 352, 420, 488, 620, 495, 375, 448];
@endphp

@script
<script>
(function () {
    // ── Map ──────────────────────────────────────────────────────────────────
    var SCREENS      = @json($screensData);
    var SITE_OPTS    = @json($siteOpts);
    var PROV_OPTS    = @json($provinceOpts);
    var COMMUNE_OPTS = @json($communeOpts);

    var filterSite    = '';
    var filterProv    = '';
    var filterCommune = '';
    var mapInst       = null;
    var markerLyr     = null;
    var mapInitialized = false;

    function filtered() {
        return SCREENS.filter(function (s) {
            if (filterSite    && s.site_id     !== filterSite)    return false;
            if (filterProv    && s.province_id !== filterProv)    return false;
            if (filterCommune && s.commune_id  !== filterCommune) return false;
            return true;
        });
    }

    function updateCount() {
        var list = filtered();
        var withCoords = list.filter(function (s) { return s.site_lat && s.site_lon; }).length;
        var cw = document.getElementById('nvm_gw'); if (cw) cw.textContent = withCoords;
        var ct = document.getElementById('nvm_gt'); if (ct) ct.textContent = list.length;
    }

    function updateClearBtn() {
        var btn = document.getElementById('nvm_clear');
        if (btn) btn.style.display = (filterSite || filterProv || filterCommune) ? '' : 'none';
    }

    function rebuildCommuneSel(scopeProvId) {
        var communeSel = document.getElementById('nvm_commune');
        if (!communeSel) return;
        var current = communeSel.value;
        while (communeSel.options.length > 1) communeSel.remove(1);
        Object.entries(COMMUNE_OPTS).forEach(function (pair) {
            var id = pair[0], data = pair[1];
            if (scopeProvId && data.province_id !== scopeProvId) return;
            var opt = document.createElement('option');
            opt.value = id; opt.textContent = data.name;
            communeSel.appendChild(opt);
        });
        communeSel.value = current;
        if (communeSel.value !== current) { communeSel.value = ''; filterCommune = ''; }
    }

    function refreshMarkers() {
        if (!mapInst || !markerLyr) return;
        markerLyr.clearLayers();
        var sites = {};
        filtered().forEach(function (s) {
            if (!s.site_lat || !s.site_lon) return;
            if (!sites[s.site_id]) {
                sites[s.site_id] = { lat: s.site_lat, lon: s.site_lon, name: s.site_name, screens: [] };
            }
            sites[s.site_id].screens.push(s);
        });
        var bounds = [];
        Object.values(sites).forEach(function (site) {
            var rows = site.screens.map(function (s) {
                var bg    = s.active ? '#dcfce7' : '#fee2e2';
                var color = s.active ? '#166534' : '#991b1b';
                var label = s.active ? 'Active' : 'Inactive';
                return '<tr>' +
                    '<td style="padding:2px 6px;font-family:monospace;font-size:11px">' + s.external_id + '</td>' +
                    '<td style="padding:2px 6px;font-size:12px"><a href="' + s.view_url + '" target="_blank" style="color:#3b82f6">' + s.name + '</a></td>' +
                    '<td style="padding:2px 6px"><span style="padding:1px 6px;border-radius:9999px;font-size:11px;background:' + bg + ';color:' + color + '">' + label + '</span></td>' +
                    '</tr>';
            }).join('');
            var popup = '<div style="min-width:280px">' +
                '<b style="font-size:13px">' + site.name + '</b>' +
                '<div style="font-size:11px;color:#6b7280;margin-bottom:6px">' + site.screens.length + ' màn hình</div>' +
                '<table style="width:100%;border-collapse:collapse">' +
                '<thead><tr style="background:#f3f4f6">' +
                '<th style="padding:2px 6px;text-align:left;font-size:11px">ID</th>' +
                '<th style="padding:2px 6px;text-align:left;font-size:11px">Tên</th>' +
                '<th style="padding:2px 6px;text-align:left;font-size:11px">Trạng thái</th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table></div>';
            L.marker([site.lat, site.lon]).addTo(markerLyr).bindPopup(popup, { maxWidth: 360 });
            bounds.push([site.lat, site.lon]);
        });
        if (bounds.length === 1)    mapInst.setView(bounds[0], 15);
        else if (bounds.length > 1) mapInst.fitBounds(bounds, { padding: [40, 40] });
        mapInst.invalidateSize();
        updateCount();
    }

    function initMap() {
        var mapEl = document.getElementById('nvm_map');
        if (!mapEl || !window.L) return;
        if (mapEl._mapInstance) {
            try { mapEl._mapInstance.remove(); } catch (e) {}
            mapEl._mapInstance = null;
        }
        if (mapInst) { try { mapInst.remove(); } catch (e) {} mapInst = null; }
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconUrl:       '/vendor/leaflet/images/marker-icon.png',
            iconRetinaUrl: '/vendor/leaflet/images/marker-icon-2x.png',
            shadowUrl:     '/vendor/leaflet/images/marker-shadow.png',
        });
        var map = L.map(mapEl).setView([16.0, 106.0], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '\u00a9 OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);
        mapInst            = map;
        mapEl._mapInstance = map;
        markerLyr          = L.layerGroup().addTo(map);
        refreshMarkers();
        setTimeout(function () { map.invalidateSize(); }, 100);
    }

    function loadLeaflet(cb) {
        if (window.L) { cb(); return; }
        var link = document.createElement('link');
        link.rel  = 'stylesheet';
        link.href = '/vendor/leaflet/leaflet.css';
        document.head.appendChild(link);
        var js = document.createElement('script');
        js.src    = '/vendor/leaflet/leaflet.js';
        js.onload = cb;
        document.head.appendChild(js);
    }

    function setupMapFilters() {
        var provSel = document.getElementById('nvm_prov');
        if (provSel && !provSel.dataset.ready) {
            provSel.dataset.ready = '1';
            Object.entries(PROV_OPTS).forEach(function (pair) {
                var opt = document.createElement('option');
                opt.value = pair[0]; opt.textContent = pair[1];
                provSel.appendChild(opt);
            });
            provSel.addEventListener('change', function () {
                filterProv    = this.value;
                filterSite    = '';
                filterCommune = '';
                var s = document.getElementById('nvm_site');    if (s) s.value = '';
                var c = document.getElementById('nvm_commune'); if (c) c.value = '';
                rebuildCommuneSel(filterProv || null);
                refreshMarkers(); updateClearBtn();
            });
        }

        var communeSel = document.getElementById('nvm_commune');
        if (communeSel && !communeSel.dataset.ready) {
            communeSel.dataset.ready = '1';
            rebuildCommuneSel(null);
            communeSel.addEventListener('change', function () {
                filterCommune = this.value;
                filterSite    = '';
                var s = document.getElementById('nvm_site'); if (s) s.value = '';
                refreshMarkers(); updateClearBtn();
            });
        }

        var siteSel = document.getElementById('nvm_site');
        if (siteSel && !siteSel.dataset.ready) {
            siteSel.dataset.ready = '1';
            Object.entries(SITE_OPTS).forEach(function (pair) {
                var opt = document.createElement('option');
                opt.value = pair[0]; opt.textContent = pair[1];
                siteSel.appendChild(opt);
            });
            siteSel.addEventListener('change', function () {
                filterSite = this.value;
                refreshMarkers(); updateClearBtn();
            });
        }

        var clearBtn = document.getElementById('nvm_clear');
        if (clearBtn && !clearBtn.dataset.ready) {
            clearBtn.dataset.ready = '1';
            clearBtn.addEventListener('click', function () {
                filterSite = ''; filterProv = ''; filterCommune = '';
                var s = document.getElementById('nvm_site');    if (s) s.value = '';
                var p = document.getElementById('nvm_prov');    if (p) p.value = '';
                var c = document.getElementById('nvm_commune'); if (c) c.value = '';
                rebuildCommuneSel(null);
                refreshMarkers(); updateClearBtn();
            });
        }

        updateCount();
        updateClearBtn();
    }

    window.addEventListener('nvm-map-activate', function () {
        setupMapFilters();
        if (!mapInitialized) {
            mapInitialized = true;
            loadLeaflet(function () { setTimeout(initMap, 80); });
        } else if (mapInst) {
            setTimeout(function () { mapInst.invalidateSize(); }, 80);
        }
    });

    // ── Campaign Performance Chart ────────────────────────────────────────────
    var CHART_LABELS      = @json($mockChartLabels);
    var CHART_REVENUE     = @json($mockChartRevenue);
    var CHART_IMPRESSIONS = @json($mockChartImpressions);
    var chartInst         = null;
    var chartInitialized  = false;

    function loadChartJs(cb) {
        if (window.Chart) { cb(); return; }
        var js = document.createElement('script');
        js.src    = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
        js.onload = cb;
        document.head.appendChild(js);
    }

    function initCampaignChart() {
        var canvas = document.getElementById('nvm_perf_chart');
        if (!canvas || !window.Chart) return;
        if (chartInst) { chartInst.destroy(); chartInst = null; }

        var isDark = document.documentElement.classList.contains('dark');
        var gridColor  = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
        var labelColor = isDark ? '#9ca3af' : '#6b7280';

        chartInst = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: CHART_LABELS,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Doanh thu (triệu VND)',
                        data: CHART_REVENUE,
                        backgroundColor: 'rgba(99,102,241,0.75)',
                        borderRadius: 4,
                        yAxisID: 'yRev',
                        order: 2,
                    },
                    {
                        type: 'line',
                        label: 'Impressions (nghìn)',
                        data: CHART_IMPRESSIONS,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249,115,22,0.12)',
                        borderWidth: 2,
                        pointRadius: 3,
                        tension: 0.35,
                        fill: true,
                        yAxisID: 'yImp',
                        order: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: labelColor, font: { size: 11 }, boxWidth: 14 },
                    },
                    tooltip: { padding: 10 },
                },
                scales: {
                    x: {
                        ticks: { color: labelColor, font: { size: 11 } },
                        grid:  { color: gridColor },
                    },
                    yRev: {
                        position: 'left',
                        title: { display: true, text: 'Triệu VND', color: '#6366f1', font: { size: 10 } },
                        ticks: { color: labelColor, font: { size: 11 } },
                        grid:  { color: gridColor },
                    },
                    yImp: {
                        position: 'right',
                        title: { display: true, text: 'Impressions (K)', color: '#f97316', font: { size: 10 } },
                        ticks: { color: labelColor, font: { size: 11 } },
                        grid:  { drawOnChartArea: false },
                    },
                },
            },
        });
    }

    window.addEventListener('nvm-chart-activate', function () {
        if (!chartInitialized) {
            chartInitialized = true;
            loadChartJs(function () { setTimeout(initCampaignChart, 60); });
        }
    });
}());
</script>
@endscript

<x-filament-panels::page
    @class([
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    @php $relationManagers = $this->getRelationManagers(); @endphp

    {{-- Top row: Overview (left) + Campaigns (right) ──────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:3fr 2fr;gap:1.5rem;align-items:start;">

        {{-- ── LEFT: Network Info / Media Owner infolist ───────────────────── --}}
        <div>
            @if ($this->hasInfolist())
                {{ $this->infolist }}
            @endif
        </div>

        {{-- ── RIGHT: Campaigns ────────────────────────────────────────────── --}}
        <div>
            <div
                x-data="{ camTab: 'list' }"
                x-init="$watch('camTab', function(v) { if (v === 'chart') window.dispatchEvent(new CustomEvent('nvm-chart-activate')); })"
                class="fi-fo-tabs fi-contained flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <x-filament::tabs :contained="true">
                    <x-filament::tabs.item
                        icon="heroicon-o-megaphone"
                        alpineActive="camTab === 'list'"
                        @click="camTab = 'list'"
                    >
                        Chiến dịch
                    </x-filament::tabs.item>
                    <x-filament::tabs.item
                        icon="heroicon-o-chart-bar"
                        alpineActive="camTab === 'chart'"
                        @click="camTab = 'chart'"
                    >
                        Performance
                    </x-filament::tabs.item>
                </x-filament::tabs>

                {{-- Tab: Campaign list --}}
                <div x-show="camTab === 'list'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Chiến dịch</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Advertiser</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Thời gian</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Budget</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Impressions</th>
                                    <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($mockCampaigns as $c)
                                    @php
                                        $statusColors = [
                                            'active'    => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                            'completed' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
                                            'paused'    => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                        ];
                                        $statusLabels = ['active' => 'Active', 'completed' => 'Completed', 'paused' => 'Paused'];
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75">
                                        <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white text-xs">{{ $c['name'] }}</td>
                                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400 text-xs">{{ $c['advertiser'] }}</td>
                                        <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($c['start'])->format('d/m/y') }}
                                            –
                                            {{ \Carbon\Carbon::parse($c['end'])->format('d/m/y') }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300 text-xs tabular-nums">
                                            {{ number_format($c['budget'] / 1_000_000, 0) }}M
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300 text-xs tabular-nums">
                                            {{ number_format($c['impressions']) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$c['status']] ?? '' }}">
                                                {{ $statusLabels[$c['status']] ?? $c['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="px-4 py-2 text-xs text-gray-400 dark:text-gray-500 italic border-t border-gray-100 dark:border-white/5">
                        * Dữ liệu demo — sẽ được thay bằng dữ liệu thực khi module Campaign sẵn sàng.
                    </p>
                </div>

                {{-- Tab: Performance chart --}}
                <div x-show="camTab === 'chart'" x-cloak class="p-5">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-3">
                        Doanh thu &amp; Impressions — 12 tháng gần nhất
                    </p>
                    <div wire:ignore style="position:relative;height:320px;">
                        <canvas id="nvm_perf_chart"></canvas>
                    </div>
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500 italic text-center">
                        * Dữ liệu demo
                    </p>
                </div>

            </div>
        </div>

    </div>

    {{-- Bottom row: Screens (full width) ───────────────────────────────────────────────────── --}}
    <div
        x-data="{ tab: 'list' }"
        x-init="$watch('tab', function(val) { if (val === 'map') window.dispatchEvent(new CustomEvent('nvm-map-activate')); })"
        class="fi-fo-tabs fi-contained flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
                <x-filament::tabs :contained="true">
                    <x-filament::tabs.item
                        icon="heroicon-o-list-bullet"
                        alpineActive="tab === 'list'"
                        @click="tab = 'list'"
                    >
                        Danh sách màn hình
                    </x-filament::tabs.item>
                    <x-filament::tabs.item
                        icon="heroicon-o-map-pin"
                        alpineActive="tab === 'map'"
                        @click="tab = 'map'"
                    >
                        Bản đồ
                    </x-filament::tabs.item>
                </x-filament::tabs>

                {{-- Tab: List --}}
                <div x-show="tab === 'list'" x-cloak class="nvm-list-tab">
                    @if (count($relationManagers))
                        <x-filament-panels::resources.relation-managers
                            :active-locale="isset($activeLocale) ? $activeLocale : null"
                            :active-manager="array_key_first($relationManagers)"
                            :content-tab-label="$this->getContentTabLabel()"
                            :content-tab-icon="$this->getContentTabIcon()"
                            :content-tab-position="$this->getContentTabPosition()"
                            :managers="$relationManagers"
                            :owner-record="$record"
                            :page-class="static::class"
                        />
                    @endif
                </div>

                {{-- Tab: Map --}}
                <div x-show="tab === 'map'" x-cloak class="p-6 flex flex-col gap-4">

                    {{-- Filter bar --}}
                    <div class="flex flex-wrap items-center gap-3">

                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Tỉnh/Thành:</label>
                            <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 ring-gray-950/10 dark:ring-white/20">
                                <select id="nvm_prov" class="fi-select-input block border-none bg-transparent py-1 ps-2.5 pe-7 text-sm text-gray-950 transition duration-75 focus:ring-0 dark:text-white [&_option]:bg-white [&_option]:dark:bg-gray-900">
                                    <option value="">Tất cả tỉnh</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Phường/Xã:</label>
                            <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 ring-gray-950/10 dark:ring-white/20">
                                <select id="nvm_commune" class="fi-select-input block border-none bg-transparent py-1 ps-2.5 pe-7 text-sm text-gray-950 transition duration-75 focus:ring-0 dark:text-white [&_option]:bg-white [&_option]:dark:bg-gray-900">
                                    <option value="">Tất cả phường/xã</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Site:</label>
                            <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 ring-gray-950/10 dark:ring-white/20">
                                <select id="nvm_site" class="fi-select-input block border-none bg-transparent py-1 ps-2.5 pe-7 text-sm text-gray-950 transition duration-75 focus:ring-0 dark:text-white [&_option]:bg-white [&_option]:dark:bg-gray-900">
                                    <option value="">Tất cả site</option>
                                </select>
                            </div>
                        </div>

                        <button id="nvm_clear" type="button" style="display:none"
                            class="text-xs font-semibold text-danger-600 hover:text-danger-500 dark:text-danger-400 transition duration-75">
                            Xoá bộ lọc
                        </button>

                        <span class="ms-auto text-xs text-gray-400 dark:text-gray-500">
                            <span id="nvm_gw">0</span> / <span id="nvm_gt">0</span> màn hình có tọa độ GPS
                        </span>
                    </div>

                    {{-- Map --}}
                    <div wire:ignore>
                        <div id="nvm_map" style="height:480px;width:100%;z-index:0;border-radius:0.5rem;overflow:hidden;"></div>
                    </div>

                    <p class="text-xs text-gray-400 dark:text-gray-500 text-center">
                        Nhấn vào marker để xem danh sách màn hình tại site đó.
                    </p>
                </div>

    </div>

</x-filament-panels::page>
