@extends('frontpage.layouts.app')

@section('title', 'Tiếp nhận phản ánh của tổ chức xã hội – OOHX')

@section('content')
<section class="pol">
    <div class="w pol-w">
        <nav class="pol-crumb" aria-label="Breadcrumb">
            <a href="{{ route('fp.index') }}">Trang chủ</a>
            <span aria-hidden="true">/</span>
            <span>Tiếp nhận phản ánh của tổ chức xã hội</span>
        </nav>

        <h1 class="pol-h1">Tiếp nhận phản ánh của tổ chức xã hội</h1>
        <p class="pol-lead">
            OOHX tiếp nhận phản ánh của các tổ chức xã hội về hoạt động của sàn và về
            thông tin đăng tải trên sàn. Mọi phản ánh đều được ghi nhận, xử lý và
            công bố kết quả tại
            <a href="{{ route('fp.reflections.index') }}">danh sách phản ánh</a>.
        </p>

        @if(session('reflection_code'))
            <div class="pol-ok" role="status">
                <strong>Đã tiếp nhận phản ánh của quý tổ chức.</strong>
                Mã tra cứu: <code>{{ session('reflection_code') }}</code>.
                Chúng tôi sẽ phản hồi qua email liên hệ mà quý tổ chức đã cung cấp.
            </div>
        @endif

        @if($errors->any())
            <div class="pol-err" role="alert">
                <strong>Vui lòng kiểm tra lại các thông tin sau:</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('fp.reflections.store') }}" class="pol-form">
            @csrf

            {{-- Bẫy spam: người thật không thấy trường này nên không bao giờ điền. --}}
            <div class="pol-hp" aria-hidden="true">
                <label for="website">Website</label>
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="pol-f">
                <label for="organization_name">Tên tổ chức <span class="req">*</span></label>
                <input type="text" name="organization_name" id="organization_name"
                       value="{{ old('organization_name') }}" required maxlength="255">
            </div>

            <div class="pol-f">
                <label for="subject">Tiêu đề phản ánh <span class="req">*</span></label>
                <input type="text" name="subject" id="subject"
                       value="{{ old('subject') }}" required maxlength="255">
            </div>

            <div class="pol-f">
                <label for="content">Nội dung phản ánh <span class="req">*</span></label>
                <textarea name="content" id="content" rows="8" required
                          minlength="20" maxlength="5000">{{ old('content') }}</textarea>
                <small>Tối thiểu 20 ký tự. Nội dung này sẽ được công khai nếu phản ánh được đăng.</small>
            </div>

            <div class="pol-f2">
                <div class="pol-f">
                    <label for="contact_name">Người liên hệ</label>
                    <input type="text" name="contact_name" id="contact_name"
                           value="{{ old('contact_name') }}" maxlength="255">
                </div>
                <div class="pol-f">
                    <label for="contact_phone">Số điện thoại</label>
                    <input type="tel" name="contact_phone" id="contact_phone"
                           value="{{ old('contact_phone') }}" maxlength="30">
                </div>
            </div>

            <div class="pol-f">
                <label for="contact_email">Email liên hệ <span class="req">*</span></label>
                <input type="email" name="contact_email" id="contact_email"
                       value="{{ old('contact_email') }}" required maxlength="255">
                <small>
                    Thông tin liên hệ chỉ dùng để phản hồi kết quả xử lý và
                    <strong>không được công khai</strong>.
                </small>
            </div>

            <button type="submit" class="pol-submit">Gửi phản ánh</button>
        </form>
    </div>
</section>
@endsection
