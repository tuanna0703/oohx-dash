@php
    // BUG FIX: $hours có thể null nếu inventory chưa có data → guard thành array
    $hours    = is_array($getState()) ? $getState() : [];
    $dayShort = ['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'];
    $grid = [];
    foreach (array_keys($dayShort) as $day) {
        $val = $hours[$day] ?? null;
        if ($val === 'closed' || empty($val)) {
            $grid[$day] = array_fill(0, 24, false);
        } elseif (array_is_list((array) $val)) {
            // New format: array of active hour indices [8, 9, 12, 14, ...]
            $active = array_map('intval', (array) $val);
            $grid[$day] = array_map(fn($h) => in_array($h, $active), range(0, 23));
        } else {
            // Legacy format: {open: '08:00', close: '18:00'}
            $open  = (int) explode(':', $val['open']  ?? '00:00')[0];
            $close = (int) explode(':', $val['close'] ?? '24:00')[0];
            $grid[$day] = array_map(fn($h) => $h >= $open && $h < $close, range(0, 23));
        }
    }
@endphp
<div class="select-none overflow-x-auto">
    <div class="flex items-center mb-1">
        <div class="w-16 shrink-0"></div>
        <div class="grid flex-1 gap-px" style="grid-template-columns:repeat(24,1fr)">
            @foreach(range(0,23) as $h)
                <div class="text-center">
                    <span class="text-xs font-semibold text-gray-500">{{ $h === 0 ? '12' : ($h <= 12 ? $h : $h-12) }}</span>
                    <span class="block text-xs text-gray-400">{{ $h < 12 ? 'AM' : 'PM' }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @foreach($dayShort as $key => $label)
        <div class="flex items-center gap-1 mb-1">
            <div class="w-16 shrink-0 text-xs font-semibold text-gray-600">{{ $label }}</div>
            <div class="grid flex-1 gap-px" style="grid-template-columns:repeat(24,1fr)">
                @foreach(range(0,23) as $h)
                    <div class="h-7 rounded-sm {{ ($grid[$key][$h] ?? false) ? 'bg-primary-500' : 'bg-gray-200' }}"></div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
