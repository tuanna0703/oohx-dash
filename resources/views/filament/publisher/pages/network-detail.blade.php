<x-filament-panels::page>

{{-- ── Mapbox CSS (load early) ────────────────────────────────────────────── --}}


{{-- ── Breadcrumb ──────────────────────────────────────────────────────────── --}}
<div class="mb-2 text-sm text-gray-500 dark:text-gray-400">
    <a href="{{ \App\Filament\Publisher\Resources\NetworkResource::getUrl('index') }}"
       class="hover:text-primary-600 transition-colors">Networks</a>
    <span class="mx-1.5 text-gray-300">/</span>
    <span class="text-gray-700 dark:text-gray-300">{{ $network->name }}</span>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     SUMMARY — 2 columns layout
════════════════════════════════════════════════════════════════════════════ --}}
<x-filament::section>
    <x-slot name="heading">
        <span class="flex items-center gap-2">
            <x-heroicon-o-information-circle class="h-4 w-4 text-gray-400"/>
            Summary
        </span>
    </x-slot>

    <div class="grid grid-cols-1 gap-8 md:grid-cols-2">

        {{-- Column 1 --}}
        <div class="space-y-5">

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Network name</p>
                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-white">{{ $network->name }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Default Floor CPM</p>
                <p class="mt-1 text-sm font-medium text-gray-800 dark:text-white">
                    {{ $network->default_floor_cpm
                        ? number_format((float)$network->default_floor_cpm) . ' ' . $network->default_floor_cpm_currency
                        : '—' }}
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Screens</p>
                <p class="mt-1 text-sm font-medium text-gray-800 dark:text-white">
                    {{ $screens->count() }}
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Status</p>
                <p class="mt-1.5">
                    @if($network->status === 'active')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 ring-1 ring-green-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                            Active
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                            Inactive
                        </span>
                    @endif
                </p>
            </div>

        </div>

        {{-- Column 2 --}}
        <div class="space-y-5">

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Description</p>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    {{ $network->description ?: '—' }}
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Created on</p>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    {{ $network->created_at->format('M j, Y, g:i A') }}
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Last modified on</p>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    {{ $network->updated_at->format('M j, Y, g:i A') }}
                </p>
            </div>

        </div>

    </div>
</x-filament::section>

{{-- ═══════════════════════════════════════════════════════════════════════════
     MAPBOX MAP
════════════════════════════════════════════════════════════════════════════ --}}
{{-- Leaflet CSS --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<x-filament::section class="mt-4 !p-0 overflow-hidden">
    <x-slot name="heading">
        <span class="flex items-center gap-2 px-1">
            <x-heroicon-o-map-pin class="h-4 w-4 text-gray-400"/>
            Screen Locations
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                {{ $mapMarkers->count() }}
            </span>
        </span>
    </x-slot>

    @if($mapMarkers->isEmpty())
        <div class="py-10 text-center text-sm italic text-gray-400">
            Không có tọa độ GPS cho các screen trong network này.
        </div>
    @else
        <div id="network-map" style="height: 380px; width: 100%; z-index: 0;"></div>
    @endif
</x-filament::section>

{{-- ═══════════════════════════════════════════════════════════════════════════
     SCREEN LIST
════════════════════════════════════════════════════════════════════════════ --}}
<x-filament::section class="mt-4">
    <x-slot name="heading">
        <span class="flex items-center gap-2">
            <x-heroicon-o-tv class="h-4 w-4 text-gray-400"/>
            Screens
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                {{ $screens->count() }}
            </span>
        </span>
    </x-slot>

    @if($screens->isEmpty())
        <div class="py-10 text-center text-sm italic text-gray-400">
            Chưa có screen nào trong network này.
        </div>
    @else
    <div class="-mx-1 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wider text-gray-500">Screen Name</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wider text-gray-500">Screen ID</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wider text-gray-500">UUID</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wider text-gray-500">Site</th>
                    <th class="px-4 py-3 text-center font-semibold uppercase tracking-wider text-gray-500">Resolution</th>
                    <th class="px-4 py-3 text-center font-semibold uppercase tracking-wider text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left font-semibold uppercase tracking-wider text-gray-500">Internal Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($screens as $i => $screen)
                <tr class="{{ $i % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/30' }}
                            hover:bg-primary-50/30 dark:hover:bg-primary-900/10 transition-colors">

                    <td class="px-4 py-3">
                        <a href="{{ \App\Filament\Publisher\Resources\ScreenResource::getUrl('edit', ['record' => $screen]) }}"
                           class="block max-w-[220px] truncate font-medium text-primary-600 hover:text-primary-700 hover:underline"
                           title="{{ $screen->name }}">
                            {{ $screen->name }}
                        </a>
                    </td>

                    <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                        {{ $screen->external_id }}
                    </td>

                    <td class="px-4 py-3 font-mono text-xs text-gray-400 max-w-[160px] truncate"
                        title="{{ $screen->uuid }}">
                        {{ $screen->uuid }}
                    </td>

                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                        {{ $screen->site?->name ?? '—' }}
                    </td>

                    <td class="px-4 py-3 text-center font-mono text-xs text-gray-600 tabular-nums whitespace-nowrap">
                        {{ $screen->spec ? $screen->spec->width_px . '×' . $screen->spec->height_px : '—' }}
                    </td>

                    <td class="px-4 py-3 text-center">
                        @php
                            [$bg, $dot, $text] = match($screen->status) {
                                'online'      => ['bg-green-100 ring-green-300',  'bg-green-500',  'text-green-700'],
                                'maintenance' => ['bg-yellow-100 ring-yellow-300','bg-yellow-500', 'text-yellow-700'],
                                default       => ['bg-red-100 ring-red-300',      'bg-red-500',    'text-red-700'],
                            };
                        @endphp
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $bg }} {{ $text }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
                            {{ ucfirst($screen->status) }}
                        </span>
                    </td>

                    <td class="px-4 py-3 text-xs text-gray-400 max-w-[160px] truncate"
                        title="{{ $screen->internal_notes }}">
                        {{ $screen->internal_notes ?: '—' }}
                    </td>

                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</x-filament::section>

{{-- ═══════════════════════════════════════════════════════════════════════════
     MAPBOX SCRIPT
════════════════════════════════════════════════════════════════════════════ --}}
@if($mapMarkers->isNotEmpty())
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const markers = @json($mapMarkers);
    const center  = @json($mapCenter);

    if (!center) return;

    const map = L.map('network-map', { zoomControl: true }).setView(
        [center.lat, center.lon], 14
    );

    // OpenStreetMap tile layer — miễn phí, không cần token
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    // Custom marker icon — AdTRUE brand pink
    const pinkIcon = L.divIcon({
        html: '<div style="width:18px;height:18px;border-radius:50%;background:#E5007D;border:3px solid white;box-shadow:0 2px 8px rgba(229,0,125,0.5)"></div>',
        className: '',
        iconSize : [18, 18],
        iconAnchor: [9, 9],
        popupAnchor: [0, -12],
    });

    const leafletMarkers = [];

    markers.forEach(function (m) {
        const marker = L.marker([m.lat, m.lon], { icon: pinkIcon })
            .bindPopup(
                '<div style="padding:4px 2px;min-width:160px">' +
                    '<p style="font-size:12px;font-weight:700;color:#111;margin:0 0 4px">' + m.name + '</p>' +
                    '<p style="font-size:11px;color:#888;margin:0;font-family:monospace">' + m.uuid + '</p>' +
                '</div>',
                { closeButton: false, maxWidth: 240 }
            )
            .addTo(map);
        leafletMarkers.push(marker);
    });

    // Fit bounds nếu có nhiều markers
    if (leafletMarkers.length > 1) {
        const group = L.featureGroup(leafletMarkers);
        map.fitBounds(group.getBounds().pad(0.15));
    }
})();
</script>
@endif

</x-filament-panels::page>
