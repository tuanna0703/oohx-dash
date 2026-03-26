@php
    $site = $getRecord();
    $siteKey = 'ssp_' . preg_replace('/[^a-z0-9]/i', '_', $site->id);

    $screens = $site->screens()->with(['inventory'])->get();

    $screensData = $screens->map(fn($s) => [
        'id'          => $s->id,
        'external_id' => $s->external_id ?? '—',
        'name'        => $s->name ?? '—',
        'floor_cpm'   => $s->inventory?->floor_cpm
            ? number_format((float) $s->inventory->floor_cpm, 2) . ' ' . ($s->inventory->floor_cpm_currency ?? 'VND')
            : '—',
        'active'      => (bool) $s->active,
        'description' => $s->description ?? '',
    ])->values()->toArray();

    $siteLat  = $site->lat  ? (float) $site->lat  : null;
    $siteLon  = $site->lon  ? (float) $site->lon  : null;
    $siteName = e($site->name ?? 'Site');
@endphp

{{-- Single root wrapper required by Livewire --}}
<div>

<script>
    window['{{ $siteKey }}'] = {
        screens: @json($screensData),
        lat:     {{ $siteLat ?? 'null' }},
        lon:     {{ $siteLon ?? 'null' }},
        name:    @json($siteName),
    };
</script>

@once
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('siteScreensPanel', (dataKey) => ({
                allScreens:   [],
                siteLat:      null,
                siteLon:      null,
                siteName:     '',
                tab:          'list',
                filterActive: '',   // '' | 'active' | 'inactive'
                mapInstance:  null,

                init() {
                    const d = window[dataKey] || {};
                    this.allScreens = d.screens || [];
                    this.siteLat    = d.lat;
                    this.siteLon    = d.lon;
                    this.siteName   = d.name || '';

                    this.$watch('filtered', () => {
                        if (this.tab === 'map' && this.mapInstance) this.refreshMarker();
                    });
                },

                get filtered() {
                    return this.allScreens.filter(s => {
                        if (this.filterActive === 'active'   && !s.active)  return false;
                        if (this.filterActive === 'inactive' &&  s.active)  return false;
                        return true;
                    });
                },

                switchTab(t) {
                    this.tab = t;
                    if (t === 'map') {
                        this.$nextTick(() => {
                            if (!this.mapInstance) {
                                this.initMap();
                            } else {
                                this.mapInstance.invalidateSize();
                                this.refreshMarker();
                            }
                        });
                    }
                },

                initMap() {
                    const el = this.$refs.mapEl;
                    if (!el || !this.siteLat || !this.siteLon) return;

                    if (this.mapInstance) { this.mapInstance.remove(); this.mapInstance = null; }

                    delete L.Icon.Default.prototype._getIconUrl;
                    L.Icon.Default.mergeOptions({
                        iconUrl:       '/vendor/leaflet/images/marker-icon.png',
                        iconRetinaUrl: '/vendor/leaflet/images/marker-icon-2x.png',
                        shadowUrl:     '/vendor/leaflet/images/marker-shadow.png',
                    });

                    const map = L.map(el).setView([this.siteLat, this.siteLon], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(map);

                    this.mapInstance = map;
                    this.refreshMarker();
                },

                refreshMarker() {
                    if (!this.mapInstance || !this.siteLat || !this.siteLon) return;

                    this.mapInstance.eachLayer(layer => {
                        if (layer instanceof L.Marker || layer instanceof L.Circle) {
                            this.mapInstance.removeLayer(layer);
                        }
                    });

                    const rows = this.filtered.map(s =>
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
                        <div style="min-width:260px">
                            <b style="font-size:13px">${this.siteName}</b>
                            <div style="font-size:11px;color:#6b7280;margin-bottom:6px">${this.filtered.length} màn hình</div>
                            ${this.filtered.length > 0 ? `
                            <table style="width:100%;border-collapse:collapse">
                                <thead><tr style="background:#f3f4f6">
                                    <th style="padding:2px 6px;text-align:left;font-size:11px">ID</th>
                                    <th style="padding:2px 6px;text-align:left;font-size:11px">Tên</th>
                                    <th style="padding:2px 6px;text-align:left;font-size:11px">Trạng thái</th>
                                </tr></thead>
                                <tbody>${rows}</tbody>
                            </table>` : '<p style="font-size:12px;color:#9ca3af">Không có màn hình phù hợp.</p>'}
                        </div>`;

                    L.marker([this.siteLat, this.siteLon])
                        .addTo(this.mapInstance)
                        .bindPopup(popup, { maxWidth: 320 })
                        .openPopup();

                    L.circle([this.siteLat, this.siteLon], {
                        radius: 80, color: '#3B82F6', fillColor: '#93C5FD', fillOpacity: 0.25, weight: 2,
                    }).addTo(this.mapInstance);
                },
            }));
        });
    </script>
@endonce

<div
    x-data="siteScreensPanel('{{ $siteKey }}')"
    class="space-y-3"
>
    {{-- Filter bar --}}
    <div class="flex flex-wrap gap-3 items-end p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="min-w-[180px]">
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Trạng thái</label>
            <select x-model="filterActive"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-white text-sm shadow-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 py-1.5 px-2">
                <option value="">-- Tất cả --</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <button
            @click="filterActive = ''"
            x-show="filterActive !== ''"
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
                        <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mô tả</th>
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
                            <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400 text-xs" x-text="s.description || '—'"></td>
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
                            <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-400 dark:text-gray-500 italic">
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
        <template x-if="!siteLat || !siteLon">
            <p class="text-sm italic text-gray-400 dark:text-gray-500 py-4 text-center">Site này chưa có tọa độ GPS.</p>
        </template>
        <template x-if="siteLat && siteLon">
            <div wire:ignore>
                <div x-ref="mapEl"
                     style="height:400px;width:100%;z-index:0;border-radius:0.75rem;overflow:hidden;border:1px solid rgba(229,231,235,1)">
                </div>
            </div>
        </template>
        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 text-center">
            Nhấn vào marker để xem danh sách màn hình.
        </p>
    </div>
</div>

</div>{{-- /single root wrapper --}}
