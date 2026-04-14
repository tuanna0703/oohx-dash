{{-- SEO Meta Tags Component
     Props:
       $seoTitle       — page title (required)
       $seoDescription — meta description (optional, auto-truncated to 160 chars)
       $seoImage       — og:image URL (optional)
       $seoUrl         — canonical URL (optional, defaults to current URL)
       $seoType        — og:type: website|article|product (optional, default: website)
       $seoJsonLd      — JSON-LD structured data array (optional)
--}}
@php
    $seoTitle       = $seoTitle ?? config('app.name', 'OOHX');
    $seoDescription = isset($seoDescription) ? Str::limit(strip_tags($seoDescription), 160) : 'OOHX - Marketplace quảng cáo ngoài trời OOH/DOOH hàng đầu Việt Nam. Tìm, so sánh và đặt booking billboard, LED, LCD trong vài phút.';
    $seoImage       = $seoImage ?? asset('images/og-default.jpg');
    $seoUrl         = $seoUrl ?? url()->current();
    $seoType        = $seoType ?? 'website';
@endphp

<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoUrl }}">

{{-- Open Graph --}}
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:site_name" content="OOHX">
<meta property="og:locale" content="vi_VN">

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

{{-- JSON-LD Structured Data --}}
@if(!empty($seoJsonLd))
<script type="application/ld+json">{!! json_encode($seoJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endif
