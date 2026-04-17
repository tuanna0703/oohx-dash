{{-- Unified owner card component
     @include('frontpage.partials.owner-card', ['owner' => $owner, 'variant' => 'full|compact|mini'])

     Variants:
       'full'    — Owners page: cover + logo + name + desc + tags + stats + footer buttons
       'compact' — Homepage featured: logo + name + tags + stats (no cover, no footer)
       'mini'    — Detail page: logo + name + verified + extra info, single row

     Props:
       $owner    — Owner model (with screen_count, city_count, venue_types_list)
       $variant  — 'full' (default) | 'compact' | 'mini'
       $extraLabel — (mini only) right-side text, e.g. network name
       $href     — (mini only) custom link URL
--}}
@php
    $variant   = $variant ?? 'full';
    $isFull    = $variant === 'full';
    $isCompact = $variant === 'compact';
    $isMini    = $variant === 'mini';
    $isFeat    = $owner->featured ?? false;

    $initials = strtoupper(collect(explode(' ', $owner->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join(''));
    $colors = [
        ['#E8EAFF','#2A4FF6'], ['#E3F8E8','#34C759'], ['#F3E8FF','#AF52DE'],
        ['#FFE8E8','#FF3B30'], ['#E8F4FF','#0071E3'], ['#FFF3E8','#FF9F0A'],
    ];
    $colorPair = $colors[($loop->index ?? 0) % count($colors)];
    $ownerLink = $href ?? '/owners/' . ($owner->slug ?? '');
    $hasLogo   = !empty($owner->logo_url);
    $isVerified = $owner->verified ?? false;
@endphp

@if($isMini)
{{-- ═══ MINI variant ═══ --}}
<a href="{{ $ownerLink }}" class="oc-mini">
    @if($hasLogo)
    <img src="{{ asset('storage/' . $owner->logo_url) }}" class="oc-mini-logo" alt="{{ $owner->name }}">
    @else
    <div class="oc-mini-logo oc-mini-logo--txt" style="background:{{ $colorPair[0] }};color:{{ $colorPair[1] }}">{{ $initials }}</div>
    @endif
    <div class="oc-mini-body">
        <div class="oc-mini-name">{{ $owner->name }}</div>
        @if($isVerified)<div class="oc-mini-ver"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:12px;height:12px;flex-shrink:0"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg> Verified</div>@endif
    </div>
    <div class="oc-mini-right">
        @if(!empty($extraLabel))<span class="oc-mini-extra">{{ $extraLabel }}</span>@endif
        <svg viewBox="0 0 24 24" fill="var(--t4)" style="width:16px;height:16px;flex-shrink:0"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
    </div>
</a>
@else
{{-- ═══ FULL / COMPACT variant ═══ --}}
@php $tags = array_slice($owner->venue_types_list ?? [], 0, $isCompact ? 2 : 3); @endphp
<a href="{{ $ownerLink }}" class="oc-card{{ $isCompact ? ' oc-card--compact' : '' }}{{ $isFeat ? ' oc-card--feat' : '' }}">
    {{-- Cover (both full + compact) --}}
    @if($isFeat)<div class="oc-card-feat-badge">Featured</div>@endif
    <div class="oc-card-cover">
        <img src="{{ $owner->cover_url ? asset('storage/' . $owner->cover_url) : 'https://placehold.co/800x300/' . ltrim($colorPair[0], '#') . '/' . ltrim($colorPair[1], '#') . '?text=' . urlencode($owner->name) }}" loading="lazy" alt="{{ $owner->name }}">
        <div class="oc-card-cover-ov"></div>
        <div class="oc-card-cover-badges"><span class="badge b-bl">{{ $owner->screen_count ?? 0 }} inv</span></div>
    </div>

    {{-- Head: logo overlapping cover + name --}}
    <div class="oc-card-head">
        @if($hasLogo)
        <img src="{{ asset('storage/' . $owner->logo_url) }}" class="oc-card-logo" loading="lazy" alt="{{ $owner->name }}">
        @else
        <div class="oc-card-logo oc-card-logo--txt" style="background:{{ $colorPair[0] }};color:{{ $colorPair[1] }}">{{ $initials }}</div>
        @endif
        <div class="oc-card-name-wrap">
            <div class="oc-card-name">{{ $owner->name }}</div>
            @if($isVerified)
            <div class="oc-card-ver">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:12px;height:12px;flex-shrink:0"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>
                Verified
            </div>
            @endif
        </div>
    </div>

    @if($isFull && ($owner->tagline || $owner->about))
    <div class="oc-card-desc">{{ $owner->tagline ?? Str::limit($owner->about ?? '', 120) }}</div>
    @endif

    {{-- Tags --}}
    @if(!empty($tags))
    <div class="oc-card-tags">
        @foreach($tags as $tag)
        <span class="oc-card-tag">{{ ucfirst(str_replace(['_', '.'], ' ', $tag)) }}</span>
        @endforeach
    </div>
    @endif

    {{-- Stats --}}
    <div class="oc-card-stats">
        <div class="oc-card-stat"><div class="oc-card-stat-n">{{ $owner->screen_count ?? 0 }}</div><div class="oc-card-stat-l">Inventory</div></div>
        <div class="oc-card-stat"><div class="oc-card-stat-n">{{ $owner->city_count ?? 0 }}</div><div class="oc-card-stat-l">Cities</div></div>
        @if($isFull)
        <div class="oc-card-stat"><div class="oc-card-stat-n">—</div><div class="oc-card-stat-l">Response</div></div>
        @endif
    </div>

    @if($isFull)
    <div class="oc-card-foot">
        <span class="btn btn-s btn-sm" style="flex:1;justify-content:center">Xem inventory</span>
        <span class="btn btn-p btn-sm" style="flex:1;justify-content:center">Liên hệ</span>
    </div>
    @endif
</a>
@endif
