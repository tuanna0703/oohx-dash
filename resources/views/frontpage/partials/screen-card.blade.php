{{-- Screen inventory card partial --}}
{{-- Receives: $screen (Screen model with spec, inventory, owner, site relations) --}}
@php
    $photo = $screen->spec?->photo_url ?? 'https://placehold.co/600x400/F5F5F7/6E6E73?text=No+Photo';
    $price = $screen->inventory?->floor_cpm ?? 0;
    $priceDisplay = $price >= 1000000 ? number_format($price / 1000000) . 'M' : number_format($price, 0, ',', '.');
    $venueType = $screen->inventory?->venue_type ?? '';
    $venueLabel = ($venueLabels ?? [])[$venueType] ?? ucfirst(str_replace(['_', '.'], ' ', $venueType));
    $city = $screen->site?->city ?? '';
    $widthM = $screen->spec?->width_cm ? round($screen->spec->width_cm / 100, 1) : null;
    $heightM = $screen->spec?->height_cm ? round($screen->spec->height_cm / 100, 1) : null;
    $sizeDisplay = ($widthM && $heightM) ? "{$widthM}x{$heightM}m" : '—';
@endphp
<article class="inv-card">
    <div class="inv-photo">
        <img src="{{ $photo }}" loading="lazy" alt="{{ $screen->name }}">
        <div class="inv-badges"><span class="badge" style="background:rgba(52,199,89,.12);color:#248A3D">Available</span></div>
        <button class="inv-save"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:16px;height:16px;flex-shrink:0"><path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/></svg></button>
    </div>
    <div class="inv-body">
        <div class="inv-title">{{ $screen->name }}</div>
        <div class="inv-loc"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>{{ $city }}</div>
        <div class="inv-specs">
            <div><div class="sp-label">Format</div><div class="sp-val">{{ $venueLabel }}</div></div>
            <div><div class="sp-label">Size</div><div class="sp-val">{{ $sizeDisplay }}</div></div>
            <div><div class="sp-label">Owner</div><div class="sp-val">{{ $screen->owner?->name ?? '—' }}</div></div>
        </div>
    </div>
    <div class="inv-foot">
        <div><div class="inv-price-label">Từ</div><div class="inv-price">{{ $priceDisplay }}<sup>đ</sup></div></div>
        <div class="inv-actions">
            <a href="{{ route('fp.detail', $screen->uuid ?? $screen->id) }}" class="btn btn-p btn-sm">Book</a>
        </div>
    </div>
</article>
