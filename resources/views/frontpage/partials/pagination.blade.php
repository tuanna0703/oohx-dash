{{-- Custom pagination for frontpage — shows page numbers + ellipsis on all devices
     Usage: {{ $paginator->withQueryString()->links('frontpage.partials.pagination') }}
--}}
@if ($paginator->hasPages())
@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $window = 2; // pages around current

    // Build page list: 1 ... [current-2 .. current+2] ... last
    $pages = collect();

    // Always show page 1
    $pages->push(1);

    // Left ellipsis
    if ($current - $window > 2) {
        $pages->push('...');
    }

    // Pages around current
    for ($i = max(2, $current - $window); $i <= min($last - 1, $current + $window); $i++) {
        $pages->push($i);
    }

    // Right ellipsis
    if ($current + $window < $last - 1) {
        $pages->push('...');
    }

    // Always show last page (if > 1)
    if ($last > 1) {
        $pages->push($last);
    }

    // Deduplicate (in case current is near edges)
    $pages = $pages->unique()->values();
@endphp
<nav class="pg" role="navigation" aria-label="Pagination">
    <div class="pg-pages">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="pg-btn pg-disabled" aria-disabled="true">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="pg-btn" rel="prev" aria-label="Trang trước">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
            </a>
        @endif

        {{-- Page numbers --}}
        @foreach ($pages as $page)
            @if ($page === '...')
                <span class="pg-dots">...</span>
            @elseif ($page == $current)
                <span class="pg-num pg-active" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" class="pg-num" aria-label="Trang {{ $page }}">{{ $page }}</a>
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="pg-btn" rel="next" aria-label="Trang sau">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
            </a>
        @else
            <span class="pg-btn pg-disabled" aria-disabled="true">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
            </span>
        @endif
    </div>

    <div class="pg-info">Trang {{ $current }} / {{ $last }}</div>
</nav>
@endif
