{{-- Unified owner card component
     @include('frontpage.partials.owner-card', ['owner' => $owner, 'variant' => 'compact|full'])
     Receives: $owner (Owner model with screen_count, city_count, venue_types_list)
     Variant: 'compact' (homepage) | 'full' (owners page, default)
--}}
@php
    $variant = $variant ?? 'full';
    $isCompact = $variant === 'compact';
    $isFeat = $owner->featured ?? false;
    $initials = strtoupper(collect(explode(' ', $owner->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join(''));
    $colors = [
        ['#1D1D1F','#3A3A3C'], ['#1C3A5E','#2A4FF6'], ['#1A3A2A','#34C759'],
        ['#3A1A3A','#AF52DE'], ['#3A1C1A','#FF3B30'], ['#1A2A3A','#0071E3'],
    ];
    $colorPair = $colors[$loop->index % count($colors)] ?? $colors[0];
    $tags = array_slice($owner->venue_types_list ?? [], 0, $isCompact ? 2 : 3);
@endphp
<a href="/owners/{{ $owner->slug }}" class="oc-card{{ $isCompact ? ' oc-card--compact' : '' }}{{ $isFeat ? ' oc-card--feat' : '' }}">
    @if(!$isCompact)
    {{-- Cover --}}
    @if($isFeat)<div class="oc-card-feat-badge">Featured</div>@endif
    <div class="oc-card-cover">
        <img src="{{ $owner->cover_url ? asset('storage/' . $owner->cover_url) : 'https://placehold.co/800x300/1C3A5E/fff?text=' . urlencode($owner->name) }}" loading="lazy" alt="{{ $owner->name }}">
        <div class="oc-card-cover-ov"></div>
        <div class="oc-card-cover-badges"><span class="badge b-bl">{{ $owner->screen_count }} inv</span></div>
    </div>
    @endif

    {{-- Head: logo + name --}}
    <div class="oc-card-head">
        @if($owner->logo_url)
        <img src="{{ asset('storage/' . $owner->logo_url) }}" class="oc-card-logo" loading="lazy" alt="{{ $owner->name }}">
        @else
        <div class="oc-card-logo oc-card-logo--initials" style="background:linear-gradient(135deg,{{ $colorPair[0] }},{{ $colorPair[1] }})">{{ $initials }}</div>
        @endif
        <div class="oc-card-name-wrap">
            <div class="oc-card-name">{{ $owner->name }}</div>
            <div class="oc-card-ver">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>
                Verified
            </div>
        </div>
    </div>

    @if(!$isCompact && ($owner->tagline || $owner->about))
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
        @unless($isCompact)
        <div class="oc-card-stat"><div class="oc-card-stat-n">—</div><div class="oc-card-stat-l">Response</div></div>
        @endunless
    </div>

    @if(!$isCompact)
    {{-- Footer --}}
    <div class="oc-card-foot">
        <span class="btn btn-s btn-sm" style="flex:1;justify-content:center">Xem inventory</span>
        <span class="btn btn-p btn-sm" style="flex:1;justify-content:center">Liên hệ</span>
    </div>
    @endif
</a>
