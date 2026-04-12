<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
    <title>@yield('title', 'OOHX – Marketplace OOH/DOOH')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    @vite(['resources/css/frontpage.css', 'resources/js/frontpage.js'])
    @stack('head')
</head>
<body class="{{ $bodyClass ?? '' }}">
    @include('frontpage.partials.header')

    @yield('content')

    @if(empty($hideFooter))
        @include('frontpage.partials.footer')
    @endif

    @include('frontpage.partials.mobile-nav')

    <button class="stt" id="stt" aria-label="Scroll to top">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>
    </button>

    @stack('scripts')
</body>
</html>
