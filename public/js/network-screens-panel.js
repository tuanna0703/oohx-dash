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
        page:            1,
        perPage:         20,

        init() {
            const d = window[dataKey] || {};
            this.allScreens     = d.screens      || [];
            this.siteOpts       = d.siteOpts     || {};
            this.provinceOpts   = d.provinceOpts || {};
            this.communeOpts    = d.communeOpts  || {};

            this.$watch('filterProvince', () => { this.filterCommune = ''; });
            this.$watch('filtered', () => {
                this.page = 1;
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

        get paginated() {
            const start = (this.page - 1) * this.perPage;
            return this.filtered.slice(start, start + this.perPage);
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filtered.length / this.perPage));
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
