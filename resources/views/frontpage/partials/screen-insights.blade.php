{{-- Phase 1 Inventory Intelligence block.
     Receives: $screen (App\Models\Screen)
     Render guard: caller should check $screen->has_insights before include.

     Traffic numbers (impressions, passby, reach) ưu tiên Data Engine estimate
     (authoritative, rule-based). Phase 1 columns (daily_footfall, monthly_reach)
     dùng làm:
       - Override nếu owner đo thực tế → hiện badge "Đo thực tế"
       - Fallback khi Data Engine chưa có estimate (pending/stale tunnel)
--}}
@php
    $audience       = $screen->audience_profile ?? [];
    $time           = $screen->time_performance ?? [];
    $nearby         = $screen->nearby_context ?? [];
    $hasAudience    = !empty(array_filter($audience, fn($v) => $v !== null && $v !== ''));
    $hasTime        = !empty(array_filter($time,     fn($v) => $v !== null && $v !== ''));
    $hasNearby      = !empty(array_filter($nearby,   fn($v) => !empty($v)));
    $hasPlacement   = !empty($screen->placement_zone);

    // ── Traffic source resolution (Data Engine > owner override > AI fallback) ──
    $de = $screen->traffic_estimate; // cached accessor, null nếu chưa có hoặc tunnel down

    // Daily impressions: DE > owner daily_footfall×0.6 (via accessor) > null
    $dailyImp         = $de?->estimated_daily_impressions ?? $screen->daily_impressions;
    $dailyImpSource   = $de?->estimated_daily_impressions !== null ? 'de'
                       : ($screen->daily_footfall ? 'owner' : null);

    // Daily passby (Data Engine only — Phase 1 không có field tương đương)
    $dailyPassby      = $de?->estimated_daily_passby ?? $screen->daily_footfall;
    $dailyPassbySource= $de?->estimated_daily_passby !== null ? 'de'
                       : ($screen->daily_footfall ? 'owner' : null);

    // Monthly reach: DE > Phase 1 monthly_reach
    $monthlyReach     = $de?->estimated_monthly_reach ?? $screen->monthly_reach;
    $monthlyReachSrc  = $de?->estimated_monthly_reach !== null ? 'de'
                       : ($screen->monthly_reach ? 'owner' : null);

    $hasTraffic       = $dailyImp || $monthlyReach || $dailyPassby;

    // Confidence + method cho tooltip badge
    $deConfidence     = $de?->confidence_score;
    $deMethod         = $de?->estimation_method;
    $deCalcAt         = $de?->last_calculated_at;

    $sourceBadge = function (?string $src) {
        if ($src === 'de')    return ['label' => 'DE', 'title' => 'Ước tính bởi OOHX Data Engine (rule-based)'];
        if ($src === 'owner') return ['label' => 'Đo', 'title' => 'Owner cung cấp từ đo thực tế'];
        return null;
    };

    $placementLabels = [
        'entrance'   => 'Lối vào',
        'checkout'   => 'Quầy thanh toán',
        'escalator'  => 'Cạnh thang cuốn',
        'food_court' => 'Khu food court',
        'facade'     => 'Mặt tiền tòa nhà',
        'lobby'      => 'Sảnh',
        'parking'    => 'Bãi đậu xe',
        'other'      => 'Khác',
    ];

    $orientationLabels = [
        'landscape' => 'Ngang',
        'portrait'  => 'Dọc',
        'square'    => 'Vuông',
    ];

    $incomeLabels = [
        'low'  => 'Thu nhập thấp',
        'mid'  => 'Thu nhập trung',
        'high' => 'Thu nhập cao',
    ];

    $dayLabels = ['mon'=>'T2','tue'=>'T3','wed'=>'T4','thu'=>'T5','fri'=>'T6','sat'=>'T7','sun'=>'CN'];
@endphp

<div class="ins">

    {{-- ── 1. STAT ROW ─────────────────────────────────────────────── --}}
    @if($hasTraffic || $hasPlacement || ($time['peak_hour_start'] ?? null))
    <div class="ins-stats">
        @if($dailyPassby)
            @php $b = $sourceBadge($dailyPassbySource); @endphp
            <div class="ins-stat">
                <div class="ins-stat-l">Lượt khách / ngày
                    @if($b)<span class="ins-stat-src" title="{{ $b['title'] }}">{{ $b['label'] }}</span>@endif
                </div>
                <div class="ins-stat-v">{{ number_format($dailyPassby) }}</div>
            </div>
        @endif

        @if($monthlyReach)
            @php $b = $sourceBadge($monthlyReachSrc); @endphp
            <div class="ins-stat">
                <div class="ins-stat-l">Reach / tháng
                    @if($b)<span class="ins-stat-src" title="{{ $b['title'] }}">{{ $b['label'] }}</span>@endif
                </div>
                <div class="ins-stat-v">{{ number_format($monthlyReach) }}</div>
            </div>
        @endif

        @if($dailyImp)
            @php $b = $sourceBadge($dailyImpSource); @endphp
            <div class="ins-stat">
                <div class="ins-stat-l">Impressions / ngày
                    @if($b)<span class="ins-stat-src" title="{{ $b['title'] }}">{{ $b['label'] }}</span>@endif
                </div>
                <div class="ins-stat-v">{{ number_format($dailyImp) }}</div>
            </div>
        @endif

        @if(!empty($time['peak_hour_start']) || !empty($time['peak_hour_end']))
        <div class="ins-stat">
            <div class="ins-stat-l">Khung giờ peak</div>
            <div class="ins-stat-v">
                {{ $time['peak_hour_start'] ?? '?' }}<span class="ins-stat-sep">–</span>{{ $time['peak_hour_end'] ?? '?' }}
            </div>
        </div>
        @endif

        @if($hasPlacement)
        <div class="ins-stat">
            <div class="ins-stat-l">Vị trí trong venue</div>
            <div class="ins-stat-v ins-stat-v-text">{{ $placementLabels[$screen->placement_zone] ?? $screen->placement_zone }}</div>
        </div>
        @endif

        @if($screen->orientation)
        <div class="ins-stat">
            <div class="ins-stat-l">Hướng màn hình</div>
            <div class="ins-stat-v ins-stat-v-text">{{ $orientationLabels[$screen->orientation] ?? $screen->orientation }}</div>
        </div>
        @endif
    </div>

    {{-- Data Engine methodology line (chỉ hiện khi có estimate từ DE) --}}
    @if($de)
        <div class="ins-source" style="margin-top:-8px">
            <strong>Nguồn traffic:</strong> OOHX Data Engine ·
            Method: <code>{{ $deMethod ?? '—' }}</code> ·
            @if($deConfidence !== null)
                Confidence:
                @php
                    $c = (float) $deConfidence;
                    $tier = $c >= 0.7 ? 'cao' : ($c >= 0.5 ? 'trung bình' : 'thấp');
                @endphp
                <strong>{{ number_format($c, 2) }}</strong> ({{ $tier }}) ·
            @endif
            @if($deCalcAt)
                Cập nhật {{ \Illuminate\Support\Carbon::parse($deCalcAt)->diffForHumans() }}
            @endif
        </div>
    @endif
    @endif

    {{-- ── 2. AUDIENCE PROFILE ─────────────────────────────────────── --}}
    @if($hasAudience)
    <div class="ins-block">
        <div class="ins-block-h">
            <svg viewBox="0 0 24 24" fill="currentColor" class="ins-ic"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            Audience profile
        </div>

        @if(($audience['male_pct'] ?? null) !== null || ($audience['female_pct'] ?? null) !== null)
        <div class="ins-row">
            @php $male = (int)($audience['male_pct'] ?? 0); $female = (int)($audience['female_pct'] ?? 0); @endphp
            <div class="ins-bar-row">
                <span class="ins-bar-l">Nam</span>
                <div class="ins-bar"><div class="ins-bar-fill ins-bar-fill--bl" style="width:{{ min($male,100) }}%"></div></div>
                <span class="ins-bar-v">{{ $male }}%</span>
            </div>
            <div class="ins-bar-row">
                <span class="ins-bar-l">Nữ</span>
                <div class="ins-bar"><div class="ins-bar-fill ins-bar-fill--pur" style="width:{{ min($female,100) }}%"></div></div>
                <span class="ins-bar-v">{{ $female }}%</span>
            </div>
        </div>
        @endif

        @php
            $ageBuckets = [
                'age_18_24_pct'    => '18-24',
                'age_25_34_pct'    => '25-34',
                'age_35_44_pct'    => '35-44',
                'age_45_plus_pct'  => '45+',
            ];
            $hasAge = collect($ageBuckets)->keys()->some(fn($k) => ($audience[$k] ?? null) !== null);
        @endphp
        @if($hasAge)
        <div class="ins-row" style="margin-top:14px">
            <div class="ins-row-h">Độ tuổi</div>
            @foreach($ageBuckets as $key => $label)
                @php $val = (int)($audience[$key] ?? 0); @endphp
                <div class="ins-bar-row">
                    <span class="ins-bar-l">{{ $label }}</span>
                    <div class="ins-bar"><div class="ins-bar-fill ins-bar-fill--grn" style="width:{{ min($val,100) }}%"></div></div>
                    <span class="ins-bar-v">{{ $val }}%</span>
                </div>
            @endforeach
        </div>
        @endif

        @php
            $hasIncome    = !empty($audience['income_tier']);
            $lifestyle    = (array) ($audience['lifestyle_tags'] ?? []);
            $hasLifestyle = !empty($lifestyle);
        @endphp
        @if($hasIncome || $hasLifestyle)
        <div class="ins-pills" style="margin-top:14px">
            @if($hasIncome)
                <span class="ins-pill ins-pill--{{ $audience['income_tier'] }}">
                    {{ $incomeLabels[$audience['income_tier']] ?? $audience['income_tier'] }}
                </span>
            @endif
            @foreach($lifestyle as $tag)
                <span class="ins-pill ins-pill--neutral">{{ $tag }}</span>
            @endforeach
        </div>
        @endif

        @if(!empty($audience['source_note']))
        <div class="ins-source">Nguồn: {{ $audience['source_note'] }}</div>
        @endif
    </div>
    @endif

    {{-- ── 3. TIME PERFORMANCE ─────────────────────────────────────── --}}
    @if($hasTime)
    <div class="ins-block">
        <div class="ins-block-h">
            <svg viewBox="0 0 24 24" fill="currentColor" class="ins-ic"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm4.2 14.2L11 13V7h1.5v5.2l4.5 2.7-.8 1.3z"/></svg>
            Hiệu suất theo thời gian
        </div>

        @if(!empty($time['best_day']))
        <div class="ins-days">
            @foreach($dayLabels as $code => $lbl)
                <div class="ins-day {{ $code === ($time['best_day'] ?? '') ? 'is-best' : '' }}">{{ $lbl }}</div>
            @endforeach
        </div>
        <div class="ins-day-note">Ngày đẹp nhất: <strong>{{ $dayLabels[$time['best_day']] ?? '' }}</strong></div>
        @endif

        @php
            $periods = [
                'morning_pct'   => ['Sáng',  '6h-12h',  'org'],
                'afternoon_pct' => ['Chiều', '12h-18h', 'bl'],
                'evening_pct'   => ['Tối',   '18h-24h', 'pur'],
            ];
            $hasPeriod = collect($periods)->keys()->some(fn($k) => ($time[$k] ?? null) !== null);
        @endphp
        @if($hasPeriod)
        <div class="ins-row" style="margin-top:16px">
            <div class="ins-row-h">Phân bổ traffic theo buổi</div>
            @foreach($periods as $key => [$lbl, $hint, $color])
                @php $val = (int)($time[$key] ?? 0); @endphp
                <div class="ins-bar-row">
                    <span class="ins-bar-l">{{ $lbl }} <small>{{ $hint }}</small></span>
                    <div class="ins-bar"><div class="ins-bar-fill ins-bar-fill--{{ $color }}" style="width:{{ min($val,100) }}%"></div></div>
                    <span class="ins-bar-v">{{ $val }}%</span>
                </div>
            @endforeach
        </div>
        @endif

        @if(!empty($time['rationale']))
        <div class="ins-source">↳ {{ $time['rationale'] }}</div>
        @endif
    </div>
    @endif

    {{-- ── 4. NEARBY CONTEXT ──────────────────────────────────────── --}}
    @if($hasNearby)
    <div class="ins-block">
        <div class="ins-block-h">
            <svg viewBox="0 0 24 24" fill="currentColor" class="ins-ic"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
            Bối cảnh xung quanh
        </div>

        @if(!empty($nearby['highlights']))
        <div class="ins-highlight">{{ $nearby['highlights'] }}</div>
        @endif

        @if(!empty($nearby['brands']))
        <div class="ins-tags-group">
            <div class="ins-tags-l">Brand lân cận</div>
            <div class="ins-tags">
                @foreach($nearby['brands'] as $brand)
                    <span class="ins-tag ins-tag--bl">{{ $brand }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($nearby['landmarks']))
        <div class="ins-tags-group">
            <div class="ins-tags-l">Địa danh / landmark</div>
            <div class="ins-tags">
                @foreach($nearby['landmarks'] as $lm)
                    <span class="ins-tag ins-tag--grn">{{ $lm }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ── 5. METHODOLOGY FOOTNOTE ────────────────────────────────── --}}
    @if(!empty($screen->traffic_methodology_note))
    <div class="ins-source ins-source-foot">
        <strong>Nguồn dữ liệu:</strong> {{ $screen->traffic_methodology_note }}
    </div>
    @endif

</div>
