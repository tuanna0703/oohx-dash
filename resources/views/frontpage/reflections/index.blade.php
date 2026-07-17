@extends('frontpage.layouts.app')

@section('title', 'Danh sách phản ánh của tổ chức xã hội – OOHX')

@section('content')
<section class="pol">
    <div class="w pol-w">
        <nav class="pol-crumb" aria-label="Breadcrumb">
            <a href="{{ route('fp.index') }}">Trang chủ</a>
            <span aria-hidden="true">/</span>
            <span>Danh sách phản ánh của tổ chức xã hội</span>
        </nav>

        <h1 class="pol-h1">Danh sách phản ánh của tổ chức xã hội</h1>
        <p class="pol-lead">
            Các phản ánh đã tiếp nhận và kết quả xử lý.
            Gửi phản ánh mới tại <a href="{{ route('fp.reflections.create') }}">đây</a>.
        </p>

        @forelse($reflections as $r)
            <article class="rfl">
                <div class="rfl-top">
                    <h2 class="rfl-subject">{{ $r->subject }}</h2>
                    <span class="rfl-badge rfl-badge--{{ $r->status }}">{{ $r->statusLabel() }}</span>
                </div>
                <div class="rfl-meta">
                    <span>{{ $r->organization_name }}</span>
                    <span aria-hidden="true">·</span>
                    <span>Tiếp nhận {{ $r->received_at->format('d/m/Y') }}</span>
                    <span aria-hidden="true">·</span>
                    <code>{{ $r->code }}</code>
                </div>
                <p class="rfl-content">{{ $r->content }}</p>
                @if($r->resolution)
                    <div class="rfl-res">
                        <h3>Kết quả xử lý</h3>
                        <p>{{ $r->resolution }}</p>
                        @if($r->resolved_at)
                            <small>Ngày xử lý: {{ $r->resolved_at->format('d/m/Y') }}</small>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="rfl-none">
                <p>Chưa có phản ánh nào được công bố.</p>
            </div>
        @endforelse

        {{ $reflections->links('frontpage.partials.pagination') }}
    </div>
</section>
@endsection
