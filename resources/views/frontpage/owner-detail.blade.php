@extends('frontpage.layouts.app', ['activeNav' => 'owners'])

@section('title', ($owner->name ?? 'Chi tiết Media Owner') . ' | OOHX')

@php
    $initials = strtoupper(collect(explode(' ', $owner->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join(''));
    $defaultCover = 'https://placehold.co/1200x460/E0E0E5/8E8E93?text=' . urlencode($owner->name);
@endphp

@push('head')
<style>
/* ── Facebook-style Page Layout ─────────────────────────── */

/* Cover */
.fp-cover { position:relative; width:100%; }
.fp-cover-img { width:100%; height:clamp(200px,28vw,360px); object-fit:cover; display:block; background:var(--bg2); }

/* Avatar overlaps cover bottom-left */
.fp-cover-inner { position:relative; max-width:var(--mw); margin:0 auto; height:100%; }
.fp-avatar-wrap { position:absolute; bottom:-40px; left:24px; z-index:3; }
.fp-avatar { width:140px; height:140px; border-radius:50%; border:5px solid var(--bg1); overflow:hidden; background:linear-gradient(135deg,#1D1D1F,#3A3A3C); display:flex; align-items:center; justify-content:center; font-size:40px; font-weight:800; color:#fff; }
.fp-avatar img { width:100%; height:100%; object-fit:cover; }

/* Info row beside avatar */
.fp-header { max-width:var(--mw); margin:0 auto; padding:0 24px; display:flex; align-items:flex-end; gap:20px; min-height:56px; }
.fp-header-spacer { width:148px; flex-shrink:0; } /* avatar width + gap */
.fp-header-info { flex:1; min-width:0; padding-top:8px; }
.fp-name { font-size:clamp(22px,3vw,30px); font-weight:900; color:var(--t1); line-height:1.2; }
.fp-tagline { font-size:14px; color:var(--t3); margin-top:2px; }
.fp-meta { display:flex; flex-wrap:wrap; gap:4px 14px; margin-top:6px; font-size:13px; color:var(--t3); }
.fp-meta-item { display:inline-flex; align-items:center; gap:4px; }
.fp-meta-item svg { width:14px; height:14px; fill:var(--t4); flex-shrink:0; }
.fp-header-actions { display:flex; gap:8px; flex-shrink:0; padding-bottom:4px; }

/* Divider + Tabs */
.fp-nav-wrap { max-width:var(--mw); margin:16px auto 0; padding:0 24px; border-bottom:1px solid var(--ln2); overflow:hidden; }
.fp-tabs { display:flex; gap:0; overflow-x:auto; overflow-y:hidden; scrollbar-width:none; }
.fp-tabs::-webkit-scrollbar { display:none; }
.fp-tab { padding:12px 16px; font-size:15px; font-weight:600; color:var(--t3); cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-1px; white-space:nowrap; transition:color .15s; border-radius:8px 8px 0 0; }
.fp-tab:hover { background:var(--bg2); color:var(--t1); }
.fp-tab.on { color:var(--bl); border-bottom-color:var(--bl); }

/* Content area */
.fp-content { max-width:var(--mw); margin:0 auto; padding:20px 24px; }
.fp-panel { display:none; }
.fp-panel.on { display:block; }

/* ── Tab: Tổng quan (2-column like FB) ──────────────────── */
.fp-overview { display:grid; grid-template-columns:340px 1fr; gap:20px; align-items:start; }
.fp-sidebar { display:flex; flex-direction:column; gap:16px; }
.fp-card { background:var(--bg1); border-radius:12px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,.06); border:1px solid var(--ln2); }
.fp-card-title { font-size:16px; font-weight:700; color:var(--t1); margin-bottom:12px; }
.fp-info-row { display:flex; align-items:flex-start; gap:10px; padding:6px 0; }
.fp-info-row svg { width:16px; height:16px; fill:var(--t4); flex-shrink:0; margin-top:2px; }
.fp-info-label { font-size:13px; color:var(--t3); }
.fp-info-val { font-size:14px; color:var(--t1); font-weight:500; }
.fp-info-val a { color:var(--bl); text-decoration:none; }
.fp-info-val a:hover { text-decoration:underline; }

.fp-stats-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
.fp-stat { background:var(--bg2); border-radius:10px; padding:12px; text-align:center; }
.fp-stat-n { font-size:20px; font-weight:800; color:var(--t1); }
.fp-stat-n .u { font-size:11px; font-weight:500; color:var(--t4); }
.fp-stat-l { font-size:11px; color:var(--t4); margin-top:2px; }

.fp-venue-tags { display:flex; flex-wrap:wrap; gap:6px; }
.fp-venue-tag { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; background:var(--bl-lt); color:var(--bl); }

/* ── Tab: Inventory ─────────────────────────────────────── */
.fp-inv-filter { margin-bottom:20px; }
.fp-inv-header { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width:768px) {
    .fp-cover-img { border-radius:0; height:clamp(160px,30vw,220px); }
    .fp-avatar-wrap { left:50%; transform:translateX(-50%); bottom:-32px; }
    .fp-avatar { width:100px; height:100px; font-size:28px; border-width:4px; }
    .fp-header { flex-direction:column; align-items:center; text-align:center; padding-top:40px; }
    .fp-header-spacer { display:none; }
    .fp-header-actions { justify-content:center; }
    .fp-meta { justify-content:center; }
    .fp-overview { grid-template-columns:1fr; }
    .fp-nav-wrap { padding:0 16px; }
    .fp-content { padding:16px; }
    .fp-stats-grid { grid-template-columns:repeat(3,1fr); }
}
@media (max-width:480px) {
    .fp-avatar { width:80px; height:80px; font-size:22px; }
    .fp-header { padding-top:36px; }
    .fp-name { font-size:20px; }
    .fp-stats-grid { grid-template-columns:repeat(2,1fr); }
    .fp-header-actions { flex-direction:column; width:100%; }
    .fp-header-actions .btn { justify-content:center; }
}
</style>
@endpush

@section('content')

{{-- ═══ Cover + Avatar ═══ --}}
<div class="fp-cover">
    <img class="fp-cover-img" src="{{ $owner->cover_url ? asset('storage/' . $owner->cover_url) : $defaultCover }}" alt="{{ $owner->name }}">
    <div class="fp-cover-inner"><div class="fp-avatar-wrap">
        <div class="fp-avatar">
            @if($owner->logo_url)
                <img src="{{ asset('storage/' . $owner->logo_url) }}" alt="{{ $owner->name }}">
            @else
                {{ $initials }}
            @endif
        </div>
    </div>
    </div>{{-- /fp-cover-inner --}}
</div>

{{-- ═══ Header: Name + Meta + Actions ═══ --}}
<div class="fp-header">
    <div class="fp-header-spacer"></div>
    <div class="fp-header-info">
        <div class="fp-name">{{ $owner->name }}</div>
        @if($owner->tagline)
            <div class="fp-tagline">{{ $owner->tagline }}</div>
        @endif
        <div class="fp-meta">
            <span class="fp-meta-item">
                <svg viewBox="0 0 24 24"><path d="M3 3v8h8V3H3zm6 6H5V5h4v4zm-6 4v8h8v-8H3zm6 6H5v-4h4v4zm4-16v8h8V3h-8zm6 6h-4V5h4v4zm-6 4v8h8v-8h-8zm6 6h-4v-4h4v4z"/></svg>
                {{ $owner->screen_count }} màn hình
            </span>
            <span class="fp-meta-item">
                <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 110-5 2.5 2.5 0 010 5z"/></svg>
                {{ $owner->city_count }} tỉnh/thành
            </span>
            @if($owner->type)
            <span class="fp-meta-item">
                <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                {{ ucfirst(str_replace('_', ' ', $owner->type)) }}
            </span>
            @endif
        </div>
    </div>
    <div class="fp-header-actions">
        @if($owner->phone)
            <a href="tel:{{ $owner->phone }}" class="btn btn-s btn-sm">
                <svg viewBox="0 0 24 24" fill="var(--t2)" style="width:14px;height:14px"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                Liên hệ
            </a>
        @endif
        @if($owner->email)
            <a href="mailto:{{ $owner->email }}" class="btn btn-p btn-sm">
                <svg viewBox="0 0 24 24" fill="#fff" style="width:14px;height:14px"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                Gửi email
            </a>
        @endif
    </div>
</div>

{{-- ═══ Tab Navigation ═══ --}}
<div class="fp-nav-wrap">
    <div class="fp-tabs">
        <div class="fp-tab on" onclick="fpTab(this,'fp-overview')">Tổng quan</div>
        <div class="fp-tab" onclick="fpTab(this,'fp-inventory')">Màn hình <span style="font-weight:400;color:var(--t4)">({{ $owner->screen_count }})</span></div>
    </div>
</div>

{{-- ═══ Content Panels ═══ --}}
<div class="fp-content">

    {{-- Panel: Tổng quan (Facebook 2-column layout) --}}
    <div id="fp-overview" class="fp-panel on">
        <div class="fp-overview">

            {{-- Left sidebar --}}
            <div class="fp-sidebar">

                {{-- Card: Giới thiệu --}}
                <div class="fp-card">
                    <div class="fp-card-title">Giới thiệu</div>

                    @if($owner->about)
                        <div style="font-size:14px;color:var(--t2);line-height:1.6;margin-bottom:12px">{!! nl2br(e($owner->about)) !!}</div>
                    @endif

                    @if($owner->website)
                    <div class="fp-info-row">
                        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 6h-2.95a15.65 15.65 0 00-1.38-3.56A8.03 8.03 0 0118.92 8zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.1 13.36 4 12.69 4 12s.1-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2s.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56A7.987 7.987 0 015.08 16zm2.95-8H5.08a7.987 7.987 0 014.33-3.56A15.65 15.65 0 008.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66c-.09-.66-.16-1.32-.16-2s.07-1.35.16-2h4.68c.09.65.16 1.32.16 2s-.07 1.34-.16 2zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95a8.03 8.03 0 01-4.33 3.56zM16.36 14c.08-.66.14-1.32.14-2s-.06-1.34-.14-2h3.38c.16.64.26 1.31.26 2s-.1 1.36-.26 2h-3.38z"/></svg>
                        <div>
                            <div class="fp-info-val"><a href="{{ $owner->website }}" target="_blank">{{ preg_replace('#^https?://(www\.)?#', '', $owner->website) }}</a></div>
                            <div class="fp-info-label">Website</div>
                        </div>
                    </div>
                    @endif

                    @if($owner->email)
                    <div class="fp-info-row">
                        <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        <div>
                            <div class="fp-info-val"><a href="mailto:{{ $owner->email }}">{{ $owner->email }}</a></div>
                            <div class="fp-info-label">Email</div>
                        </div>
                    </div>
                    @endif

                    @if($owner->phone)
                    <div class="fp-info-row">
                        <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                        <div>
                            <div class="fp-info-val">{{ $owner->phone }}</div>
                            <div class="fp-info-label">Điện thoại</div>
                        </div>
                    </div>
                    @endif

                    @if($owner->founded)
                    <div class="fp-info-row">
                        <svg viewBox="0 0 24 24"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg>
                        <div>
                            <div class="fp-info-val">Từ {{ $owner->founded }}</div>
                            <div class="fp-info-label">Năm thành lập</div>
                        </div>
                    </div>
                    @endif

                    @php
                        $addressParts = array_filter([
                            $owner->address,
                            $owner->commune?->full_name,
                            $owner->province?->full_name,
                        ]);
                    @endphp
                    @if(!empty($addressParts))
                    <div class="fp-info-row">
                        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        <div>
                            <div class="fp-info-val">{{ implode(', ', $addressParts) }}</div>
                            <div class="fp-info-label">Trụ sở</div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Card: Thống kê --}}
                <div class="fp-card">
                    <div class="fp-card-title">Thống kê</div>
                    <div class="fp-stats-grid">
                        <div class="fp-stat">
                            <div class="fp-stat-n">{{ $owner->screen_count }}<span class="u">+</span></div>
                            <div class="fp-stat-l">Màn hình</div>
                        </div>
                        <div class="fp-stat">
                            <div class="fp-stat-n">{{ $owner->city_count }}</div>
                            <div class="fp-stat-l">Tỉnh/Thành</div>
                        </div>
                        <div class="fp-stat">
                            <div class="fp-stat-n">{{ count($owner->venue_types_list ?? []) }}</div>
                            <div class="fp-stat-l">Loại địa điểm</div>
                        </div>
                        @if($owner->founded)
                        <div class="fp-stat">
                            <div class="fp-stat-n">{{ now()->year - $owner->founded }}<span class="u"> năm</span></div>
                            <div class="fp-stat-l">Kinh nghiệm</div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Card: Loại địa điểm --}}
                @if(!empty($owner->venue_types_list))
                <div class="fp-card">
                    <div class="fp-card-title">Loại địa điểm</div>
                    <div class="fp-venue-tags">
                        @foreach($owner->venue_types_list as $vt)
                            <span class="fp-venue-tag">{{ $vt }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Right: Featured screens (carousel) --}}
            <div style="min-width:0">
                <div class="fp-card" style="padding:16px 0 16px 16px">
                    <div class="fp-card-title" style="padding-right:16px">Màn hình nổi bật</div>
                    <div class="hs">
                        @foreach($ownerScreens->take(6) as $screen)
                            @include('frontpage.partials.screen-card', ['screen' => $screen])
                        @endforeach
                    </div>
                    @if($ownerScreens->total() > 6)
                        <div style="text-align:center;margin-top:12px;padding-right:16px">
                            <a href="javascript:void(0)" onclick="fpTab(document.querySelectorAll('.fp-tab')[1],'fp-inventory')" class="btn btn-s btn-sm" style="display:inline-flex">
                                Xem tất cả {{ $ownerScreens->total() }} màn hình
                            </a>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- Panel: Inventory (full list with mega search) --}}
    <div id="fp-inventory" class="fp-panel">
        <div class="fp-inv-filter">
            @include('frontpage.partials.mega-search', [
                'variant'          => 'compact',
                'filters'          => $filters,
                'locationsByRegion' => $locationsByRegion,
            ])
        </div>
        <div class="fp-inv-header">
            <div class="avail-badge"><div class="avail-dot"></div>{{ $ownerScreens->total() }} vị trí</div>
        </div>
        <div class="inv-grid">
            @foreach($ownerScreens as $screen)
                @include('frontpage.partials.screen-card', ['screen' => $screen])
            @endforeach
        </div>
        {{ $ownerScreens->withQueryString()->links('frontpage.partials.pagination') }}
    </div>

</div>

@endsection

@push('scripts')
<script>
function fpTab(el, id) {
    document.querySelectorAll('.fp-tab').forEach(function(t) { t.classList.remove('on'); });
    document.querySelectorAll('.fp-panel').forEach(function(p) { p.classList.remove('on'); });
    el.classList.add('on');
    document.getElementById(id).classList.add('on');
    // Update URL tab param
    var url = new URL(window.location.href);
    if (id === 'fp-inventory') { url.searchParams.set('tab', 'inventory'); }
    else { url.searchParams.delete('tab'); }
    history.replaceState(null, '', url.toString().replace(/%5B/gi,'[').replace(/%5D/gi,']'));
    window.scrollTo({ top: document.querySelector('.fp-nav-wrap').offsetTop - 60, behavior: 'smooth' });
}

// Auto-switch to inventory tab when filters are active or tab=inventory
(function(){
    var params = new URLSearchParams(window.location.search);
    var hasFilter = params.has('city[]') || params.has('network[]') || params.has('venue_type[]')
                 || params.has('region[]') || params.has('q') || params.get('tab') === 'inventory';
    if (hasFilter) {
        // Set tab param so mega-search preserves it
        if (!params.has('tab')) {
            history.replaceState(null, '', window.location.pathname + '?' + params.toString().replace(/%5B/gi,'[').replace(/%5D/gi,']') + (params.toString() ? '&' : '') + 'tab=inventory');
        }
        fpTab(document.querySelectorAll('.fp-tab')[1], 'fp-inventory');
    }

    // Mega Search JS
    @include('frontpage.partials.mega-search-js')
})();
</script>
@endpush
