{{-- Mega Search Box — unified component
     Props:
       $locationsByRegion — array (region_name => [{code, name, count}, ...])
       $filters           — array (categories_networks, networks, formats, cities)
       $variant           — 'hero' (homepage) | 'compact' (explore, default)
       $activeQ           — string (optional, pre-filled search text)
       $activeCities      — array (optional, active city codes)
       $activeNetworks    — array (optional, active network codes)
       $activeVenueTypes  — array (optional, active venue_type slugs)
       $searchChips       — collection (optional, suggested search pills for hero variant)
--}}
@php
    $variant        = $variant ?? 'compact';
    $activeQ        = $activeQ ?? request()->input('q', '');
    $activeCities   = $activeCities ?? (is_array(request()->input('city', [])) ? request()->input('city', []) : [request()->input('city')]);
    $activeNetworks = $activeNetworks ?? (is_array(request()->input('network', [])) ? request()->input('network', []) : [request()->input('network')]);
    $activeVenueTypes = $activeVenueTypes ?? (is_array(request()->input('venue_type', [])) ? request()->input('venue_type', []) : [request()->input('venue_type')]);
    $activeCities     = array_filter($activeCities);
    $activeNetworks   = array_filter($activeNetworks);
    $activeVenueTypes = array_filter($activeVenueTypes);
    $hasFilters = !empty($activeCities) || !empty($activeNetworks) || !empty($activeVenueTypes) || !empty($activeQ);
    $isHero = $variant === 'hero';
    $categoriesNetworks = $filters['categories_networks'] ?? $filters['formats'] ?? collect();
@endphp

<div class="ms-wrap {{ $isHero ? 'ms-hero' : 'ms-compact' }}">
    {{-- Search box --}}
    <div class="ms-box" id="ms-box">
        {{-- Filter: Tỉnh/Thành --}}
        <button class="ms-pill" type="button" id="ms-btn-city">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
            <span class="ms-pill-txt" id="ms-lbl-city">{{ count($activeCities) > 0 ? count($activeCities) . ' tỉnh/thành' : 'Tỉnh/Thành' }}</span>
            @if(count($activeCities) > 0)<span class="ms-pill-badge">{{ count($activeCities) }}</span>@endif
            <svg class="ms-pill-chv" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
        </button>
        {{-- Filter: Loại hình (with nested networks) --}}
        <button class="ms-pill" type="button" id="ms-btn-type">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            @php $activeCount = count($activeVenueTypes) + count($activeNetworks); @endphp
            <span class="ms-pill-txt" id="ms-lbl-type">{{ $activeCount > 0 ? $activeCount . ' bộ lọc' : 'Loại hình' }}</span>
            @if($activeCount > 0)<span class="ms-pill-badge">{{ $activeCount }}</span>@endif
            <svg class="ms-pill-chv" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
        </button>
        <div class="ms-divider"></div>
        {{-- Search input --}}
        <input class="ms-input" type="text" id="ms-q" value="{{ $activeQ }}" placeholder="Tìm theo tên, địa chỉ, network...">
        <button class="ms-submit" type="button" id="ms-search-btn">
            <svg viewBox="0 0 24 24" fill="#fff" style="width:16px;height:16px"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            <span class="ms-submit-txt">Tìm kiếm</span>
        </button>
    </div>

    {{-- Mega Dropdowns (fixed positioned via JS) --}}
    <div class="ms-drop-anchor" id="ms-anchor">
    {{-- Drop: Tỉnh/Thành (grouped by region) --}}
    <div class="mega-drop" id="ms-drop-city">
        <div class="mega-head">
            <div class="mega-head-title">Chọn tỉnh/thành</div>
            <span class="mega-head-clear" id="ms-clear-city">Xoá tất cả</span>
        </div>
        <div class="mega-body">
            <div class="ms-loc-cols">
                @foreach(($locationsByRegion ?? []) as $regionName => $provinces)
                <div class="ms-loc-region">
                    <div class="ms-loc-region-title">{{ $regionName }}</div>
                    @foreach($provinces as $p)
                    <div class="ms-loc-item {{ in_array($p['code'], $activeCities) ? 'on' : '' }}" data-code="{{ $p['code'] }}">
                        <div class="ms-loc-check"></div>
                        <span>{{ $p['name'] }}</span>
                        <span class="ms-loc-count">{{ $p['count'] }}</span>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </div>
        <div class="mega-foot">
            <button class="btn btn-s btn-sm ms-drop-close" type="button">Đóng</button>
            <button class="btn btn-p btn-sm" type="button" id="ms-apply-city">Áp dụng</button>
        </div>
    </div>

    {{-- Drop: Loại hình + Networks (hierarchical) --}}
    <div class="mega-drop" id="ms-drop-type">
        <div class="mega-head">
            <div class="mega-head-title">Chọn loại hình & network</div>
            <span class="mega-head-clear" id="ms-clear-type">Xoá tất cả</span>
        </div>
        <div class="mega-body" style="max-height:55vh">
            @foreach($categoriesNetworks as $cat)
            @php
                $catSlug = is_array($cat) ? $cat['type'] : $cat->type;
                $catLabel = is_array($cat) ? $cat['label'] : $cat->label;
                $catIcon = is_array($cat) ? ($cat['icon'] ?? 'tv') : ($cat->icon ?? 'tv');
                $catCount = is_array($cat) ? $cat['count'] : $cat->count;
                $catNetworks = is_array($cat) ? ($cat['networks'] ?? []) : [];
                $isCatActive = in_array($catSlug, $activeVenueTypes);
            @endphp
            <div class="ms-cat-group">
                <label class="ms-cat-head {{ $isCatActive ? 'on' : '' }}" data-code="{{ $catSlug }}" data-group="venue_type">
                    <div class="sb-checkbox"></div>
                    <span class="material-symbols-outlined" style="font-size:18px;color:var(--t3)">{{ $catIcon }}</span>
                    <span class="ms-cat-name">{{ $catLabel }}</span>
                    <span class="sb-count">{{ $catCount }}</span>
                </label>
                @if(!empty($catNetworks))
                <div class="ms-cat-nets">
                    @foreach($catNetworks as $net)
                    <label class="ms-net-item {{ in_array($net['code'], $activeNetworks) ? 'on' : '' }}" data-code="{{ $net['code'] }}" data-group="network">
                        <div class="sb-checkbox"></div>
                        <span>{{ $net['name'] }}</span>
                        <span class="sb-count">{{ $net['count'] }}</span>
                    </label>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        <div class="mega-foot">
            <button class="btn btn-s btn-sm ms-drop-close" type="button">Đóng</button>
            <button class="btn btn-p btn-sm" type="button" id="ms-apply-type">Áp dụng</button>
        </div>
    </div>
    </div>

    {{-- Active filter tags --}}
    @if($hasFilters)
    <div class="ms-tags" id="ms-tags">
        @foreach($activeCities as $c)
        <span class="ms-tag" data-type="city" data-val="{{ $c }}">{{ $c }} <span class="ms-tag-x">&times;</span></span>
        @endforeach
        @foreach($activeNetworks as $n)
        <span class="ms-tag" data-type="network" data-val="{{ $n }}">{{ $n }} <span class="ms-tag-x">&times;</span></span>
        @endforeach
        @foreach($activeVenueTypes as $v)
        <span class="ms-tag" data-type="venue_type" data-val="{{ $v }}">{{ $v }} <span class="ms-tag-x">&times;</span></span>
        @endforeach
        <span class="ms-tag ms-tag-clear" id="ms-clear-all">Xoá tất cả</span>
    </div>
    @endif

    {{-- Search chips (hero variant only) --}}
    @if($isHero && !empty($searchChips))
    <div class="ms-chips">
        @foreach($searchChips->take(4) as $chip)
        <span class="ms-chip" data-q="{{ e($chip) }}">{{ $chip }}</span>
        @endforeach
    </div>
    @endif
</div>
{{-- Backdrop — outside ms-wrap to avoid layout interference --}}
<div class="ms-backdrop" id="ms-backdrop"></div>
