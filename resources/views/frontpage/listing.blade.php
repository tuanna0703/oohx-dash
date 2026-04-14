@extends('frontpage.layouts.app', ['activeNav' => 'explore', 'bodyClass' => 'theme-listing'])

@section('title', 'Khám phá OOH/DOOH Inventory | OOHX')

@php
    $activeSort = request()->input('sort', '');
@endphp

@section('content')
<div class="ex-hero"><div class="w">
    {{-- Title row --}}
    <div class="ex-top">
        <h1 class="ex-title">Khám phá Inventory</h1>
        <a href="{{ route('fp.map') }}" class="ph-map-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
            Bản đồ
        </a>
    </div>

    @include('frontpage.partials.mega-search', [
        'variant'          => 'compact',
        'locationsByRegion' => $locationsByRegion,
        'filters'          => $filters,
    ])
</div></div>

<div class="w ex-results">
    @php
        $items = $screens ?? collect();
    @endphp
    <div class="results-header">
        <div class="rh-count">Hiển thị <strong>{{ number_format($items->total()) }}</strong> screens</div>
        <select class="sort-sel" id="sort-sel">
            <option value="" {{ $activeSort === '' ? 'selected' : '' }}>Mặc định</option>
            <option value="price_asc" {{ $activeSort === 'price_asc' ? 'selected' : '' }}>Giá thấp nhất</option>
            <option value="price_desc" {{ $activeSort === 'price_desc' ? 'selected' : '' }}>Giá cao nhất</option>
            <option value="newest" {{ $activeSort === 'newest' ? 'selected' : '' }}>Mới nhất</option>
        </select>
        <div class="view-btn on" id="vg"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M3 3v8h8V3H3zm6 6H5V5h4v4zm-6 4v8h8v-8H3zm6 6H5v-4h4v4zm4-16v8h8V3h-8zm6 6h-4V5h4v4zm-6 4v8h8v-8h-8zm6 6h-4v-4h4v4z"/></svg></div>
        <div class="view-btn" id="vl"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:18px;height:18px;flex-shrink:0"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg></div>
    </div>

    <div class="inv-grid" id="inv-grid">
        @forelse($screens as $screen)
            @include('frontpage.partials.screen-card', ['screen' => $screen])
        @empty
        <div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--t4)">Không tìm thấy inventory phù hợp</div>
        @endforelse
    </div>

    {{ $screens->withQueryString()->links('frontpage.partials.pagination') }}
</div>
@endsection

@push('scripts')
<script>
(function(){
    // ═══ MEGA SEARCH (unified component) ═══
    @include('frontpage.partials.mega-search-js')

    // ═══ GRID/LIST TOGGLE ═══
    var grid = document.getElementById('inv-grid');
    var vg = document.getElementById('vg');
    var vl = document.getElementById('vl');
    if (grid && vg && vl) {
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
    }

    // ═══ SORT DROPDOWN ═══
    var sortSel = document.getElementById('sort-sel');
    if (sortSel) {
        sortSel.addEventListener('change', function(){
            var url = new URL(window.location.href);
            if (sortSel.value) { url.searchParams.set('sort', sortSel.value); }
            else { url.searchParams.delete('sort'); }
            window.location.href = url.toString();
        });
    }
})();
</script>
@endpush
