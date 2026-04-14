@extends('frontpage.layouts.app', ['activeNav' => 'explore', 'bodyClass' => 'theme-listing'])

@section('title', 'Khám phá OOH/DOOH Inventory | OOHX')

@php
    $activeVenueTypes = request()->input('venue_type', []);
    if (is_string($activeVenueTypes)) $activeVenueTypes = [$activeVenueTypes];
    $activeCities = request()->input('city', []);
    if (is_string($activeCities)) $activeCities = [$activeCities];
    $activeNetworks = request()->input('network', []);
    if (is_string($activeNetworks)) $activeNetworks = [$activeNetworks];
    $activeOwners = request()->input('owner', []);
    if (is_string($activeOwners)) $activeOwners = [$activeOwners];
    $activeOrientations = request()->input('orientation', []);
    if (is_string($activeOrientations)) $activeOrientations = [$activeOrientations];
    $activeMinPrice = request()->input('min_price', '');
    $activeMaxPrice = request()->input('max_price', '');
    $activeSort = request()->input('sort', '');
    $activeQ = request()->input('q', '');
    $hasFilters = !empty($activeVenueTypes) || !empty($activeCities) || !empty($activeNetworks)
                || !empty($activeOwners) || !empty($activeOrientations)
                || !empty($activeMinPrice) || !empty($activeMaxPrice)
                || !empty($activeQ) || !empty($activeSort);
    $filterCount = count($activeVenueTypes) + count($activeCities) + count($activeNetworks)
                 + count($activeOwners) + count($activeOrientations)
                 + ($activeMinPrice ? 1 : 0) + ($activeMaxPrice ? 1 : 0) + ($activeQ ? 1 : 0);
@endphp

@section('content')
@php $group = $group ?? 'screen'; @endphp
<div class="ex-hero"><div class="w">

    {{-- Title row --}}
    <div class="ex-top">
        <h1 class="ex-title">Khám phá Inventory</h1>
        <a href="{{ route('fp.map') }}" class="ph-map-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
            Bản đồ
        </a>
    </div>

    {{-- Mega Search Box — mobile: stacked, desktop: inline --}}
    <div class="ex-search-wrap">
        {{-- Search input row --}}
        <div class="ex-input-row">
            <svg class="ex-input-icon" viewBox="0 0 24 24" fill="var(--t4)"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            <input class="ex-input" type="text" id="ex-q" value="{{ $activeQ }}" placeholder="Tìm tên, địa chỉ, network...">
            <button class="ex-submit" type="button" id="ex-search-btn">
                <svg viewBox="0 0 24 24" fill="#fff" style="width:16px;height:16px"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <span class="ex-submit-text">Tìm kiếm</span>
            </button>
        </div>

        {{-- Filter pills row --}}
        <div class="ex-pills">
            <button class="ex-pill" type="button" id="exf-city">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
                <span class="ex-pill-label">{{ count($activeCities) > 0 ? count($activeCities) . ' tỉnh/thành' : 'Tỉnh/Thành' }}</span>
                @if(count($activeCities) > 0)<span class="ex-pill-badge">{{ count($activeCities) }}</span>@endif
                <svg class="ex-pill-chv" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
            </button>
            <button class="ex-pill" type="button" id="exf-network">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm4 4H8v-2h2v2zm0-4H8v-2h2v2zm10 4h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10z"/></svg>
                <span class="ex-pill-label">{{ count($activeNetworks) > 0 ? count($activeNetworks) . ' network' : 'Network' }}</span>
                @if(count($activeNetworks) > 0)<span class="ex-pill-badge">{{ count($activeNetworks) }}</span>@endif
                <svg class="ex-pill-chv" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
            </button>
            <button class="ex-pill" type="button" id="exf-type">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                <span class="ex-pill-label">{{ count($activeVenueTypes) > 0 ? count($activeVenueTypes) . ' loại' : 'Loại hình' }}</span>
                @if(count($activeVenueTypes) > 0)<span class="ex-pill-badge">{{ count($activeVenueTypes) }}</span>@endif
                <svg class="ex-pill-chv" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
            </button>
        </div>

        {{-- Active filter tags --}}
        @if($hasFilters)
        <div class="ex-active-tags" id="ex-active-tags">
            @foreach($activeCities as $c)
            <span class="ex-tag" data-type="city" data-val="{{ $c }}">{{ $c }} <span class="ex-tag-x">&times;</span></span>
            @endforeach
            @foreach($activeNetworks as $n)
            <span class="ex-tag" data-type="network" data-val="{{ $n }}">{{ $n }} <span class="ex-tag-x">&times;</span></span>
            @endforeach
            @foreach($activeVenueTypes as $v)
            <span class="ex-tag" data-type="venue_type" data-val="{{ $v }}">{{ $v }} <span class="ex-tag-x">&times;</span></span>
            @endforeach
            <span class="ex-tag ex-tag-clear" id="ex-clear-all">Xoá tất cả</span>
        </div>
        @endif
    </div>

    {{-- Mega Dropdowns --}}
    <div class="ex-drop-backdrop" id="ex-backdrop"></div>
    <div class="mega-anchor" id="ex-mega-anchor">
        {{-- Drop: City --}}
        <div class="mega-drop" id="exd-city">
            <div class="mega-head">
                <div class="mega-head-title">Chọn tỉnh/thành</div>
                <span class="mega-head-clear" id="exc-city">Xoá tất cả</span>
            </div>
            <div class="mega-body" style="max-height:50vh">
                <div class="exd-grid">
                    @foreach(($filters['cities'] ?? collect())->take(20) as $city)
                    <label class="exd-item {{ in_array($city['code'], $activeCities) ? 'on' : '' }}" data-code="{{ $city['code'] }}">
                        <div class="sb-checkbox"></div>
                        <span>{{ $city['name'] }}</span>
                        <span class="sb-count">{{ $city['count'] }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="mega-foot">
                <button class="btn btn-s btn-sm exd-close" type="button">Đóng</button>
                <button class="btn btn-p btn-sm" type="button" id="exa-city">Áp dụng</button>
            </div>
        </div>

        {{-- Drop: Network --}}
        <div class="mega-drop" id="exd-network">
            <div class="mega-head">
                <div class="mega-head-title">Chọn network</div>
                <span class="mega-head-clear" id="exc-network">Xoá tất cả</span>
            </div>
            <div class="mega-body" style="max-height:50vh">
                <div class="exd-grid">
                    @foreach(($filters['networks'] ?? collect()) as $n)
                    <label class="exd-item {{ in_array($n->code, $activeNetworks) ? 'on' : '' }}" data-code="{{ $n->code }}">
                        <div class="sb-checkbox"></div>
                        <span>{{ $n->name }}</span>
                        <span class="sb-count">{{ $n->count }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="mega-foot">
                <button class="btn btn-s btn-sm exd-close" type="button">Đóng</button>
                <button class="btn btn-p btn-sm" type="button" id="exa-network">Áp dụng</button>
            </div>
        </div>

        {{-- Drop: Type --}}
        <div class="mega-drop" id="exd-type">
            <div class="mega-head">
                <div class="mega-head-title">Chọn loại hình</div>
                <span class="mega-head-clear" id="exc-type">Xoá tất cả</span>
            </div>
            <div class="mega-body" style="max-height:50vh">
                <div class="exd-grid">
                    @foreach(($filters['formats'] ?? collect()) as $f)
                    <label class="exd-item {{ in_array($f['type'], $activeVenueTypes) ? 'on' : '' }}" data-code="{{ $f['type'] }}">
                        <div class="sb-checkbox"></div>
                        <span>{{ $f['label'] }}</span>
                        <span class="sb-count">{{ $f['count'] }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <div class="mega-foot">
                <button class="btn btn-s btn-sm exd-close" type="button">Đóng</button>
                <button class="btn btn-p btn-sm" type="button" id="exa-type">Áp dụng</button>
            </div>
        </div>
    </div>
</div></div>

<div class="w"><div class="listing-layout">

    {{-- Backdrop for mobile drawer --}}
    <div class="filter-backdrop" id="filter-backdrop"></div>

    {{-- ═══ SIDEBAR — wrapped in <form> ═══ --}}
    <form method="GET" action="{{ route('fp.listing') }}" class="sidebar" id="filter-form">

        {{-- Drag bar (mobile only, tap to close — same as .mp-drag) --}}
        <div class="drawer-drag"><div class="drawer-drag-bar"></div></div>

        {{-- Scrollable content --}}
        <div class="sidebar-scroll">
            {{-- Search --}}
            <div class="sb-card">
                <div class="sb-title">Tìm kiếm</div>
                <input type="text" name="q" value="{{ $activeQ }}" placeholder="Tên, địa chỉ, khu vực..." class="sb-input">
            </div>

            {{-- Loại hình (venue_type) --}}
            <div class="sb-card">
                <div class="sb-title">Loại hình</div>
                <div class="sb-group" data-max="8">
                    @foreach(($filters['formats'] ?? collect()) as $f)
                        <label class="sb-item {{ in_array($f['type'], $activeVenueTypes) ? 'on' : '' }}">
                            <input type="checkbox" name="venue_type[]" value="{{ $f['type'] }}" {{ in_array($f['type'], $activeVenueTypes) ? 'checked' : '' }} hidden>
                            <div class="sb-checkbox"></div>
                            <span>{{ $f['label'] }}</span>
                            <span class="sb-count">{{ $f['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Tỉnh/Thành (city) --}}
            <div class="sb-card">
                <div class="sb-title">Tỉnh/Thành</div>
                <div class="sb-group" data-max="8">
                    @foreach(($filters['cities'] ?? collect())->take(20) as $city)
                        <label class="sb-item {{ in_array($city['code'], $activeCities) ? 'on' : '' }}">
                            <input type="checkbox" name="city[]" value="{{ $city['code'] }}" {{ in_array($city['code'], $activeCities) ? 'checked' : '' }} hidden>
                            <div class="sb-checkbox"></div>
                            <span>{{ $city['name'] }}</span>
                            <span class="sb-count">{{ $city['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Media Owner --}}
            @if(($filters['owners'] ?? collect())->isNotEmpty())
            <div class="sb-card">
                <div class="sb-title">Media Owner</div>
                <div class="sb-group" data-max="6">
                    @foreach($filters['owners'] as $o)
                        <label class="sb-item {{ in_array($o->slug, $activeOwners) ? 'on' : '' }}">
                            <input type="checkbox" name="owner[]" value="{{ $o->slug }}" {{ in_array($o->slug, $activeOwners) ? 'checked' : '' }} hidden>
                            <div class="sb-checkbox"></div>
                            <span>{{ $o->name }}</span>
                            <span class="sb-count">{{ $o->count }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Network --}}
            @if(($filters['networks'] ?? collect())->isNotEmpty())
            <div class="sb-card">
                <div class="sb-title">Network</div>
                <div class="sb-group" data-max="6">
                    @foreach($filters['networks'] as $n)
                        <label class="sb-item {{ in_array($n->code, $activeNetworks) ? 'on' : '' }}">
                            <input type="checkbox" name="network[]" value="{{ $n->code }}" {{ in_array($n->code, $activeNetworks) ? 'checked' : '' }} hidden>
                            <div class="sb-checkbox"></div>
                            <span>{{ $n->name }}</span>
                            <span class="sb-count">{{ $n->count }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Mức giá --}}
            <div class="sb-card">
                <div class="sb-title">Mức giá (₫/tháng)</div>
                <div class="sb-price-row">
                    <input type="number" name="min_price" value="{{ $activeMinPrice }}" placeholder="Từ" class="sb-input sb-price-input" min="0">
                    <span style="color:var(--t4);font-size:12px">—</span>
                    <input type="number" name="max_price" value="{{ $activeMaxPrice }}" placeholder="Đến" class="sb-input sb-price-input" min="0">
                </div>
                <div class="sb-price-presets">
                    <button type="button" class="sb-preset" data-min="0" data-max="5000000">&lt; 5M</button>
                    <button type="button" class="sb-preset" data-min="5000000" data-max="10000000">5-10M</button>
                    <button type="button" class="sb-preset" data-min="10000000" data-max="">&gt; 10M</button>
                </div>
            </div>

            {{-- Hướng màn hình --}}
            <div class="sb-card">
                <div class="sb-title">Hướng màn hình</div>
                <div class="sb-group sb-group-inline">
                    @foreach(['landscape'=>'Ngang','portrait'=>'Dọc','square'=>'Vuông'] as $val => $label)
                    <label class="sb-item sb-item-pill {{ in_array($val, $activeOrientations) ? 'on' : '' }}">
                        <input type="checkbox" name="orientation[]" value="{{ $val }}" {{ in_array($val, $activeOrientations) ? 'checked' : '' }} hidden>
                        <span>{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Sort (hidden, synced from dropdown) --}}
            <input type="hidden" name="sort" id="sort-input" value="{{ $activeSort }}">

            {{-- Áp dụng --}}
            <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;border-radius:10px">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:14px;height:14px;flex-shrink:0"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg> Áp dụng
            </button>

            @if($hasFilters)
                <a href="{{ route('fp.listing') }}" class="btn btn-s" style="width:100%;justify-content:center;border-radius:10px;margin-top:6px;text-decoration:none">
                    Xoá bộ lọc
                </a>
            @endif
        </div>
    </form>

    {{-- ═══ MAIN CONTENT ═══ --}}
    <div>
        @php
            $items = match($group) { 'network' => $networks ?? collect(), 'site' => $sites ?? collect(), default => $screens ?? collect() };
        @endphp
        <div class="results-header">
            <div class="rh-count">Hiển thị <strong>{{ number_format($items->total()) }}</strong> {{ match($group) { 'network' => 'networks', 'site' => 'sites', default => 'screens' } }}</div>
            @if($group === 'screen')
            <select class="sort-sel" id="sort-sel" onchange="document.getElementById('sort-input').value=this.value; document.getElementById('filter-form').dispatchEvent(new Event('submit'));">
                <option value="" {{ $activeSort === '' ? 'selected' : '' }}>Mặc định</option>
                <option value="price_asc" {{ $activeSort === 'price_asc' ? 'selected' : '' }}>Giá thấp nhất</option>
                <option value="price_desc" {{ $activeSort === 'price_desc' ? 'selected' : '' }}>Giá cao nhất</option>
                <option value="newest" {{ $activeSort === 'newest' ? 'selected' : '' }}>Mới nhất</option>
            </select>
            <div class="view-btn on" id="vg"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M3 3v8h8V3H3zm6 6H5V5h4v4zm-6 4v8h8v-8H3zm6 6H5v-4h4v4zm4-16v8h8V3h-8zm6 6h-4V5h4v4zm-6 4v8h8v-8h-8zm6 6h-4v-4h4v4z"/></svg></div>
            <div class="view-btn" id="vl"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:18px;height:18px;flex-shrink:0"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg></div>
            @endif
        </div>

        {{-- Network cards --}}
        @if($group === 'network')
        <div class="nw-grid">
            @forelse($networks as $network)
                @include('frontpage.partials.network-card', ['network' => $network])
            @empty
            <div style="text-align:center;padding:60px 0;color:var(--t4)">Không tìm thấy network nào</div>
            @endforelse
        </div>
        <div class="load-more">{{ $networks->withQueryString()->links() }}</div>

        {{-- Site cards --}}
        @elseif($group === 'site')
        <div class="inv-grid" id="inv-grid">
            @forelse($sites as $site)
                @include('frontpage.partials.site-card', ['site' => $site])
            @empty
            <div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--t4)">Không tìm thấy site nào</div>
            @endforelse
        </div>
        <div class="load-more">{{ $sites->withQueryString()->links() }}</div>

        {{-- Screen cards (default) --}}
        @else
        <div class="inv-grid" id="inv-grid">
            @forelse($screens as $screen)
                @include('frontpage.partials.screen-card', ['screen' => $screen])
            @empty
            <div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--t4)">Không tìm thấy inventory phù hợp</div>
            @endforelse
        </div>
        <div class="load-more">{{ $screens->withQueryString()->links() }}</div>
        @endif
    </div>
</div></div>

{{-- Floating filter button — mobile only --}}
<button class="filter-float" id="filter-float" type="button">
    <svg viewBox="0 0 24 24" fill="#fff"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
    Bộ lọc
    @if($filterCount > 0)<span class="filter-float-count">{{ $filterCount }}</span>@endif
</button>
@endsection

@push('scripts')
<script>
(function(){
    // ═══ MEGA SEARCH DROPDOWNS ═══
    var backdrop = document.getElementById('ex-backdrop');
    var anchor = document.getElementById('ex-mega-anchor');
    var openDrop = null;

    function closeDrop() {
        if (openDrop) { openDrop.classList.remove('open'); openDrop = null; }
        if (backdrop) backdrop.classList.remove('open');
        document.querySelectorAll('.ex-pill').forEach(function(b){ b.classList.remove('on'); });
    }

    function toggleDrop(btnId, dropId) {
        var btn = document.getElementById(btnId);
        var drop = document.getElementById(dropId);
        if (!btn || !drop) return;

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (openDrop === drop) { closeDrop(); return; }
            closeDrop();
            drop.classList.add('open');
            openDrop = drop;
            btn.classList.add('on');
            if (backdrop) backdrop.classList.add('open');
        });
    }

    toggleDrop('exf-city', 'exd-city');
    toggleDrop('exf-network', 'exd-network');
    toggleDrop('exf-type', 'exd-type');

    if (backdrop) backdrop.addEventListener('click', closeDrop);
    document.querySelectorAll('.exd-close').forEach(function(btn){ btn.addEventListener('click', closeDrop); });

    // Dropdown item toggle
    document.querySelectorAll('.exd-item').forEach(function(item){
        item.addEventListener('click', function(){ item.classList.toggle('on'); });
    });

    // Clear buttons inside dropdowns
    ['city','network','type'].forEach(function(key){
        var clearBtn = document.getElementById('exc-' + key);
        if (clearBtn) {
            clearBtn.addEventListener('click', function(){
                document.querySelectorAll('#exd-' + key + ' .exd-item.on').forEach(function(el){ el.classList.remove('on'); });
            });
        }
    });

    // Build URL from mega search state
    function buildSearchUrl() {
        var params = new URLSearchParams();
        var q = document.getElementById('ex-q').value.trim();
        if (q) params.append('q', q);

        document.querySelectorAll('#exd-city .exd-item.on').forEach(function(el){
            params.append('city[]', el.dataset.code);
        });
        document.querySelectorAll('#exd-network .exd-item.on').forEach(function(el){
            params.append('network[]', el.dataset.code);
        });
        document.querySelectorAll('#exd-type .exd-item.on').forEach(function(el){
            params.append('venue_type[]', el.dataset.code);
        });

        // Preserve group + sidebar filters
        var group = '{{ $group }}';
        if (group !== 'screen') params.append('group', group);
        @if($activeMinPrice) params.append('min_price', '{{ $activeMinPrice }}'); @endif
        @if($activeMaxPrice) params.append('max_price', '{{ $activeMaxPrice }}'); @endif
        @foreach($activeOwners as $o) params.append('owner[]', '{{ $o }}'); @endforeach
        @foreach($activeOrientations as $o) params.append('orientation[]', '{{ $o }}'); @endforeach

        var str = params.toString();
        window.location.href = '/explore' + (str ? '?' + str : '');
    }

    // Apply buttons
    ['city','network','type'].forEach(function(key){
        var applyBtn = document.getElementById('exa-' + key);
        if (applyBtn) applyBtn.addEventListener('click', buildSearchUrl);
    });

    // Search button + Enter key
    var searchBtn = document.getElementById('ex-search-btn');
    if (searchBtn) searchBtn.addEventListener('click', buildSearchUrl);
    var qInput = document.getElementById('ex-q');
    if (qInput) qInput.addEventListener('keydown', function(e){
        if (e.key === 'Enter') { e.preventDefault(); buildSearchUrl(); }
    });

    // Tag removal
    document.querySelectorAll('.ex-tag-x').forEach(function(x){
        x.addEventListener('click', function(e){
            e.stopPropagation();
            var tag = x.closest('.ex-tag');
            var type = tag.dataset.type;
            var val = tag.dataset.val;
            // Remove from URL params
            var url = new URL(window.location.href);
            var all = url.searchParams.getAll(type + '[]');
            url.searchParams.delete(type + '[]');
            all.filter(function(v){ return v !== val; }).forEach(function(v){ url.searchParams.append(type + '[]', v); });
            window.location.href = url.toString();
        });
    });

    // Clear all tags
    var clearAll = document.getElementById('ex-clear-all');
    if (clearAll) clearAll.addEventListener('click', function(){ window.location.href = '/explore'; });

    // ═══ GRID/LIST + SIDEBAR (existing) ═══

    // Grid/List toggle with localStorage persistence
    var grid = document.getElementById('inv-grid');
    var vg = document.getElementById('vg');
    var vl = document.getElementById('vl');
    var saved = localStorage.getItem('listing_view');
    if (saved === 'list') { grid.classList.add('list-view'); vl.classList.add('on'); vg.classList.remove('on'); }

    vg.addEventListener('click', function(){
        vg.classList.add('on'); vl.classList.remove('on');
        grid.classList.remove('list-view');
        localStorage.setItem('listing_view', 'grid');
    });
    vl.addEventListener('click', function(){
        vl.classList.add('on'); vg.classList.remove('on');
        grid.classList.add('list-view');
        localStorage.setItem('listing_view', 'list');
    });

    // Sidebar checkbox — sync .on class with native checkbox state
    // <label> already toggles checkbox natively, just sync the CSS class
    document.querySelectorAll('.sb-item input[type="checkbox"]').forEach(function(cb){
        cb.addEventListener('change', function(){
            cb.closest('.sb-item').classList.toggle('on', cb.checked);
        });
    });

    // Form submit — strip empty params for clean URL
    var form = document.getElementById('filter-form');
    form.addEventListener('submit', function(e){
        e.preventDefault();
        var parts = [];
        new FormData(form).forEach(function(val, key){
            if (val !== '' && val !== null) {
                parts.push(encodeURIComponent(key).replace(/%5B/gi,'[').replace(/%5D/gi,']') + '=' + encodeURIComponent(val));
            }
        });
        window.location.href = form.action + (parts.length ? '?' + parts.join('&') : '');
    });

    // Search on Enter
    var qInput = document.querySelector('input[name="q"]');
    if (qInput) {
        qInput.addEventListener('keydown', function(e){
            if (e.key === 'Enter') { e.preventDefault(); form.dispatchEvent(new Event('submit')); }
        });
    }
    // Mobile filter drawer
    var filterFloat = document.getElementById('filter-float');

    function openFilterDrawer() {
        document.getElementById('filter-form').classList.add('open');
        document.body.style.overflow = 'hidden';
        if (filterFloat) filterFloat.classList.add('hidden');
    }
    function closeFilterDrawer() {
        document.getElementById('filter-form').classList.remove('open');
        document.body.style.overflow = '';
        if (filterFloat) filterFloat.classList.remove('hidden');
    }

    // Bind: floating button
    if (filterFloat) filterFloat.addEventListener('click', openFilterDrawer);

    // Bind: header filter trigger
    var filterTrigger = document.querySelector('.filter-trigger');
    if (filterTrigger) filterTrigger.addEventListener('click', openFilterDrawer);

    // Bind: drawer close + drag bar
    document.querySelectorAll('.drawer-close-btn, .drawer-drag').forEach(function(btn) {
        btn.addEventListener('click', closeFilterDrawer);
    });

    // Bind: backdrop
    var backdrop = document.getElementById('filter-backdrop');
    if (backdrop) backdrop.addEventListener('click', closeFilterDrawer);

    // Price presets
    document.querySelectorAll('.sb-preset').forEach(function(btn){
        btn.addEventListener('click', function(){
            var min = btn.dataset.min || '';
            var max = btn.dataset.max || '';
            var minInp = document.querySelector('input[name="min_price"]');
            var maxInp = document.querySelector('input[name="max_price"]');
            if (minInp) minInp.value = min;
            if (maxInp) maxInp.value = max;
        });
    });

    // Show-more toggle for long filter groups
    document.querySelectorAll('.sb-group[data-max]').forEach(function(group){
        var max = parseInt(group.dataset.max) || 8;
        var items = group.querySelectorAll('.sb-item');
        if (items.length <= max) return;

        // Hide items beyond max (unless checked)
        var hiddenCount = 0;
        items.forEach(function(item, i){
            if (i >= max && !item.classList.contains('on')) {
                item.classList.add('sb-hidden');
                hiddenCount++;
            }
        });
        if (hiddenCount === 0) return;

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'sb-show-more';
        toggle.textContent = 'Xem thêm (' + hiddenCount + ')';
        toggle.addEventListener('click', function(){
            var hidden = group.querySelectorAll('.sb-hidden');
            if (hidden.length > 0) {
                hidden.forEach(function(el){ el.classList.remove('sb-hidden'); });
                toggle.textContent = 'Thu gọn';
            } else {
                items.forEach(function(item, i){
                    if (i >= max && !item.classList.contains('on')) item.classList.add('sb-hidden');
                });
                toggle.textContent = 'Xem thêm (' + hiddenCount + ')';
            }
        });
        group.after(toggle);
    });
})();
</script>
@endpush
