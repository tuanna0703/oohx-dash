@php
    /** @var \App\Models\Screen $record */
    $screen = $record ?? $getRecord();

    $audience      = $screen->audience_profile ?? [];
    $time          = $screen->time_performance ?? [];
    $nearby        = $screen->nearby_context   ?? [];

    $hasAudience   = ! empty(array_filter($audience, fn ($v) => $v !== null && $v !== ''));
    $hasTime       = ! empty(array_filter($time,     fn ($v) => $v !== null && $v !== ''));
    $hasNearby     = ! empty(array_filter($nearby,   fn ($v) => ! empty($v)));
    $hasTraffic    = $screen->daily_footfall || $screen->monthly_reach || $screen->daily_impressions;
    $hasPlacement  = ! empty($screen->placement_zone);
    $hasAnything   = $hasAudience || $hasTime || $hasNearby || $hasTraffic || $hasPlacement;

    $placementLabels = [
        'entrance' => 'Lối vào', 'checkout' => 'Quầy thanh toán', 'escalator' => 'Cạnh thang cuốn',
        'food_court' => 'Khu food court', 'facade' => 'Mặt tiền tòa nhà', 'lobby' => 'Sảnh',
        'parking' => 'Bãi đậu xe', 'other' => 'Khác',
    ];
    $orientationLabels = [
        'landscape' => 'Ngang', 'portrait' => 'Dọc', 'square' => 'Vuông',
    ];
    $dayLabels = ['mon'=>'T2','tue'=>'T3','wed'=>'T4','thu'=>'T5','fri'=>'T6','sat'=>'T7','sun'=>'CN'];
@endphp

<div class="space-y-4 text-sm">

    @if(! $hasAnything)
        <div class="p-6 text-center bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 text-gray-500">
            Chưa có Inventory Intelligence data.<br>
            Dùng nút <strong>Enrich AI Context</strong> ở table actions để generate.
        </div>
    @endif

    {{-- ── 1. Stat row ─────────────────────────────────────────── --}}
    @if($hasTraffic || $hasPlacement || ! empty($time['peak_hour_start']))
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        @if($screen->daily_footfall)
        <div class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 mb-1">Lượt khách / ngày</div>
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($screen->daily_footfall) }}</div>
        </div>
        @endif
        @if($screen->monthly_reach)
        <div class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 mb-1">Reach / tháng</div>
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($screen->monthly_reach) }}</div>
        </div>
        @endif
        @if($screen->daily_impressions)
        <div class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 mb-1">Impr. / ngày <span class="font-normal lowercase">(ước tính)</span></div>
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ number_format($screen->daily_impressions) }}</div>
        </div>
        @endif
        @if(! empty($time['peak_hour_start']) || ! empty($time['peak_hour_end']))
        <div class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 mb-1">Khung giờ peak</div>
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $time['peak_hour_start'] ?? '?' }}<span class="text-gray-400 mx-1">–</span>{{ $time['peak_hour_end'] ?? '?' }}</div>
        </div>
        @endif
        @if($hasPlacement)
        <div class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 mb-1">Vị trí venue</div>
            <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $placementLabels[$screen->placement_zone] ?? $screen->placement_zone }}</div>
        </div>
        @endif
        @if($screen->orientation)
        <div class="p-3 bg-gray-50 dark:bg-gray-900/40 rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 mb-1">Hướng màn hình</div>
            <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $orientationLabels[$screen->orientation] ?? $screen->orientation }}</div>
        </div>
        @endif
    </div>
    @endif

    {{-- ── 2. Audience profile ─────────────────────────────────── --}}
    @if($hasAudience)
    <div class="rounded-lg border border-primary-200 dark:border-primary-800 overflow-hidden">
        <div class="px-4 py-2 bg-primary-50 dark:bg-primary-900/30 font-bold text-primary-800 dark:text-primary-100 text-xs uppercase tracking-wide">
            🎯 Audience Profile
        </div>
        <div class="p-4 space-y-3">
            @if(($audience['male_pct'] ?? null) !== null || ($audience['female_pct'] ?? null) !== null)
            @php $male = (int)($audience['male_pct'] ?? 0); $female = (int)($audience['female_pct'] ?? 0); @endphp
            <div class="grid grid-cols-[60px_1fr_40px] items-center gap-2">
                <span class="text-xs font-semibold text-gray-600">Nam</span>
                <div class="h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-blue-500" style="width:{{ min($male, 100) }}%"></div>
                </div>
                <span class="text-xs font-bold text-right">{{ $male }}%</span>
            </div>
            <div class="grid grid-cols-[60px_1fr_40px] items-center gap-2">
                <span class="text-xs font-semibold text-gray-600">Nữ</span>
                <div class="h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-pink-500" style="width:{{ min($female, 100) }}%"></div>
                </div>
                <span class="text-xs font-bold text-right">{{ $female }}%</span>
            </div>
            @endif

            @php
                $ageBuckets = [
                    'age_18_24_pct' => '18-24', 'age_25_34_pct' => '25-34',
                    'age_35_44_pct' => '35-44', 'age_45_plus_pct' => '45+',
                ];
                $hasAge = collect($ageBuckets)->keys()->some(fn ($k) => ($audience[$k] ?? null) !== null);
            @endphp
            @if($hasAge)
            <div class="pt-2 border-t border-gray-100 dark:border-gray-800 space-y-2">
                <div class="text-[11px] font-bold uppercase text-gray-500">Độ tuổi</div>
                @foreach($ageBuckets as $key => $label)
                    @php $val = (int)($audience[$key] ?? 0); @endphp
                    <div class="grid grid-cols-[60px_1fr_40px] items-center gap-2">
                        <span class="text-xs font-semibold text-gray-600">{{ $label }}</span>
                        <div class="h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-green-500" style="width:{{ min($val, 100) }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-right">{{ $val }}%</span>
                    </div>
                @endforeach
            </div>
            @endif

            <div class="flex flex-wrap items-center gap-2 pt-2 text-xs">
                @if(! empty($audience['income_tier']))
                    <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 font-semibold">
                        Income: {{ $audience['income_tier'] }}
                    </span>
                @endif
                @foreach((array) ($audience['lifestyle_tags'] ?? []) as $tag)
                    <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">{{ $tag }}</span>
                @endforeach
            </div>

            @if(! empty($audience['source_note']))
                <p class="text-xs text-gray-500 italic break-words pt-1">↳ {{ $audience['source_note'] }}</p>
            @endif
        </div>
    </div>
    @endif

    {{-- ── 3. Time performance ─────────────────────────────────── --}}
    @if($hasTime)
    <div class="rounded-lg border border-orange-200 dark:border-orange-800 overflow-hidden">
        <div class="px-4 py-2 bg-orange-50 dark:bg-orange-900/30 font-bold text-orange-800 dark:text-orange-100 text-xs uppercase tracking-wide">
            ⏰ Time Performance
        </div>
        <div class="p-4 space-y-3">
            @if(! empty($time['best_day']))
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-500 mb-2">Ngày trong tuần</div>
                <div class="grid grid-cols-7 gap-1.5">
                    @foreach($dayLabels as $code => $lbl)
                        @php $isBest = $code === ($time['best_day'] ?? ''); @endphp
                        <div class="text-center text-xs font-bold py-2 rounded-md
                            {{ $isBest
                                ? 'bg-orange-500 text-white shadow-md scale-105'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-500' }}">
                            {{ $lbl }}
                        </div>
                    @endforeach
                </div>
                <div class="text-[11px] text-gray-500 mt-1">Best day: <strong class="text-orange-600">{{ $dayLabels[$time['best_day']] ?? '' }}</strong></div>
            </div>
            @endif

            @php
                $periods = [
                    'morning_pct'   => ['Sáng', '6h-12h'],
                    'afternoon_pct' => ['Chiều', '12h-18h'],
                    'evening_pct'   => ['Tối', '18h-24h'],
                ];
                $hasPeriod = collect($periods)->keys()->some(fn ($k) => ($time[$k] ?? null) !== null);
            @endphp
            @if($hasPeriod)
            <div class="pt-2 border-t border-gray-100 dark:border-gray-800 space-y-2">
                <div class="text-[11px] font-bold uppercase text-gray-500">Phân bổ traffic theo buổi</div>
                @foreach($periods as $key => [$lbl, $hint])
                    @php $val = (int)($time[$key] ?? 0); @endphp
                    <div class="grid grid-cols-[120px_1fr_40px] items-center gap-2">
                        <span class="text-xs font-semibold text-gray-600">{{ $lbl }} <span class="text-gray-400 font-normal">{{ $hint }}</span></span>
                        <div class="h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-orange-400" style="width:{{ min($val, 100) }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-right">{{ $val }}%</span>
                    </div>
                @endforeach
            </div>
            @endif

            @if(! empty($time['rationale']))
                <p class="text-xs text-gray-500 italic break-words pt-1">↳ {{ $time['rationale'] }}</p>
            @endif
        </div>
    </div>
    @endif

    {{-- ── 4. Nearby context ──────────────────────────────────── --}}
    @if($hasNearby)
    <div class="rounded-lg border border-purple-200 dark:border-purple-800 overflow-hidden">
        <div class="px-4 py-2 bg-purple-50 dark:bg-purple-900/30 font-bold text-purple-800 dark:text-purple-100 text-xs uppercase tracking-wide">
            📍 Bối cảnh xung quanh
        </div>
        <div class="p-4 space-y-3">
            @if(! empty($nearby['highlights']))
                <p class="text-sm leading-relaxed break-words">{{ $nearby['highlights'] }}</p>
            @endif

            @if(! empty($nearby['brands']))
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-500 mb-2">Brand lân cận</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach((array) $nearby['brands'] as $brand)
                        <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 text-xs font-semibold break-words max-w-full">{{ $brand }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            @if(! empty($nearby['landmarks']))
            <div>
                <div class="text-[11px] font-bold uppercase text-gray-500 mb-2">Landmark / địa danh</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach((array) $nearby['landmarks'] as $lm)
                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300 text-xs font-semibold break-words max-w-full">{{ $lm }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ── 5. Methodology footnote ────────────────────────────── --}}
    @if(! empty($screen->traffic_methodology_note))
    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/40 border border-dashed border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-600 dark:text-gray-400 break-words">
        <strong class="text-gray-700 dark:text-gray-200">Nguồn dữ liệu:</strong> {{ $screen->traffic_methodology_note }}
    </div>
    @endif

</div>
