@php
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
        'active'        => (bool) $s->active,
        'view_url'      => \App\Filament\Resources\ScreenResource::getUrl('view', ['record' => $s->id]),
    ])->values()->all();

    $siteOpts = $screens
        ->filter(fn($s) => $s->site_id && $s->site)
        ->mapWithKeys(fn($s) => [(string) $s->site_id => $s->site->name])
        ->sortBy(fn($v) => $v)->all();

    $provinceOpts = $screens
        ->filter(fn($s) => $s->site?->province_id)
        ->mapWithKeys(fn($s) => [(string) $s->site->province_id => $s->site->province?->name ?? '?'])
        ->filter()->sortBy(fn($v) => $v)->all();
@endphp

{{--
    NOTE: Data is injected via @json() (not Js::from()) because this component lives inside
    a double-quoted HTML attribute (x-data="..."). Js::from() outputs raw JSON with unescaped "
    which would prematurely close the HTML attribute. @json() uses JSON_HEX_QUOT to encode " as
    \u0022 — safe in HTML attributes, correctly parsed by JavaScript as the " character.

    Popup HTML uses backtick template literals with single-quoted HTML attributes.
    Backticks are safe inside double-quoted HTML attributes (no conflict with HTML parser).
    Single-quoted HTML attributes avoid any " character that would close the x-data attribute.
--}}
<div
    x-data="{
        allScreens:   @json($screensData),
        siteOpts:     @json($siteOpts),
        provinceOpts: @json($provinceOpts),
        filterSite:      '',
        filterProvince:  '',
        mapInstance:     null,
        markerLayer:     null,

        get filteredScreens() {
            return this.allScreens.filter(s => {
                if (this.filterSite     && s.site_id     !== this.filterSite)     return false;
                if (this.filterProvince && s.province_id !== this.filterProvince) return false;
                return true;
            });
        },

        get withCoordsCount() {
            return this.filteredScreens.filter(s => s.site_lat && s.site_lon).length;
        },

        init() {
            this.\$watch('filterSite',     () => this.refreshMarkers());
            this.\$watch('filterProvince', () => { this.filterSite = ''; this.refreshMarkers(); });
            // Delay 350ms to let the Filament slide-over CSS animation finish before Leaflet
            // reads container dimensions. Without this, Leaflet sees offsetWidth=0 and breaks.
            this.loadLeaflet(() => setTimeout(() => this.\$nextTick(() => this.initMap()), 350));
        },

        loadLeaflet(cb) {
            if (window.L) { cb(); return; }
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = '/vendor/leaflet/leaflet.css';
            document.head.appendChild(link);
            const js = document.createElement('script');
            js.src = '/vendor/leaflet/leaflet.js';
            js.onload = cb;
            document.head.appendChild(js);
        },

        initMap() {
            const el = this.\$refs.mapEl;
            if (!el || !window.L) return;
            if (this.mapInstance) { this.mapInstance.remove(); this.mapInstance = null; }
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconUrl:       '/vendor/leaflet/images/marker-icon.png',
                iconRetinaUrl: '/vendor/leaflet/images/marker-icon-2x.png',
                shadowUrl:     '/vendor/leaflet/images/marker-shadow.png',
            });
            const map = L.map(el).setView([16.0, 106.0], 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '\u00a9 OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);
            this.mapInstance = map;
            this.markerLayer = L.layerGroup().addTo(map);
            this.refreshMarkers();
            // Extra invalidateSize after a tick to catch any residual layout shift
            setTimeout(() => map.invalidateSize(), 50);
        },

        refreshMarkers() {
            if (!this.mapInstance || !this.markerLayer) return;
            this.markerLayer.clearLayers();
            const sites = {};
            this.filteredScreens.forEach(s => {
                if (!s.site_lat || !s.site_lon) return;
                if (!sites[s.site_id]) sites[s.site_id] = { lat: s.site_lat, lon: s.site_lon, name: s.site_name, screens: [] };
                sites[s.site_id].screens.push(s);
            });
            const bounds = [];
            Object.values(sites).forEach(site => {
                const rows = site.screens.map(s =>
                    `<tr>` +
                    `<td style='padding:2px 6px;font-family:monospace;font-size:11px'>${s.external_id}</td>` +
                    `<td style='padding:2px 6px;font-size:12px'><a href='${s.view_url}' target='_blank' style='color:#3b82f6'>${s.name}</a></td>` +
                    `<td style='padding:2px 6px'><span style='padding:1px 6px;border-radius:9999px;font-size:11px;background:${s.active ? '#dcfce7' : '#fee2e2'};color:${s.active ? '#166534' : '#991b1b'}'>${s.active ? 'Active' : 'Inactive'}</span></td>` +
                    `</tr>`
                ).join('');
                const popup =
                    `<div style='min-width:280px'>` +
                    `<b style='font-size:13px'>${site.name}</b>` +
                    `<div style='font-size:11px;color:#6b7280;margin-bottom:6px'>${site.screens.length} màn hình</div>` +
                    `<table style='width:100%;border-collapse:collapse'>` +
                    `<thead><tr style='background:#f3f4f6'>` +
                    `<th style='padding:2px 6px;text-align:left;font-size:11px'>ID</th>` +
                    `<th style='padding:2px 6px;text-align:left;font-size:11px'>Tên</th>` +
                    `<th style='padding:2px 6px;text-align:left;font-size:11px'>Trạng thái</th>` +
                    `</tr></thead>` +
                    `<tbody>${rows}</tbody>` +
                    `</table></div>`;
                L.marker([site.lat, site.lon]).addTo(this.markerLayer).bindPopup(popup, { maxWidth: 360 });
                bounds.push([site.lat, site.lon]);
            });
            if (bounds.length === 1)    this.mapInstance.setView(bounds[0], 15);
            else if (bounds.length > 1) this.mapInstance.fitBounds(bounds, { padding: [40, 40] });
            this.mapInstance.invalidateSize();
        },
    }"
    class="space-y-3 -mx-6 -mb-6"
>
    {{-- Filter bar --}}
    <div class="flex flex-wrap items-center gap-3 px-6 pt-1">

        {{-- Site filter --}}
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Site:</label>
            <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 ring-gray-950/10 dark:ring-white/20">
                <select
                    x-model="filterSite"
                    class="fi-select-input block border-none bg-transparent py-1 ps-2.5 pe-7 text-sm text-gray-950 transition duration-75 focus:ring-0 dark:text-white [&_option]:bg-white [&_option]:dark:bg-gray-900"
                >
                    <option value="">Tất cả site</option>
                    <template x-for="[id, name] in Object.entries(siteOpts)" :key="id">
                        <option :value="id" x-text="name"></option>
                    </template>
                </select>
            </div>
        </div>

        {{-- Province filter --}}
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Tỉnh/Thành:</label>
            <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 ring-gray-950/10 dark:ring-white/20">
                <select
                    x-model="filterProvince"
                    class="fi-select-input block border-none bg-transparent py-1 ps-2.5 pe-7 text-sm text-gray-950 transition duration-75 focus:ring-0 dark:text-white [&_option]:bg-white [&_option]:dark:bg-gray-900"
                >
                    <option value="">Tất cả tỉnh</option>
                    <template x-for="[id, name] in Object.entries(provinceOpts)" :key="id">
                        <option :value="id" x-text="name"></option>
                    </template>
                </select>
            </div>
        </div>

        {{-- Clear button --}}
        <button
            x-show="filterSite || filterProvince"
            x-cloak
            @click="filterSite = ''; filterProvince = ''"
            type="button"
            class="text-xs font-semibold text-danger-600 hover:text-danger-500 dark:text-danger-400 transition duration-75"
        >
            Xoá bộ lọc
        </button>

        {{-- GPS status --}}
        <span class="ms-auto text-xs text-gray-400 dark:text-gray-500">
            <span x-text="withCoordsCount"></span> / <span x-text="filteredScreens.length"></span> màn hình có tọa độ GPS
        </span>
    </div>

    {{-- Map container — wire:ignore prevents Livewire from clobbering the Leaflet DOM --}}
    <div wire:ignore>
        <div
            x-ref="mapEl"
            style="height:560px;width:100%;z-index:0;"
        ></div>
    </div>

    {{-- Footer note --}}
    <p class="px-6 pb-3 text-xs text-gray-400 dark:text-gray-500 text-center">
        Nhấn vào marker để xem danh sách màn hình tại site đó.
    </p>

</div>
