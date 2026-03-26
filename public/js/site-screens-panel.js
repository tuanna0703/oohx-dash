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
