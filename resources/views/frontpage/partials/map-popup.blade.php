{{-- Shared map popup HTML
     Props:
       $pfx — ID prefix, e.g. '' (for /map), 'hp-' (for home)
--}}
@php $pfx = $pfx ?? ''; @endphp
<div class="map-popup" id="{{ $pfx }}popup" style="display:none">
    <div class="mpop-close" onclick="OOHXMap.hidePopup('{{ $pfx }}')">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#fff" style="width:14px;height:14px;flex-shrink:0"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
    </div>
    <div class="mpop-img"><img id="{{ $pfx }}popup-img" src="" alt=""><div class="mpop-avail"><span class="mpop-avail-dot"></span>Còn trống</div></div>
    <div class="mpop-body">
        <div class="mpop-bc" id="{{ $pfx }}popup-bc"></div>
        <div class="mpop-name" id="{{ $pfx }}popup-name"></div>
        <div class="mpop-loc" id="{{ $pfx }}popup-loc">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:12px;height:12px;flex-shrink:0"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            <span id="{{ $pfx }}popup-city"></span>
        </div>
        <div class="mpop-specs" id="{{ $pfx }}popup-specs"></div>
        <div id="{{ $pfx }}popup-product" class="mpop-product" style="display:none">
            <a href="#" id="{{ $pfx }}popup-product-link">
                <svg viewBox="0 0 24 24" fill="currentColor" style="width:11px;height:11px;flex-shrink:0"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                <span id="{{ $pfx }}popup-product-text"></span>
            </a>
        </div>
    </div>

    {{-- SINGLE mode: price + actions --}}
    <div class="mpop-foot" id="{{ $pfx }}popup-foot-single">
        <div>
            <div class="mpop-price" id="{{ $pfx }}popup-price"></div>
            <div class="mpop-price-unit" id="{{ $pfx }}popup-price-unit"></div>
        </div>
        <div class="mpop-actions">
            <a href="#" id="{{ $pfx }}popup-link" class="btn btn-p btn-sm" style="justify-content:center;flex:1">Xem chi tiết</a>
        </div>
    </div>

    {{-- MULTI mode: screen list --}}
    <div class="mpop-list" id="{{ $pfx }}popup-list" style="display:none">
        <div class="mpop-list-title" id="{{ $pfx }}popup-list-title">Danh sách màn hình</div>
        <div class="mpop-list-body" id="{{ $pfx }}popup-list-body"></div>
    </div>
</div>
