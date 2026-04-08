@extends('frontpage.layouts.app', ['activeNav' => 'owners'])

@section('title', 'Media Owners | OOHX')

@section('content')
<div class="pg-hero"><div class="w"><div style="font-size:13px;font-weight:700;color:var(--bl);letter-spacing:.3px;margin-bottom:8px">VERIFIED PARTNERS</div><h1>Media Owners</h1><p>{{ $owners->total() }}+ đối tác verified. Inventory thật, data thật. Đặt booking trực tiếp không qua trung gian.</p><div class="pg-hero-search"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t4)" style="width:18px;height:18px;flex-shrink:0"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg><input placeholder="Tìm media owner theo tên, khu vực, format…"></div></div></div><div class="w"><div class="cat-chips"><div class="cat-chip on">Tất cả ({{ $owners->total() }})</div>@foreach($venueTypes->take(6) as $vt)
<div class="cat-chip">{{ $vt['label'] }}</div>
@endforeach</div><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px"><div style="font-size:14px;font-weight:600;color:var(--t2)">Hiển thị <strong style="color:var(--bl)">{{ $owners->total() }}</strong> media owners</div><select style="height:36px;padding:0 12px;border-radius:8px;border:1.5px solid var(--ln2);background:#fff;font-family:var(--font);font-size:13px;color:var(--t2);outline:none;appearance:none;-webkit-appearance:none;padding-right:28px"><option>A → Z</option><option>Nhiều inventory nhất</option><option>Fill rate cao nhất</option><option>Mới nhất</option></select></div><div class="owners-layout">@foreach($owners as $owner)
@php
    $initials = strtoupper(collect(explode(' ', $owner->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join(''));
    $colors = [['#1D1D1F','#3A3A3C'],['#1C3A5E','#2A4FF6'],['#1A3A2A','#34C759'],['#3A1A3A','#AF52DE'],['#3A1C1A','#FF3B30'],['#1A2A3A','#0071E3']];
    $c = $colors[$loop->index % 6];
    $tags = array_slice($owner->venue_types_list ?? [], 0, 3);
@endphp
<a href="{{ route('fp.owner-detail', $owner->slug) }}" class="oc{{ $owner->featured ? ' oc-feat' : '' }} rv">
    @if($owner->featured)<div class="oc-feat-badge">Featured</div>@endif
    <div class="oc-cover">
        <img src="{{ $owner->cover_url ?? 'https://placehold.co/800x300/1C3A5E/fff?text=' . urlencode($owner->name) }}" loading="lazy">
        <div class="oc-cover-ov"></div>
        <div class="oc-cover-badges"><span class="badge b-bl">{{ $owner->screen_count }} inv</span></div>
    </div>
    <div class="oc-logo-wrap"><div class="oc-logo" style="background:linear-gradient(135deg,{{ $c[0] }},{{ $c[1] }})">{{ $initials }}</div></div>
    <div class="oc-body">
        <div class="oc-name">{{ $owner->name }} <div class="oc-name-ver"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--bl)" style="width:12px;height:12px;flex-shrink:0"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82 1.89 3.2L12 21.04l3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg> Verified</div></div>
        <div class="oc-desc">{{ $owner->tagline ?? Str::limit($owner->about ?? '', 120) }}</div>
        @if(!empty($tags))
        <div class="oc-tags">@foreach($tags as $tag)<span class="oc-tag">{{ ucfirst(str_replace(['_','.'],' ',$tag)) }}</span>@endforeach</div>
        @endif
        <div class="oc-stats">
            <div class="oc-stat"><div class="oc-sn">{{ $owner->screen_count }}</div><div class="oc-sl">Inventory</div></div>
            <div class="oc-stat"><div class="oc-sn">{{ $owner->city_count }}</div><div class="oc-sl">Cities</div></div>
            <div class="oc-stat"><div class="oc-sn">—</div><div class="oc-sl">Response</div></div>
        </div>
    </div>
    <div class="oc-foot">
        <button class="btn btn-s btn-sm" style="flex:1;justify-content:center" onclick="event.preventDefault()">Xem inventory</button>
        <button class="btn btn-p btn-sm" style="flex:1;justify-content:center" onclick="event.preventDefault()">Liên hệ</button>
    </div>
</a>
@endforeach</div><div style="display:flex;justify-content:center;padding-bottom:64px">{{ $owners->links() }}</div></div>
@endsection

@push('scripts')
<script>
(function(){
  document.querySelectorAll('.cat-chip').forEach(function(c){
    c.addEventListener('click',function(){
      document.querySelectorAll('.cat-chip').forEach(function(x){x.classList.remove('on');});
      c.classList.add('on');
    });
  });
})();
</script>
@endpush
