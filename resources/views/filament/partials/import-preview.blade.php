<x-filament-panels::page>

{{-- ── Expired / invalid token ──────────────────────────────────────────── --}}
@if($expired)
<x-filament::section>
    <div class="py-10 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-danger-100">
            <x-heroicon-o-clock class="h-9 w-9 text-danger-500"/>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Phiên preview không hợp lệ</h3>
        <p class="mt-1 mb-6 text-sm text-gray-500">Token không tồn tại hoặc session đã hết. Vui lòng upload lại file.</p>
        <x-filament::button wire:click="backToUpload" icon="heroicon-o-arrow-left">
            Quay lại Upload
        </x-filament::button>
    </div>
</x-filament::section>

@else

@php
    $s        = $this->getSummary();
    $format   = ($preview['format'] ?? '') === 'adtrue' ? 'AdTRUE SSP Template' : 'Hivestack Format';
    $totalNew = $s['sites_new']    + $s['screens_new'];
    $totalUpd = $s['sites_update'] + $s['screens_update'];
    $totalErr = $s['sites_error']  + $s['screens_error'];
    $totalAll = count($preview['sites'] ?? []) + count($preview['screens'] ?? []);
    $hasErr   = $totalErr > 0;
@endphp

{{-- ── Page header ──────────────────────────────────────────────────────── --}}
<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Review Import</h2>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-semibold text-primary-700">
                <x-heroicon-s-document class="h-3 w-3"/> {{ $format }}
            </span>
            <span class="text-sm text-gray-400">Chưa có dữ liệu nào được lưu vào database</span>
        </div>
    </div>
    <div class="flex shrink-0 gap-2">
        <x-filament::button
            color="gray"
            wire:click="backToUpload"
            icon="heroicon-o-arrow-left">
            Đổi file
        </x-filament::button>
        <x-filament::button
            color="{{ $hasErr ? 'warning' : 'success' }}"
            wire:click="confirmImport"
            wire:loading.attr="disabled"
            wire:target="confirmImport"
            icon="heroicon-o-check-circle"
            wire:confirm="{{ $hasErr
                ? 'File có ' . $totalErr . ' dòng lỗi sẽ bị bỏ qua. Import ' . ($totalNew + $totalUpd) . ' dòng hợp lệ?'
                : 'Xác nhận lưu ' . $totalNew . ' mới và cập nhật ' . $totalUpd . ' bản ghi?' }}">
            <span wire:loading.remove wire:target="confirmImport">Confirm & Import</span>
            <span wire:loading wire:target="confirmImport" class="flex items-center gap-1.5">
                <x-filament::loading-indicator class="h-4 w-4"/> Đang lưu...
            </span>
        </x-filament::button>
    </div>
</div>

{{-- ── Summary cards — click để filter ────────────────────────────────────── --}}
<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach([
        ['all',    $totalAll,  'Tất cả bản ghi', 'gray'],
        ['new',    $totalNew,  '✦ Thêm mới',     'success'],
        ['update', $totalUpd,  '↻ Cập nhật',     'warning'],
        ['error',  $totalErr,  '✕ Lỗi',          'danger'],
    ] as [$action, $count, $label, $color])
    @php
        $active = $filterAction === $action;
        $colors = [
            'gray'    => ['border-gray-300',    'bg-gray-50',    'text-gray-700'],
            'success' => ['border-success-400', 'bg-success-50', 'text-success-700'],
            'warning' => ['border-warning-400', 'bg-warning-50', 'text-warning-700'],
            'danger'  => ['border-danger-400',  'bg-danger-50',  'text-danger-700'],
        ][$color];
    @endphp
    <button
        wire:click="setFilter('{{ $action }}')"
        class="rounded-xl border-2 p-4 text-center transition-all focus:outline-none
               {{ $active
                   ? $colors[0] . ' ' . $colors[1] . ' ring-2 ring-offset-1'
                   : 'border-gray-200 bg-white hover:border-gray-300 dark:bg-gray-800' }}
               {{ $colors[2] }}">
        <div class="text-2xl font-bold tabular-nums">{{ number_format($count) }}</div>
        <div class="mt-1 text-xs font-medium">{{ $label }}</div>
    </button>
    @endforeach
</div>

{{-- Error notice banner --}}
@if($hasErr)
<div class="mb-4 flex items-start gap-3 rounded-lg border border-danger-200 bg-danger-50 px-4 py-3">
    <x-heroicon-s-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-danger-500"/>
    <p class="text-sm text-danger-700">
        <strong>{{ $totalErr }} dòng lỗi</strong> sẽ bị bỏ qua khi import.
        Bấm thẻ <strong>"✕ Lỗi"</strong> để xem chi tiết từng dòng.
    </p>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════════════════
     SITES TABLE
════════════════════════════════════════════════════════════════════════════ --}}
@if($this->getSiteTotal() > 0)
<x-filament::section collapsible class="mb-4">
    <x-slot name="heading">
        <span class="flex items-center gap-2 text-sm">
            <x-heroicon-o-map-pin class="h-4 w-4 text-gray-400"/>
            Sites
            <span class="font-normal text-gray-400">
                {{ $s['sites_new'] }} mới &middot;
                {{ $s['sites_update'] }} cập nhật &middot;
                {{ $s['sites_error'] }} lỗi
            </span>
        </span>
    </x-slot>

    <div class="-mx-1 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="w-10 px-3 py-2.5 text-left font-semibold text-gray-500">Row</th>
                    <th class="w-28 px-3 py-2.5 text-left font-semibold text-gray-500">Status</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">Site ID</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">Tên Site</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">GPS</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">Thay đổi / Lỗi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->getFilteredSites() as $site)
                @php
                    [$rowBg, $badge, $badgeLabel] = match($site['action']) {
                        'new'    => ['bg-success-50/60', 'bg-success-100 text-success-700 ring-success-200', '✦ Mới'],
                        'update' => ['bg-warning-50/60', 'bg-warning-100 text-warning-700 ring-warning-200', '↻ Update'],
                        'error'  => ['bg-danger-50/70',  'bg-danger-100  text-danger-700  ring-danger-200',  '✕ Lỗi'],
                        default  => ['', 'bg-gray-100 text-gray-600 ring-gray-200', $site['action']],
                    };
                @endphp
                <tr class="{{ $rowBg }} transition-colors">
                    <td class="px-3 py-2 text-xs tabular-nums text-gray-400">{{ $site['row'] }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $badge }}">
                            {{ $badgeLabel }}
                        </span>
                    </td>
                    <td class="max-w-[160px] px-3 py-2">
                        <span class="block truncate font-mono text-xs text-gray-600"
                              title="{{ $site['data']['external_id'] }}">
                            {{ $site['data']['external_id'] }}
                        </span>
                    </td>
                    <td class="max-w-[200px] px-3 py-2 font-medium text-gray-800">
                        <span class="block truncate" title="{{ $site['data']['name'] }}">
                            {{ $site['data']['name'] }}
                        </span>
                    </td>
                    <td class="px-3 py-2 font-mono text-xs text-gray-500 whitespace-nowrap">
                        {{ $site['data']['lat'] }}, {{ $site['data']['lon'] }}
                    </td>
                    <td class="max-w-[260px] px-3 py-2">
                        @if($site['action'] === 'error')
                            <span class="text-xs text-danger-600">{{ $site['error'] }}</span>
                        @elseif(!empty($site['changes']))
                            <ul class="space-y-0.5 text-xs text-warning-700">
                                @foreach($site['changes'] as $c)
                                    <li class="flex items-start gap-1"><span class="shrink-0 text-warning-400">•</span>{{ $c }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-xs italic text-gray-400">Không có thay đổi</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-3 py-6 text-center text-sm italic text-gray-400">
                        Không có site nào khớp bộ lọc
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($this->getSiteTotal() > $perPage)
    <div class="mt-3 flex items-center justify-between px-1 text-sm text-gray-500">
        <span>Trang {{ $sitePage }} / {{ ceil($this->getSiteTotal() / $perPage) }}
            &middot; {{ number_format($this->getSiteTotal()) }} bản ghi</span>
        <div class="flex gap-2">
            @if($sitePage > 1)
                <x-filament::button size="sm" color="gray"
                    wire:click="$set('sitePage', {{ $sitePage - 1 }})">← Trước</x-filament::button>
            @endif
            @if($this->hasSiteNextPage())
                <x-filament::button size="sm" color="gray"
                    wire:click="$set('sitePage', {{ $sitePage + 1 }})">Sau →</x-filament::button>
            @endif
        </div>
    </div>
    @endif
</x-filament::section>
@endif

{{-- ═══════════════════════════════════════════════════════════════════════════
     SCREENS TABLE
════════════════════════════════════════════════════════════════════════════ --}}
@if($this->getScreenTotal() > 0)
<x-filament::section collapsible class="mb-24">
    <x-slot name="heading">
        <span class="flex items-center gap-2 text-sm">
            <x-heroicon-o-tv class="h-4 w-4 text-gray-400"/>
            Screens
            <span class="font-normal text-gray-400">
                {{ $s['screens_new'] }} mới &middot;
                {{ $s['screens_update'] }} cập nhật &middot;
                {{ $s['screens_error'] }} lỗi
            </span>
        </span>
    </x-slot>

    <div class="-mx-1 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="w-10 px-3 py-2.5 text-left font-semibold text-gray-500">Row</th>
                    <th class="w-28 px-3 py-2.5 text-left font-semibold text-gray-500">Status</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">Screen ID</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">Tên</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">Site ID</th>
                    <th class="px-3 py-2.5 text-center font-semibold text-gray-500">Res.</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">Venue Type</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-500">CPM (VND)</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-500">Thay đổi / Lỗi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->getFilteredScreens() as $scr)
                @php
                    [$rowBg, $badge, $badgeLabel] = match($scr['action']) {
                        'new'    => ['bg-success-50/60', 'bg-success-100 text-success-700 ring-success-200', '✦ Mới'],
                        'update' => ['bg-warning-50/60', 'bg-warning-100 text-warning-700 ring-warning-200', '↻ Update'],
                        'error'  => ['bg-danger-50/70',  'bg-danger-100  text-danger-700  ring-danger-200',  '✕ Lỗi'],
                        default  => ['', 'bg-gray-100 text-gray-600 ring-gray-200', $scr['action']],
                    };
                @endphp
                <tr class="{{ $rowBg }} transition-colors">
                    <td class="px-3 py-2 text-xs tabular-nums text-gray-400">{{ $scr['row'] }}</td>
                    <td class="px-3 py-2">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $badge }}">
                            {{ $badgeLabel }}
                        </span>
                    </td>
                    <td class="max-w-[130px] px-3 py-2">
                        <span class="block truncate font-mono text-xs text-gray-600"
                              title="{{ $scr['data']['external_id'] }}">
                            {{ $scr['data']['external_id'] }}
                        </span>
                    </td>
                    <td class="max-w-[150px] px-3 py-2 font-medium text-gray-800">
                        <span class="block truncate" title="{{ $scr['data']['name'] }}">
                            {{ $scr['data']['name'] }}
                        </span>
                    </td>
                    <td class="max-w-[120px] px-3 py-2">
                        <span class="block truncate font-mono text-xs text-gray-500"
                              title="{{ $scr['data']['site_external_id'] }}">
                            {{ $scr['data']['site_external_id'] }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-center font-mono text-xs text-gray-600 whitespace-nowrap tabular-nums">
                        {{ $scr['data']['width_px'] }}×{{ $scr['data']['height_px'] }}
                    </td>
                    <td class="max-w-[130px] px-3 py-2 text-xs text-gray-600">
                        <span class="block truncate" title="{{ $scr['data']['venue_type'] }}">
                            {{ $scr['data']['venue_type'] }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-right font-mono text-xs text-gray-700 tabular-nums whitespace-nowrap">
                        {{ $scr['data']['floor_cpm'] ? number_format((float)$scr['data']['floor_cpm']) : '—' }}
                    </td>
                    <td class="max-w-[220px] px-3 py-2">
                        @if($scr['action'] === 'error')
                            <span class="text-xs leading-relaxed text-danger-600">{{ $scr['error'] }}</span>
                        @elseif(!empty($scr['changes']))
                            <ul class="space-y-0.5 text-xs text-warning-700">
                                @foreach($scr['changes'] as $c)
                                    <li class="flex items-start gap-1"><span class="shrink-0 text-warning-400">•</span>{{ $c }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-xs italic text-gray-400">Không có thay đổi</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-3 py-6 text-center text-sm italic text-gray-400">
                        Không có screen nào khớp bộ lọc
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($this->getScreenTotal() > $perPage)
    <div class="mt-3 flex items-center justify-between px-1 text-sm text-gray-500">
        <span>Trang {{ $screenPage }} / {{ ceil($this->getScreenTotal() / $perPage) }}
            &middot; {{ number_format($this->getScreenTotal()) }} bản ghi</span>
        <div class="flex gap-2">
            @if($screenPage > 1)
                <x-filament::button size="sm" color="gray"
                    wire:click="$set('screenPage', {{ $screenPage - 1 }})">← Trước</x-filament::button>
            @endif
            @if($this->hasScreenNextPage())
                <x-filament::button size="sm" color="gray"
                    wire:click="$set('screenPage', {{ $screenPage + 1 }})">Sau →</x-filament::button>
            @endif
        </div>
    </div>
    @endif
</x-filament::section>
@endif

{{-- ── Sticky bottom bar ────────────────────────────────────────────────── --}}
<div class="fixed bottom-0 left-0 right-0 z-20 border-t border-gray-200 bg-white/95 shadow-2xl backdrop-blur-sm dark:border-gray-700 dark:bg-gray-900/95"
     style="margin-left: var(--sidebar-width, 256px)">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-6 py-3">
        <div class="flex flex-1 flex-wrap items-center gap-x-5 gap-y-1 text-sm">
            <span class="font-semibold text-success-600">✦ {{ number_format($totalNew) }} thêm mới</span>
            <span class="font-semibold text-warning-600">↻ {{ number_format($totalUpd) }} cập nhật</span>
            @if($hasErr)
                <span class="font-semibold text-danger-600">✕ {{ number_format($totalErr) }} lỗi bị bỏ qua</span>
            @endif
        </div>
        <div class="flex shrink-0 gap-2">
            <x-filament::button color="gray" wire:click="backToUpload" icon="heroicon-o-arrow-left">
                Đổi file
            </x-filament::button>
            <x-filament::button
                color="{{ $hasErr ? 'warning' : 'success' }}"
                wire:click="confirmImport"
                wire:loading.attr="disabled"
                wire:target="confirmImport"
                icon="heroicon-o-check-circle"
                size="lg"
                wire:confirm="{{ $hasErr
                    ? 'File có ' . $totalErr . ' dòng lỗi sẽ bị bỏ qua. Import ' . ($totalNew + $totalUpd) . ' dòng hợp lệ?'
                    : 'Xác nhận lưu ' . $totalNew . ' mới và cập nhật ' . $totalUpd . ' bản ghi?' }}">
                <span wire:loading.remove wire:target="confirmImport">Confirm & Import</span>
                <span wire:loading wire:target="confirmImport" class="flex items-center gap-1.5">
                    <x-filament::loading-indicator class="h-4 w-4"/> Đang lưu...
                </span>
            </x-filament::button>
        </div>
    </div>
</div>

@endif {{-- end expired check --}}
</x-filament-panels::page>
