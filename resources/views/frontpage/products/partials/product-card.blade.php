{{-- Product card — used on /products listing and homepage
     Receives: $product (Product model with owner, network, screens_count)
--}}
@php
    $cover = $product->cover_url ?? 'https://placehold.co/600x400/F5F5F7/6E6E73?text=' . urlencode($product->category_label);
    $typeLabels = ['single'=>'Đơn lẻ','package'=>'Gói','custom'=>'Combo'];
    $typeColors = ['single'=>'var(--bl)','package'=>'var(--grn)','custom'=>'var(--org)'];
@endphp
<a href="/products/{{ $product->slug }}" class="pd-card">
    <div class="pd-photo">
        <img src="{{ $cover }}" loading="lazy" alt="{{ $product->name }}">
        <div class="pd-badges">
            <span class="pd-badge" style="background:{{ $typeColors[$product->type] ?? 'var(--bl)' }}">{{ $typeLabels[$product->type] ?? $product->type }}</span>
            <span class="pd-badge pd-badge-cat">{{ $product->category_label }}</span>
        </div>
        @if($product->isPackage() && $product->total_units > 1)
        <div class="pd-units">{{ $product->total_units }} vị trí</div>
        @endif
    </div>
    <div class="pd-body">
        <div class="pd-name">{{ $product->name }}</div>
        @if($product->owner)
        <div class="pd-owner">{{ $product->owner->name }}</div>
        @endif
        @if($product->city)
        <div class="pd-loc">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
            {{ $product->city }}
        </div>
        @endif
        @if($product->short_description)
        <div class="pd-desc">{{ Str::limit($product->short_description, 80) }}</div>
        @endif
    </div>
    <div class="pd-foot">
        <div>
            <div class="pd-price">{{ $product->price_display }}</div>
        </div>
        <span class="btn btn-p btn-sm pd-btn">{{ $product->isPackage() ? 'Chọn gói' : 'Chi tiết' }}</span>
    </div>
</a>
