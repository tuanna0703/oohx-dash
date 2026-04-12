@extends('frontpage.layouts.app', ['activeNav' => 'owners'])

@section('title', 'Media Owners | OOHX')

@section('content')
<div class="pg-hero"><div class="w"><div style="font-size:13px;font-weight:700;color:var(--bl);letter-spacing:.3px;margin-bottom:8px">VERIFIED PARTNERS</div><h1>Media Owners</h1><p>{{ $owners->total() }}+ đối tác verified. Inventory thật, data thật. Đặt booking trực tiếp không qua trung gian.</p><div class="pg-hero-search"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--t4)" style="width:18px;height:18px;flex-shrink:0"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg><input placeholder="Tìm media owner theo tên, khu vực, format…"></div></div></div><div class="w"><div class="cat-chips"><div class="cat-chip on">Tất cả ({{ $owners->total() }})</div>@foreach($venueTypes->take(6) as $vt)
<div class="cat-chip">{{ $vt['label'] }}</div>
@endforeach</div><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px"><div style="font-size:14px;font-weight:600;color:var(--t2)">Hiển thị <strong style="color:var(--bl)">{{ $owners->total() }}</strong> media owners</div><select style="height:36px;padding:0 12px;border-radius:8px;border:1.5px solid var(--ln2);background:#fff;font-family:var(--font);font-size:13px;color:var(--t2);outline:none;appearance:none;-webkit-appearance:none;padding-right:28px"><option>A → Z</option><option>Nhiều inventory nhất</option><option>Fill rate cao nhất</option><option>Mới nhất</option></select></div><div class="oc-grid oc-grid--full">
    @foreach($owners as $owner)
        @include('frontpage.partials.owner-card', ['owner' => $owner, 'variant' => 'full'])
    @endforeach
</div><div style="display:flex;justify-content:center;padding-bottom:64px">{{ $owners->links() }}</div></div>
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
