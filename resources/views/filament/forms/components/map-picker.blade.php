{{-- Map Picker Component for Site lat/lon
     Sử dụng Leaflet.js + OpenStreetMap + Nominatim (không cần API key)
     Cập nhật data.lat / data.lon trong Livewire form state khi:
       - Click trên map
       - Kéo marker
       - Tìm địa điểm qua search
--}}

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs="
        crossorigin=""
    ></script>
@endonce

<div
    x-data="{
        map: null,
        marker: null,
        searchQuery: '',
        searching: false,
        searchError: '',
        _skipWatch: false,

        get latVal() { return parseFloat(this.$wire.data?.lat) || null },
        get lonVal() { return parseFloat(this.$wire.data?.lon) || null },

        init() {
            this.$nextTick(() => {
                if (typeof L === 'undefined') {
                    console.error('Leaflet not loaded');
                    return;
                }
                this.initMap();

                // Khi người dùng nhập lat/lon trực tiếp → update marker
                this.$watch('$wire.data.lat', () => {
                    if (this._skipWatch) { this._skipWatch = false; return; }
                    this.syncMarkerFromForm();
                });
                this.$watch('$wire.data.lon', () => {
                    if (this._skipWatch) return;
                    this.syncMarkerFromForm();
                });
            });
        },

        initMap() {
            const lat = this.latVal || 10.7769;
            const lon = this.lonVal || 106.7009;
            const zoom = (this.latVal && this.lonVal) ? 15 : 6;

            this.map = L.map(this.$refs.mapEl, { zoomControl: true }).setView([lat, lon], zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            if (this.latVal && this.lonVal) {
                this._placeMarker(this.latVal, this.lonVal);
            }

            this.map.on('click', (e) => this.onMapClick(e.latlng));
        },

        onMapClick(latlng) {
            const lat = parseFloat(latlng.lat.toFixed(7));
            const lon = parseFloat(latlng.lng.toFixed(7));
            this._placeMarker(lat, lon);
            this._updateForm(lat, lon);
        },

        _placeMarker(lat, lon) {
            if (this.marker) {
                this.marker.setLatLng([lat, lon]);
            } else {
                this.marker = L.marker([lat, lon], { draggable: true }).addTo(this.map);
                this.marker.on('dragend', (e) => {
                    const p = e.target.getLatLng();
                    this._updateForm(parseFloat(p.lat.toFixed(7)), parseFloat(p.lng.toFixed(7)));
                });
            }
        },

        _updateForm(lat, lon) {
            this._skipWatch = true;
            this.$wire.set('data.lat', lat);
            this.$wire.set('data.lon', lon);
        },

        syncMarkerFromForm() {
            const lat = this.latVal;
            const lon = this.lonVal;
            if (!lat || !lon || !this.map) return;
            this._placeMarker(lat, lon);
            this.map.setView([lat, lon], Math.max(this.map.getZoom(), 14));
        },

        async searchLocation() {
            const q = this.searchQuery.trim();
            if (!q) return;

            this.searching = true;
            this.searchError = '';

            try {
                const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
                    + encodeURIComponent(q);

                const resp = await fetch(url, {
                    headers: { 'Accept-Language': 'vi,en', 'User-Agent': 'AdTRUE-SSP/1.0' }
                });

                if (!resp.ok) throw new Error('HTTP ' + resp.status);

                const results = await resp.json();

                if (!results.length) {
                    this.searchError = 'Không tìm thấy địa điểm.';
                    return;
                }

                const { lat, lon, display_name } = results[0];
                const parsedLat = parseFloat(parseFloat(lat).toFixed(7));
                const parsedLon = parseFloat(parseFloat(lon).toFixed(7));

                this._placeMarker(parsedLat, parsedLon);
                this._updateForm(parsedLat, parsedLon);
                this.map.setView([parsedLat, parsedLon], 16);

                if (this.marker) {
                    this.marker.bindPopup(display_name).openPopup();
                }
            } catch (err) {
                this.searchError = 'Lỗi kết nối. Thử lại sau.';
                console.error('Map search error:', err);
            } finally {
                this.searching = false;
            }
        },
    }"
    x-init="init()"
    class="col-span-full fi-fo-field-wrp"
>
    {{-- Label --}}
    <div class="mb-2 flex items-center gap-2">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
            Bản đồ vị trí
        </span>
        <span class="text-xs text-gray-400">
            — click trên bản đồ hoặc kéo marker để đặt tọa độ
        </span>
    </div>

    {{-- Search Box --}}
    <div class="mb-2 flex gap-2">
        <input
            x-model="searchQuery"
            @keydown.enter.prevent="searchLocation()"
            type="text"
            placeholder="Tìm địa điểm... (ví dụ: AEON Mall Hà Đông, Hà Nội)"
            class="w-full flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm
                   placeholder-gray-400
                   focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500
                   dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
        />
        <button
            type="button"
            @click="searchLocation()"
            :disabled="searching"
            class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium
                   text-white shadow-sm hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60
                   focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
        >
            <svg x-show="!searching" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
            </svg>
            <svg x-show="searching" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            <span x-text="searching ? 'Đang tìm...' : 'Tìm'"></span>
        </button>
    </div>

    {{-- Search Error --}}
    <p x-show="searchError" x-text="searchError" class="mb-1 text-xs text-danger-600"></p>

    {{-- Map Container — wire:ignore prevents Livewire from destroying Leaflet --}}
    <div wire:ignore>
        <div
            x-ref="mapEl"
            style="height: 360px; width: 100%; border-radius: 8px; border: 1px solid #d1d5db; z-index: 0;"
        ></div>
    </div>

    {{-- Footer hint --}}
    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
        Powered by <a href="https://www.openstreetmap.org" target="_blank" class="underline">OpenStreetMap</a>.
        Tìm kiếm dùng Nominatim — giới hạn 1 request/giây.
    </p>
</div>
