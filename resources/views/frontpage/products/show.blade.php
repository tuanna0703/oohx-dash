@extends('frontpage.layouts.app', ['activeNav' => 'explore', 'bodyClass' => 'page-detail'])

@section('title', $product->name . ' | OOHX')

@section('content')
<div class="w" style="padding-top:20px;padding-bottom:64px">

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--t4);margin-bottom:16px;display:flex;gap:6px;align-items:center">
        <a href="/products" style="color:var(--bl);text-decoration:none">Sản phẩm</a>
        <span>›</span>
        <span>{{ $product->category_label }}</span>
        <span>›</span>
        <span style="color:var(--t2)">{{ $product->name }}</span>
    </div>

    <div class="pd-detail-layout">
        {{-- Left: photos + info --}}
        <div class="pd-detail-main">
            {{-- Photo gallery --}}
            <div class="pd-detail-gallery">
                <img src="{{ $product->cover_url ?? 'https://placehold.co/800x500/F5F5F7/999?text=No+Photo' }}" alt="{{ $product->name }}" class="pd-detail-hero-img">
                @if(count($product->photo_urls) > 1)
                <div class="pd-detail-thumbs">
                    @foreach($product->photo_urls as $url)
                    <img src="{{ $url }}" alt="" loading="lazy" class="pd-detail-thumb">
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Title + meta --}}
            <div style="margin-top:20px">
                @php
                    $typeLabels = ['single'=>'Đơn lẻ','package'=>'Gói','custom'=>'Combo'];
                @endphp
                <div style="display:flex;gap:6px;margin-bottom:8px">
                    <span class="badge b-bl">{{ $typeLabels[$product->type] ?? $product->type }}</span>
                    <span class="badge b-gray">{{ $product->category_label }}</span>
                </div>
                <h1 style="font-size:24px;font-weight:800;color:var(--t1);letter-spacing:-.4px;margin-bottom:8px">{{ $product->name }}</h1>
                <div style="display:flex;align-items:center;gap:12px;font-size:13px;color:var(--t3);flex-wrap:wrap">
                    @if($product->owner)
                    <a href="/owners/{{ $product->owner->slug }}" style="color:var(--bl);font-weight:600;text-decoration:none">{{ $product->owner->name }}</a>
                    @endif
                    @if($product->network)<span>{{ $product->network->name }}</span>@endif
                    @if($product->city)<span>📍 {{ $product->city }}</span>@endif
                    @if($product->isPackage())<span>{{ $product->total_units }} vị trí</span>@endif
                </div>
            </div>

            {{-- Description --}}
            @if($product->description)
            <div style="margin-top:20px;font-size:14px;color:var(--t2);line-height:1.7">{!! $product->description !!}</div>
            @endif

            {{-- Specs --}}
            @if($product->specs && count($product->specs) > 0)
            <div style="margin-top:24px">
                <h3 style="font-size:16px;font-weight:700;color:var(--t1);margin-bottom:12px">Thông số kỹ thuật</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    @foreach($product->specs as $key => $value)
                    <div style="display:flex;justify-content:space-between;padding:8px 12px;background:var(--bg2);border-radius:8px;font-size:13px">
                        <span style="color:var(--t4)">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                        <span style="font-weight:600;color:var(--t1)">{{ $value }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Screens list (for package/custom) --}}
            @if(!$product->isSingle() && $product->screens->count() > 0)
            <div style="margin-top:24px">
                <h3 style="font-size:16px;font-weight:700;color:var(--t1);margin-bottom:12px">
                    Danh sách {{ $product->isPackage() ? 'vị trí' : 'items' }} ({{ $product->screens->count() }})
                </h3>
                <div style="display:flex;flex-direction:column;gap:0;border:1px solid var(--ln2);border-radius:12px;overflow:hidden">
                    @foreach($product->screens->take(20) as $screen)
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid var(--ln2);font-size:13px">
                        <img src="{{ $screen->spec?->photo ?? '' }}" style="width:40px;height:40px;border-radius:6px;object-fit:cover;background:var(--bg2);flex-shrink:0" loading="lazy">
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:600;color:var(--t1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $screen->name }}</div>
                            <div style="font-size:11px;color:var(--t4)">{{ $screen->site?->city ?? '' }}</div>
                        </div>
                        <div style="font-size:12px;color:var(--t3);white-space:nowrap">{{ $screen->spec?->width_px ?? '—' }}x{{ $screen->spec?->height_px ?? '—' }}px</div>
                    </div>
                    @endforeach
                    @if($product->screens->count() > 20)
                    <div style="padding:12px;text-align:center;font-size:12px;color:var(--t4)">và {{ $product->screens->count() - 20 }} vị trí khác</div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Similar --}}
            @if($similar->isNotEmpty())
            <div style="margin-top:32px">
                <h3 style="font-size:16px;font-weight:700;color:var(--t1);margin-bottom:12px">Sản phẩm tương tự</h3>
                <div class="inv-grid">
                    @foreach($similar as $s)
                        @include('frontpage.products.partials.product-card', ['product' => $s])
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Right: booking panel --}}
        <div class="pd-detail-sidebar">
            <div class="bp">
                <div class="bp-head">
                    <div class="bp-price">{{ $product->price_display }}</div>
                    <div style="font-size:13px;color:var(--t4);margin:4px 0 10px">Chưa bao gồm VAT</div>
                    <div class="bp-avail"><div class="bp-dot"></div>{{ $product->status === 'active' ? 'Còn trống' : 'Tạm hết' }}</div>
                </div>

                {{-- Package options --}}
                @if($product->isPackage() && $product->package_options)
                <div class="bp-body">
                    <div class="bp-lbl">Chọn gói</div>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px">
                        @foreach($product->package_options as $i => $opt)
                        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid var(--ln2);border-radius:10px;cursor:pointer;transition:all 140ms">
                            <input type="radio" name="package_option" value="{{ $i }}" {{ $i === 0 ? 'checked' : '' }} style="accent-color:var(--bl)">
                            <div style="flex:1">
                                <div style="font-size:13px;font-weight:700;color:var(--t1)">{{ $opt['name'] }}</div>
                                <div style="font-size:12px;color:var(--t4)">{{ $opt['quantity'] }} vị trí</div>
                            </div>
                            <div style="font-size:14px;font-weight:800;color:var(--bl)">{{ number_format($opt['price'], 0, ',', '.') }} ₫</div>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="bp-body">
                    @auth
                    <form method="POST" action="/cart/add">
                        @csrf
                        <input type="hidden" name="screen_id" value="{{ $product->screens->first()?->id }}">
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <button type="submit" class="btn btn-p btn-lg" style="width:100%;justify-content:center;border-radius:12px;height:52px">
                            <svg viewBox="0 0 24 24" fill="#fff" style="width:18px;height:18px"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                            Thêm vào Plan
                        </button>
                    </form>
                    @else
                    <a href="/login" class="btn btn-p btn-lg" style="width:100%;justify-content:center;border-radius:12px;height:52px;text-decoration:none">Đăng nhập để Booking</a>
                    @endauth
                </div>

                <div class="bp-foot">
                    <svg viewBox="0 0 24 24" fill="var(--grn)" style="width:14px;height:14px;flex-shrink:0"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                    Thanh toán bảo mật bởi OOHX
                </div>
            </div>

            {{-- Owner info --}}
            @if($product->owner)
            <div style="margin-top:16px;padding:18px;background:var(--bg2);border-radius:16px;border:1px solid var(--ln2)">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                    @if($product->owner->logo_url)
                    <img src="{{ $product->owner->logo_url }}" style="width:36px;height:36px;border-radius:8px;object-fit:cover">
                    @endif
                    <div>
                        <div style="font-size:13px;font-weight:700;color:var(--t1)">{{ $product->owner->name }}</div>
                        <div style="font-size:11px;color:var(--bl);font-weight:600">Verified Owner</div>
                    </div>
                </div>
                <a href="/owners/{{ $product->owner->slug }}" class="btn btn-s btn-sm" style="width:100%;justify-content:center;border-radius:8px;text-decoration:none">Xem profile</a>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
