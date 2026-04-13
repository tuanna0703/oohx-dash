{{-- Network card — used on /explore?group=network
     Receives: $network (Network model with owner, screen_count, sites_count)
--}}
@php
    $logo = $network->logo ? asset('storage/' . $network->logo) : null;
@endphp
<a href="/explore?network[]={{ $network->code }}&group=site" class="nw-card">
    <div class="nw-card-left">
        @if($logo)
        <img src="{{ $logo }}" class="nw-card-logo" loading="lazy" alt="{{ $network->name }}">
        @else
        <div class="nw-card-logo nw-card-logo--placeholder">
            <svg viewBox="0 0 24 24" fill="var(--t4)" style="width:24px;height:24px"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
        </div>
        @endif
    </div>
    <div class="nw-card-body">
        <div class="nw-card-name">{{ $network->name }}</div>
        @if($network->owner)
        <div class="nw-card-owner">{{ $network->owner->name }}</div>
        @endif
        <div class="nw-card-stats">
            <span>{{ $network->sites_count ?? 0 }} sites</span>
            <span class="nw-card-dot"></span>
            <span>{{ $network->screen_count ?? 0 }} screens</span>
        </div>
    </div>
    <div class="nw-card-arrow">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
    </div>
</a>
