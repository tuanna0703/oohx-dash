@php
    $hours = is_array($getState()) ? $getState() : [];
    $dayLabels = [
        'mon' => 'Thứ 2', 'tue' => 'Thứ 3', 'wed' => 'Thứ 4',
        'thu' => 'Thứ 5', 'fri' => 'Thứ 6', 'sat' => 'Thứ 7',
        'sun' => 'Chủ nhật',
    ];

    $rows = [];
    foreach ($dayLabels as $key => $label) {
        $val = $hours[$key] ?? null;

        if ($val === 'closed' || empty($val)) {
            $rows[] = ['label' => $label, 'active' => false, 'time' => ''];
        } elseif (is_array($val) && !array_is_list($val) && isset($val['open'], $val['close'])) {
            $rows[] = ['label' => $label, 'active' => true, 'time' => $val['open'] . ' – ' . $val['close']];
        } elseif (is_array($val) && array_is_list($val)) {
            // Hour array format → derive range
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
        <div class="w-20 text-sm font-medium {{ $row['active'] ? 'text-gray-800' : 'text-gray-400' }}">
            {{ $row['label'] }}
        </div>
        <div class="text-sm {{ $row['active'] ? 'text-gray-600' : 'text-gray-400 italic' }}">
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
