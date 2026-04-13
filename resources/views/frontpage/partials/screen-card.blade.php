{{-- Unified screen card partial
     Receives: $screen (Screen model with spec, inventory, owner, site, site.network, products relations)
     Optional: $vnCatLabels (array), $compact (bool — smaller card for similar/featured)
--}}
@php
    $photo = $screen->spec?->photo ?? 'https://placehold.co/600x400/F5F5F7/6E6E73?text=No+Photo';
    $price = $screen->inventory?->floor_cpm ?? 0;
    $priceDisplay = number_format($price, 0, ',', '.');
    $vnCatId = $screen->inventory?->vn_category_id;
    $venueLabel = ($vnCatLabels ?? [])[$vnCatId] ?? '—';
    $city = $screen->site?->city ?? '';
    $widthM = $screen->spec?->width_cm ? round($screen->spec->width_cm / 100, 1) : null;
    $heightM = $screen->spec?->height_cm ? round($screen->spec->height_cm / 100, 1) : null;
    $sizeDisplay = ($widthM && $heightM) ? "{$widthM}x{$heightM}m" : '—';
    $compact = $compact ?? false;
    $detailUrl = route('fp.detail', $screen->uuid ?? $screen->id);
    // Product badge: lấy product đầu tiên nếu screen thuộc product
    $product = $screen->relationLoaded('products') ? $screen->products->first() : null;
@endphp
<a href="{{ $detailUrl }}" class="sc-card {{ $compact ? 'sc-compact' : '' }}">
    <div class="sc-photo">
        <img src="{{ $photo }}" loading="lazy" alt="{{ $screen->name }}">
        <div class="sc-badge">Còn trống</div>
        @if($product)
        <div class="sc-product-badge">
            @if($product->total_units > 1)
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:11px;height:11px"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                {{ $product->total_units }} vị trí
            @endif
            @if($product->allowsIndividual())
                · Mua lẻ được
            @endif
        </div>
        @endif
    </div>
    <div class="sc-body">
        <div class="sc-title">{{ $screen->name }}</div>
        @if($city)
            <div class="sc-loc">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="sc-loc-icon"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                {{ $city }}
            </div>
        @endif

        @unless($compact)
            <div class="sc-specs">
                <div class="sc-spec"><span class="sc-spec-l">Chủ sở hữu</span><span class="sc-spec-v">{{ $screen->owner?->name ?? '—' }}</span></div>
                <div class="sc-spec"><span class="sc-spec-l">Network</span><span class="sc-spec-v">{{ $screen->site?->network?->name ?? '—' }}</span></div>
                <div class="sc-spec"><span class="sc-spec-l">Site</span><span class="sc-spec-v">{{ $screen->site?->name ?? '—' }}</span></div>
                <div class="sc-spec"><span class="sc-spec-l">Địa điểm</span><span class="sc-spec-v">{{ $venueLabel }}</span></div>
                <div class="sc-spec"><span class="sc-spec-l">Kích thước</span><span class="sc-spec-v">{{ $sizeDisplay }}</span></div>
            </div>
        @endunless
    </div>
    <div class="sc-foot">
        <div>
            <div class="sc-price">{{ $priceDisplay }}<sup>đ</sup></div>
            <div class="sc-price-sub">/ tháng</div>
        </div>
        <span class="btn btn-p btn-sm sc-btn">Chi tiết</span>
    </div>
</a>
