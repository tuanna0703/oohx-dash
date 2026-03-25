@php
    $record = $getRecord();
    $site = $record->relationLoaded('site') ? $record->site : $record->site()->first();
    $lat = $site?->lat ?? $record->lat ?? null;
    $lon = $site?->lon ?? $record->lon ?? null;
    $screenName = e($record->name ?? 'Screen');
    $hasLocation = $lat && $lon;
@endphp

@if($hasLocation)
    <div wire:ignore
         x-data="{
             initMap() {
                 if (!document.querySelector('link[href*=leaflet]')) {
                     const l = document.createElement('link');
                     l.rel = 'stylesheet';
                     l.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                     document.head.appendChild(l);
                 }
                 const doInit = () => {
                     const el = this.$el;
                     if (el._leaflet_id) return;
                     const map = L.map(el).setView([{{ $lat }}, {{ $lon }}], 16);
                     L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                         attribution: 'OpenStreetMap contributors',
                         maxZoom: 19,
                     }).addTo(map);
                     L.circleMarker([{{ $lat }}, {{ $lon }}], {
                         radius: 8,
                         color: '#1d4ed8',
                         fillColor: '#2563EB',
                         fillOpacity: 1,
                         weight: 3,
                     }).addTo(map).bindPopup('{{ $screenName }}').openPopup();
                     L.circle([{{ $lat }}, {{ $lon }}], {
                         radius: 100, color: '#3B82F6', fillColor: '#93C5FD', fillOpacity: 0.35, weight: 2,
                     }).addTo(map);
                 };
                 typeof L !== 'undefined' ? doInit() : (() => {
                     const s = document.createElement('script');
                     s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                     s.onload = doInit;
                     document.head.appendChild(s);
                 })();
             }
         }"
         x-init="initMap()"
         style="height:320px;width:100%;z-index:0;border-radius:0.5rem;overflow:hidden;">
    </div>
@else
    <p class="text-sm italic text-gray-400">Không có tọa độ GPS.</p>
@endif
