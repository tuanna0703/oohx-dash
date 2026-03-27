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
@endphp

@script
<script>
(function () {
    var SCREENS   = @json($screensData);
    var SITE_OPTS = @json($siteOpts);
    var PROV_OPTS = @json($provinceOpts);

    var filterSite = '';
    var filterProv = '';
    var mapInst    = null;
    var markerLyr  = null;
    var initialized = false;

    function filtered() {
        return SCREENS.filter(function (s) {
            if (filterSite && s.site_id !== filterSite) return false;
            if (filterProv && s.province_id !== filterProv) return false;
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
        if (btn) btn.style.display = (filterSite || filterProv) ? '' : 'none';
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

    function setupFilters() {
        var provSel = document.getElementById('nvm_prov');
        if (provSel && !provSel.dataset.ready) {
            provSel.dataset.ready = '1';
            Object.entries(PROV_OPTS).forEach(function (pair) {
                var opt = document.createElement('option');
                opt.value = pair[0]; opt.textContent = pair[1];
                provSel.appendChild(opt);
            });
            provSel.addEventListener('change', function () {
                filterProv = this.value;
                filterSite = '';
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
                filterSite = ''; filterProv = '';
                var s = document.getElementById('nvm_site'); if (s) s.value = '';
                var p = document.getElementById('nvm_prov'); if (p) p.value = '';
                refreshMarkers(); updateClearBtn();
            });
        }

        updateCount();
        updateClearBtn();
    }

    // Triggered by Alpine $watch when map tab becomes active
    window.addEventListener('nvm-map-activate', function () {
        setupFilters();
        if (!initialized) {
            initialized = true;
            loadLeaflet(function () { setTimeout(initMap, 80); });
        } else if (mapInst) {
            setTimeout(function () { mapInst.invalidateSize(); }, 80);
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
    {{-- Network info --}}
    @if ($this->hasInfolist())
        {{ $this->infolist }}
    @endif

    {{-- Tabbed screens section --}}
    @php $relationManagers = $this->getRelationManagers(); @endphp

    <div
        x-data="{ tab: 'list' }"
        x-init="$watch('tab', function(val) { if (val === 'map') window.dispatchEvent(new CustomEvent('nvm-map-activate')); })"
    >
        {{-- Tab bar --}}
        <x-filament::tabs>
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
        <div x-show="tab === 'list'" x-cloak>
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
        <div x-show="tab === 'map'" x-cloak>
            <x-filament::section>
                {{-- Filter bar --}}
                <div class="flex flex-wrap items-center gap-3 mb-4">

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Tỉnh/Thành:</label>
                        <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 ring-gray-950/10 dark:ring-white/20">
                            <select
                                id="nvm_prov"
                                class="fi-select-input block border-none bg-transparent py-1 ps-2.5 pe-7 text-sm text-gray-950 transition duration-75 focus:ring-0 dark:text-white [&_option]:bg-white [&_option]:dark:bg-gray-900"
                            >
                                <option value="">Tất cả tỉnh</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">Site:</label>
                        <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 ring-gray-950/10 dark:ring-white/20">
                            <select
                                id="nvm_site"
                                class="fi-select-input block border-none bg-transparent py-1 ps-2.5 pe-7 text-sm text-gray-950 transition duration-75 focus:ring-0 dark:text-white [&_option]:bg-white [&_option]:dark:bg-gray-900"
                            >
                                <option value="">Tất cả site</option>
                            </select>
                        </div>
                    </div>

                    <button
                        id="nvm_clear"
                        type="button"
                        style="display:none"
                        class="text-xs font-semibold text-danger-600 hover:text-danger-500 dark:text-danger-400 transition duration-75"
                    >Xoá bộ lọc</button>

                    <span class="ms-auto text-xs text-gray-400 dark:text-gray-500">
                        <span id="nvm_gw">0</span> / <span id="nvm_gt">0</span> màn hình có tọa độ GPS
                    </span>
                </div>

                {{-- Map container --}}
                <div wire:ignore>
                    <div id="nvm_map" style="height:580px;width:100%;z-index:0;border-radius:0.5rem;overflow:hidden;"></div>
                </div>

                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500 text-center">
                    Nhấn vào marker để xem danh sách màn hình tại site đó.
                </p>
            </x-filament::section>
        </div>
    </div>

</x-filament-panels::page>
