@extends('frontpage.layouts.app', ['activeNav' => 'explore', 'bodyClass' => 'theme-listing'])

@section('title', 'Sản phẩm OOH/DOOH | OOHX')

@php
    $activeCategories = request()->input('category', []);
    if (is_string($activeCategories)) $activeCategories = [$activeCategories];
    $activeCities = request()->input('city', []);
    if (is_string($activeCities)) $activeCities = [$activeCities];
    $activeType = request()->input('type', '');
    $activeSort = request()->input('sort', '');
    $activeQ = request()->input('q', '');
    $hasFilters = !empty($activeCategories) || !empty($activeCities) || !empty($activeQ) || !empty($activeType);
@endphp

@section('content')
<div class="ph"><div class="w">
    <div class="ph-top">
        <div class="ph-title">Sản phẩm quảng cáo</div>
        <a href="/map" class="ph-map-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
            Bản đồ ({{ $products->total() }})
        </a>
    </div>
    <div class="fchips">
        <a href="/products" class="fchip {{ !$hasFilters ? 'on' : '' }}">Tất cả</a>
        @foreach(($filters['categories'] ?? collect()) as $cat)
            <a href="/products?category[]={{ $cat['slug'] }}" class="fchip {{ in_array($cat['slug'], $activeCategories) ? 'on' : '' }}">{{ $cat['label'] }} <span class="fchip-count">{{ $cat['count'] }}</span></a>
        @endforeach
    </div>
</div></div>

<div class="w"><div class="listing-layout">

    {{-- Sidebar filter panel --}}
    <div class="filter-backdrop" id="filter-backdrop"></div>
    <form method="GET" action="/products" class="sidebar" id="filter-form">
        <div class="drawer-drag"><div class="drawer-drag-bar"></div></div>
        <div class="sidebar-scroll">
            <div class="sb-card">
                <div class="sb-title">Tìm kiếm</div>
                <input type="text" name="q" value="{{ $activeQ }}" placeholder="Tên sản phẩm, khu vực..." class="sb-search-inp" style="width:100%;height:36px;padding:0 10px;border-radius:8px;border:1.5px solid var(--ln2);background:#fff;font-family:var(--font);font-size:13px;color:var(--t2);outline:none">
            </div>

            <div class="sb-card">
                <div class="sb-title">Loại sản phẩm</div>
                <div class="sb-group">
                    @foreach(($filters['types'] ?? collect()) as $t)
                        <label class="sb-item {{ $activeType === $t['slug'] ? 'on' : '' }}">
                            <input type="radio" name="type" value="{{ $t['slug'] }}" {{ $activeType === $t['slug'] ? 'checked' : '' }} style="display:none">
                            <div class="sb-checkbox"></div>
                            <span>{{ $t['label'] }}</span>
                            <span class="sb-count">{{ $t['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="sb-card">
                <div class="sb-title">Danh mục</div>
                <div class="sb-group">
                    @foreach(($filters['categories'] ?? collect()) as $cat)
                        <label class="sb-item {{ in_array($cat['slug'], $activeCategories) ? 'on' : '' }}">
                            <input type="checkbox" name="category[]" value="{{ $cat['slug'] }}" {{ in_array($cat['slug'], $activeCategories) ? 'checked' : '' }} style="display:none">
                            <div class="sb-checkbox"></div>
                            <span>{{ $cat['label'] }}</span>
                            <span class="sb-count">{{ $cat['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="sb-card">
                <div class="sb-title">Thành phố</div>
                <div class="sb-group">
                    @foreach(($filters['cities'] ?? collect())->take(12) as $city)
                        <label class="sb-item {{ in_array($city['name'], $activeCities) ? 'on' : '' }}">
                            <input type="checkbox" name="city[]" value="{{ $city['name'] }}" {{ in_array($city['name'], $activeCities) ? 'checked' : '' }} style="display:none">
                            <div class="sb-checkbox"></div>
                            <span>{{ $city['name'] }}</span>
                            <span class="sb-count">{{ $city['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <input type="hidden" name="sort" id="sort-input" value="{{ $activeSort }}">
            <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;border-radius:10px">Áp dụng</button>
            @if($hasFilters)
                <a href="/products" class="btn btn-s" style="width:100%;justify-content:center;border-radius:10px;margin-top:6px;text-decoration:none">Xoá bộ lọc</a>
            @endif
        </div>
    </form>

    {{-- Main content --}}
    <div>
        <div class="results-header">
            <div class="rh-count">Hiển thị <strong>{{ number_format($products->total()) }}</strong> sản phẩm</div>
            <select class="sort-sel" id="sort-sel" onchange="document.getElementById('sort-input').value=this.value; document.getElementById('filter-form').submit();">
                <option value="" {{ $activeSort === '' ? 'selected' : '' }}>Mặc định</option>
                <option value="price_asc" {{ $activeSort === 'price_asc' ? 'selected' : '' }}>Giá thấp nhất</option>
                <option value="price_desc" {{ $activeSort === 'price_desc' ? 'selected' : '' }}>Giá cao nhất</option>
                <option value="newest" {{ $activeSort === 'newest' ? 'selected' : '' }}>Mới nhất</option>
            </select>
        </div>

        <div class="inv-grid" id="inv-grid">
            @forelse($products as $product)
                @include('frontpage.products.partials.product-card', ['product' => $product])
            @empty
                <div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--t4)">Không tìm thấy sản phẩm phù hợp</div>
            @endforelse
        </div>

        <div class="load-more">{{ $products->withQueryString()->links() }}</div>
    </div>
</div></div>

<button class="filter-float" id="filter-float" type="button">
    <svg viewBox="0 0 24 24" fill="#fff"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
    Bộ lọc
    @if($hasFilters)<span class="filter-float-count">{{ count($activeCategories) + count($activeCities) + ($activeQ ? 1 : 0) + ($activeType ? 1 : 0) }}</span>@endif
</button>
@endsection

@push('scripts')
<script>
(function(){
    var filterFloat = document.getElementById('filter-float');
    var form = document.getElementById('filter-form');
    function openFilter(){ form.classList.add('open'); document.body.style.overflow='hidden'; if(filterFloat) filterFloat.classList.add('hidden'); }
    function closeFilter(){ form.classList.remove('open'); document.body.style.overflow=''; if(filterFloat) filterFloat.classList.remove('hidden'); }
    if(filterFloat) filterFloat.addEventListener('click', openFilter);
    document.querySelectorAll('.drawer-drag').forEach(function(d){ d.addEventListener('click', closeFilter); });
    var backdrop = document.getElementById('filter-backdrop');
    if(backdrop) backdrop.addEventListener('click', closeFilter);
    document.querySelectorAll('.sb-item input[type="checkbox"],.sb-item input[type="radio"]').forEach(function(cb){
        cb.addEventListener('change', function(){ cb.closest('.sb-item').classList.toggle('on', cb.checked); });
    });
})();
</script>
@endpush
