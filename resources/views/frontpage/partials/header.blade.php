<header class="hdr">
    <div class="hdr-in">
        <a href="{{ route('fp.index') }}" class="hdr-logo"><img id="logo" src="" alt="OOHX"></a>
        <nav class="hdr-nav">
            <a href="{{ route('fp.listing') }}" @class(['ac' => ($activeNav ?? '') === 'explore'])>Khám phá</a>
            <a href="{{ route('fp.map') }}" @class(['ac' => ($activeNav ?? '') === 'map'])>Bản đồ</a>
            <a href="{{ route('fp.owners') }}" @class(['ac' => ($activeNav ?? '') === 'owners'])>Media Owners</a>
            <a href="#" @class(['ac' => ($activeNav ?? '') === 'quote'])>Báo giá</a>
            <a href="{{ route('fp.agency') }}" @class(['ac' => ($activeNav ?? '') === 'agency'])>Agency</a>
        </nav>
        <div class="hdr-acts">
            <button class="hdr-ico">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:20px;height:20px;flex-shrink:0"><path d="M17 3H7c-1.1 0-2 .9-2 2L5 21l7-3 7 3V5c0-1.1-.9-2-2-2zm0 15l-5-2.18L7 18V5h10v13z"/></svg>
                <div class="hdot" style="background:var(--bl)"></div>
            </button>
            <button class="hdr-ico">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t3)" style="width:20px;height:20px;flex-shrink:0"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96C5 16.1 6.9 18 9 18h12v-2H9.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63H19c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0 0 23.43 5H5.21l-.94-2H1zm16 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                <div class="hdot" style="background:var(--red)"></div>
            </button>
            <div class="hdr-sep"></div>
            <button class="btn btn-s btn-sm" id="lbtn" style="display:none">Đăng nhập</button>
            <button class="btn btn-p btn-sm" id="sbtn" style="display:none">Đăng ký</button>
            {{-- Hamburger — visible < 1024px --}}
            <button class="hdr-hamburger" id="hdr-hamburger" aria-label="Menu">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
            </button>
        </div>
    </div>
</header>

{{-- Slide menu — full screen overlay for mobile/tablet --}}
<div class="slide-menu" id="slide-menu">
    <div class="slide-menu-head">
        <a href="{{ route('fp.index') }}" class="hdr-logo"><img id="logo-menu" src="" alt="OOHX"></a>
        <button class="slide-menu-close" id="slide-menu-close" aria-label="Đóng">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </button>
    </div>
    <nav class="slide-menu-nav">
        <a href="{{ route('fp.index') }}" @class(['on' => ($activeNav ?? '') === 'home'])>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            Trang chủ
        </a>
        <a href="{{ route('fp.listing') }}" @class(['on' => ($activeNav ?? '') === 'explore'])>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            Khám phá
        </a>
        <a href="{{ route('fp.map') }}" @class(['on' => ($activeNav ?? '') === 'map'])>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
            Bản đồ
        </a>
        <a href="{{ route('fp.owners') }}" @class(['on' => ($activeNav ?? '') === 'owners'])>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            Media Owners
        </a>
        <a href="#" @class(['on' => ($activeNav ?? '') === 'quote'])>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
            Báo giá
        </a>
        <a href="{{ route('fp.agency') }}" @class(['on' => ($activeNav ?? '') === 'agency'])>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
            Agency
        </a>
    </nav>
    <div class="slide-menu-foot">
        <a href="#" class="btn btn-p" style="width:100%;justify-content:center;border-radius:10px">Đăng ký</a>
        <a href="#" class="btn btn-s" style="width:100%;justify-content:center;border-radius:10px">Đăng nhập</a>
    </div>
</div>
<div class="slide-menu-backdrop" id="slide-menu-backdrop"></div>

<style>@media(min-width:768px){#lbtn,#sbtn{display:inline-flex}}</style>
<script>
(function(){
    var menu = document.getElementById('slide-menu');
    var backdrop = document.getElementById('slide-menu-backdrop');
    var btn = document.getElementById('hdr-hamburger');
    var close = document.getElementById('slide-menu-close');
    function open(){ menu.classList.add('open'); backdrop.classList.add('open'); document.body.style.overflow='hidden'; }
    function shut(){ menu.classList.remove('open'); backdrop.classList.remove('open'); document.body.style.overflow=''; }
    btn.addEventListener('click', open);
    close.addEventListener('click', shut);
    backdrop.addEventListener('click', shut);
})();
</script>
