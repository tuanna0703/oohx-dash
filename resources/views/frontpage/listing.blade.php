@extends('frontpage.layouts.app', ['activeNav' => 'explore', 'bodyClass' => 'theme-listing'])

@section('title', 'Khám phá OOH/DOOH Inventory | OOHX')

@php
    $activeVenueTypes = request()->input('venue_type', []);
    if (is_string($activeVenueTypes)) $activeVenueTypes = [$activeVenueTypes];
    $activeCities = request()->input('city', []);
    if (is_string($activeCities)) $activeCities = [$activeCities];
    $activeSort = request()->input('sort', '');
    $activeQ = request()->input('q', '');
    $hasFilters = !empty($activeVenueTypes) || !empty($activeCities) || !empty($activeQ) || !empty($activeSort);
@endphp

@section('content')
<div class="ph"><div class="w">
    <div class="ph-top">
        <div class="ph-title">Khám phá Inventory</div>
        <a href="{{ route('fp.map') }}" class="ph-map-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
            Bản đồ ({{ number_format($screens->total()) }})
        </a>
    </div>

    {{-- Quick filter chips — horizontal scroll gesture --}}
    <div class="fchips">
        <a href="{{ route('fp.listing') }}" class="fchip {{ !$hasFilters ? 'on' : '' }}">Tất cả</a>
        @foreach(($filters['formats'] ?? collect())->take(6) as $f)
            <a href="/explore?venue_type[]={{ $f['type'] }}" class="fchip {{ in_array($f['type'], $activeVenueTypes) ? 'on' : '' }}">{{ $f['label'] }} <span class="fchip-count">{{ $f['count'] }}</span></a>
        @endforeach
    </div>
</div></div>

<div class="w"><div class="listing-layout">

    {{-- Backdrop for mobile drawer --}}
    <div class="filter-backdrop" id="filter-backdrop" onclick="closeFilterDrawer()"></div>

    {{-- ═══ SIDEBAR — wrapped in <form> ═══ --}}
    <form method="GET" action="{{ route('fp.listing') }}" class="sidebar" id="filter-form">

        {{-- Drawer header (mobile only) --}}
        <div class="drawer-close">
            <span class="drawer-close-title">Bộ lọc</span>
            <button type="button" class="drawer-close-btn" onclick="closeFilterDrawer()">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </div>

        {{-- Search --}}
        <div class="sb-card">
            <div class="sb-title">Tìm kiếm</div>
            <div style="display:flex;gap:6px">
                <input type="text" name="q" value="{{ $activeQ }}" placeholder="Tên, địa chỉ, khu vực..." class="sb-search-inp" style="flex:1;height:36px;padding:0 10px;border-radius:8px;border:1.5px solid var(--ln2);background:#fff;font-family:var(--font);font-size:13px;color:var(--t2);outline:none">
            </div>
        </div>

        {{-- Địa điểm (venue_type) --}}
        <div class="sb-card">
            <div class="sb-title">Địa điểm</div>
            <div class="sb-group">
                @foreach(($filters['formats'] ?? collect()) as $f)
                    <label class="sb-item {{ in_array($f['type'], $activeVenueTypes) ? 'on' : '' }}">
                        <input type="checkbox" name="venue_type[]" value="{{ $f['type'] }}" {{ in_array($f['type'], $activeVenueTypes) ? 'checked' : '' }} style="display:none">
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
            <div class="sb-group">
                @foreach(($filters['cities'] ?? collect())->take(12) as $city)
                    <label class="sb-item {{ in_array($city['code'], $activeCities) ? 'on' : '' }}">
                        <input type="checkbox" name="city[]" value="{{ $city['code'] }}" {{ in_array($city['code'], $activeCities) ? 'checked' : '' }} style="display:none">
                        <div class="sb-checkbox"></div>
                        <span>{{ $city['name'] }}</span>
                        <span class="sb-count">{{ $city['count'] }}</span>
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
    </form>

    {{-- ═══ MAIN CONTENT ═══ --}}
    <div>
        <div class="results-header">
            <div class="rh-count">Hiển thị <strong>{{ number_format($screens->total()) }}</strong> kết quả</div>
            <div class="rh-right">
                <button type="button" class="filter-trigger" onclick="openFilterDrawer()">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                    Bộ lọc
                    @if($hasFilters)<span class="filter-count">{{ count($activeVenueTypes) + count($activeCities) + ($activeQ ? 1 : 0) }}</span>@endif
                </button>
                <select class="sort-sel" id="sort-sel" onchange="document.getElementById('sort-input').value=this.value; document.getElementById('filter-form').dispatchEvent(new Event('submit'));">
                    <option value="" {{ $activeSort === '' ? 'selected' : '' }}>Mặc định</option>
                    <option value="price_asc" {{ $activeSort === 'price_asc' ? 'selected' : '' }}>Giá thấp nhất</option>
                    <option value="price_desc" {{ $activeSort === 'price_desc' ? 'selected' : '' }}>Giá cao nhất</option>
                    <option value="newest" {{ $activeSort === 'newest' ? 'selected' : '' }}>Mới nhất</option>
                </select>
                <div class="view-btn on" id="vg"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M3 3v8h8V3H3zm6 6H5V5h4v4zm-6 4v8h8v-8H3zm6 6H5v-4h4v4zm4-16v8h8V3h-8zm6 6h-4V5h4v4zm-6 4v8h8v-8h-8zm6 6h-4v-4h4v4z"/></svg></div>
                <div class="view-btn" id="vl"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:18px;height:18px;flex-shrink:0"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg></div>
            </div>
        </div>

        <div class="inv-grid" id="inv-grid">
            @forelse($screens as $screen)
                @include('frontpage.partials.screen-card', ['screen' => $screen])
            @empty
            <div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--t4)">Không tìm thấy inventory phù hợp</div>
            @endforelse
        </div>

        <div class="load-more">{{ $screens->withQueryString()->links() }}</div>
    </div>
</div></div>
@endsection

@push('scripts')
<script>
(function(){
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
    window.openFilterDrawer = function() {
        document.getElementById('filter-form').classList.add('open');
        document.getElementById('filter-backdrop').classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    window.closeFilterDrawer = function() {
        document.getElementById('filter-form').classList.remove('open');
        document.getElementById('filter-backdrop').classList.remove('open');
        document.body.style.overflow = '';
    };
})();
</script>
@endpush
