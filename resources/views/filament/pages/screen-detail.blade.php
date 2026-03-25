
    @php
        $inv  = $screen->inventory;
        $spec = $screen->spec;
        $site = $screen->site;

        $statusColor = match($screen->status) {
            'online'      => 'bg-green-100 text-green-700 ring-green-300',
            'maintenance' => 'bg-yellow-100 text-yellow-700 ring-yellow-300',
            default       => 'bg-red-100 text-red-700 ring-red-300',
        };

        $dayShort = ['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'];
        $hours24  = range(0, 23);

        $lat = $screen->lat ?? $site?->lat;
        $lon = $screen->lon ?? $site?->lon;
        $hasLocation = $lat && $lon;
    @endphp

    {{-- ── HEADER STATS ────────────────────────────────────────────────────────── --}}
    <div class="mb-4 flex flex-wrap gap-6 rounded-xl border border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Screen ID</p>
            <p class="mt-0.5 font-mono text-sm font-semibold text-gray-800 dark:text-white">{{ $screen->external_id }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Status</p>
            <p class="mt-1">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $statusColor }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $screen->status === 'online' ? 'bg-green-500' : ($screen->status === 'maintenance' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                {{ ucfirst($screen->status) }}
            </span>
                @if($screen->active)
                    <span class="ml-1 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700 ring-1 ring-green-300">Active</span>
                @endif
            </p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Last Ad Request</p>
            <p class="mt-0.5 text-sm text-gray-600">{{ $lastAdRequest ? \Carbon\Carbon::parse($lastAdRequest)->format('M j, Y, g:i A') : '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Last Played On</p>
            <p class="mt-0.5 text-sm text-gray-600">{{ $lastPlayedOn ? \Carbon\Carbon::parse($lastPlayedOn)->format('M j, Y, g:i A') : '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Last Modified</p>
            <p class="mt-0.5 text-sm text-gray-600">{{ $screen->updated_at->format('M j, Y, g:i A') }}</p>
        </div>
    </div>

    {{-- ── SUMMARY ──────────────────────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">
            <span class="flex items-center gap-2"><x-heroicon-o-information-circle class="h-4 w-4 text-gray-400"/> Summary</span>
        </x-slot>

        <div class="grid grid-cols-2 gap-x-12 gap-y-5 md:grid-cols-5">
            @foreach([
                ['Screen ID',              $screen->external_id,                                      'mono'],
                ['Unit ID',                $screen->unit_id ?? '—',                                   ''],
                ['Internal notes',         $screen->internal_notes ?? '—',                            ''],
                ['Ad request API UUID',    $screen->uuid,                                             'mono text-xs'],
                ['OpenRTB UUID',           $screen->uuid,                                             'mono text-xs'],
                ['Network',                $inv?->network?->name ?? $inv?->network_name ?? '—',       ''],
                ['Site',                   $site?->name ?? '—',                                       ''],
                ['Venue type',             $inv?->venue_type ?? '—',                                  ''],
                ['Time zone',              $inv?->timezone ?? '—',                                    ''],
                ['Geographic context',     trim(($site?->region ?? '') . ($site?->city ? ' > '.$site->city : '')) ?: '—', ''],
                ['Resolution',             $spec ? $spec->width_px.'×'.$spec->height_px.' px' : '—', ''],
                ['Physical size',          $spec && $spec->width_cm ? $spec->width_cm.' cm × '.$spec->height_cm.' cm' : '—', ''],
                ['Facing direction',       $spec?->facing_direction ?? '—',                           ''],
                ['Floor CPM',              $inv?->floor_cpm_usd ? '$'.number_format((float)$inv->floor_cpm_usd, 2) : '—', ''],
                ['Overridden screen count', isset($inv?->screen_count_override) && $inv->screen_count_override > 1 ? $inv->screen_count_override : '—', ''],
            ] as [$label, $value, $extra])
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-sm {{ $extra === 'mono' ? 'font-mono' : '' }} {{ str_contains($extra, 'text-xs') ? 'text-xs' : '' }} text-gray-800 dark:text-gray-200 break-all">
                        {{ $value }}
                    </p>
                </div>
            @endforeach

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Selective listing</p>
                <p class="mt-1 text-sm text-gray-800 dark:text-gray-200">
                    @php
                        $listing = ['Ad server', 'Deals'];
                        if ($inv?->programmatic_enabled) $listing[] = 'Open exchange';
                    @endphp
                    {{ implode(', ', $listing) }}
                </p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Has impression data?</p>
                <p class="mt-1">
                    @if($screen->multipliers->isNotEmpty())
                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700 ring-1 ring-green-300">Yes</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-500 ring-1 ring-gray-300">No</span>
                    @endif
                </p>
            </div>
        </div>
    </x-filament::section>

    {{-- ── PLAYBACK ─────────────────────────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">
            <span class="flex items-center gap-2"><x-heroicon-o-play-circle class="h-4 w-4 text-gray-400"/> Playback</span>
        </x-slot>

        <div class="space-y-4">
            <div>
                <p class="text-sm text-gray-500">Default ad duration</p>
                <p class="mt-0.5 font-semibold text-gray-800 dark:text-white">
                    {{ $inv?->spot_length ?? 15 }} secs
                    <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                    min: {{ $inv?->min_spot_length ?? 3 }} secs, max: {{ $inv?->max_spot_length ?? 180 }} secs
                </span>
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach([
                    ['HTML',   'heroicon-o-code-bracket', $spec?->allow_html],
                    ['Images', 'heroicon-o-photo',        $spec?->allow_image],
                    ['Videos', 'heroicon-o-film',         $spec?->allow_video],
                    ['ZIP',    'heroicon-o-archive-box',  $spec?->allow_zip],
                ] as [$label, $icon, $allowed])
                    <div class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
                        <x-dynamic-component :component="$icon" class="h-8 w-8 text-blue-400 flex-shrink-0"/>
                        <div>
                            <p class="text-xs text-gray-500">Allow {{ $label }}?</p>
                            @if($allowed)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700">Yes</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-600">No</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div>
                <p class="text-sm text-gray-500">Advertiser frequency cap</p>
                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-white">0 seconds</p>
            </div>
        </div>
    </x-filament::section>

    {{-- ── IAB CATEGORIES ───────────────────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">
        <span class="flex items-center gap-2 text-sm font-bold">
            <span class="font-black italic text-base text-red-600">iab.</span> IAB categories
        </span>
        </x-slot>
        <div class="grid grid-cols-2 gap-8">
            <div>
                <p class="text-sm text-gray-500">Category frequency cap</p>
                <p class="mt-0.5 text-sm font-medium text-gray-800 dark:text-white">0 seconds</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Strict frequency capping</p>
                <p class="mt-1">
                    <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-600">No</span>
                </p>
            </div>
        </div>
    </x-filament::section>

    {{-- ── SCREEN LOCATION & GEOFENCE ──────────────────────────────────────────── --}}
    <x-filament::section class="mt-4 !p-0 overflow-hidden">
        <x-slot name="heading">
        <span class="flex items-center gap-2 px-1">
            <x-heroicon-o-map-pin class="h-4 w-4 text-gray-400"/> Screen location and geofence
        </span>
        </x-slot>
        @if($hasLocation)
            <div id="screen-map" style="height:320px;width:100%;z-index:0;"></div>
        @else
            <div class="py-8 text-center text-sm italic text-gray-400">Không có tọa độ GPS.</div>
        @endif
    </x-filament::section>

    {{-- ── OPERATING HOURS (read-only) ────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">
        <span class="flex items-center gap-2">
            <x-heroicon-o-clock class="h-4 w-4 text-gray-400"/> Operating hours
        </span>
        </x-slot>

        <div class="select-none overflow-x-auto">
            <div class="flex items-center">
                <div class="w-20 shrink-0"></div>
                <div class="grid flex-1 gap-px" style="grid-template-columns:repeat(24,1fr)">
                    @foreach($hours24 as $h)
                        <div class="text-center leading-none">
                            <span class="text-xs font-semibold text-gray-500">{{ $h === 0 ? '12' : ($h <= 12 ? $h : $h - 12) }}</span>
                            <span class="block text-xs text-gray-400">{{ $h < 12 ? 'AM' : 'PM' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-1 space-y-1">
                @foreach($dayShort as $key => $label)
                    <div class="flex items-center gap-1">
                        <div class="w-20 shrink-0 text-xs font-semibold text-gray-600">{{ $label }}</div>
                        <div class="grid flex-1 gap-px" style="grid-template-columns:repeat(24,1fr)">
                            @foreach($hours24 as $h)
                                <div class="h-7 rounded-sm {{ ($hoursGrid[$key][$h] ?? false) ? 'bg-primary-500' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>

    {{-- ── IMPRESSIONS PER PLAY ─────────────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">
        <span class="flex items-center gap-2">
            <x-heroicon-o-presentation-chart-line class="h-4 w-4 text-gray-400"/> Impressions per play
        </span>
        </x-slot>

        @if($impressionChart->isEmpty())
            <p class="py-4 text-center text-sm italic text-gray-400">No impression data for selected time period.</p>
        @else
            <canvas id="impressionChart" height="80"></canvas>
        @endif

        <div class="mt-6 overflow-x-auto">
            @php
                $weekDays  = collect(range(0, 6))->map(fn($i) => now()->startOfWeek()->addDays($i));
                $dayLabels = $weekDays->map(fn($d) => strtoupper($d->format('F j, Y')));
            @endphp
            <table class="min-w-full text-xs">
                <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-2 pr-4 text-left text-gray-400 w-20"></th>
                    @foreach($dayLabels as $dl)
                        <th class="py-2 px-2 text-center font-semibold text-gray-600">{{ $dl }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($hours24 as $h)
                    @php
                        $timeLabel = $h === 0 ? '12:00 AM' : ($h < 12 ? "{$h}:00 AM" : ($h === 12 ? '12:00 PM' : ($h - 12).':00 PM'));
                    @endphp
                    <tr class="border-b border-gray-100 {{ $h % 2 === 0 ? 'bg-white' : 'bg-gray-50/30' }}">
                        <td class="py-1.5 pr-4 text-gray-500 whitespace-nowrap">{{ $timeLabel }}</td>
                        @foreach($weekDays as $day)
                            @php
                                $dow   = $day->dayOfWeek;
                                $key   = "{$dow}_{$h}";
                                $val   = $heatmap->get($key)?->total_imp;
                                $alpha = $val ? number_format(min(1, 0.15 + (float)$val / 25 * 0.85), 2) : null;
                                $bg    = $alpha ? "background-color:rgba(22,163,74,{$alpha})" : '';
                            @endphp
                            <td class="py-1.5 px-2 text-center tabular-nums" style="{{ $bg }}">
                                {{ $val ? number_format((float)$val, 2) : '—' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ── FALLBACK CREATIVES ───────────────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">
        <span class="flex items-center gap-2">
            <x-heroicon-o-photo class="h-4 w-4 text-gray-400"/> Fallback creatives
        </span>
        </x-slot>
        <div class="py-8 text-center">
            <x-heroicon-o-photo class="mx-auto h-12 w-12 text-gray-300 mb-2"/>
            <p class="text-sm text-gray-400">Looks like there are no creatives here.</p>
        </div>
    </x-filament::section>

    {{-- ── SCRIPTS (inline để tránh multiple root element) ───────────────────── --}}
    <script>
        (function () {
            // Load Leaflet CSS + JS động
            if (!document.querySelector('link[href*="leaflet"]')) {
                const l = document.createElement('link');
                l.rel = 'stylesheet';
                l.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(l);
            }

            function loadScript(src, cb) {
                if (document.querySelector('script[src="' + src + '"]')) { cb && cb(); return; }
                const s = document.createElement('script');
                s.src = src; s.onload = cb || null;
                document.head.appendChild(s);
            }

            @if($hasLocation)
            const LAT = {{ $lat }};
            const LON = {{ $lon }};

            loadScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', function () {
                if (document.getElementById('screen-map')?._leaflet_id) return;
                const map = L.map('screen-map').setView([LAT, LON], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap', maxZoom: 19,
                }).addTo(map);

                const icon = L.divIcon({
                    html: '<div style="width:14px;height:14px;border-radius:50%;background:#2563EB;border:3px solid white;box-shadow:0 2px 6px rgba(37,99,235,0.5)"></div>',
                    className: '', iconSize: [14, 14], iconAnchor: [7, 7],
                });
                L.marker([LAT, LON], { icon }).addTo(map)
                    .bindPopup('<b>{{ addslashes($screen->name) }}</b>').openPopup();
                L.circle([LAT, LON], {
                    radius: 100, color: '#3B82F6', fillColor: '#93C5FD', fillOpacity: 0.35, weight: 2,
                }).addTo(map);
            });
            @endif

            @if($impressionChart->isNotEmpty())
            loadScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', function () {
                const el = document.getElementById('impressionChart');
                if (!el || el._chart) return;
                el._chart = new Chart(el, {
                    type: 'line',
                    data: {
                        labels: @json($impressionChart->pluck('hour_slot')),
                        datasets: [{
                            label: 'Impressions per play',
                            data: @json($impressionChart->pluck('avg_imp')),
                            borderColor: '#06B6D4',
                            backgroundColor: 'rgba(6,182,212,0.08)',
                            borderWidth: 2, tension: 0.4, pointRadius: 2, fill: true,
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { maxTicksLimit: 14, font: { size: 10 } } },
                            y: { beginAtZero: true, ticks: { font: { size: 10 } } },
                        },
                    },
                });
            });
            @endif
        })();
    </script>


