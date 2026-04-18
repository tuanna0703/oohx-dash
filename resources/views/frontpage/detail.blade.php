@extends('frontpage.layouts.app', ['activeNav' => 'explore', 'bodyClass' => 'page-detail'])

@section('title', ($screen->name ?? 'Chi tiết vị trí') . ' | OOHX')

@section('seo')
@include('frontpage.partials.seo-meta', [
    'seoTitle'       => ($screen->name ?? 'Chi tiết vị trí') . ' | OOHX',
    'seoDescription' => $screen->name . ' tại ' . ($screen->site?->name ?? '') . ', ' . ($screen->site?->city ?? '') . '. ' . ($screen->spec?->width_px ?? '') . 'x' . ($screen->spec?->height_px ?? '') . 'px. ' . (($screen->inventory?->display_price ?? 0) > 0 ? 'Giá từ ' . number_format($screen->inventory->display_price, 0, ',', '.') . 'đ/' . ($screen->inventory->display_price_unit ?? 'tháng') . '.' : 'Liên hệ báo giá.'),
    'seoImage'       => $screen->spec?->photo_url ? asset('storage/' . $screen->spec->photo_url) : null,
    'seoUrl'         => route('fp.detail', $screen->slug ?? $screen->uuid),
    'seoType'        => 'product',
    'seoJsonLd'      => [
        '@context' => 'https://schema.org',
        '@type'    => 'Product',
        'name'     => $screen->name,
        'description' => $screen->name . ' tại ' . ($screen->site?->name ?? '') . ', ' . ($screen->site?->city ?? ''),
        'image'    => $screen->spec?->photo_url ? asset('storage/' . $screen->spec->photo_url) : '',
        'brand'    => ['@type' => 'Organization', 'name' => $screen->owner?->name ?? 'OOHX'],
        'offers'   => [
            '@type'         => 'Offer',
            'price'         => (float) ($screen->inventory?->display_price ?? 0),
            'priceCurrency' => $screen->inventory?->floor_cpm_currency ?? 'VND',
            'availability'  => 'https://schema.org/InStock',
        ],
    ],
])
@endsection

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
@media(min-width:768px){#lbtn,#sbtn{display:inline-flex}}
.lb-overlay{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.92);display:flex;align-items:center;justify-content:center}
.lb-img{max-width:90vw;max-height:85vh;object-fit:contain;border-radius:8px}
.lb-close{position:absolute;top:16px;right:16px;width:40px;height:40px;border-radius:50%;border:none;background:rgba(255,255,255,.15);color:#fff;font-size:24px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.lb-prev,.lb-next{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;border:none;background:rgba(255,255,255,.15);cursor:pointer;display:flex;align-items:center;justify-content:center}
.lb-prev{left:16px}.lb-next{right:16px}
.lb-prev:hover,.lb-next:hover,.lb-close:hover{background:rgba(255,255,255,.3)}
.lb-counter{position:absolute;bottom:20px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.7);font-size:13px;font-weight:600}
.gal-thumb-on{outline:2px solid var(--bl);outline-offset:1px;border-radius:8px}

/* ── Standalone Location section ────────────────────────────── */
.loc-section{padding:48px 0;background:var(--bg2);border-top:1px solid var(--ln2);border-bottom:1px solid var(--ln2)}
.loc-head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;margin-bottom:20px;flex-wrap:wrap}
.loc-eyebrow{font-size:13px;font-weight:700;color:var(--bl);margin-bottom:6px;letter-spacing:.5px}
.loc-title{font-size:clamp(20px,3vw,28px);font-weight:800;color:var(--t1);letter-spacing:-.5px;margin-bottom:8px;line-height:1.2}
.loc-addr{display:inline-flex;align-items:center;gap:6px;font-size:14px;color:var(--t3);line-height:1.4}
.loc-meta{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.loc-poi-count{font-size:13px;color:var(--t3);padding:6px 12px;background:#fff;border-radius:980px;border:1px solid var(--ln2)}
.loc-poi-count strong{color:var(--bl);font-weight:800}
.loc-osm-link{font-size:13px;color:var(--bl);text-decoration:none;font-weight:600}
.loc-osm-link:hover{text-decoration:underline}
#loc-map{width:100%;height:480px;border-radius:16px;border:1px solid var(--ln2);box-shadow:var(--sh1);background:#fff;overflow:hidden}
@media(max-width:768px){#loc-map{height:380px}}
.loc-legend{display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-top:14px;font-size:13px;color:var(--t3)}
.loc-leg-i{display:inline-flex;align-items:center;gap:8px}
.loc-leg-dot{display:inline-block;border-radius:50%}
.loc-leg-dot--screen{width:14px;height:14px;background:var(--bl);box-shadow:0 0 0 2px rgba(42,79,246,.2)}
.loc-leg-dot--poi{width:10px;height:10px;background:#7F8C8D}

/* ── Map markers (detail page Vị trí tab) ── */
.poi-dot{background:none;border:none}
.poi-dot-inner{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.25);cursor:pointer;transition:transform 160ms}
.poi-dot-inner:hover{transform:scale(1.4);z-index:1000}

.screen-pin{background:none;border:none;position:relative}
.screen-pin-inner{position:absolute;top:0;left:0;width:44px;height:44px;border-radius:50%;background:var(--bl);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;border:3px solid #fff;box-shadow:0 4px 16px rgba(42,79,246,.4);cursor:pointer;z-index:2}
.screen-pin-pulse{position:absolute;top:-4px;left:-4px;width:52px;height:52px;border-radius:50%;background:rgba(42,79,246,.25);animation:screen-pulse 2s ease-in-out infinite;z-index:1}
@keyframes screen-pulse{0%,100%{transform:scale(1);opacity:.6}50%{transform:scale(1.3);opacity:.1}}
</style>
@endpush

@section('content')
<div class="w">
@php
    $bcCatId = $screen->inventory?->vn_category_id;
    $bcCatLabel = ($vnCatLabels ?? [])[$bcCatId] ?? null;
    $bcCatSlug = $bcCatId ? \DB::table('venue_categories')->where('id', $bcCatId)->value('slug') : null;
    $bcSep = '<span class="bc-sep"><svg viewBox="0 0 24 24" fill="var(--t4)" style="width:14px;height:14px"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></span>';
@endphp
<div class="bc">
    <a href="{{ route('fp.index') }}">OOHX</a>
    {!! $bcSep !!}
    <a href="{{ route('fp.listing') }}">Inventory</a>
    @if($bcCatLabel)
    {!! $bcSep !!}
    <a href="/explore?venue_type[]={{ $bcCatSlug }}">{{ $bcCatLabel }}</a>
    @endif
    @if($screen->site?->city)
    {!! $bcSep !!}
    <a href="/explore?city[]={{ \Illuminate\Support\Str::slug(explode('>', $screen->site->city)[0]) }}">{{ trim(explode('>', $screen->site->city)[0]) }}</a>
    @endif
    {!! $bcSep !!}
    <span style="color:var(--t2)">{{ $screen->name }}</span>
</div>
@php
    $photos = collect($screen->spec?->photos ?? [])
        ->map(fn($p) => asset('storage/' . $p))
        ->toArray();
    if (empty($photos) && $screen->spec?->photo_url) {
        $photos = [asset('storage/' . $screen->spec->photo_url)];
    }
    // Fallback chain: if no spec photos, use display_photo (site/network/owner/venue category)
    if (empty($photos)) {
        $fallback = $screen->display_photo;
        if ($fallback) $photos = [$fallback];
    }
    $mainPhoto = $photos[0] ?? 'https://placehold.co/800x500/F5F5F7/6E6E73?text=No+Photo';
@endphp
<div class="gallery{{ count($photos) <= 1 ? ' gallery--single' : '' }}">
  <div class="gal-main">
    <img src="{{ $mainPhoto }}" id="gal-main-img" alt="{{ $screen->name }}">
    <div class="gal-actions"><button class="gal-btn" id="savebtn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:17px;height:17px;flex-shrink:0"><path d="M16.5 3c-1.74 0-3.41.81-4.5 2.09C10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5c2 0 3.5 1.5 3.5 3.5 0 2.89-3.14 5.74-7.9 10.05z"/></svg></button><button class="gal-btn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:17px;height:17px;flex-shrink:0"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg></button><button class="gal-btn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:17px;height:17px;flex-shrink:0"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg></button></div>
    <div class="gal-badge"><span class="badge b-grn"><span style="width:7px;height:7px;border-radius:50%;background:var(--grn);display:inline-block"></span> Còn trống</span></div>
    <div class="gal-count"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:13px;height:13px;flex-shrink:0"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg> {{ count($photos) }} ảnh</div>
  </div>
  @if(count($photos) > 1)
  <div class="gal-thumbs">
    @foreach(array_slice($photos, 0, 5) as $i => $thumb)
    <div class="gal-thumb {{ $i === 0 ? 'gal-thumb-on' : '' }}" data-src="{{ $thumb }}"><img src="{{ $thumb }}" loading="lazy" alt="">
    @if($i === 4 && count($photos) > 5)<div class="gal-more">+{{ count($photos) - 5 }}</div>@endif
    </div>
    @endforeach
  </div>
  @endif
</div>

{{-- Lightbox overlay --}}
@if(count($photos) > 0)
<div class="lb-overlay" id="lb-overlay" style="display:none">
    <button class="lb-close" id="lb-close">&times;</button>
    <button class="lb-prev" id="lb-prev"><svg viewBox="0 0 24 24" fill="#fff" style="width:24px;height:24px"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg></button>
    <img class="lb-img" id="lb-img" src="" alt="">
    <button class="lb-next" id="lb-next"><svg viewBox="0 0 24 24" fill="#fff" style="width:24px;height:24px"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></button>
    <div class="lb-counter" id="lb-counter"></div>
</div>
@endif
<div class="detail-layout">
<div>
<h1 class="dtitle">{{ $screen->name }}</h1>
<div class="dmeta"><div class="dloc"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:14px;height:14px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> {{ $screen->site?->city ?? '' }}</div><span class="msep"></span><span style="font-size:13px;color:var(--t3)">{{ $screen->owner?->name ?? '' }}</span></div>
@if($screen->owner)
@include('frontpage.partials.owner-card', [
    'owner'      => $screen->owner,
    'variant'    => 'mini',
    'extraLabel' => $screen->site?->network?->name ?? '',
    'href'       => route('fp.owner-detail', $screen->owner->slug ?? '#') . '?tab=inventory' . (($screen->site?->network?->code ?? '') ? '&network[]=' . $screen->site->network->code : ''),
])
@endif
<div class="stats-row rv"><div class="stat-box"><div class="stat-n">{{ $screen->site?->name ?? '—' }}</div><div class="stat-l">Site</div></div><div class="stat-box"><div class="stat-n">{{ $screen->site?->network?->name ?? '—' }}</div><div class="stat-l">Network</div></div><div class="stat-box"><div class="stat-n">{{ $screen->spec?->width_cm ? round($screen->spec->width_cm/100,1).'×'.round($screen->spec->height_cm/100,1).'m' : '—' }}</div><div class="stat-l">Kích thước</div></div><div class="stat-box"><div class="stat-n">{{ $screen->inventory?->floor_cpm ? number_format((float)$screen->inventory->floor_cpm, 0, ',', '.') . 'đ' : '—' }}</div><div class="stat-l">Floor CPM</div></div></div>
<div class="tabs"><div class="tab on" onclick="sw(this,'tp-info')">Thông tin</div>@if($screen->has_insights)<div class="tab" onclick="sw(this,'tp-insights')">Insights</div>@endif<div class="tab" onclick="sw(this,'tp-avail')">Lịch trống</div><div class="tab" onclick="sw(this,'tp-review')">Đánh giá</div></div>
<div id="tp-info" class="tp on">
<div class="specs-grid rv"><div class="sc"><div class="sc-l">Chủ sở hữu</div><div class="sc-v">{{ $screen->owner?->name ?? '—' }}</div></div><div class="sc"><div class="sc-l">Network</div><div class="sc-v">{{ $screen->site?->network?->name ?? '—' }}</div></div><div class="sc"><div class="sc-l">Site</div><div class="sc-v">{{ $screen->site?->name ?? '—' }}</div></div><div class="sc"><div class="sc-l">Địa điểm</div><div class="sc-v">{{ ($vnCatLabels ?? [])[$screen->inventory?->vn_category_id] ?? '—' }}</div></div><div class="sc"><div class="sc-l">Kích thước</div><div class="sc-v">{{ $screen->spec?->width_cm ? round($screen->spec->width_cm/100,1).' × '.round($screen->spec->height_cm/100,1).' m' : '—' }}</div></div><div class="sc"><div class="sc-l">Độ phân giải</div><div class="sc-v">{{ $screen->spec?->width_px && $screen->spec?->height_px ? $screen->spec->width_px.' × '.$screen->spec->height_px.' px' : '—' }}</div></div><div class="sc"><div class="sc-l">Loại nội dung</div><div class="sc-v">{{ implode(', ', array_filter([$screen->spec?->allow_image ? 'Ảnh' : null, $screen->spec?->allow_video ? 'Video' : null])) ?: 'Video / Ảnh tĩnh' }}</div></div><div class="sc"><div class="sc-l">Floor CPM</div><div class="sc-v">{{ $screen->inventory?->floor_cpm ? number_format((float)$screen->inventory->floor_cpm, 0, ',', '.') . ' ' . ($screen->inventory->floor_cpm_currency ?? 'VND') : '—' }}</div></div><div class="sc"><div class="sc-l">Vị trí</div><div class="sc-v">{{ $screen->site?->address ?? '—' }}</div></div><div class="sc"><div class="sc-l">Tỉnh/Thành</div><div class="sc-v">{{ $screen->site?->city ?? '—' }}</div></div><div class="sc"><div class="sc-l">Thời lượng QC</div><div class="sc-v">{{ ($screen->inventory?->spot_length ?? 15) }}s</div></div></div>
@if($screen->description)
<div class="rv" style="margin-bottom:28px"><h3 style="font-size:18px;font-weight:700;color:var(--t1);margin-bottom:14px;letter-spacing:-.3px">Mô tả</h3><div class="desc"><p>{{ $screen->description }}</p></div></div>
@endif
<div class="rv" style="margin-bottom:28px"><h3 style="font-size:18px;font-weight:700;color:var(--t1);margin-bottom:16px;letter-spacing:-.3px">Điểm nổi bật</h3><div class="hl-grid"><div class="hl-card"><div class="hl-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></div><div><div class="hl-title">AI Traffic Data</div><div class="hl-sub">Cập nhật theo giờ thực</div></div></div><div class="hl-card"><div class="hl-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg></div><div><div class="hl-title">Proof of Play</div><div class="hl-sub">Video xác nhận hàng ngày</div></div></div><div class="hl-card"><div class="hl-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg></div><div><div class="hl-title">Verified Location</div><div class="hl-sub">Kiểm tra thực địa 2024</div></div></div><div class="hl-card"><div class="hl-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M20.38 8.57l-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44zm-9.79 6.84a2 2 0 0 0 2.83 0l5.66-8.49-8.49 5.66a2 2 0 0 0 0 2.83z"/></svg></div><div><div class="hl-title">Fast Booking</div><div class="hl-sub">Xác nhận trong 48h</div></div></div></div></div>
</div>
@if($screen->has_insights)
<div id="tp-insights" class="tp">
    @include('frontpage.partials.screen-insights')
</div>
@endif
<div id="tp-avail" class="tp">
<p style="font-size:14px;color:var(--t3);margin-bottom:20px">Xanh = còn trống · Cam = đã đặt một phần · Đỏ = đã đặt hết.</p>
<div class="avail-grid">
@php
    $maxSov = (int)($screen->inventory?->share_of_voice_max_pct ?? 100);
    for ($m = 0; $m < 2; $m++):
        $month = now()->addMonths($m);
        $firstDay = $month->copy()->startOfMonth();
        $daysInMonth = $month->daysInMonth;
        $startDow = ($firstDay->dayOfWeek + 6) % 7; // Mon=0
@endphp
<div class="month-card"><div class="month-name">Tháng {{ $month->month }}/{{ $month->year }}</div>
<div class="cal-grid">
<div class="cal-dh">T2</div><div class="cal-dh">T3</div><div class="cal-dh">T4</div><div class="cal-dh">T5</div><div class="cal-dh">T6</div><div class="cal-dh">T7</div><div class="cal-dh">CN</div>
@for($i = 0; $i < $startDow; $i++)<div class="cal-day cd-em"></div>@endfor
@for($d = 1; $d <= $daysInMonth; $d++)
@php
    $date = $firstDay->copy()->day($d)->format('Y-m-d');
    $sovUsed = $bookedDates[$date] ?? 0;
    $cls = $sovUsed >= $maxSov ? 'cd-bk' : ($sovUsed > 0 ? 'cd-pt' : 'cd-ok');
    if ($firstDay->copy()->day($d)->lt(now()->startOfDay())) $cls = 'cd-em';
@endphp
<div class="cal-day {{ $cls }}">{{ $d }}</div>
@endfor
</div></div>
@php endfor; @endphp
</div>
<div class="cal-legend"><div class="cl-i"><div class="cl-d" style="background:rgba(52,199,89,.4)"></div>Còn trống</div><div class="cl-i"><div class="cl-d" style="background:rgba(255,159,10,.4)"></div>Đã đặt một phần</div><div class="cl-i"><div class="cl-d" style="background:rgba(255,59,48,.3)"></div>Đã đặt hết</div></div>
</div>
<div id="tp-review" class="tp">
<div style="text-align:center;padding:48px 20px;color:var(--t4)">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--ln2)" style="width:48px;height:48px;margin:0 auto 16px;display:block"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
  <div style="font-size:16px;font-weight:700;color:var(--t3);margin-bottom:6px">Chưa có đánh giá</div>
  <div style="font-size:13px">Đánh giá sẽ hiển thị sau khi có booking được hoàn thành.</div>
</div>
{{-- Reviews sẽ được tích hợp khi có Review model --}}
@if(false)
<div class="rv-summary-old"><div><div class="rv-big">4.9</div><div class="rv-stars-row"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:16px;height:16px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:16px;height:16px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:16px;height:16px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:16px;height:16px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:16px;height:16px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div><div class="rv-cnt">23 đánh giá</div></div><div class="rv-bars"><div class="rv-bar-row"><span class="rv-bar-n">5</span><div class="rv-bar-track"><div class="rv-bar-fill" style="width:88%"></div></div><span class="rv-bar-c">20</span></div><div class="rv-bar-row"><span class="rv-bar-n">4</span><div class="rv-bar-track"><div class="rv-bar-fill" style="width:9%"></div></div><span class="rv-bar-c">2</span></div><div class="rv-bar-row"><span class="rv-bar-n">3</span><div class="rv-bar-track"><div class="rv-bar-fill" style="width:3%"></div></div><span class="rv-bar-c">1</span></div><div class="rv-bar-row"><span class="rv-bar-n">2</span><div class="rv-bar-track"><div class="rv-bar-fill" style="width:0%"></div></div><span class="rv-bar-c">0</span></div><div class="rv-bar-row"><span class="rv-bar-n">1</span><div class="rv-bar-track"><div class="rv-bar-fill" style="width:0%"></div></div><span class="rv-bar-c">0</span></div></div></div>
<div class="rv-item"><div class="rv-head"><div class="rv-av" style="background:#2A4FF6">MH</div><div><div class="rv-nm">Minh Hằng · Ogilvy Vietnam</div><div class="rv-dt">Tháng 3/2025</div></div></div><div class="rv-st"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div><div class="rv-tx">Vị trí cực kỳ tốt, traffic thực tế khớp số liệu owner cung cấp. Proof-of-play nhận đúng hạn. Team hỗ trợ nhanh, sẽ book lại.</div></div><div class="rv-item"><div class="rv-head"><div class="rv-av" style="background:#FF9F0A">TL</div><div><div class="rv-nm">Tuấn Long · MediaLink Agency</div><div class="rv-dt">Tháng 2/2025</div></div></div><div class="rv-st"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div><div class="rv-tx">Chạy campaign Honda tháng 2. Kết quả brand recall tốt hơn KPI 15%. Sẽ tiếp tục sử dụng vị trí này.</div></div><div class="rv-item"><div class="rv-head"><div class="rv-av" style="background:#34C759">NK</div><div><div class="rv-nm">Nguyễn Kim · Unilever</div><div class="rv-dt">Tháng 1/2025</div></div></div><div class="rv-st"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div><div class="rv-tx">Giá tốt so với vị trí tương đương. Process booking rất nhanh, 30 phút là xong.</div></div><div class="rv-item"><div class="rv-head"><div class="rv-av" style="background:#AF52DE">PT</div><div><div class="rv-nm">Phương Thảo · Publicis Vietnam</div><div class="rv-dt">Tháng 12/2024</div></div></div><div class="rv-st"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--org)" style="width:13px;height:13px;flex-shrink:0"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div><div class="rv-tx">LED P4 hiển thị cực sắc nét, visibility tốt cả ban ngày lẫn ban đêm.</div></div></div>
@endif
</div>{{-- /tp-review --}}
</div>{{-- /left column --}}
<div class="detail-sidebar">
@php
    $inv = $screen->inventory;
    $pricingModel = $inv?->pricing_model ?? 'io';
    $allowsCpm = $inv?->allowsCpm() ?? false;
    $allowsIo = $inv?->allowsIo() ?? true;
    $isBoth = $pricingModel === 'both';
    $ioRate = (float)($inv?->io_rate ?? 0);
    $cpmRate = (float)($inv?->floor_cpm ?? 0);
    $ioRateUnit = $inv?->io_rate_unit ?? 'month';
    $kpiSpots = $inv?->io_kpi_spots_per_day;
    $defIoUnits = $ioRateUnit === 'week' ? 4 : 3;
    $ioUnitLabel = $ioRateUnit === 'week' ? 'tuần' : 'tháng';
    // Default calculation (I/O preferred as headline)
    $defMode = $allowsIo ? 'io' : 'cpm';
    $defSub = $defMode === 'io' ? ($ioRate * 1 * $defIoUnits) : ($cpmRate * 1000);
    $hasPrice = ($allowsIo && $ioRate > 0) || ($allowsCpm && $cpmRate > 0);
@endphp
@if(!$hasPrice)
{{-- No price → contact-only panel --}}
<div class="bp bp-quote">
<div class="bp-head">
<div class="bp-quote-ic">
<svg viewBox="0 0 24 24" fill="currentColor" style="width:28px;height:28px"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
</div>
<div class="bp-quote-title">Liên hệ báo giá</div>
<div class="bp-quote-sub">Vị trí này chưa công khai giá. Liên hệ Media Owner để nhận báo giá chi tiết.</div>
<div class="bp-avail" style="margin-top:10px"><div class="bp-dot"></div>Còn trống</div>
</div>
</div>
@else
<div class="bp">
<div class="bp-head">
@if($isBoth)
{{-- Dual price display --}}
<div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">
<div class="bp-price">{{ number_format($ioRate, 0, ',', '.') }} ₫</div>
<div style="font-size:13px;color:var(--t4)">/ {{ $ioRateUnit === 'week' ? 'mh/tuần' : 'mh/tháng' }}</div>
</div>
<div style="font-size:12px;color:var(--t3);margin:4px 0 2px">hoặc <strong>{{ number_format($cpmRate, 0, ',', '.') }} ₫/CPM</strong></div>
@elseif($allowsCpm && !$allowsIo)
<div class="bp-price">{{ number_format($cpmRate, 0, ',', '.') }} ₫</div>
<div style="font-size:13px;color:var(--t4);margin:4px 0 10px">/ CPM · Chưa bao gồm VAT</div>
@else
<div class="bp-price">{{ number_format($ioRate, 0, ',', '.') }} ₫</div>
<div style="font-size:13px;color:var(--t4);margin:4px 0 10px">/ {{ $ioRateUnit === 'week' ? 'màn hình/tuần' : 'màn hình/tháng' }} · Chưa bao gồm VAT</div>
@endif
@if($allowsIo && $kpiSpots)
<div style="font-size:12px;color:var(--bl);font-weight:600;margin:4px 0 8px">KPI: {{ number_format($kpiSpots) }} spots/màn hình/ngày</div>
@endif
<div class="bp-avail"><div class="bp-dot"></div>Còn trống</div>
</div>
<div class="bp-body">
@if($isBoth)
{{-- Toggle: buyer chọn kiểu bán --}}
<div class="bp-field">
<div class="bp-lbl">Kiểu booking</div>
<div class="bp-toggle" id="bp-mode-toggle">
<button type="button" class="bp-tog-btn bp-tog-on" data-mode="io">I/O Booking</button>
<button type="button" class="bp-tog-btn" data-mode="cpm">CPM</button>
</div>
</div>
@endif
<div class="bp-field"><div class="bp-lbl">Ngày bắt đầu</div><input class="bp-inp" type="date" value="{{ now()->addDays(7)->format('Y-m-d') }}" id="bp-start"></div>
{{-- I/O fields --}}
<div id="bp-io-fields" style="{{ ($defMode !== 'io') ? 'display:none' : '' }}">
<div class="bp-field"><div class="bp-lbl">Thời lượng</div><select class="bp-inp" id="bp-duration">
@if($ioRateUnit === 'week')
<option value="1">1 tuần</option><option value="2">2 tuần</option><option value="4" selected>4 tuần</option><option value="8">8 tuần</option><option value="12">12 tuần</option>
@else
<option value="1">1 tháng</option><option value="2">2 tháng</option><option value="3" selected>3 tháng</option><option value="6">6 tháng</option><option value="12">12 tháng</option>
@endif
</select></div>
<div class="bp-field"><div class="bp-lbl">Số màn hình</div><input class="bp-inp" type="number" min="1" value="1" id="bp-screens"></div>
</div>
{{-- CPM fields --}}
<div id="bp-cpm-fields" style="{{ ($defMode !== 'cpm') ? 'display:none' : '' }}">
<div class="bp-field"><div class="bp-lbl">Số CPM (× 1,000 impressions)</div><input class="bp-inp" type="number" min="1" value="1000" id="bp-cpms"></div>
</div>
<div class="bp-field"><div class="bp-lbl">Brand / Campaign</div><input class="bp-inp" type="text" placeholder="VD: Honda Civic Launch Q2"></div>
<div class="bp-sum">
<div class="bp-row"><span class="bp-rl" id="bp-calc-label">—</span><span class="bp-rv" id="bp-calc-sub">{{ number_format($defSub, 0, ',', '.') }} ₫</span></div>
<div class="bp-row"><span class="bp-rl">VAT (10%)</span><span class="bp-rv" id="bp-calc-vat">{{ number_format($defSub * 0.1, 0, ',', '.') }} ₫</span></div>
<div class="bp-row bp-total"><span class="bp-rl">Tổng cộng</span><span class="bp-rv" id="bp-calc-total">{{ number_format($defSub * 1.1, 0, ',', '.') }} ₫</span></div>
</div>
<div class="bp-ctas">@auth<form method="POST" action="{{ route('buyer.cart.add') }}" class="cart-add-form"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="screen_id" value="{{ $screen->id }}"><input type="hidden" name="start_date" id="bp-h-start" value="{{ now()->addDays(7)->format('Y-m-d') }}"><input type="hidden" name="end_date" id="bp-h-end" value="{{ now()->addDays(7)->addMonths(3)->format('Y-m-d') }}"><input type="hidden" name="pricing_model" id="bp-h-mode" value="{{ $defMode }}"><input type="hidden" name="booked_cpms" id="bp-h-cpms" value="1000"><input type="hidden" name="screen_count" id="bp-h-screens" value="1"><input type="hidden" name="duration_units" id="bp-h-dunits" value="{{ $defIoUnits }}">
<button type="submit" class="btn btn-p btn-lg" style="width:100%;justify-content:center;border-radius:12px;height:52px"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:18px;height:18px;flex-shrink:0"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Thêm vào Plan</button></form>@else<a href="{{ route('login') }}" class="btn btn-p btn-lg" style="width:100%;justify-content:center;border-radius:12px;height:52px;text-decoration:none"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:18px;height:18px;flex-shrink:0"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Đăng nhập để Booking</a>@endauth</div>
</div>
<div class="bp-foot"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:14px;height:14px;flex-shrink:0"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg> Thanh toán bảo mật bởi OOHX · Hoàn tiền 100% nếu hủy trước 48h</div></div>
@endif
@php
    $ownerPhone = $screen->owner?->phone;
    $ownerEmail = $screen->owner?->email;
    $ownerName  = $screen->owner?->name ?? 'OOHX';
@endphp
<div style="margin-top:16px;padding:18px;background:var(--bg2);border-radius:16px;border:1px solid var(--ln2)">
<div style="font-size:14px;font-weight:700;color:var(--t1);margin-bottom:4px">Liên hệ {{ $ownerName }}</div>
<div style="font-size:12px;color:var(--t4);margin-bottom:12px">Media Owner quản lý vị trí này</div>
<div style="display:flex;flex-direction:column;gap:9px">
@if($ownerPhone)
<a href="tel:{{ $ownerPhone }}" class="btn btn-s" style="justify-content:flex-start;gap:10px;border-radius:10px;height:44px;font-weight:600;text-decoration:none"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:16px;height:16px;flex-shrink:0"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg> Gọi: {{ $ownerPhone }}</a>
@endif
@if($ownerEmail)
<a href="mailto:{{ $ownerEmail }}" class="btn btn-s" style="justify-content:flex-start;gap:10px;border-radius:10px;height:44px;font-weight:600;text-decoration:none"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:16px;height:16px;flex-shrink:0"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg> Email: {{ $ownerEmail }}</a>
@endif
@if(!$ownerPhone && !$ownerEmail)
<a href="mailto:hi@oohx.net" class="btn btn-s" style="justify-content:flex-start;gap:10px;border-radius:10px;height:44px;font-weight:600;text-decoration:none"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:16px;height:16px;flex-shrink:0"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg> Liên hệ OOHX: hi@oohx.net</a>
@endif
</div>
@if($screen->owner?->slug)
<a href="/owners/{{ $screen->owner->slug }}" style="display:block;margin-top:10px;font-size:12px;color:var(--bl);font-weight:600;text-decoration:none">Xem hồ sơ {{ $ownerName }} &rarr;</a>
@endif
</div>
</div>{{-- /detail-sidebar --}}
</div>{{-- /detail-layout --}}
</div>{{-- /w --}}

{{-- ── LOCATION SECTION (standalone, always visible — Leaflet pattern same as /map page) ── --}}
@if($screen->site?->lat && $screen->site?->lon)
<section class="loc-section">
    <div class="w">
        <div class="loc-head">
            <div>
                <div class="loc-eyebrow">VỊ TRÍ TRÊN BẢN ĐỒ</div>
                <h2 class="loc-title">{{ $screen->site->name ?? $screen->name }}</h2>
                <div class="loc-addr">
                    <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;flex-shrink:0;color:var(--bl)"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    {{ $screen->site->address ?? '' }}{{ $screen->site->address && $screen->site->city ? ', ' : '' }}{{ $screen->site->city ?? '' }}
                </div>
            </div>
            <div class="loc-meta">
                @if(! empty($nearbyPois))
                    <span class="loc-poi-count"><strong>{{ count($nearbyPois) }}</strong> POI lân cận đã phân tích</span>
                @endif
                <a href="https://www.openstreetmap.org/?mlat={{ $screen->site->lat }}&mlon={{ $screen->site->lon }}#map=17/{{ $screen->site->lat }}/{{ $screen->site->lon }}" target="_blank" rel="noopener" class="loc-osm-link">
                    Mở trên OpenStreetMap →
                </a>
            </div>
        </div>

        <div id="loc-map"></div>

        <div class="loc-legend">
            <span class="loc-leg-i"><span class="loc-leg-dot loc-leg-dot--screen"></span> Vị trí màn hình quảng cáo</span>
            @if(! empty($nearbyPois))
                <span class="loc-leg-i"><span class="loc-leg-dot loc-leg-dot--poi"></span> Điểm POI lân cận (cafe, trường, ngân hàng, mall, …)</span>
            @endif
        </div>
    </div>
</section>
@endif

<div class="similar-section"><div class="w"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:20px"><div><div style="font-size:13px;font-weight:700;color:var(--bl);margin-bottom:6px">GỢI Ý THÊM</div><h2 style="font-size:clamp(20px,3vw,28px);font-weight:800;color:var(--t1);letter-spacing:-.5px">Inventory tương tự</h2></div><a href="{{ route('fp.listing') }}" class="btn btn-s btn-sm" style="margin-top:6px;flex-shrink:0">Xem tất cả <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:14px;height:14px;flex-shrink:0"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></a></div><div class="inv-grid">@foreach($similarScreens as $screen)@include('frontpage.partials.screen-card', ['screen' => $screen, 'compact' => true])@endforeach</div></div></div>
@endsection

@push('scripts')
<script>
// Tab switching
function sw(el,id){
  document.querySelectorAll(".tab").forEach(t=>t.classList.remove("on"));
  document.querySelectorAll(".tp").forEach(p=>p.classList.remove("on"));
  el.classList.add("on");
  document.getElementById(id).classList.add("on");
}

(function(){
  // Save/Bookmark button
  var sb=document.getElementById("savebtn");
  @if($isSaved) if(sb){sb.classList.add("on");sb.querySelector("svg").setAttribute("fill","var(--red)");} @endif
  if(sb)sb.addEventListener("click",function(){
    @auth
    fetch('/save/{{ $screen->id }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}})
      .then(function(r){return r.json()})
      .then(function(d){
        sb.classList.toggle("on",d.saved);
        sb.querySelector("svg").setAttribute("fill",d.saved?"var(--red)":"var(--t3)");
      });
    @else
    window.location.href='{{ route("login") }}';
    @endauth
  });

  // Thumbnail click → switch main image
  document.querySelectorAll('.gal-thumb[data-src]').forEach(function(thumb){
    thumb.addEventListener('click', function(){
      var mainImg = document.getElementById('gal-main-img');
      if (mainImg) mainImg.src = thumb.dataset.src;
      document.querySelectorAll('.gal-thumb').forEach(function(t){ t.classList.remove('gal-thumb-on'); });
      thumb.classList.add('gal-thumb-on');
    });
  });

  // Lightbox
  @if(count($photos) > 0)
  var lbPhotos = @json($photos);
  var lbIdx = 0;
  var lbOverlay = document.getElementById('lb-overlay');
  var lbImg = document.getElementById('lb-img');
  var lbCounter = document.getElementById('lb-counter');
  function lbShow(idx){
    lbIdx = ((idx % lbPhotos.length) + lbPhotos.length) % lbPhotos.length;
    lbImg.src = lbPhotos[lbIdx];
    lbCounter.textContent = (lbIdx+1) + ' / ' + lbPhotos.length;
    lbOverlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  function lbHide(){ lbOverlay.style.display='none'; document.body.style.overflow=''; }
  document.getElementById('lb-close').addEventListener('click', lbHide);
  document.getElementById('lb-prev').addEventListener('click', function(){ lbShow(lbIdx-1); });
  document.getElementById('lb-next').addEventListener('click', function(){ lbShow(lbIdx+1); });
  lbOverlay.addEventListener('click', function(e){ if(e.target===lbOverlay) lbHide(); });
  document.addEventListener('keydown', function(e){
    if(lbOverlay.style.display==='none') return;
    if(e.key==='Escape') lbHide();
    if(e.key==='ArrowLeft') lbShow(lbIdx-1);
    if(e.key==='ArrowRight') lbShow(lbIdx+1);
  });
  // Click main image → open lightbox
  var mainImg = document.getElementById('gal-main-img');
  if(mainImg) mainImg.style.cursor='pointer';
  if(mainImg) mainImg.addEventListener('click', function(){ lbShow(0); });
  // Click thumbnail → open lightbox at that index
  document.querySelectorAll('.gal-thumb[data-src]').forEach(function(thumb,i){
    thumb.style.cursor='pointer';
    thumb.addEventListener('dblclick', function(){ lbShow(i); });
  });
  @endif

  // Share button — copy URL
  var shareBtn = document.querySelectorAll('.gal-btn')[1];
  if (shareBtn) shareBtn.addEventListener('click', function(){
    if (navigator.share) {
      navigator.share({title:document.title, url:window.location.href});
    } else {
      navigator.clipboard.writeText(window.location.href).then(function(){
        shareBtn.style.background='var(--bl-lt)';
        setTimeout(function(){shareBtn.style.background='';},1500);
      });
    }
  });

  // ── Booking calculator with mode switching ──
  // CHỈ chạy khi panel booking thực sự render (price > 0). Khi price=0, panel quote
  // hiện thay → các DOM id (bp-calc-*) không tồn tại → tránh TypeError halt JS.
  @if($hasPrice)
  var ioRate = {{ $ioRate }};
  var cpmRate = {{ $cpmRate }};
  var rateUnit = '{{ $ioRateUnit }}';
  var unitLabel = rateUnit === 'week' ? 'tuần' : 'tháng';
  var currentMode = '{{ $defMode }}';

  var startInp = document.getElementById('bp-start');
  var hStart = document.getElementById('bp-h-start');
  var hEnd = document.getElementById('bp-h-end');
  var hMode = document.getElementById('bp-h-mode');
  var ioFields = document.getElementById('bp-io-fields');
  var cpmFields = document.getElementById('bp-cpm-fields');
  var durSel = document.getElementById('bp-duration');
  var screensInp = document.getElementById('bp-screens');
  var cpmsInp = document.getElementById('bp-cpms');
  var hScreens = document.getElementById('bp-h-screens');
  var hDunits = document.getElementById('bp-h-dunits');
  var hCpms = document.getElementById('bp-h-cpms');

  function fmtVND(n){ return n.toLocaleString('vi-VN'); }
  function fmtPrice(n){ return n >= 1e6 ? (n/1e6).toFixed(1).replace('.0','') + 'M' : fmtVND(n); }
  function addMonths(d,m){ var x=new Date(d);x.setMonth(x.getMonth()+m);return x.toISOString().slice(0,10); }
  function addWeeks(d,w){ var x=new Date(d);x.setDate(x.getDate()+w*7);return x.toISOString().slice(0,10); }

  function updateCalc(){
    if(hStart && startInp) hStart.value = startInp.value;
    if(hMode) hMode.value = currentMode;
    if(currentMode === 'cpm'){
      var cpms = parseInt(cpmsInp?.value) || 1;
      var sub = cpmRate * cpms;
      document.getElementById('bp-calc-label').textContent = fmtVND(cpmRate) + ' ₫ × ' + fmtVND(cpms) + ' CPM';
      document.getElementById('bp-calc-sub').textContent = fmtVND(sub) + ' ₫';
      document.getElementById('bp-calc-vat').textContent = fmtVND(Math.round(sub * 0.1)) + ' ₫';
      document.getElementById('bp-calc-total').textContent = fmtVND(Math.round(sub * 1.1)) + ' ₫';
      if(hCpms) hCpms.value = cpms;
    } else {
      var units = parseInt(durSel?.value) || 1;
      var screens = parseInt(screensInp?.value) || 1;
      var sub = ioRate * screens * units;
      document.getElementById('bp-calc-label').textContent = fmtPrice(ioRate) + ' × ' + screens + ' mh × ' + units + ' ' + unitLabel;
      document.getElementById('bp-calc-sub').textContent = fmtVND(sub) + ' ₫';
      document.getElementById('bp-calc-vat').textContent = fmtVND(Math.round(sub * 0.1)) + ' ₫';
      document.getElementById('bp-calc-total').textContent = fmtVND(Math.round(sub * 1.1)) + ' ₫';
      if(hScreens) hScreens.value = screens;
      if(hDunits) hDunits.value = units;
      if(hEnd && startInp) hEnd.value = rateUnit === 'week' ? addWeeks(startInp.value, units) : addMonths(startInp.value, units);
    }
  }

  function switchMode(mode){
    currentMode = mode;
    if(ioFields) ioFields.style.display = mode === 'io' ? '' : 'none';
    if(cpmFields) cpmFields.style.display = mode === 'cpm' ? '' : 'none';
    // Toggle button styling
    document.querySelectorAll('.bp-tog-btn').forEach(function(b){
      b.classList.toggle('bp-tog-on', b.dataset.mode === mode);
    });
    updateCalc();
  }

  // Mode toggle (for 'both' pricing_model)
  document.querySelectorAll('.bp-tog-btn').forEach(function(btn){
    btn.addEventListener('click', function(){ switchMode(this.dataset.mode); });
  });

  // Input listeners
  if(durSel) durSel.addEventListener('change', updateCalc);
  if(screensInp) screensInp.addEventListener('input', updateCalc);
  if(cpmsInp) cpmsInp.addEventListener('input', updateCalc);
  if(startInp) startInp.addEventListener('change', updateCalc);

  // Initial calculation
  updateCalc();
  @endif

})();
</script>

@if($screen->site?->lat && $screen->site?->lon)
{{-- Leaflet — pattern giống /map page (init NGAY, container always visible, không tab logic) --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function(){
  if (typeof L === 'undefined') {
    console.warn('[loc-map] Leaflet không load — kiểm tra CDN');
    return;
  }

  var LAT  = {{ (float)$screen->site->lat }};
  var LNG  = {{ (float)$screen->site->lon }};
  var POIS = @json($nearbyPois ?? []);

  var POI_STYLE = {
    cafe:{c:'#8B4513',e:'☕'}, restaurant:{c:'#E67E22',e:'🍴'}, fast_food:{c:'#E74C3C',e:'🍔'},
    bar:{c:'#9B59B6',e:'🍺'}, school:{c:'#3498DB',e:'🎓'}, university:{c:'#2980B9',e:'🏛'},
    kindergarten:{c:'#3498DB',e:'🎓'}, hospital:{c:'#E74C3C',e:'🏥'}, clinic:{c:'#E91E63',e:'⚕'},
    pharmacy:{c:'#27AE60',e:'💊'}, bank:{c:'#16A085',e:'🏦'}, atm:{c:'#16A085',e:'💳'},
    fuel:{c:'#F39C12',e:'⛽'}, parking:{c:'#7F8C8D',e:'🅿'}, cinema:{c:'#9B59B6',e:'🎬'},
    hotel:{c:'#34495E',e:'🏨'}, mall:{c:'#E67E22',e:'🛍'}, supermarket:{c:'#27AE60',e:'🛒'},
    convenience:{c:'#27AE60',e:'🏪'}, clothes:{c:'#E91E63',e:'👕'}, gym:{c:'#16A085',e:'💪'},
    fitness_centre:{c:'#16A085',e:'💪'}, bus_station:{c:'#3498DB',e:'🚌'}, station:{c:'#3498DB',e:'🚏'},
    park:{c:'#27AE60',e:'🌳'}, office:{c:'#34495E',e:'🏢'}, company:{c:'#34495E',e:'🏢'},
  };

  var map = L.map('loc-map', {
    center: [LAT, LNG], zoom: 17,
    zoomControl: true, scrollWheelZoom: false,
    preferCanvas: true,
  });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:'&copy; OpenStreetMap', maxZoom:19,
  }).addTo(map);

  POIS.forEach(function(p){
    var s = POI_STYLE[p.cat] || {c:'#7F8C8D', e:'📍'};
    var ic = L.divIcon({
      className:'poi-dot',
      html:'<div class="poi-dot-inner" style="background:'+s.c+'">'+s.e+'</div>',
      iconSize:[22,22], iconAnchor:[11,11],
    });
    L.marker([p.lat, p.lon], {icon:ic, zIndexOffset:100})
      .addTo(map)
      .bindPopup('<div style="font-weight:600;font-size:12px">'+p.name+'</div>'
               + '<div style="font-size:11px;color:#888;margin-top:2px">'+(p.cat||'')+'</div>');
  });

  var screenIcon = L.divIcon({
    className:'screen-pin',
    html:'<div class="screen-pin-inner">📺</div><div class="screen-pin-pulse"></div>',
    iconSize:[44,44], iconAnchor:[22,22],
  });
  L.marker([LAT, LNG], {icon:screenIcon, zIndexOffset:1000})
    .addTo(map)
    .bindPopup('<b>{!! addslashes($screen->name) !!}</b><br>{!! addslashes($screen->site->address ?? "") !!}');

  console.log('[loc-map] OK — ' + POIS.length + ' POI markers');
})();
</script>
@endif
@endpush
