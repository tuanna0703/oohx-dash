@php
    $hours = is_array($getState()) ? $getState() : [];
    $dayLabels = [
        'mon' => 'Thứ 2', 'tue' => 'Thứ 3', 'wed' => 'Thứ 4',
        'thu' => 'Thứ 5', 'fri' => 'Thứ 6', 'sat' => 'Thứ 7',
        'sun' => 'Chủ nhật',
    ];

    // Parse time value: supports "HH:MM", decimal (0.416 = 10:00), or int hour
    $parseTime = function ($v) {
        if ($v === null || $v === '') return null;
        $v = (string) $v;
        // Already HH:MM
        if (preg_match('/^\d{1,2}:\d{2}$/', $v)) return $v;
        // Decimal fraction of day (Excel format: 0.416 ≈ 10:00)
        if (is_numeric($v) && (float) $v >= 0 && (float) $v < 1) {
            $totalMinutes = round((float) $v * 24 * 60);
            // Round to nearest 15 minutes for cleaner display
            $totalMinutes = (int) (round($totalMinutes / 15) * 15);
            $h = (int) floor($totalMinutes / 60);
            $m = $totalMinutes % 60;
            return str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
        }
        // Integer hour
        if (is_numeric($v) && (int) $v >= 0 && (int) $v <= 24) {
            return str_pad((int) $v, 2, '0', STR_PAD_LEFT) . ':00';
        }
        return $v;
    };

    $rows = [];
    foreach ($dayLabels as $key => $label) {
        $val = $hours[$key] ?? null;

        if ($val === 'closed' || empty($val)) {
            $rows[] = ['label' => $label, 'active' => false, 'time' => ''];
        } elseif (is_array($val) && !array_is_list($val) && isset($val['open'], $val['close'])) {
            $open  = $parseTime($val['open']);
            $close = $parseTime($val['close']);
            $rows[] = ['label' => $label, 'active' => true, 'time' => $open . ' – ' . $close];
        } elseif (is_array($val) && array_is_list($val)) {
            $sorted = collect($val)->sort()->values();
            if ($sorted->isEmpty()) {
                $rows[] = ['label' => $label, 'active' => false, 'time' => ''];
            } else {
                $open  = str_pad($sorted->first(), 2, '0', STR_PAD_LEFT) . ':00';
                $close = str_pad($sorted->last() + 1, 2, '0', STR_PAD_LEFT) . ':00';
                $rows[] = ['label' => $label, 'active' => true, 'time' => $open . ' – ' . $close];
            }
        } else {
            $rows[] = ['label' => $label, 'active' => true, 'time' => '00:00 – 24:00'];
        }
    }

    $activeCount = collect($rows)->where('active', true)->count();
    $allSame = $activeCount > 0 && collect($rows)->where('active', true)->pluck('time')->unique()->count() === 1;
@endphp

<div class="space-y-1">
    @foreach($rows as $row)
    <div class="flex items-center gap-3 py-1.5">
        <div class="w-4 flex items-center justify-center">
            @if($row['active'])
                <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
            @else
                <div class="w-2.5 h-2.5 rounded-full bg-gray-300"></div>
            @endif
        </div>
        <div class="w-20 text-sm font-medium {{ $row['active'] ? 'text-gray-800 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500' }}">
            {{ $row['label'] }}
        </div>
        <div class="text-sm {{ $row['active'] ? 'text-gray-600 dark:text-gray-400' : 'text-gray-400 dark:text-gray-600 italic' }}">
            {{ $row['active'] ? $row['time'] : 'Đóng cửa' }}
        </div>
    </div>
    @endforeach

    @if($activeCount === 7 && $allSame)
        <div class="pt-2 text-xs text-gray-400">
            Hàng ngày {{ collect($rows)->where('active', true)->first()['time'] }}
        </div>
    @elseif($activeCount === 0)
        <div class="pt-2 text-xs text-red-400">Tất cả ngày đóng cửa</div>
    @endif
</div>
