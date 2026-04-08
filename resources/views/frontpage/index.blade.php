@extends('frontpage.layouts.app', ['activeNav' => 'home'])

@section('title', 'OOHX – Marketplace OOH/DOOH')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>@media(min-width:768px){#login-btn,#signup-btn{display:inline-flex!important}}</style>
@endpush

@section('content')

<section class="hero">
  <div class="w" style="text-align:center">
    <div style="animation:fade-up .8s var(--ease) both">
      <div class="hero-badge" style="display:inline-flex">
        <div class="hero-badge-dot"></div>
        {{ number_format($stats['total_screens']) }} inventory đang mở đặt lịch
      </div>
    </div>

    <div class="hero-text">
      <h1 class="hero-hed" style="margin-bottom:18px">
        Marketplace OOH/DOOH<br>
        <span class="acc">thông minh nhất Việt Nam.</span>
      </h1>
    </div>

    <p class="hero-desc">
      Tìm, so sánh và đặt booking billboard · LED · LCD trong vài phút. Dữ liệu traffic xác thực, AI hỗ trợ lên plan, xác nhận trong 48h.
    </p>

    <!-- Search -->
    <div class="search-wrap" id="search-wrap">

      <!-- Filter buttons row -->
      <div class="search-filters">
        <!-- Location filter -->
        <button class="sf-btn" id="sf-loc" onclick="toggleDrop('loc')">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
          <span id="sf-loc-label">Vị trí</span>
          <span class="sf-badge" id="sf-loc-badge" style="display:none">0</span>
          <svg class="chv" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
        </button>
        <!-- Billboard type filter -->
        <button class="sf-btn" id="sf-type" onclick="toggleDrop('type')">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
          <span id="sf-type-label">Loại biển</span>
          <span class="sf-badge" id="sf-type-badge" style="display:none">0</span>
          <svg class="chv" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
        </button>
        <!-- Divider -->
        <div style="width:1px;height:32px;background:var(--ln2);align-self:center"></div>
        <!-- Active filter tags injected here -->
        <div id="active-tags" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"></div>
      </div>

      <!-- Text search row -->
      <div class="search-box">
        <div class="search-ai-tag">
          <span class="ai-pill">
            <svg viewBox="0 0 24 24" fill="var(--bl)" style="width:12px;height:12px;flex-shrink:0"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.28 6.28 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.19a8.16 8.16 0 0 0 4.77 1.52V6.27a4.85 4.85 0 0 1-1-.42z"/></svg>
            <div class="ai-blink"></div> AI
          </span>
        </div>
        <input class="search-input" id="q" type="text" placeholder="Tìm theo tên đường, tòa nhà, hoặc mô tả campaign…">
        <button class="search-btn">
          <svg viewBox="0 0 24 24" fill="#fff"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
          Tìm ngay
        </button>
      </div>

      <!-- ── LOCATION MEGA DROPDOWN ── -->
      <div class="mega-drop" id="drop-loc">
        <div class="mega-head">
          <div class="mega-head-title">Chọn vị trí</div>
          <span class="mega-head-clear" onclick="clearLoc()">Xoá tất cả</span>
        </div>
        <div class="mega-body">
          <div class="loc-cols">
            @foreach($locationsByRegion as $regionName => $provinces)
            <div class="loc-region">
              <div class="loc-region-title">{{ $regionName }}</div>
              @foreach($provinces as $p)
              <div class="loc-item" onclick="selectCity(this,'{{ $p['name'] }}')"><div class="loc-check"></div><span>{{ $p['name'] }}</span><span class="loc-count">{{ $p['count'] }}</span></div>
              @endforeach
            </div>
            @endforeach
          </div>
          <!-- District sub-panel (shown when city selected) -->
          <div class="loc-district-panel" id="dist-panel">
            <div class="loc-region-title" style="margin-bottom:10px" id="dist-panel-title">Chọn quận / huyện</div>
            <div class="loc-district-grid" id="dist-grid"></div>
          </div>
        </div>
        <div class="mega-foot">
          <button class="btn btn-s btn-sm" onclick="closeDrop()">Đóng</button>
          <button class="btn btn-p btn-sm" onclick="applyLoc()">
            <svg viewBox="0 0 24 24" fill="#fff" style="width:14px;height:14px"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            Áp dụng
          </button>
        </div>
      </div>

      <!-- ── BILLBOARD TYPE MEGA DROPDOWN ── -->
      <div class="mega-drop" id="drop-type">
        <div class="mega-head">
          <div class="mega-head-title">Chọn loại biển quảng cáo</div>
          <span class="mega-head-clear" onclick="clearType()">Xoá tất cả</span>
        </div>
        <div class="mega-body">
          <div class="type-grid" id="type-grid">
            @foreach($venueTypes->take(6) as $vt)
            <div class="type-card" onclick="toggleType(this,'{{ $vt['label'] }}')">
              <div class="type-icon"><svg viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px"><path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14z"/></svg></div>
              <div>
                <div class="type-name">{{ $vt['label'] }}</div>
                <div class="type-count">{{ $vt['count'] }} vị trí</div>
              </div>
            </div>
            @endforeach
          </div>
        </div>
        <div class="mega-foot">
          <button class="btn btn-s btn-sm" onclick="closeDrop()">Đóng</button>
          <button class="btn btn-p btn-sm" onclick="applyType()">
            <svg viewBox="0 0 24 24" fill="#fff" style="width:14px;height:14px"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            Áp dụng
          </button>
        </div>
      </div>
    </div><!-- /search-wrap -->

    <!-- Backdrop -->
    <div class="drop-backdrop" id="backdrop" onclick="closeDrop()"></div>

    <div class="search-chips">
      <span class="schip" onclick="setQ('LED Hà Nội premium')">LED Hà Nội premium</span>
      <span class="schip" onclick="setQ('Mall TP.HCM')">Mall TP.HCM</span>
      <span class="schip" onclick="setQ('Transit sân bay')">Transit sân bay</span>
      <span class="schip" onclick="setQ('Billboard tháng 6')">Billboard tháng 6</span>
    </div>

    <!-- CTA buttons -->
    <div class="hero-actions" style="margin-top:28px">
      <a href="{{ route('fp.listing') }}" class="btn btn-p btn-lg"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:18px;height:18px;flex-shrink:0"><path d="M3 3v8h8V3H3zm6 6H5V5h4v4zm-6 4v8h8v-8H3zm6 6H5v-4h4v4zm4-16v8h8V3h-8zm6 6h-4V5h4v4zm-6 4v8h8v-8h-8zm6 6h-4v-4h4v4z"/></svg> Khám phá Inventory</a>
      <a href="{{ route('fp.map') }}" class="btn btn-s btn-lg"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:18px;height:18px;flex-shrink:0"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg> Xem bản đồ</a>
    </div>
    <div class="hero-hint">Miễn phí đăng ký · Không phí môi giới · Xác nhận 48h</div>

    <!-- Stats -->
    <div class="hero-stats">
      <div class="hstat"><div class="hstat-n">{{ number_format($stats['total_screens'] / 1000, 1) }}<span class="unit">K+</span></div><div class="hstat-l">Inventory</div></div>
      <div class="hstat"><div class="hstat-n">{{ $stats['total_cities'] }}</div><div class="hstat-l">Tỉnh thành</div></div>
      <div class="hstat"><div class="hstat-n">{{ $stats['total_owners'] }}<span class="unit">+</span></div><div class="hstat-l">Media owners</div></div>
      <div class="hstat"><div class="hstat-n">48<span class="unit">h</span></div><div class="hstat-l">Confirm time</div></div>
    </div>
  </div>

  <!-- Browser mockup -->
  <div class="w" style="padding-bottom:0">
    <div class="hero-visual" style="position:relative;padding:0 20px">
      <!-- Floating card left -->
      <div class="hero-float hf-left" style="position:absolute;left:0;bottom:80px;z-index:2">
        <div class="hf-label">Live Impressions</div>
        <div class="hf-value" id="imp-n" style="color:var(--bl)">120,847</div>
        <div class="hf-sub">LED Láng Hạ — hôm nay</div>
        <div class="hf-bar" style="margin-top:10px">
          <div class="hf-bar-item" style="height:20px;background:rgba(42,79,246,.15)"></div>
          <div class="hf-bar-item" style="height:32px;background:rgba(42,79,246,.25)"></div>
          <div class="hf-bar-item" style="height:26px;background:rgba(42,79,246,.2)"></div>
          <div class="hf-bar-item" style="height:38px;background:var(--bl)"></div>
          <div class="hf-bar-item" style="height:42px;background:var(--bl)"></div>
          <div class="hf-bar-item" style="height:34px;background:rgba(0,113,227,.6)"></div>
          <div class="hf-bar-item" style="height:44px;background:var(--bl)"></div>
        </div>
      </div>

      <!-- Floating card right -->
      <div class="hero-float hf-right" style="position:absolute;right:0;top:60px;z-index:2">
        <div class="hf-label">AI Match</div>
        <div class="hf-value" style="color:var(--grn);font-size:28px">94%</div>
        <div class="hf-sub" style="margin-top:6px">phù hợp với campaign</div>
        <div style="margin-top:8px;font-size:11px;color:var(--t4)">12 vị trí đề xuất</div>
      </div>

      <div class="hero-browser">
        <div class="hb-bar">
          <div class="hb-dots">
            <div class="hb-dot" style="background:#FF5F57"></div>
            <div class="hb-dot" style="background:#FEBC2E"></div>
            <div class="hb-dot" style="background:#28C840"></div>
          </div>
          <div class="hb-url">oohx.net</div>
        </div>
        <div class="hb-screen">
          <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA4MDAgNTAwIiB3aWR0aD0iODAwIiBoZWlnaHQ9IjUwMCI+CiAgPGRlZnM+CiAgICA8bGluZWFyR3JhZGllbnQgaWQ9ImRhc2gtYmciIHgxPSIwIiB5MT0iMCIgeDI9IjEiIHkyPSIxIj4KICAgICAgPHN0b3Agb2Zmc2V0PSIwJSIgc3RvcC1jb2xvcj0iI0Y1RjVGNyIvPgogICAgICA8c3RvcCBvZmZzZXQ9IjEwMCUiIHN0b3AtY29sb3I9IiNGRkZGRkYiLz4KICAgIDwvbGluZWFyR3JhZGllbnQ+CiAgPC9kZWZzPgogIDxyZWN0IHdpZHRoPSI4MDAiIGhlaWdodD0iNTAwIiBmaWxsPSJ1cmwoI2Rhc2gtYmcpIi8+CiAgPCEtLSBTaWRlYmFyIC0tPgogIDxyZWN0IHg9IjAiIHk9IjAiIHdpZHRoPSIxODAiIGhlaWdodD0iNTAwIiBmaWxsPSIjMUQxRDFGIi8+CiAgPCEtLSBTaWRlYmFyIGxvZ28gYXJlYSAtLT4KICA8cmVjdCB4PSIxNiIgeT0iMTYiIHdpZHRoPSIxNDgiIGhlaWdodD0iMzYiIHJ4PSI4IiBmaWxsPSIjMkMyQzJFIi8+CiAgPHJlY3QgeD0iMjgiIHk9IjI1IiB3aWR0aD0iNjAiIGhlaWdodD0iMTYiIHJ4PSI0IiBmaWxsPSIjMDA3MUUzIi8+CiAgPHJlY3QgeD0iOTYiIHk9IjI4IiB3aWR0aD0iNTAiIGhlaWdodD0iMTAiIHJ4PSIzIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuNSkiLz4KICA8IS0tIFNpZGViYXIgbmF2IGl0ZW1zIC0tPgogIDxnPgogICAgPHJlY3QgeD0iMTAiIHk9IjY4IiB3aWR0aD0iMTYwIiBoZWlnaHQ9IjM2IiByeD0iOCIgZmlsbD0iIzAwNzFFMyIvPgogICAgPHJlY3QgeD0iMjgiIHk9Ijc5IiB3aWR0aD0iODAiIGhlaWdodD0iMTIiIHJ4PSIzIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuOSkiLz4KICAgIDxyZWN0IHg9IjEwIiB5PSIxMTIiIHdpZHRoPSIxNjAiIGhlaWdodD0iMzYiIHJ4PSI4IiBmaWxsPSJ0cmFuc3BhcmVudCIvPgogICAgPHJlY3QgeD0iMjgiIHk9IjEyMyIgd2lkdGg9IjcwIiBoZWlnaHQ9IjEyIiByeD0iMyIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjQpIi8+CiAgICA8cmVjdCB4PSIxMCIgeT0iMTU2IiB3aWR0aD0iMTYwIiBoZWlnaHQ9IjM2IiByeD0iOCIgZmlsbD0idHJhbnNwYXJlbnQiLz4KICAgIDxyZWN0IHg9IjI4IiB5PSIxNjciIHdpZHRoPSI4MCIgaGVpZ2h0PSIxMiIgcng9IjMiIGZpbGw9InJnYmEoMjU1LDI1NSwyNTUsMC40KSIvPgogICAgPHJlY3QgeD0iMTAiIHk9IjIwMCIgd2lkdGg9IjE2MCIgaGVpZ2h0PSIzNiIgcng9IjgiIGZpbGw9InRyYW5zcGFyZW50Ii8+CiAgICA8cmVjdCB4PSIyOCIgeT0iMjExIiB3aWR0aD0iNjUiIGhlaWdodD0iMTIiIHJ4PSIzIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuNCkiLz4KICAgIDxyZWN0IHg9IjEwIiB5PSIyNDQiIHdpZHRoPSIxNjAiIGhlaWdodD0iMzYiIHJ4PSI4IiBmaWxsPSJ0cmFuc3BhcmVudCIvPgogICAgPHJlY3QgeD0iMjgiIHk9IjI1NSIgd2lkdGg9Ijc1IiBoZWlnaHQ9IjEyIiByeD0iMyIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjQpIi8+CiAgPC9nPgogIDwhLS0gVXNlciBhdmF0YXIgYm90dG9tIC0tPgogIDxjaXJjbGUgY3g9IjI4IiBjeT0iNDcyIiByPSIxNiIgZmlsbD0iIzAwNzFFMyIvPgogIDxyZWN0IHg9IjU0IiB5PSI0NjQiIHdpZHRoPSI4MCIgaGVpZ2h0PSI4IiByeD0iMyIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjYpIi8+CiAgPHJlY3QgeD0iNTQiIHk9IjQ3NiIgd2lkdGg9IjU1IiBoZWlnaHQ9IjciIHJ4PSIzIiBmaWxsPSJyZ2JhKDI1NSwyNTUsMjU1LDAuMykiLz4KICA8IS0tIE1haW4gY29udGVudCAtLT4KICA8IS0tIEhlYWRlciAtLT4KICA8cmVjdCB4PSIxODAiIHk9IjAiIHdpZHRoPSI2MjAiIGhlaWdodD0iNTYiIGZpbGw9IiNmZmYiIC8+CiAgPHJlY3QgeD0iMTgwIiB5PSI1NiIgd2lkdGg9IjYyMCIgaGVpZ2h0PSIxIiBmaWxsPSIjRThFOEVEIi8+CiAgPHJlY3QgeD0iMjAwIiB5PSIxNiIgd2lkdGg9IjE1MCIgaGVpZ2h0PSIxMiIgcng9IjQiIGZpbGw9IiMxRDFEMUYiLz4KICA8cmVjdCB4PSIyMDAiIHk9IjM0IiB3aWR0aD0iMTAwIiBoZWlnaHQ9IjkiIHJ4PSIzIiBmaWxsPSIjQUVBRUIyIi8+CiAgPCEtLSBIZWFkZXIgcmlnaHQgYWN0aW9ucyAtLT4KICA8Y2lyY2xlIGN4PSI3MzAiIGN5PSIyOCIgcj0iMTYiIGZpbGw9IiNGNUY1RjciLz4KICA8cmVjdCB4PSI3MjAiIHk9IjIzIiB3aWR0aD0iMjAiIGhlaWdodD0iMTAiIHJ4PSIzIiBmaWxsPSIjQUVBRUIyIi8+CiAgPGNpcmNsZSBjeD0iNzY4IiBjeT0iMjgiIHI9IjE2IiBmaWxsPSIjMDA3MUUzIi8+CiAgPHJlY3QgeD0iNzU4IiB5PSIyMyIgd2lkdGg9IjIwIiBoZWlnaHQ9IjEwIiByeD0iMyIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjgpIi8+CiAgPCEtLSBNZXRyaWMgY2FyZHMgcm93IC0tPgogIDxnPgogICAgPCEtLSBDYXJkIDEgLS0+CiAgICA8cmVjdCB4PSIxOTYiIHk9IjcyIiB3aWR0aD0iMTM1IiBoZWlnaHQ9IjkwIiByeD0iMTIiIGZpbGw9IiNmZmYiIHN0cm9rZT0iI0U4RThFRCIvPgogICAgPHJlY3QgeD0iMjEwIiB5PSI4NiIgd2lkdGg9IjYwIiBoZWlnaHQ9IjgiIHJ4PSIzIiBmaWxsPSIjQUVBRUIyIi8+CiAgICA8cmVjdCB4PSIyMTAiIHk9IjEwMiIgd2lkdGg9IjkwIiBoZWlnaHQ9IjE4IiByeD0iNCIgZmlsbD0iIzFEMUQxRiIvPgogICAgPHJlY3QgeD0iMjEwIiB5PSIxMjgiIHdpZHRoPSI1MCIgaGVpZ2h0PSI3IiByeD0iMyIgZmlsbD0iIzM0Qzc1OSIvPgogICAgPCEtLSBDYXJkIDIgLS0+CiAgICA8cmVjdCB4PSIzMzkiIHk9IjcyIiB3aWR0aD0iMTM1IiBoZWlnaHQ9IjkwIiByeD0iMTIiIGZpbGw9IiNmZmYiIHN0cm9rZT0iI0U4RThFRCIvPgogICAgPHJlY3QgeD0iMzUzIiB5PSI4NiIgd2lkdGg9IjY1IiBoZWlnaHQ9IjgiIHJ4PSIzIiBmaWxsPSIjQUVBRUIyIi8+CiAgICA8cmVjdCB4PSIzNTMiIHk9IjEwMiIgd2lkdGg9Ijg1IiBoZWlnaHQ9IjE4IiByeD0iNCIgZmlsbD0iIzFEMUQxRiIvPgogICAgPHJlY3QgeD0iMzUzIiB5PSIxMjgiIHdpZHRoPSI1NSIgaGVpZ2h0PSI3IiByeD0iMyIgZmlsbD0iIzAwNzFFMyIvPgogICAgPCEtLSBDYXJkIDMgLS0+CiAgICA8cmVjdCB4PSI0ODIiIHk9IjcyIiB3aWR0aD0iMTM1IiBoZWlnaHQ9IjkwIiByeD0iMTIiIGZpbGw9IiNmZmYiIHN0cm9rZT0iI0U4RThFRCIvPgogICAgPHJlY3QgeD0iNDk2IiB5PSI4NiIgd2lkdGg9IjU1IiBoZWlnaHQ9IjgiIHJ4PSIzIiBmaWxsPSIjQUVBRUIyIi8+CiAgICA8cmVjdCB4PSI0OTYiIHk9IjEwMiIgd2lkdGg9Ijk1IiBoZWlnaHQ9IjE4IiByeD0iNCIgZmlsbD0iIzFEMUQxRiIvPgogICAgPHJlY3QgeD0iNDk2IiB5PSIxMjgiIHdpZHRoPSI0NSIgaGVpZ2h0PSI3IiByeD0iMyIgZmlsbD0iI0ZGOUYwQSIvPgogICAgPCEtLSBDYXJkIDQgLS0+CiAgICA8cmVjdCB4PSI2MjUiIHk9IjcyIiB3aWR0aD0iMTM1IiBoZWlnaHQ9IjkwIiByeD0iMTIiIGZpbGw9IiMwMDcxRTMiLz4KICAgIDxyZWN0IHg9IjYzOSIgeT0iODYiIHdpZHRoPSI2MCIgaGVpZ2h0PSI4IiByeD0iMyIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjcpIi8+CiAgICA8cmVjdCB4PSI2MzkiIHk9IjEwMiIgd2lkdGg9Ijg4IiBoZWlnaHQ9IjE4IiByeD0iNCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjk1KSIvPgogICAgPHJlY3QgeD0iNjM5IiB5PSIxMjgiIHdpZHRoPSI1MiIgaGVpZ2h0PSI3IiByeD0iMyIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjcpIi8+CiAgPC9nPgogIDwhLS0gQ2hhcnQgYXJlYSAtLT4KICA8cmVjdCB4PSIxOTYiIHk9IjE3NCIgd2lkdGg9IjQyMCIgaGVpZ2h0PSIyMDAiIHJ4PSIxMiIgZmlsbD0iI2ZmZiIgc3Ryb2tlPSIjRThFOEVEIi8+CiAgPHJlY3QgeD0iMjE0IiB5PSIxODgiIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAiIHJ4PSI0IiBmaWxsPSIjMUQxRDFGIi8+CiAgPHJlY3QgeD0iMjE0IiB5PSIyMDQiIHdpZHRoPSI3MCIgaGVpZ2h0PSI4IiByeD0iMyIgZmlsbD0iI0FFQUVCMiIvPgogIDwhLS0gQmFyIGNoYXJ0IC0tPgogIDxnPgogICAgPHJlY3QgeD0iMjI1IiB5PSIzMTAiIHdpZHRoPSIyOCIgaGVpZ2h0PSI1NSIgcng9IjQiIGZpbGw9IiNFOEYwRkQiLz4KICAgIDxyZWN0IHg9IjI2NSIgeT0iMjkwIiB3aWR0aD0iMjgiIGhlaWdodD0iNzUiIHJ4PSI0IiBmaWxsPSIjRThGMEZEIi8+CiAgICA8cmVjdCB4PSIzMDUiIHk9IjI3NSIgd2lkdGg9IjI4IiBoZWlnaHQ9IjkwIiByeD0iNCIgZmlsbD0iIzAwNzFFMyIgb3BhY2l0eT0iMC42Ii8+CiAgICA8cmVjdCB4PSIzNDUiIHk9IjI2MCIgd2lkdGg9IjI4IiBoZWlnaHQ9IjEwNSIgcng9IjQiIGZpbGw9IiMwMDcxRTMiIG9wYWNpdHk9IjAuNzUiLz4KICAgIDxyZWN0IHg9IjM4NSIgeT0iMjQ1IiB3aWR0aD0iMjgiIGhlaWdodD0iMTIwIiByeD0iNCIgZmlsbD0iIzAwNzFFMyIvPgogICAgPHJlY3QgeD0iNDI1IiB5PSIyNTUiIHdpZHRoPSIyOCIgaGVpZ2h0PSIxMTAiIHJ4PSI0IiBmaWxsPSIjMDA3MUUzIiBvcGFjaXR5PSIwLjg1Ii8+CiAgICA8cmVjdCB4PSI0NjUiIHk9IjI3MCIgd2lkdGg9IjI4IiBoZWlnaHQ9Ijk1IiByeD0iNCIgZmlsbD0iIzAwNzFFMyIgb3BhY2l0eT0iMC43Ii8+CiAgICA8cmVjdCB4PSI1MDUiIHk9IjI1OCIgd2lkdGg9IjI4IiBoZWlnaHQ9IjEwNyIgcng9IjQiIGZpbGw9IiMwMDcxRTMiIG9wYWNpdHk9IjAuOCIvPgogICAgPHJlY3QgeD0iNTQ1IiB5PSIyNDIiIHdpZHRoPSIyOCIgaGVpZ2h0PSIxMjMiIHJ4PSI0IiBmaWxsPSIjMzRDNzU5Ii8+CiAgPC9nPgogIDwhLS0gWCBheGlzIGxhYmVscyAtLT4KICA8ZyBmaWxsPSIjQUVBRUIyIiBmb250LWZhbWlseT0ic3lzdGVtLXVpIiBmb250LXNpemU9IjkiPgogICAgPHRleHQgeD0iMjMyIiB5PSIzODAiPlQxPC90ZXh0Pjx0ZXh0IHg9IjI3MiIgeT0iMzgwIj5UMjwvdGV4dD48dGV4dCB4PSIzMTIiIHk9IjM4MCI+VDM8L3RleHQ+CiAgICA8dGV4dCB4PSIzNTIiIHk9IjM4MCI+VDQ8L3RleHQ+PHRleHQgeD0iMzkyIiB5PSIzODAiPlQ1PC90ZXh0Pjx0ZXh0IHg9IjQzMiIgeT0iMzgwIj5UNjwvdGV4dD4KICAgIDx0ZXh0IHg9IjQ3MiIgeT0iMzgwIj5UNzwvdGV4dD48dGV4dCB4PSI1MTIiIHk9IjM4MCI+VDg8L3RleHQ+PHRleHQgeD0iNTUyIiB5PSIzODAiPlQ5PC90ZXh0PgogIDwvZz4KICA8IS0tIFJpZ2h0IHBhbmVsOiBpbnZlbnRvcnkgbGlzdCAtLT4KICA8cmVjdCB4PSI2MjUiIHk9IjE3NCIgd2lkdGg9IjEzNSIgaGVpZ2h0PSIyMDAiIHJ4PSIxMiIgZmlsbD0iI2ZmZiIgc3Ryb2tlPSIjRThFOEVEIi8+CiAgPHJlY3QgeD0iNjM5IiB5PSIxODgiIHdpZHRoPSI5MCIgaGVpZ2h0PSI5IiByeD0iMyIgZmlsbD0iIzFEMUQxRiIvPgogIDxnPgogICAgPHJlY3QgeD0iNjM5IiB5PSIyMTAiIHdpZHRoPSIxMDciIGhlaWdodD0iMzAiIHJ4PSI2IiBmaWxsPSIjRjVGNUY3Ii8+CiAgICA8cmVjdCB4PSI2NDciIHk9IjIxOCIgd2lkdGg9IjYwIiBoZWlnaHQ9IjciIHJ4PSIyIiBmaWxsPSIjM0EzQTNDIi8+CiAgICA8cmVjdCB4PSI2NDciIHk9IjIyOSIgd2lkdGg9IjQwIiBoZWlnaHQ9IjUiIHJ4PSIyIiBmaWxsPSIjQUVBRUIyIi8+CiAgICA8cmVjdCB4PSI3MTAiIHk9IjIxOCIgd2lkdGg9IjI4IiBoZWlnaHQ9IjE0IiByeD0iNCIgZmlsbD0icmdiYSg1MiwxOTksODksMC4xNSkiLz4KICAgIDxyZWN0IHg9IjcxNCIgeT0iMjIyIiB3aWR0aD0iMjAiIGhlaWdodD0iNiIgcng9IjIiIGZpbGw9IiMzNEM3NTkiLz4KICAgIAogICAgPHJlY3QgeD0iNjM5IiB5PSIyNDgiIHdpZHRoPSIxMDciIGhlaWdodD0iMzAiIHJ4PSI2IiBmaWxsPSIjRjVGNUY3Ii8+CiAgICA8cmVjdCB4PSI2NDciIHk9IjI1NiIgd2lkdGg9IjU1IiBoZWlnaHQ9IjciIHJ4PSIyIiBmaWxsPSIjM0EzQTNDIi8+CiAgICA8cmVjdCB4PSI2NDciIHk9IjI2NyIgd2lkdGg9IjQ1IiBoZWlnaHQ9IjUiIHJ4PSIyIiBmaWxsPSIjQUVBRUIyIi8+CiAgICA8cmVjdCB4PSI3MTAiIHk9IjI1NiIgd2lkdGg9IjI4IiBoZWlnaHQ9IjE0IiByeD0iNCIgZmlsbD0icmdiYSgyNTUsMTU5LDEwLDAuMTUpIi8+CiAgICA8cmVjdCB4PSI3MTQiIHk9IjI2MCIgd2lkdGg9IjIwIiBoZWlnaHQ9IjYiIHJ4PSIyIiBmaWxsPSIjRkY5RjBBIi8+CiAgICAKICAgIDxyZWN0IHg9IjYzOSIgeT0iMjg2IiB3aWR0aD0iMTA3IiBoZWlnaHQ9IjMwIiByeD0iNiIgZmlsbD0iI0Y1RjVGNyIvPgogICAgPHJlY3QgeD0iNjQ3IiB5PSIyOTQiIHdpZHRoPSI2NSIgaGVpZ2h0PSI3IiByeD0iMiIgZmlsbD0iIzNBM0EzQyIvPgogICAgPHJlY3QgeD0iNjQ3IiB5PSIzMDUiIHdpZHRoPSIzNSIgaGVpZ2h0PSI1IiByeD0iMiIgZmlsbD0iI0FFQUVCMiIvPgogICAgPHJlY3QgeD0iNzEwIiB5PSIyOTQiIHdpZHRoPSIyOCIgaGVpZ2h0PSIxNCIgcng9IjQiIGZpbGw9InJnYmEoNTIsMTk5LDg5LDAuMTUpIi8+CiAgICA8cmVjdCB4PSI3MTQiIHk9IjI5OCIgd2lkdGg9IjIwIiBoZWlnaHQ9IjYiIHJ4PSIyIiBmaWxsPSIjMzRDNzU5Ii8+CiAgICAKICAgIDxyZWN0IHg9IjYzOSIgeT0iMzI0IiB3aWR0aD0iMTA3IiBoZWlnaHQ9IjMwIiByeD0iNiIgZmlsbD0iI0Y1RjVGNyIvPgogICAgPHJlY3QgeD0iNjQ3IiB5PSIzMzIiIHdpZHRoPSI1MCIgaGVpZ2h0PSI3IiByeD0iMiIgZmlsbD0iIzNBM0EzQyIvPgogICAgPHJlY3QgeD0iNjQ3IiB5PSIzNDMiIHdpZHRoPSI0MiIgaGVpZ2h0PSI1IiByeD0iMiIgZmlsbD0iI0FFQUVCMiIvPgogICAgPHJlY3QgeD0iNzEwIiB5PSIzMzIiIHdpZHRoPSIyOCIgaGVpZ2h0PSIxNCIgcng9IjQiIGZpbGw9InJnYmEoMjU1LDU5LDQ4LDAuMTIpIi8+CiAgICA8cmVjdCB4PSI3MTQiIHk9IjMzNiIgd2lkdGg9IjIwIiBoZWlnaHQ9IjYiIHJ4PSIyIiBmaWxsPSIjRkYzQjMwIi8+CiAgPC9nPgogIDwhLS0gQm90dG9tIHRhYmxlIC0tPgogIDxyZWN0IHg9IjE5NiIgeT0iMzg0IiB3aWR0aD0iNTY0IiBoZWlnaHQ9IjEwMCIgcng9IjEyIiBmaWxsPSIjZmZmIiBzdHJva2U9IiNFOEU4RUQiLz4KICA8cmVjdCB4PSIyMTQiIHk9IjM5NiIgd2lkdGg9IjgwIiBoZWlnaHQ9IjkiIHJ4PSIzIiBmaWxsPSIjMUQxRDFGIi8+CiAgPCEtLSBUYWJsZSByb3dzIC0tPgogIDxnPgogICAgPHJlY3QgeD0iMjE0IiB5PSI0MTgiIHdpZHRoPSI1MjgiIGhlaWdodD0iMSIgZmlsbD0iI0U4RThFRCIvPgogICAgPHJlY3QgeD0iMjE0IiB5PSI0MTgiIHdpZHRoPSIxNjAiIGhlaWdodD0iMjUiIGZpbGw9InRyYW5zcGFyZW50Ii8+CiAgICA8cmVjdCB4PSIyMjAiIHk9IjQyNSIgd2lkdGg9IjEwMCIgaGVpZ2h0PSI3IiByeD0iMyIgZmlsbD0iIzNBM0EzQyIvPgogICAgPHJlY3QgeD0iNDIwIiB5PSI0MjUiIHdpZHRoPSI2MCIgaGVpZ2h0PSI3IiByeD0iMyIgZmlsbD0iIzM0Qzc1OSIvPgogICAgPHJlY3QgeD0iNTYwIiB5PSI0MjUiIHdpZHRoPSI3MCIgaGVpZ2h0PSI3IiByeD0iMyIgZmlsbD0iI0FFQUVCMiIvPgogICAgCiAgICA8cmVjdCB4PSIyMTQiIHk9IjQ0MyIgd2lkdGg9IjUyOCIgaGVpZ2h0PSIxIiBmaWxsPSIjRThFOEVEIi8+CiAgICA8cmVjdCB4PSIyMjAiIHk9IjQ1MCIgd2lkdGg9Ijg1IiBoZWlnaHQ9IjciIHJ4PSIzIiBmaWxsPSIjM0EzQTNDIi8+CiAgICA8cmVjdCB4PSI0MjAiIHk9IjQ1MCIgd2lkdGg9IjU1IiBoZWlnaHQ9IjciIHJ4PSIzIiBmaWxsPSIjRkY5RjBBIi8+CiAgICA8cmVjdCB4PSI1NjAiIHk9IjQ1MCIgd2lkdGg9IjY1IiBoZWlnaHQ9IjciIHJ4PSIzIiBmaWxsPSIjQUVBRUIyIi8+CiAgICAKICAgIDxyZWN0IHg9IjIyMCIgeT0iNDc1IiB3aWR0aD0iOTUiIGhlaWdodD0iNyIgcng9IjMiIGZpbGw9IiMzQTNBM0MiLz4KICAgIDxyZWN0IHg9IjQyMCIgeT0iNDc1IiB3aWR0aD0iNjUiIGhlaWdodD0iNyIgcng9IjMiIGZpbGw9IiMzNEM3NTkiLz4KICAgIDxyZWN0IHg9IjU2MCIgeT0iNDc1IiB3aWR0aD0iNzUiIGhlaWdodD0iNyIgcng9IjMiIGZpbGw9IiNBRUFFQjIiLz4KICA8L2c+CiAgPHRleHQgeD0iMjAwIiB5PSI0OTciIGZvbnQtZmFtaWx5PSJzeXN0ZW0tdWksc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxMSIgZmlsbD0icmdiYSgwLDAsMCwwLjIpIiBmb250LXdlaWdodD0iNjAwIj5PT0hYIMK3IEFnZW5jeSBEYXNoYm9hcmQ8L3RleHQ+Cjwvc3ZnPg==" alt="OOHX App">
        </div>
      </div>
    </div>
  </div>
</section>

<div class="trust">
  <div class="trust-in">
    <div class="trust-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>Verified inventory</div>
    <div class="trust-sep"></div>
    <div class="trust-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M20.38 8.57l-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44zm-9.79 6.84a2 2 0 0 0 2.83 0l5.66-8.49-8.49 5.66a2 2 0 0 0 0 2.83z"/></svg>Traffic real-time</div>
    <div class="trust-sep"></div>
    <div class="trust-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.28 6.28 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.19a8.16 8.16 0 0 0 4.77 1.52V6.27a4.85 4.85 0 0 1-1-.42z"/></svg>AI-powered search</div>
    <div class="trust-sep"></div>
    <div class="trust-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>Secure payment</div>
    <div class="trust-sep"></div>
    <div class="trust-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:18px;height:18px;flex-shrink:0"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>Agency support 24/7</div>
  </div>
</div>

<section class="sec rv">
  <div class="w">
    <div class="sec-head">
      <div class="sec-head-row">
        <div>
          <span class="eyebrow">INVENTORY TYPE</span>
          <h2 class="section-hed">Browse by Format</h2>
          <p class="section-sub" style="margin-top:10px;font-size:16px">Outdoor, indoor, digital hay static — tất cả trên một nền tảng.</p>
        </div>
        <a href="{{ route('fp.listing') }}" class="btn btn-s btn-sm" style="margin-top:6px;flex-shrink:0">Tất cả <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:14px;height:14px;flex-shrink:0"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></a>
      </div>
    </div>
    <div class="cat-grid">
      @foreach($venueTypes->take(5) as $vt)
      <a href="{{ route('fp.listing', ['venue_type' => $vt['type']]) }}" class="cat-card rv">
        <div class="cat-img"><div class="cat-img-ov"></div><div class="cat-count">{{ $vt['count'] }}</div></div>
        <div class="cat-body"><div class="cat-name">{{ $vt['label'] }}</div></div>
      </a>
      @endforeach
    </div>
  </div>
</section>

<section class="sec sec-gray rv">
  <div class="w">
    <div class="sec-head">
      <div class="sec-head-row">
        <div>
          <span class="eyebrow">MARKET COVERAGE</span>
          <h2 class="section-hed">{{ $stats['total_cities'] }} Tỉnh thành</h2>
          <p class="section-sub" style="margin-top:10px;font-size:16px">Chọn thị trường để bắt đầu lên plan.</p>
        </div>
        <a href="{{ route('fp.listing') }}" class="btn btn-s btn-sm" style="margin-top:6px;flex-shrink:0">All markets <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:14px;height:14px;flex-shrink:0"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></a>
      </div>
    </div>
    <div class="city-grid">
      @foreach($topCities as $i => $city)
      <a href="{{ route('fp.listing', ['city' => $city['code']]) }}" class="city-card{{ $i < 2 ? ' city-card--lg' : '' }} rv">
        <div class="city-img"><div class="city-ov"></div></div>
        <div class="city-info">
          <div class="city-name">{{ $city['name'] }}</div>
          <div class="city-meta"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255,255,255,.72)" style="width:12px;height:12px"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> {{ $city['count'] }} inventory</div>
        </div>
      </a>
      @endforeach
    </div>
  </div>
</section>

<section class="sec rv">
  <div class="w">
      <div class="sec-head">
        <div class="sec-head-row">
          <div>
            <span class="eyebrow">PREMIUM POSITIONS</span>
            <h2 class="section-hed">Đang còn trống</h2>
            <p class="section-sub" style="margin-top:10px;font-size:16px">Ảnh thực địa · Traffic data xác thực · Đặt booking nhanh</p>
          </div>
          <a href="{{ route('fp.listing') }}" class="btn btn-s btn-sm" style="margin-top:6px;flex-shrink:0">View all <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:14px;height:14px;flex-shrink:0"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></a>
        </div>
      </div>
    <div class="hs" style="margin-left:-22px;margin-right:-22px;padding-left:22px;padding-right:22px">
    @forelse($featuredScreens as $screen)
        @include('frontpage.partials.screen-card', ['screen' => $screen])
    @empty
        <div style="padding:40px;text-align:center;color:var(--t4)">Chưa có inventory</div>
    @endforelse
    </div>
  </div>
</section>

<section class="sec sec-gray rv">
  <div class="w">
    <div class="sec-head">
      <div class="sec-head-row">
        <div>
          <span class="eyebrow">GEO INTELLIGENCE</span>
          <h2 class="section-hed">Live Map View</h2>
          <p class="section-sub" style="margin-top:10px;font-size:16px">OOH là business của vị trí — xem thực tế trên bản đồ.</p>
        </div>
        <a href="{{ route('fp.map') }}" class="btn btn-p btn-sm" style="margin-top:6px;flex-shrink:0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:14px;height:14px;flex-shrink:0"><path d="M21 11V3h-8l3.29 3.29-10 10L3 13v8h8l-3.29-3.29 10-10z"/></svg> Open map</a>
      </div>
    </div>
    <div class="map-wrap">
      <div class="map-mock" id="hp-map-container">
        <div id="hp-leaflet-map" style="position:absolute;inset:0;z-index:0"></div>

        {{-- City picker bar --}}
        <div class="hp-map-city-bar" id="hp-city-bar">
          <button class="hmc-btn{{ ($mapData['citySlug'] ?? 'hanoi') === 'hanoi' ? ' on' : '' }}" data-city="hanoi" data-lat="21.0285" data-lng="105.8542" data-zoom="12">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg> Hà Nội
          </button>
          <button class="hmc-btn{{ ($mapData['citySlug'] ?? '') === 'hcm' ? ' on' : '' }}" data-city="hcm" data-lat="10.7769" data-lng="106.7009" data-zoom="12">TP.HCM</button>
          <button class="hmc-btn{{ ($mapData['citySlug'] ?? '') === 'danang' ? ' on' : '' }}" data-city="danang" data-lat="16.0544" data-lng="108.2022" data-zoom="13">Đà Nẵng</button>
          <button class="hmc-btn{{ ($mapData['citySlug'] ?? '') === 'haiphong' ? ' on' : '' }}" data-city="haiphong" data-lat="20.8449" data-lng="106.6881" data-zoom="13">Hải Phòng</button>
        </div>

        {{-- Popup overlay --}}
        <div class="hp-map-popup" id="hp-popup" style="display:none">
          <div class="hmpop-close" onclick="document.getElementById('hp-popup').style.display='none'">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:12px;height:12px"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
          </div>
          <div class="hmpop-img"><img id="hp-popup-img" src="" alt=""></div>
          <div class="hmpop-body">
            <div class="hmpop-name" id="hp-popup-name"></div>
            <div class="hmpop-meta" id="hp-popup-meta">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:11px;height:11px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
              <span id="hp-popup-city"></span>
            </div>
            <span class="badge b-grn" style="display:inline-flex;margin-bottom:6px;font-size:11px;padding:2px 8px">Available</span>
            <div class="hmpop-price" id="hp-popup-price"></div>
          </div>
          <div class="hmpop-action">
            <a href="#" id="hp-popup-link" class="btn btn-p btn-xs" style="border-radius:8px">Xem chi tiết</a>
          </div>
        </div>
      </div>

      <div class="map-bar">
        <div class="map-count"><strong id="hp-map-total">{{ number_format($mapData['total'] ?? 0) }}</strong> màn hình tại <span id="hp-map-city-label">{{ $mapData['cityName'] ?? 'Hà Nội' }}</span></div>
        <a href="{{ route('fp.map') }}" class="btn btn-s btn-xs" style="flex-shrink:0;border-radius:8px">Xem tất cả trên bản đồ →</a>
      </div>
    </div>
  </div>
</section>

<section class="sec rv">
  <div class="w">
    <div class="sec-head">
      <div class="sec-head-row">
        <div>
          <span class="eyebrow">VERIFIED PARTNERS</span>
          <h2 class="section-hed">Trusted Media Owners</h2>
          <p class="section-sub" style="margin-top:10px;font-size:16px">Chỉ làm việc với owner đã verified — inventory thật, data thật.</p>
        </div>
        <a href="{{ route('fp.owners') }}" class="btn btn-s btn-sm" style="margin-top:6px;flex-shrink:0">All owners <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t2)" style="width:14px;height:14px;flex-shrink:0"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></a>
      </div>
    </div>
    <div class="owner-grid">
    @foreach($featuredOwners as $owner)
        @include('frontpage.partials.owner-card-mini', ['owner' => $owner])
    @endforeach
    </div>
      </div>
</section>

<section class="sec sec-gray rv">
  <div class="w">
    <div style="text-align:center;max-width:560px;margin:0 auto 40px">
      <span class="eyebrow">WORKFLOW</span>
      <h2 class="section-hed">4 bước đặt booking OOH</h2>
      <p class="section-sub" style="margin:12px auto 0;font-size:16px">Không email qua lại. Tìm, kiểm tra, đặt và theo dõi trực tiếp.</p>
    </div>
    <div class="steps-grid" style="background:#fff;border-radius:var(--r4);border:1px solid var(--ln2);overflow:hidden;box-shadow:var(--sh2)">
      <div class="step-card rv"><div class="step-num" style="background:var(--bl)">01</div><div class="step-title">Search & Filter</div><div class="step-desc">AI search theo khu vực, loại biển, budget và availability. Map view tức thì.</div></div><div class="step-card rv"><div class="step-num" style="background:#34C759">02</div><div class="step-title">Compare</div><div class="step-desc">Ảnh thực địa, traffic data, availability calendar. So sánh tối đa 5 vị trí.</div></div><div class="step-card rv"><div class="step-num" style="background:#FF9F0A">03</div><div class="step-title">Submit Booking</div><div class="step-desc">Điền thông tin campaign, upload creative. Media owner nhận ngay, xử lý trong 48h.</div></div><div class="step-card rv"><div class="step-num" style="background:#AF52DE">04</div><div class="step-title">Track & Report</div><div class="step-desc">Dashboard theo dõi booking status + proof-of-play report tự động.</div></div>
    </div>
  </div>
</section>

<section class="sec rv">
  <div class="w">
    <div class="value-grid">
      <div>
        <span class="eyebrow">AI-POWERED PLATFORM</span>
        <h2 class="section-hed" style="margin-top:8px;margin-bottom:12px">Marketplace OOH đầu tiên có AI</h2>
        <p class="section-sub" style="margin-bottom:32px;font-size:16px">Kết nối agency, brand và media owner — nhanh hơn, minh bạch hơn, đo được kết quả.</p>
        <div class="ai-cards"><div class="ai-card rv"><div class="ai-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:20px;height:20px;flex-shrink:0"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.28 6.28 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.19a8.16 8.16 0 0 0 4.77 1.52V6.27a4.85 4.85 0 0 1-1-.42z"/></svg></div><div><div class="ai-card-title">AI Campaign Match</div><div class="ai-card-desc">Mô tả campaign bằng ngôn ngữ tự nhiên — AI đề xuất inventory phù hợp nhất trong giây.</div><div class="ai-card-tag"><span class="badge badge-bl">GPT-powered</span></div></div></div><div class="ai-card rv"><div class="ai-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:20px;height:20px;flex-shrink:0"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg></div><div><div class="ai-card-title">Real-time Availability</div><div class="ai-card-desc">Kiểm tra slot trống trước khi brief AdOps. Sync với owner trong 5 phút.</div><div class="ai-card-tag"><span class="badge badge-bl">Live sync &lt;5min</span></div></div></div><div class="ai-card rv"><div class="ai-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:20px;height:20px;flex-shrink:0"><path d="M20.38 8.57l-1.23 1.85a8 8 0 0 1-.22 7.58H5.07A8 8 0 0 1 15.58 6.85l1.85-1.23A10 10 0 0 0 3.35 19a2 2 0 0 0 1.72 1h13.85a2 2 0 0 0 1.74-1 10 10 0 0 0-.27-10.44zm-9.79 6.84a2 2 0 0 0 2.83 0l5.66-8.49-8.49 5.66a2 2 0 0 0 0 2.83z"/></svg></div><div><div class="ai-card-title">AI Audience Estimation</div><div class="ai-card-desc">Ước tính reach từ traffic data thực tế, thời điểm trong ngày và demographic khu vực.</div><div class="ai-card-tag"><span class="badge badge-bl">Data-driven ±8%</span></div></div></div><div class="ai-card rv"><div class="ai-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:20px;height:20px;flex-shrink:0"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg></div><div><div class="ai-card-title">Verified Data Network</div><div class="ai-card-desc">Ảnh thực địa, location verified, traffic data nguồn đáng tin. Không có inventory ảo.</div><div class="ai-card-tag"><span class="badge badge-bl">100% verified</span></div></div></div></div>
      </div>
      <div>
        <div class="value-visual">
          <img src="https://images.unsplash.com/photo-1551836022-deb4988cc6c0?w=800&q=80&auto=format&fit=crop" loading="lazy">
          <div class="value-visual-ov">
            <div class="vv-number">3.2×</div>
            <div class="vv-label">Faster than traditional booking</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="sec sec-gray rv">
  <div class="w">
    <div style="text-align:center;max-width:560px;margin:0 auto 40px">
      <span class="eyebrow">PACKAGES</span>
      <h2 class="section-hed">Campaign Packages</h2>
      <p class="section-sub" style="margin:12px auto 0;font-size:16px">Chưa biết bắt đầu từ đâu? OOHX tư vấn inventory phù hợp nhất.</p>
    </div>
    <div class="pkg-grid"><div class="pkg rv"><div class="pkg-head" style="background:linear-gradient(135deg,#1C3A5E,#2A4FF6)"><div class="pkg-head-bg" style="background-image:url('https://images.unsplash.com/photo-1567721913486-6585f069b3b7?w=600&q=40')"></div><div class="pkg-ey">Brand awareness</div><div class="pkg-name">City Launch</div><div class="pkg-desc">Phủ sóng 3 TP lớn trong 1 tháng</div></div><div class="pkg-body"><ul class="pkg-items"><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>5–8 LED/Billboard premium</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>HN + HCM + Đà Nẵng</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>2M+ impressions estimate</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>Weekly performance report</li></ul><div class="pkg-price"><span class="pkg-price-n">Từ 300M</span><span class="pkg-price-u">/ tháng</span></div><button class="btn btn-o" style="width:100%;justify-content:center;border-radius:var(--r2)">City Launch</button></div></div><div class="pkg--feat pkg rv"><div class="pkg-head" style="background:linear-gradient(135deg,#1A3A2A,#34C759)"><div class="pkg-head-bg" style="background-image:url('https://images.unsplash.com/photo-1519567770579-c2fc5436bcf8?w=600&q=40')"></div><div class="pkg-ey">FMCG / Retail</div><div class="pkg-name">Retail Activation</div><div class="pkg-desc">LCD mall network + in-store activation</div></div><div class="pkg-body"><ul class="pkg-items"><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>LCD mall 10–15 screens</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>Digital standee khu POP</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>QR tracking tích hợp</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>Footfall report từng điểm</li></ul><div class="pkg-price"><span class="pkg-price-n">Từ 120M</span><span class="pkg-price-u">/ tháng</span></div><button class="btn btn-p" style="width:100%;justify-content:center;border-radius:var(--r2)">Retail Activation</button></div></div><div class="pkg rv"><div class="pkg-head" style="background:linear-gradient(135deg,#3A1A3A,#AF52DE)"><div class="pkg-head-bg" style="background-image:url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=600&q=40')"></div><div class="pkg-ey">Event / Launch</div><div class="pkg-name">Event Blitz</div><div class="pkg-desc">Phủ sóng nhanh cho sự kiện ngắn ngày</div></div><div class="pkg-body"><ul class="pkg-items"><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>LED 5km quanh event</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>Min 7 ngày booking</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>Production support</li><li class="pkg-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--grn)" style="width:16px;height:16px;flex-shrink:0"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>Proof-of-play video</li></ul><div class="pkg-price"><span class="pkg-price-n">Từ 45M</span><span class="pkg-price-u">/ 7 ngày</span></div><button class="btn btn-o" style="width:100%;justify-content:center;border-radius:var(--r2)">Event Blitz</button></div></div></div>
  </div>
</section>

<section class="sec rv">
  <div class="w">
    <div class="owner-cta">
      <div class="oc-mesh"></div>
      <div class="oc-in">
        <div>
          <div style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:980px;padding:5px 13px;font-size:12px;font-weight:700;color:rgba(255,255,255,.9);margin-bottom:18px">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255,255,255,.9)" style="width:14px;height:14px;flex-shrink:0"><path d="M17 11c.34 0 .67.03 1 .08V9l-5-5H5c-1.11 0-2 .89-2 2v14c0 1.11.89 2 2 2h6.26c-.79-1.13-1.26-2.51-1.26-4 0-3.87 3.13-7 7-7zM13 4.5l4.5 4.5H13V4.5zM17 19v-3h-3v-2h3v-3l4 4-4 4z"/></svg> MEDIA OWNER
          </div>
          <div class="oc-title">Đưa inventory của bạn lên OOHX</div>
          <p class="oc-desc">Tiếp cận agency &amp; brand đang tìm inventory mỗi ngày. Quản lý availability và nhận booking từ một dashboard.</p>
          <div class="oc-actions">
            <button class="btn btn-w btn-lg"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:18px;height:18px;flex-shrink:0"><path d="M17 11c.34 0 .67.03 1 .08V9l-5-5H5c-1.11 0-2 .89-2 2v14c0 1.11.89 2 2 2h6.26c-.79-1.13-1.26-2.51-1.26-4 0-3.87 3.13-7 7-7zM13 4.5l4.5 4.5H13V4.5zM17 19v-3h-3v-2h3v-3l4 4-4 4z"/></svg> Register as Owner</button>
            <button class="btn btn-w">Learn more</button>
          </div>
        </div>
        <div class="oc-features">
          <div class="oc-feat"><div class="oc-feat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255,255,255,.9)" style="width:18px;height:18px;flex-shrink:0"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg></div><div><div class="oc-feat-title">Đăng inventory nhanh chóng</div><div class="oc-feat-sub">Upload ảnh, thông số, vị trí trong vài phút</div></div></div>
          <div class="oc-feat"><div class="oc-feat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255,255,255,.9)" style="width:18px;height:18px;flex-shrink:0"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg></div><div><div class="oc-feat-title">Quản lý availability calendar</div><div class="oc-feat-sub">Cập nhật slot theo real-time</div></div></div>
          <div class="oc-feat"><div class="oc-feat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255,255,255,.9)" style="width:18px;height:18px;flex-shrink:0"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg></div><div><div class="oc-feat-title">Nhận booking notification tức thì</div><div class="oc-feat-sub">Không bỏ lỡ bất kỳ lead nào</div></div></div>
          <div class="oc-feat"><div class="oc-feat-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255,255,255,.9)" style="width:18px;height:18px;flex-shrink:0"><path d="m16 6 2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg></div><div><div class="oc-feat-title">Tăng fill rate lên 40%</div><div class="oc-feat-sub">Tiếp cận đúng buyer đang tìm kiếm</div></div></div>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function(){
  // Impression counter
  let imp=120847;
  const c=document.getElementById('imp-n');
  if(c) setInterval(()=>{imp+=Math.floor(Math.random()*12+3);c.textContent=imp.toLocaleString('en-US')},1400);

  // Save toggle
  document.querySelectorAll('.inv-save').forEach(b=>b.addEventListener('click',e=>{
    e.stopPropagation(); b.classList.toggle('on');
    b.querySelector('svg').style.fill = b.classList.contains('on') ? 'var(--red)' : 'var(--t3)';
  }));

  // ── HOMEPAGE MINI MAP ──────────────────────────────────────────────
  var CITY_CENTERS = {
    hanoi:    {lat:21.0285, lng:105.8542, zoom:12, name:'Hà Nội'},
    hcm:     {lat:10.7769, lng:106.7009, zoom:12, name:'TP. Hồ Chí Minh'},
    danang:  {lat:16.0544, lng:108.2022, zoom:13, name:'Đà Nẵng'},
    haiphong:{lat:20.8449, lng:106.6881, zoom:13, name:'Hải Phòng'},
    cantho:  {lat:10.0452, lng:105.7469, zoom:13, name:'Cần Thơ'},
  };

  var HP_PINS = @json($mapData['pins'] ?? []);
  var currentCity = @json($mapData['citySlug'] ?? 'hanoi');
  var cityInfo = CITY_CENTERS[currentCity] || CITY_CENTERS.hanoi;

  var hpMap = L.map('hp-leaflet-map', {
    zoomControl: false,
    attributionControl: false,
    dragging: true,
    scrollWheelZoom: false,
  }).setView([cityInfo.lat, cityInfo.lng], cityInfo.zoom);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:18}).addTo(hpMap);
  L.control.attribution({position:'bottomleft',prefix:false}).addAttribution('© <a href="https://openstreetmap.org">OSM</a>').addTo(hpMap);

  // Pin color
  function pinColor(type) {
    if (!type) return 'bl';
    if (type.indexOf('indoor') >= 0 || type.indexOf('mall') >= 0) return 'grn';
    if (type.indexOf('transit') >= 0 || type.indexOf('airport') >= 0) return 'org';
    return 'bl';
  }

  function fmtPrice(p) {
    if (p >= 1e9) return (p/1e9).toFixed(1).replace('.0','') + 'B';
    if (p >= 1e6) return Math.round(p/1e6) + 'M';
    if (p > 0) return p.toLocaleString('vi-VN');
    return '—';
  }

  // Render pins
  var hpMarkers = L.layerGroup().addTo(hpMap);

  function renderHpPins(pins, flyToCity) {
    hpMarkers.clearLayers();
    pins.forEach(function(pin) {
      var col = pinColor(pin.type);
      var icon = L.divIcon({
        className: 'oohx-pin oohx-pin--' + col,
        html: '<div class="oohx-pin-box">' + fmtPrice(pin.price) + '</div><div class="oohx-pin-arrow"></div>',
        iconSize: [70, 36], iconAnchor: [35, 36],
      });
      var m = L.marker([pin.lat, pin.lng], {icon: icon}).addTo(hpMarkers);
      m.on('click', function() { showHpPopup(pin); });
    });

    // Always center on the selected city, not on pin locations
    // (pin data may have incorrect coordinates)
    if (flyToCity) {
      var c = CITY_CENTERS[flyToCity] || CITY_CENTERS.hanoi;
      hpMap.setView([c.lat, c.lng], c.zoom);
    }
  }

  renderHpPins(HP_PINS, currentCity);

  // Popup
  function showHpPopup(pin) {
    document.getElementById('hp-popup-name').textContent = pin.name;
    document.getElementById('hp-popup-city').textContent = pin.city || pin.addr || '';
    document.getElementById('hp-popup-price').innerHTML = fmtPrice(pin.price) + ' ₫<span style="font-size:11px;color:var(--t4);font-weight:400"> /tháng</span>';
    document.getElementById('hp-popup-img').src = pin.photo || 'https://placehold.co/300x160/F5F5F7/6E6E73?text=No+Photo';
    document.getElementById('hp-popup-link').href = '/explore/' + pin.id;
    document.getElementById('hp-popup').style.display = 'flex';
    hpMap.panTo([pin.lat, pin.lng]);
  }

  // City picker buttons
  document.querySelectorAll('.hmc-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.hmc-btn').forEach(function(b){ b.classList.remove('on'); });
      btn.classList.add('on');
      document.getElementById('hp-popup').style.display = 'none';

      var city = btn.dataset.city;
      var lat  = parseFloat(btn.dataset.lat);
      var lng  = parseFloat(btn.dataset.lng);
      var zoom = parseInt(btn.dataset.zoom);

      // Save cookie
      document.cookie = 'oohx_city=' + city + ';path=/;max-age=' + (30*86400) + ';SameSite=Lax';
      currentCity = city;

      // Fly to city center first, then fetch pins
      hpMap.flyTo([lat, lng], zoom, {duration:0.6});
      fetch('/?_map_city=' + city, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); })
        .then(function(data){
          HP_PINS = data.pins || [];
          renderHpPins(HP_PINS, null); // don't re-center, already flew
          document.getElementById('hp-map-total').textContent = (data.total || 0).toLocaleString('vi-VN');
          document.getElementById('hp-map-city-label').textContent = data.cityName || '';
        })
        .catch(function(){ /* fallback: just fly, keep old pins */ });
    });
  });

  // ── GEOLOCATION DETECT (first visit only) ──────────────────────────
  if (!getCookie('oohx_city') && navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
      var lat = pos.coords.latitude;
      var lng = pos.coords.longitude;
      var closest = 'hanoi';
      var minDist = Infinity;
      for (var key in CITY_CENTERS) {
        var c = CITY_CENTERS[key];
        var d = Math.pow(lat - c.lat, 2) + Math.pow(lng - c.lng, 2);
        if (d < minDist) { minDist = d; closest = key; }
      }
      if (closest !== currentCity) {
        var btn = document.querySelector('.hmc-btn[data-city="' + closest + '"]');
        if (btn) btn.click();
      }
      document.cookie = 'oohx_city=' + closest + ';path=/;max-age=' + (30*86400) + ';SameSite=Lax';
    }, function(){
      // Permission denied or error — use default, save cookie
      document.cookie = 'oohx_city=hanoi;path=/;max-age=' + (30*86400) + ';SameSite=Lax';
    }, {timeout: 5000, maximumAge: 3600000});
  }

  function getCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? m[1] : null;
  }
})();

// ── SEARCH FILTER DROPDOWNS ────────────────────────────────────────
var selCities = [];
var selTypes  = [];
var activeDrop = null;

var DISTRICTS = {
  'Hà Nội': ['Ba Đình','Hoàn Kiếm','Hai Bà Trưng','Đống Đa','Tây Hồ','Cầu Giấy','Thanh Xuân','Hoàng Mai','Long Biên','Nam Từ Liêm','Bắc Từ Liêm','Hà Đông'],
  'TP. Hồ Chí Minh': ['Quận 1','Quận 3','Quận 5','Quận 7','Quận 10','Bình Thạnh','Tân Bình','Phú Nhuận','Bình Chánh','Thủ Đức'],
  'Đà Nẵng': ['Hải Châu','Thanh Khê','Sơn Trà','Ngũ Hành Sơn','Liên Chiểu','Cẩm Lệ'],
  'Hải Phòng': ['Hồng Bàng','Ngô Quyền','Lê Chân','Dương Kinh','Đồ Sơn'],
};

function setQ(txt) { document.getElementById('q').value = txt; }

function toggleDrop(id) {
  var target = 'drop-' + id;
  var btn = id === 'loc' ? document.getElementById('sf-loc') : document.getElementById('sf-type');
  if (activeDrop === target) { closeDrop(); return; }
  if (activeDrop) {
    document.getElementById(activeDrop).classList.remove('open');
    document.getElementById('sf-loc').classList.remove('on');
    document.getElementById('sf-type').classList.remove('on');
  }
  // Position dropdown below the button using fixed coords
  var rect = btn.getBoundingClientRect();
  var drop = document.getElementById(target);
  drop.style.top = (rect.bottom + 10) + 'px';
  drop.classList.add('open');
  document.getElementById('backdrop').classList.add('open');
  btn.classList.add('on');
  activeDrop = target;
}

function closeDrop() {
  if (activeDrop) document.getElementById(activeDrop).classList.remove('open');
  document.getElementById('backdrop').classList.remove('open');
  document.getElementById('sf-loc').classList.remove('on');
  document.getElementById('sf-type').classList.remove('on');
  activeDrop = null;
}

// ── Location logic ─────────────────────────────────────────────────
function selectCity(el, city) {
  el.classList.toggle('on');
  var idx = selCities.indexOf(city);
  if (idx > -1) selCities.splice(idx, 1); else selCities.push(city);
  // Show district sub-panel if exactly 1 city & has districts
  var panel = document.getElementById('dist-panel');
  var distCities = selCities.filter(function(c) { return DISTRICTS[c]; });
  if (distCities.length === 1) {
    renderDistricts(distCities[0]);
    panel.classList.add('open');
  } else {
    panel.classList.remove('open');
  }
  updateLocBadge();
}

function renderDistricts(city) {
  document.getElementById('dist-panel-title').textContent = 'Chọn quận / huyện · ' + city;
  var grid = document.getElementById('dist-grid');
  grid.innerHTML = '';
  (DISTRICTS[city] || []).forEach(function(d) {
    var t = document.createElement('div');
    t.className = 'loc-dis-tag';
    t.textContent = d;
    t.onclick = function() { t.classList.toggle('on'); updateLocBadge(); };
    grid.appendChild(t);
  });
}

function updateLocBadge() {
  var distSel = document.querySelectorAll('.loc-dis-tag.on').length;
  var total = selCities.length + distSel;
  var badge = document.getElementById('sf-loc-badge');
  var label = document.getElementById('sf-loc-label');
  if (total > 0) {
    badge.textContent = total; badge.style.display = 'inline-flex';
    label.textContent = selCities.length === 1 ? selCities[0] : 'Vị trí';
  } else {
    badge.style.display = 'none';
    label.textContent = 'Vị trí';
  }
  renderActiveTags();
}

function clearLoc() {
  selCities = [];
  document.querySelectorAll('#drop-loc .loc-item').forEach(function(e) { e.classList.remove('on'); });
  document.querySelectorAll('.loc-dis-tag').forEach(function(e) { e.classList.remove('on'); });
  document.getElementById('dist-panel').classList.remove('open');
  updateLocBadge();
}

function applyLoc() { closeDrop(); renderActiveTags(); }

// ── Type logic ─────────────────────────────────────────────────────
function toggleType(el, type) {
  el.classList.toggle('on');
  var idx = selTypes.indexOf(type);
  if (idx > -1) selTypes.splice(idx, 1); else selTypes.push(type);
  updateTypeBadge();
}

function updateTypeBadge() {
  var badge = document.getElementById('sf-type-badge');
  var label = document.getElementById('sf-type-label');
  if (selTypes.length > 0) {
    badge.textContent = selTypes.length; badge.style.display = 'inline-flex';
    label.textContent = selTypes.length === 1 ? selTypes[0] : 'Loại biển';
  } else {
    badge.style.display = 'none';
    label.textContent = 'Loại biển';
  }
  renderActiveTags();
}

function clearType() {
  selTypes = [];
  document.querySelectorAll('.type-card').forEach(function(e) { e.classList.remove('on'); });
  updateTypeBadge();
}

function applyType() { closeDrop(); renderActiveTags(); }

// ── Active tags ─────────────────────────────────────────────────────
function renderActiveTags() {
  var container = document.getElementById('active-tags');
  container.innerHTML = '';
  var all = [];
  selCities.forEach(function(c) { all.push({label: c, type: 'loc'}); });
  document.querySelectorAll('.loc-dis-tag.on').forEach(function(t) { all.push({label: t.textContent, type: 'loc'}); });
  selTypes.forEach(function(t) { all.push({label: t, type: 'type'}); });
  all.forEach(function(item) {
    var tag = document.createElement('div');
    tag.style.cssText = 'display:inline-flex;align-items:center;gap:5px;padding:5px 10px 5px 12px;border-radius:980px;background:var(--bl-lt);color:var(--bl);font-size:12px;font-weight:600;white-space:nowrap;cursor:pointer;border:1.5px solid var(--bl);';
    tag.innerHTML = item.label + '<svg viewBox="0 0 24 24" fill="var(--bl)" style="width:13px;height:13px;flex-shrink:0"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';
    tag.onclick = function() {
      if (item.type === 'loc') {
        var ci = selCities.indexOf(item.label);
        if (ci > -1) { selCities.splice(ci, 1); document.querySelectorAll('#drop-loc .loc-item').forEach(function(e) { if (e.querySelector('span') && e.querySelector('span').textContent === item.label) e.classList.remove('on'); }); }
        else { document.querySelectorAll('.loc-dis-tag').forEach(function(e) { if (e.textContent === item.label) e.classList.remove('on'); }); }
        updateLocBadge();
      } else {
        var ti = selTypes.indexOf(item.label);
        if (ti > -1) selTypes.splice(ti, 1);
        document.querySelectorAll('.type-card').forEach(function(e) { if (e.querySelector('.type-name') && e.querySelector('.type-name').textContent === item.label) e.classList.remove('on'); });
        updateTypeBadge();
      }
    };
    container.appendChild(tag);
  });
}

</script>
@endpush
