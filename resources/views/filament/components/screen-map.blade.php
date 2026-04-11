@php
    $record = $getRecord();
    // Support cả Screen ($record->site) và Site ($record chính là site)
    $isSite = $record instanceof \App\Models\Site;
    $site = $isSite ? $record : ($record->relationLoaded('site') ? $record->site : $record->site()->first());
    $lat = $site?->lat ?? $record->lat ?? null;
    $lon = $site?->lon ?? $record->lon ?? null;
    $screenName = e($record->name ?? 'Location');
    $hasLocation = $lat && $lon;
@endphp

@if($hasLocation)
    @pushOnce('styles')
        <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
    @endPushOnce
    @once
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    @endonce

    <div wire:ignore
         x-data="{
             initMap() {
                 const el = this.$el;
                 if (el._leaflet_id) return;

                 delete L.Icon.Default.prototype._getIconUrl;
                 L.Icon.Default.mergeOptions({
                     iconUrl:       '/vendor/leaflet/images/marker-icon.png',
                     iconRetinaUrl: '/vendor/leaflet/images/marker-icon-2x.png',
                     shadowUrl:     '/vendor/leaflet/images/marker-shadow.png',
                 });

                 const map = L.map(el).setView([{{ $lat }}, {{ $lon }}], 16);
                 L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                     attribution: '&copy; OpenStreetMap contributors',
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
             }
         }"
         x-init="initMap()"
         style="height:320px;width:100%;z-index:0;border-radius:0.5rem;overflow:hidden;">
    </div>
@else
    <p class="text-sm italic text-gray-400">Không có tọa độ GPS.</p>
@endif
