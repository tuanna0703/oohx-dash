@php
    /** @var \App\Models\Oohx\CollectorRun $record */
    $record = $getRecord();
    $stats = $record->stats ?? [];

    // POI stats keys: inserted, updated, skipped, parsed, api_calls, tasks_planned, tasks_completed
    // Weather stats keys: inserted, api_calls (ít field hơn)
    $hasRichStats = isset($stats['parsed']) || isset($stats['updated']);

    // Layout compact cho weather vs detailed cho POI
    $entries = [];
    foreach ($stats as $key => $value) {
        if (! is_scalar($value)) continue;
        $entries[] = [
            'key'   => $key,
            'label' => ucfirst(str_replace('_', ' ', $key)),
            'value' => is_int($value) ? number_format($value) : (string) $value,
        ];
    }
@endphp

@if($hasRichStats)
    {{-- POI-style rich breakdown --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
        @foreach($entries as $e)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 mb-1">
                    {{ $e['label'] }}
                </div>
                <div class="text-lg font-bold text-gray-900 dark:text-gray-100 font-mono">
                    {{ $e['value'] }}
                </div>
            </div>
        @endforeach
    </div>

    @if(isset($stats['parsed']) && isset($stats['skipped']) && $stats['parsed'] > 0)
        @php $skipRatio = round($stats['skipped'] / $stats['parsed'] * 100, 1); @endphp
        @if($skipRatio > 10)
            <div class="mt-3 rounded-lg bg-warning-50 dark:bg-warning-950/30 border border-warning-200 dark:border-warning-800 px-3 py-2 text-sm text-warning-800 dark:text-warning-200">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="inline h-4 w-4" />
                <strong>{{ $skipRatio }}%</strong> POIs bị skip (không match category). Review category filter nếu tỉ lệ này tăng.
            </div>
        @endif
    @endif
@else
    {{-- Weather-style compact line --}}
    <div class="text-sm text-gray-700 dark:text-gray-300 font-mono">
        @foreach($entries as $i => $e)
            {{ $e['label'] }}: <strong>{{ $e['value'] }}</strong>{{ $i < count($entries) - 1 ? ' · ' : '' }}
        @endforeach
    </div>
@endif
