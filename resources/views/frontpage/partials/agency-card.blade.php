{{-- Agency card — mirrors owner-card layout (full variant)
     Receives: $agency (Organization model with type=agency, campaign_count)
--}}
@php
    $initials = strtoupper(collect(explode(' ', $agency->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join(''));
    $colors = [
        ['#E8EAFF','#2A4FF6'], ['#E3F8E8','#34C759'], ['#F3E8FF','#AF52DE'],
        ['#FFE8E8','#FF3B30'], ['#E8F4FF','#0071E3'], ['#FFF3E8','#FF9F0A'],
    ];
    $colorPair = $colors[($loop->index ?? 0) % count($colors)];
    $hasLogo = !empty($agency->logo_url);
    $agencyLink = '#'; // Future: /agencies/{slug}
@endphp
<a href="{{ $agencyLink }}" class="oc-card">
    {{-- Cover --}}
    <div class="oc-card-cover">
        <img src="https://placehold.co/800x300/{{ ltrim($colorPair[0], '#') }}/{{ ltrim($colorPair[1], '#') }}?text={{ urlencode($agency->name) }}" loading="lazy" alt="{{ $agency->name }}">
        <div class="oc-card-cover-ov"></div>
        <div class="oc-card-cover-badges"><span class="badge b-bl">{{ ucfirst($agency->type) }}</span></div>
    </div>

    {{-- Head: logo + name --}}
    <div class="oc-card-head">
        @if($hasLogo)
        <img src="{{ asset('storage/' . $agency->logo_url) }}" class="oc-card-logo" loading="lazy" alt="{{ $agency->name }}">
        @else
        <div class="oc-card-logo oc-card-logo--txt" style="background:{{ $colorPair[0] }};color:{{ $colorPair[1] }}">{{ $initials }}</div>
        @endif
        <div class="oc-card-name-wrap">
            <div class="oc-card-name">{{ $agency->name }}</div>
            @if($agency->website)
            <div class="oc-card-ver" style="color:var(--t4)">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:11px;height:11px;flex-shrink:0"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                {{ parse_url($agency->website, PHP_URL_HOST) ?? $agency->website }}
            </div>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="oc-card-stats">
        <div class="oc-card-stat"><div class="oc-card-stat-n">{{ $agency->campaign_count ?? 0 }}</div><div class="oc-card-stat-l">Chiến dịch</div></div>
        <div class="oc-card-stat"><div class="oc-card-stat-n">{{ $agency->payment_terms_days ?? '—' }}@if($agency->payment_terms_days)<span style="font-size:11px;color:var(--t4)">d</span>@endif</div><div class="oc-card-stat-l">Terms</div></div>
        <div class="oc-card-stat"><div class="oc-card-stat-n">—</div><div class="oc-card-stat-l">Rating</div></div>
    </div>

    {{-- Footer --}}
    <div class="oc-card-foot">
        <span class="btn btn-s btn-sm" style="flex:1;justify-content:center">Xem chi tiết</span>
        <span class="btn btn-p btn-sm" style="flex:1;justify-content:center">Liên hệ</span>
    </div>
</a>
