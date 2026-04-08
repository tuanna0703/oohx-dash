{{-- Owner mini card partial (used on homepage) --}}
{{-- Receives: $owner (Owner model with screen_count, city_count, venue_types_list) --}}
@php
    $initials = strtoupper(collect(explode(' ', $owner->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join(''));
    $colors = [
        ['#1D1D1F', '#3A3A3C'], ['#1C3A5E', '#2A4FF6'], ['#1A3A2A', '#34C759'],
        ['#3A1A3A', '#AF52DE'], ['#3A1C1A', '#FF3B30'], ['#1A2A3A', '#0071E3'],
    ];
    $colorPair = $colors[$loop->index % count($colors)] ?? $colors[0];
    $tags = array_slice($owner->venue_types_list ?? [], 0, 2);
@endphp
<div class="owner-card rv">
    <div class="oc-head">
        <div class="oc-logo" style="background:linear-gradient(135deg,{{ $colorPair[0] }},{{ $colorPair[1] }})">{{ $initials }}</div>
        <div>
            <div class="oc-name">{{ $owner->name }}</div>
            <div class="oc-ver"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:11px;height:11px;flex-shrink:0"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>Verified</div>
        </div>
    </div>
    @if(!empty($tags))
    <div class="oc-tags">
        @foreach($tags as $tag)
        <span class="oc-tag">{{ ucfirst(str_replace(['_', '.'], ' ', $tag)) }}</span>
        @endforeach
    </div>
    @endif
    <div class="oc-stats">
        <div><div class="oc-sn">{{ $owner->screen_count ?? 0 }}</div><div class="oc-sl">Inventory</div></div>
        <div><div class="oc-sn">{{ $owner->city_count ?? 0 }}</div><div class="oc-sl">Cities</div></div>
    </div>
</div>
