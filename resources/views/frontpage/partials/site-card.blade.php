{{-- Site card — used on /explore?group=site
     Receives: $site (Site model with owner, network, screen_count)
--}}
@php
    $photo = null;
    // Try to get first screen photo from site
    if ($site->relationLoaded('screens') && $site->screens->isNotEmpty()) {
        $photo = $site->screens->first()?->spec?->photo_url;
    }
    $photoUrl = $photo ? asset('storage/' . $photo) : 'https://placehold.co/600x400/F5F5F7/6E6E73?text=' . urlencode(Str::limit($site->name, 15));
@endphp
<a href="/explore?site={{ $site->id }}" class="st-card">
    <div class="st-card-photo">
        <img src="{{ $photoUrl }}" loading="lazy" alt="{{ $site->name }}">
        <div class="st-card-count">{{ $site->screen_count ?? 0 }} screens</div>
    </div>
    <div class="st-card-body">
        <div class="st-card-name">{{ $site->name }}</div>
        <div class="st-card-meta">
            @if($site->network)
            <span class="st-card-network">{{ $site->network->name }}</span>
            @endif
            @if($site->owner)
            <span>{{ $site->owner->name }}</span>
            @endif
        </div>
        @if($site->city)
        <div class="st-card-loc">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:11px;height:11px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
            {{ $site->city }}
        </div>
        @endif
    </div>
</a>
