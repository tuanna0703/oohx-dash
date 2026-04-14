{{-- Smart Filter — vertical stacked chips for narrow layouts (map sidebar)
     Props:
       $filters           — array (networks, formats, cities) from getFilterAggregates()
       $locationsByRegion — array (region_name => [{code, name, count}, ...])
       $target            — string URL base (default: current path)
--}}
@php
    $target          = $target ?? request()->path();
    $activeQ         = request()->input('q', '');
    $activeRegions   = is_array(request()->input('region', [])) ? request()->input('region', []) : [request()->input('region')];
    $activeCities    = is_array(request()->input('city', [])) ? request()->input('city', []) : [request()->input('city')];
    $activeNetworks  = is_array(request()->input('network', [])) ? request()->input('network', []) : [request()->input('network')];
    $activeTypes     = is_array(request()->input('venue_type', [])) ? request()->input('venue_type', []) : [request()->input('venue_type')];
    $activeRegions   = array_filter($activeRegions);
    $activeCities    = array_filter($activeCities);
    $activeNetworks  = array_filter($activeNetworks);
    $activeTypes     = array_filter($activeTypes);
    $hasFilters      = !empty($activeRegions) || !empty($activeCities) || !empty($activeNetworks) || !empty($activeTypes) || !empty($activeQ);
    $regions         = config('regions', []);
@endphp

<div class="sft" id="sft" data-target="/{{ ltrim($target, '/') }}">
    {{-- Search --}}
    <div class="sft-search">
        <svg viewBox="0 0 24 24" fill="var(--t4)" class="sft-search-icon"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        <input type="text" class="sft-search-input" id="sft-q" value="{{ $activeQ }}" placeholder="Tìm tên, địa chỉ...">
    </div>

    {{-- Vùng miền --}}
    <div class="sft-section">
        <div class="sft-label">Vùng miền</div>
        <div class="sft-chips">
            @foreach($regions as $code => $cfg)
            <button type="button" class="sft-chip {{ in_array($code, $activeRegions) ? 'on' : '' }}" data-group="region" data-val="{{ $code }}">{{ $cfg['name'] }}</button>
            @endforeach
        </div>
    </div>

    {{-- Tỉnh/Thành --}}
    @php
        $allCities = collect($locationsByRegion ?? [])->flatten(1)->sortByDesc('count')->values();
    @endphp
    @if($allCities->isNotEmpty())
    <div class="sft-section">
        <div class="sft-label">Tỉnh/Thành</div>
        <div class="sft-chips sft-chips-wrap" data-max="6">
            @foreach($allCities as $city)
            <button type="button" class="sft-chip {{ in_array($city['code'], $activeCities) ? 'on' : '' }}" data-group="city" data-val="{{ $city['code'] }}">{{ $city['name'] }} <span class="sft-chip-count">{{ $city['count'] }}</span></button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Network --}}
    @if(($filters['networks'] ?? collect())->isNotEmpty())
    <div class="sft-section">
        <div class="sft-label">Network</div>
        <div class="sft-chips sft-chips-wrap" data-max="4">
            @foreach($filters['networks'] as $n)
            <button type="button" class="sft-chip {{ in_array($n->code, $activeNetworks) ? 'on' : '' }}" data-group="network" data-val="{{ $n->code }}">{{ $n->name }} <span class="sft-chip-count">{{ $n->count }}</span></button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Loại hình --}}
    @if(($filters['formats'] ?? collect())->isNotEmpty())
    <div class="sft-section">
        <div class="sft-label">Loại hình</div>
        <div class="sft-chips sft-chips-wrap" data-max="4">
            @foreach($filters['formats'] as $f)
            @php $fType = is_array($f) ? $f['type'] : $f->type; $fLabel = is_array($f) ? $f['label'] : $f->label; $fCount = is_array($f) ? $f['count'] : $f->count; @endphp
            <button type="button" class="sft-chip {{ in_array($fType, $activeTypes) ? 'on' : '' }}" data-group="venue_type" data-val="{{ $fType }}">{{ $fLabel }} <span class="sft-chip-count">{{ $fCount }}</span></button>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Actions --}}
    <div class="sft-actions">
        <button type="button" class="btn btn-p btn-sm sft-apply" id="sft-apply" style="flex:1;justify-content:center;border-radius:10px">Áp dụng</button>
        @if($hasFilters)
        <a href="/{{ ltrim($target, '/') }}" class="btn btn-s btn-sm" style="border-radius:10px;text-decoration:none">Xoá</a>
        @endif
    </div>
</div>
