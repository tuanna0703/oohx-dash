@once
<style>
.svm-list-tab .fi-ta-ctn { border-radius: 0 !important; box-shadow: none !important; }
</style>
@endonce

@php
    $screensData = $this->screensMapData;
    $site        = $this->record;

    // ── Mock campaign data (replace with real query when Campaign module is ready) ──
    $mockCampaigns = [
        ['name' => 'Tết Sale 2025',      'advertiser' => 'Vinamilk',      'start' => '2025-01-10', 'end' => '2025-02-10', 'budget' => 20_000_000, 'status' => 'completed'],
        ['name' => 'Summer Blast',       'advertiser' => 'Pepsi Vietnam', 'start' => '2025-04-01', 'end' => '2025-05-31', 'budget' => 15_000_000, 'status' => 'active'],
        ['name' => 'Brand Awareness Q1', 'advertiser' => 'Samsung VN',    'start' => '2025-01-01', 'end' => '2025-03-31', 'budget' => 30_000_000, 'status' => 'completed'],
        ['name' => 'Back to School',     'advertiser' => 'Bitis',         'start' => '2025-08-01', 'end' => '2025-09-15', 'budget' => 10_000_000, 'status' => 'active'],
    ];

    // ── Chart: last 12 months performance (mock) ──────────────────────────────
    $mockChartLabels      = ['Apr/24','May/24','Jun/24','Jul/24','Aug/24','Sep/24','Oct/24','Nov/24','Dec/24','Jan/25','Feb/25','Mar/25'];
    $mockChartRevenue     = [12, 18, 24, 16, 25, 28, 35, 40, 58, 42, 30, 38];
    $mockChartImpressions = [48, 72, 96, 64, 100, 112, 140, 160, 232, 168, 120, 152];
@endphp

@script
<script>
(function () {
    // ── Map ──────────────────────────────────────────────────────────────────
    var SCREENS   = @json($screensData);
    var SITE_LAT  = {{ $site->lat ? (float) $site->lat : 'null' }};
    var SITE_LON  = {{ $site->lon ? (float) $site->lon : 'null' }};
    var SITE_NAME = @json($site->name ?? '—');

    var mapInst        = null;
    var mapInitialized = false;

    function initMap() {
        var mapEl = document.getElementById('svm_map');
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
        var map = L.map(mapEl).setView(
            SITE_LAT && SITE_LON ? [SITE_LAT, SITE_LON] : [16.0, 106.0],
            SITE_LAT && SITE_LON ? 15 : 6
        );
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '\u00a9 OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);
        mapInst            = map;
        mapEl._mapInstance = map;

        if (SITE_LAT && SITE_LON && SCREENS.length) {
            var rows = SCREENS.map(function (s) {
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
                '<b style="font-size:13px">' + SITE_NAME + '</b>' +
                '<div style="font-size:11px;color:#6b7280;margin-bottom:6px">' + SCREENS.length + ' screens</div>' +
                '<table style="width:100%;border-collapse:collapse">' +
                '<thead><tr style="background:#f3f4f6">' +
                '<th style="padding:2px 6px;text-align:left;font-size:11px">ID</th>' +
                '<th style="padding:2px 6px;text-align:left;font-size:11px">Name</th>' +
                '<th style="padding:2px 6px;text-align:left;font-size:11px">Status</th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table></div>';
            L.marker([SITE_LAT, SITE_LON]).addTo(map).bindPopup(popup, { maxWidth: 360 }).openPopup();
        }

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

    window.addEventListener('svm-map-activate', function () {
        if (!mapInitialized) {
            mapInitialized = true;
            loadLeaflet(function () { setTimeout(initMap, 80); });
        } else if (mapInst) {
            setTimeout(function () { mapInst.invalidateSize(); }, 80);
        }
    });

    // ── Performance Chart ─────────────────────────────────────────────────────
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

    function initSiteChart() {
        var canvas = document.getElementById('svm_perf_chart');
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
                        label: 'Revenue (million VND)',
                        data: CHART_REVENUE,
                        backgroundColor: 'rgba(99,102,241,0.75)',
                        borderRadius: 4,
                        yAxisID: 'yRev',
                        order: 2,
                    },
                    {
                        type: 'line',
                        label: 'Impressions (K)',
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
                        title: { display: true, text: 'Million VND', color: '#6366f1', font: { size: 10 } },
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

    window.addEventListener('svm-chart-activate', function () {
        if (!chartInitialized) {
            chartInitialized = true;
            loadChartJs(function () {
                setTimeout(function () {
                    initSiteChart();
                    setTimeout(function () { if (chartInst) chartInst.resize(); }, 150);
                }, 60);
            });
        } else if (chartInst) {
            setTimeout(function () { chartInst.resize(); }, 60);
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
    @php
        $relationManagers = $this->getRelationManagers();
        $site  = $this->record;
        $owner = $site->owner;

        $statusBadgeColor = match($site->status) {
            'active' => ['bg' => '#dcfce7', 'text' => '#166534'],
            'paused' => ['bg' => '#fef9c3', 'text' => '#854d0e'],
            'closed' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
            default  => ['bg' => '#f3f4f6', 'text' => '#374151'],
        };
        $ownerStatusColor = match($owner?->status) {
            'active'    => ['bg' => '#dcfce7', 'text' => '#166534'],
            'suspended' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
            default     => ['bg' => '#fef9c3', 'text' => '#854d0e'],
        };
    @endphp

    {{-- ── Single 4-tab card: General / Media Owner / Campaigns / Performance ── --}}
    <div
        x-data="{ activeTab: 'general' }"
        x-init="$watch('activeTab', function(v) { if (v === 'chart') window.dispatchEvent(new CustomEvent('svm-chart-activate')); })"
        class="fi-fo-tabs fi-contained flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <x-filament::tabs :contained="true">
            <x-filament::tabs.item icon="heroicon-o-map-pin"           alpineActive="activeTab === 'general'"   @click="activeTab = 'general'">General</x-filament::tabs.item>
            <x-filament::tabs.item icon="heroicon-o-building-office-2" alpineActive="activeTab === 'owner'"     @click="activeTab = 'owner'">Media Owner</x-filament::tabs.item>
            <x-filament::tabs.item icon="heroicon-o-megaphone"         alpineActive="activeTab === 'campaigns'" @click="activeTab = 'campaigns'">Campaigns</x-filament::tabs.item>
            <x-filament::tabs.item icon="heroicon-o-chart-bar"         alpineActive="activeTab === 'chart'"     @click="activeTab = 'chart'">Performance</x-filament::tabs.item>
        </x-filament::tabs>

        {{-- Tab: General --}}
        <div x-show="activeTab === 'general'" x-cloak style="padding:1.5rem;">
            <dl style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;">
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Site Name</dt>
                    <dd style="margin-top:4px;font-size:14px;font-weight:600;color:#111827;">{{ $site->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Site ID</dt>
                    <dd style="margin-top:4px;font-size:14px;font-family:monospace;color:#111827;">{{ $site->external_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Status</dt>
                    <dd style="margin-top:4px;">
                        <span style="padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:{{ $statusBadgeColor['bg'] }};color:{{ $statusBadgeColor['text'] }}">
                            {{ ucfirst($site->status ?? 'unknown') }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Province</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $site->province?->full_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">District</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $site->commune?->full_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">City</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $site->city ?? '—' }}</dd>
                </div>
                <div style="grid-column:1/-1;">
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Address</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $site->address ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Latitude</dt>
                    <dd style="margin-top:4px;font-size:14px;font-family:monospace;color:#111827;">{{ $site->lat ? number_format((float) $site->lat, 7) : '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Longitude</dt>
                    <dd style="margin-top:4px;font-size:14px;font-family:monospace;color:#111827;">{{ $site->lon ? number_format((float) $site->lon, 7) : '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Total Screens</dt>
                    <dd style="margin-top:4px;font-size:14px;font-weight:600;color:#111827;">{{ $this->totalScreens }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Region</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $site->region ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Country</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $site->country ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Last Modified</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $site->updated_at?->format('M j, Y, g:i A') ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Tab: Media Owner --}}
        <div x-show="activeTab === 'owner'" x-cloak style="padding:1.5rem;">
            @if ($owner?->logo_url)
                <img src="{{ $owner->logo_url }}" alt="Logo" style="height:56px;object-fit:contain;margin-bottom:1.25rem;">
            @endif
            <dl style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;">
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Name</dt>
                    <dd style="margin-top:4px;font-size:14px;font-weight:600;color:#111827;">{{ $owner?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Status</dt>
                    <dd style="margin-top:4px;">
                        @if ($owner?->status)
                            <span style="padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:600;background:{{ $ownerStatusColor['bg'] }};color:{{ $ownerStatusColor['text'] }}">
                                {{ ucfirst($owner->status) }}
                            </span>
                        @else
                            <span style="font-size:14px;color:#9ca3af;">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Founded</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $owner?->founded ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Website</dt>
                    <dd style="margin-top:4px;font-size:14px;">
                        @if ($owner?->website)
                            <a href="{{ $owner->website }}" target="_blank" style="color:#6366f1;text-decoration:none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">{{ $owner->website }}</a>
                        @else
                            <span style="color:#9ca3af;">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Email</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $owner?->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Phone</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $owner?->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Member Since</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $owner?->created_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
                @if ($owner?->tagline)
                <div style="grid-column:1/-1;">
                    <dt style="font-size:12px;font-weight:500;color:#6b7280;">Tagline</dt>
                    <dd style="margin-top:4px;font-size:14px;color:#111827;">{{ $owner->tagline }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Tab: Campaigns --}}
        <div x-show="activeTab === 'campaigns'" x-cloak>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:1px solid #e5e7eb;background:#f9fafb;">
                            <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;">Campaign</th>
                            <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;">Advertiser</th>
                            <th style="padding:10px 16px;text-align:left;font-size:12px;font-weight:600;color:#6b7280;">Period</th>
                            <th style="padding:10px 16px;text-align:right;font-size:12px;font-weight:600;color:#6b7280;">Budget</th>
                            <th style="padding:10px 16px;text-align:center;font-size:12px;font-weight:600;color:#6b7280;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mockCampaigns as $c)
                            @php
                                $statusStyles = [
                                    'active'    => 'background:#dcfce7;color:#166534;',
                                    'completed' => 'background:#f3f4f6;color:#374151;',
                                    'paused'    => 'background:#fef9c3;color:#854d0e;',
                                ];
                                $statusLabels = ['active' => 'Active', 'completed' => 'Completed', 'paused' => 'Paused'];
                            @endphp
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:10px 16px;font-size:14px;font-weight:500;color:#111827;">{{ $c['name'] }}</td>
                                <td style="padding:10px 16px;font-size:14px;color:#6b7280;">{{ $c['advertiser'] }}</td>
                                <td style="padding:10px 16px;font-size:14px;color:#6b7280;white-space:nowrap;">
                                    {{ \Carbon\Carbon::parse($c['start'])->format('d/m/y') }} – {{ \Carbon\Carbon::parse($c['end'])->format('d/m/y') }}
                                </td>
                                <td style="padding:10px 16px;font-size:14px;color:#374151;text-align:right;font-variant-numeric:tabular-nums;">
                                    {{ number_format($c['budget'] / 1_000_000, 0) }}M
                                </td>
                                <td style="padding:10px 16px;text-align:center;">
                                    <span style="padding:2px 10px;border-radius:9999px;font-size:12px;font-weight:600;{{ $statusStyles[$c['status']] ?? '' }}">
                                        {{ $statusLabels[$c['status']] ?? $c['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p style="padding:8px 16px;font-size:12px;color:#9ca3af;font-style:italic;border-top:1px solid #f3f4f6;">
                * Demo data — will be replaced with real data when the Campaign module is ready.
            </p>
        </div>

        {{-- Tab: Performance chart --}}
        <div x-show="activeTab === 'chart'" x-cloak style="min-width:0;padding:1.5rem;">
            <p style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:12px;">
                Revenue &amp; Impressions — Last 12 months
            </p>
            <div wire:ignore style="position:relative;width:100%;height:320px;overflow:hidden;">
                <canvas id="svm_perf_chart" style="display:block;width:100%!important;height:100%!important;"></canvas>
            </div>
            <p style="margin-top:12px;font-size:12px;color:#9ca3af;font-style:italic;text-align:center;">* Demo data</p>
        </div>

    </div>

    {{-- ── Screens (list + map) — full width below ─────────────────────────── --}}
    <div
        x-data="{ tab: 'list' }"
        x-init="$watch('tab', function(val) { if (val === 'map') window.dispatchEvent(new CustomEvent('svm-map-activate')); })"
        class="fi-fo-tabs fi-contained flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <x-filament::tabs :contained="true">
            <x-filament::tabs.item
                icon="heroicon-o-list-bullet"
                alpineActive="tab === 'list'"
                @click="tab = 'list'"
            >
                Screens
            </x-filament::tabs.item>
            <x-filament::tabs.item
                icon="heroicon-o-map-pin"
                alpineActive="tab === 'map'"
                @click="tab = 'map'"
            >
                Map
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{-- Tab: List --}}
        <div x-show="tab === 'list'" x-cloak class="svm-list-tab">
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

            <div class="flex flex-wrap items-center gap-3">
                @if ($site->lat && $site->lon)
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        GPS: {{ number_format((float) $site->lat, 5) }}, {{ number_format((float) $site->lon, 5) }}
                    </span>
                @else
                    <span class="text-xs" style="color:#d97706;">No GPS coordinates recorded for this site.</span>
                @endif
                <span class="ms-auto text-xs text-gray-400 dark:text-gray-500">
                    {{ $this->totalScreens }} screens at this site
                </span>
            </div>

            <div wire:ignore>
                <div id="svm_map" style="height:480px;width:100%;z-index:0;border-radius:0.5rem;overflow:hidden;"></div>
            </div>

            <p class="text-xs text-gray-400 dark:text-gray-500 text-center">
                Click the marker to view screens at this site.
            </p>
        </div>

    </div>

</x-filament-panels::page>
