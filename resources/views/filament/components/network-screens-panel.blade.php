@php
    $network = $getRecord();
    $networkKey = 'nsp_' . preg_replace('/[^a-z0-9]/i', '_', $network->id);

    $screens = \App\Models\Screen::whereHas('inventory', fn($q) => $q->where('network_id', $network->id))
        ->with(['site.province', 'site.commune', 'inventory'])
        ->get();

    $screensData = $screens->map(fn($s) => [
        'id'            => $s->id,
        'external_id'   => $s->external_id ?? '—',
        'name'          => $s->name ?? '—',
        'site_id'       => (string) ($s->site_id ?? ''),
        'site_name'     => $s->site?->name ?? '—',
        'site_lat'      => $s->site?->lat  ? (float) $s->site->lat  : null,
        'site_lon'      => $s->site?->lon  ? (float) $s->site->lon  : null,
        'province_id'   => $s->site?->province_id ? (string) $s->site->province_id : '',
        'province_name' => $s->site?->province?->name ?? '',
        'commune_id'    => $s->site?->commune_id  ? (string) $s->site->commune_id  : '',
        'commune_name'  => $s->site?->commune?->full_name ?? '',
        'floor_cpm'     => $s->inventory?->floor_cpm
            ? number_format((float) $s->inventory->floor_cpm, 2) . ' ' . ($s->inventory->floor_cpm_currency ?? 'VND')
            : '—',
        'active'        => (bool) $s->active,
    ])->values()->toArray();

    $siteOpts = $screens
        ->filter(fn($s) => $s->site_id && $s->site)
        ->mapWithKeys(fn($s) => [(string) $s->site_id => $s->site->name])
        ->sortBy(fn($v) => $v)->toArray();

    $provinceOpts = $screens
        ->filter(fn($s) => $s->site?->province_id)
        ->mapWithKeys(fn($s) => [(string) $s->site->province_id => $s->site->province?->name ?? '?'])
        ->filter()->sortBy(fn($v) => $v)->toArray();

    $communeOpts = $screens
        ->filter(fn($s) => $s->site?->commune_id)
        ->mapWithKeys(fn($s) => [(string) $s->site->commune_id => $s->site->commune?->full_name ?? '?'])
        ->filter()->sortBy(fn($v) => $v)->toArray();
@endphp

{{-- Pass data via window variable keyed by network --}}
<script>
    window['{{ $networkKey }}'] = {
        screens:      @json($screensData),
        siteOpts:     @json($siteOpts),
        provinceOpts: @json($provinceOpts),
        communeOpts:  @json($communeOpts),
    };
</script>

@once
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('networkScreensPanel', (dataKey) => ({
                allScreens:      [],
                siteOpts:        {},
                provinceOpts:    {},
                communeOpts:     {},
                tab:             'list',
                filterSite:      '',
                filterProvince:  '',
                filterCommune:   '',
                mapInstance:     null,
                markerLayer:     null,

                init() {
                    const d = window[dataKey] || {};
                    this.allScreens     = d.screens      || [];
                    this.siteOpts       = d.siteOpts     || {};
                    this.provinceOpts   = d.provinceOpts || {};
                    this.communeOpts    = d.communeOpts  || {};

                    this.$watch('filterProvince', () => { this.filterCommune = ''; });
                    this.$watch('filtered', () => {
                        if (this.tab === 'map' && this.mapInstance) this.refreshMarkers();
                    });
                },

                get filtered() {
                    return this.allScreens.filter(s => {
                        if (this.filterSite     && s.site_id     !== this.filterSite)     return false;
                        if (this.filterProvince && s.province_id !== this.filterProvince) return false;
                        if (this.filterCommune  && s.commune_id  !== this.filterCommune)  return false;
                        return true;
                    });
                },

                get availableCommunes() {
                    if (!this.filterProvince) return this.communeOpts;
                    const result = {};
                    this.allScreens
                        .filter(s => s.province_id === this.filterProvince && s.commune_id)
                        .forEach(s => { result[s.commune_id] = s.commune_name; });
                    return result;
                },

                switchTab(t) {
                    this.tab = t;
                    if (t === 'map') {
                        this.$nextTick(() => {
                            if (!this.mapInstance) {
                                this.initMap();
                            } else {
                                this.mapInstance.invalidateSize();
                                this.refreshMarkers();
                            }
                        });
                    }
                },

                initMap() {
                    const el = this.$refs.mapEl;
                    if (!el) return;

                    if (this.mapInstance) { this.mapInstance.remove(); this.mapInstance = null; }

                    delete L.Icon.Default.prototype._getIconUrl;
                    L.Icon.Default.mergeOptions({
                        iconUrl:       '/vendor/leaflet/images/marker-icon.png',
                        iconRetinaUrl: '/vendor/leaflet/images/marker-icon-2x.png',
                        shadowUrl:     '/vendor/leaflet/images/marker-shadow.png',
                    });

                    const map = L.map(el).setView([16.0, 106.0], 6);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(map);

                    this.mapInstance  = map;
                    this.markerLayer  = L.layerGroup().addTo(map);
                    this.refreshMarkers();
                },

                refreshMarkers() {
                    if (!this.mapInstance || !this.markerLayer) return;
                    this.markerLayer.clearLayers();

                    const sites = {};
                    this.filtered.forEach(s => {
                        if (!s.site_lat || !s.site_lon) return;
                        if (!sites[s.site_id]) {
                            sites[s.site_id] = { lat: s.site_lat, lon: s.site_lon, name: s.site_name, screens: [] };
                        }
                        sites[s.site_id].screens.push(s);
                    });

                    const bounds = [];
                    Object.values(sites).forEach(site => {
                        const rows = site.screens.map(s =>
                            `<tr>
                                <td style="padding:2px 6px;font-family:monospace;font-size:11px">${s.external_id}</td>
                                <td style="padding:2px 6px;font-size:12px">${s.name}</td>
                                <td style="padding:2px 6px">
                                    <span style="padding:1px 6px;border-radius:9999px;font-size:11px;background:${s.active ? '#dcfce7' : '#fee2e2'};color:${s.active ? '#166534' : '#991b1b'}">
                                        ${s.active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                            </tr>`
                        ).join('');

                        const popup = `
                            <div style="min-width:280px">
                                <b style="font-size:13px">${site.name}</b>
                                <div style="font-size:11px;color:#6b7280;margin-bottom:6px">${site.screens.length} màn hình</div>
                                <table style="width:100%;border-collapse:collapse">
                                    <thead><tr style="background:#f3f4f6">
                                        <th style="padding:2px 6px;text-align:left;font-size:11px">ID</th>
                                        <th style="padding:2px 6px;text-align:left;font-size:11px">Tên</th>
                                        <th style="padding:2px 6px;text-align:left;font-size:11px">Trạng thái</th>
                                    </tr></thead>
                                    <tbody>${rows}</tbody>
                                </table>
                            </div>`;

                        L.marker([site.lat, site.lon]).addTo(this.markerLayer).bindPopup(popup, { maxWidth: 350 });
                        bounds.push([site.lat, site.lon]);
                    });

                    if (bounds.length > 0) {
                        if (bounds.length === 1) {
                            this.mapInstance.setView(bounds[0], 15);
                        } else {
                            this.mapInstance.fitBounds(bounds, { padding: [40, 40] });
                        }
                    }
                },
            }));
        });
    </script>
@endonce

<div
    x-data="networkScreensPanel('{{ $networkKey }}')"
    class="space-y-3"
>
    {{-- Filter bar --}}
    <div class="flex flex-wrap gap-3 items-end p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">

        <div class="flex-1 min-w-[160px]">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Site</label>
            <select x-model="filterSite"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-white text-sm shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 py-1.5 px-2">
                <option value="">-- Tất cả --</option>
                <template x-for="[id, name] in Object.entries(siteOpts)" :key="id">
                    <option :value="id" x-text="name"></option>
                </template>
            </select>
        </div>

        <div class="flex-1 min-w-[160px]">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tỉnh / Thành phố</label>
            <select x-model="filterProvince"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-white text-sm shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 py-1.5 px-2">
                <option value="">-- Tất cả --</option>
                <template x-for="[id, name] in Object.entries(provinceOpts)" :key="id">
                    <option :value="id" x-text="name"></option>
                </template>
            </select>
        </div>

        <div class="flex-1 min-w-[160px]">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Phường / Xã</label>
            <select x-model="filterCommune"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-white text-sm shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 py-1.5 px-2">
                <option value="">-- Tất cả --</option>
                <template x-for="[id, name] in Object.entries(availableCommunes)" :key="id">
                    <option :value="id" x-text="name"></option>
                </template>
            </select>
        </div>

        <button
            @click="filterSite = ''; filterProvince = ''; filterCommune = ''"
            x-show="filterSite || filterProvince || filterCommune"
            class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline self-end pb-2">
            Xoá bộ lọc
        </button>
    </div>

    {{-- Tab bar --}}
    <div class="flex items-center justify-between">
        <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <button @click="tab = 'list'"
                    :class="tab === 'list'
                        ? 'bg-white dark:bg-gray-700 shadow text-gray-900 dark:text-white'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-all flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                Danh sách
            </button>
            <button @click="switchTab('map')"
                    :class="tab === 'map'
                        ? 'bg-white dark:bg-gray-700 shadow text-gray-900 dark:text-white'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-all flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                Bản đồ
            </button>
        </div>

        <span class="text-sm text-gray-500 dark:text-gray-400">
            <span x-text="filtered.length" class="font-medium text-gray-700 dark:text-gray-200"></span>
            <span x-show="filtered.length < allScreens.length">
                / <span x-text="allScreens.length"></span>
            </span>
            màn hình
        </span>
    </div>

    {{-- List view --}}
    <div x-show="tab === 'list'">
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Screen ID</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tên màn hình</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Site</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tỉnh / Thành</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Phường / Xã</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Floor CPM</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="s in filtered" :key="s.id">
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60 transition-colors">
                            <td class="px-3 py-2.5">
                                <span class="font-mono text-xs text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded"
                                      x-text="s.external_id"></span>
                            </td>
                            <td class="px-3 py-2.5 text-gray-800 dark:text-gray-200 font-medium" x-text="s.name"></td>
                            <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400" x-text="s.site_name"></td>
                            <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400" x-text="s.province_name || '—'"></td>
                            <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400 text-xs" x-text="s.commune_name || '—'"></td>
                            <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400 font-mono text-xs" x-text="s.floor_cpm"></td>
                            <td class="px-3 py-2.5">
                                <span :class="s.active
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'"
                                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                      x-text="s.active ? 'Active' : 'Inactive'">
                                </span>
                            </td>
                        </tr>
                    </template>

                    <template x-if="filtered.length === 0">
                        <tr>
                            <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-400 dark:text-gray-500 italic">
                                Không có màn hình phù hợp với bộ lọc
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Map view --}}
    <div x-show="tab === 'map'" x-cloak>
        <div wire:ignore>
            <div x-ref="mapEl"
                 style="height:520px;width:100%;z-index:0;border-radius:0.75rem;overflow:hidden;border:1px solid rgba(229,231,235,1)">
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 text-center">
            Nhấn vào marker để xem danh sách màn hình tại site đó.
            <span x-show="filtered.filter(s => s.site_lat && s.site_lon).length < filtered.length">
                (<span x-text="filtered.length - filtered.filter(s => s.site_lat && s.site_lon).length"></span> màn hình không có tọa độ GPS.)
            </span>
        </p>
    </div>
</div>
