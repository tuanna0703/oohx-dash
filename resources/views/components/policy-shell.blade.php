@props(['page'])

{{--
    Khung chung cho các trang chính sách bắt buộc của sàn TMĐT.

    Dùng:
        <x-policy-shell :page="$page">
            ...nội dung chính sách...
        </x-policy-shell>
--}}
<section class="pol">
    <div class="w pol-w">
        <nav class="pol-crumb" aria-label="Breadcrumb">
            <a href="{{ route('fp.index') }}">Trang chủ</a>
            <span aria-hidden="true">/</span>
            <span>{{ $page['title'] }}</span>
        </nav>

        <h1 class="pol-h1">{{ $page['title'] }}</h1>

        <div class="pol-meta">
            <span>Phiên bản {{ $page['version'] }}</span>
            @if(! empty($page['effective_from']))
                <span aria-hidden="true">·</span>
                <span>Hiệu lực từ {{ $page['effective_from'] }}</span>
            @endif
        </div>

        @if(empty($page['effective_from']))
            <div class="pol-draft" role="note">
                <strong>Văn bản đang hoàn thiện.</strong>
                Nội dung dưới đây là bản nháp và chưa có hiệu lực áp dụng. Bản chính
                thức sẽ được công bố tại đúng địa chỉ này. Trong thời gian chờ, mọi
                thắc mắc xin liên hệ
                <a href="mailto:{{ config('policies.company.email') }}">{{ config('policies.company.email') }}</a>
                hoặc hotline {{ config('policies.company.hotline') }}.
            </div>
        @endif

        <article class="pol-body">
            {{ $slot }}
        </article>
    </div>
</section>
