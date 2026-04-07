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
        </div>
    </div>
</header>
<style>@media(min-width:768px){#lbtn,#sbtn{display:inline-flex}}</style>
