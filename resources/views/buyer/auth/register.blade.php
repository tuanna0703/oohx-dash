@extends('frontpage.layouts.app', ['activeNav' => '', 'bodyClass' => 'page-auth', 'hideFooter' => true])

@section('title', 'Đăng ký | OOHX')

@section('content')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Tạo tài khoản</h1>
            <p class="auth-sub">Đăng ký để bắt đầu booking OOH/DOOH inventory</p>
        </div>

        @if($errors->any())
        <div class="auth-error">
            @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('buyer.register') }}" class="auth-form">
            @csrf
            <div class="auth-field">
                <label for="name">Họ và tên</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus placeholder="Nguyễn Văn A">
            </div>
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="you@company.com">
            </div>
            <div class="auth-field">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" required placeholder="Tối thiểu 8 ký tự">
            </div>
            <div class="auth-field">
                <label for="password_confirmation">Xác nhận mật khẩu</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Nhập lại mật khẩu">
            </div>

            <div class="auth-divider"><span>Thông tin tổ chức</span></div>

            <div class="auth-field">
                <label for="organization_name">Tên công ty / Agency</label>
                <input type="text" id="organization_name" name="organization_name" value="{{ old('organization_name') }}" required placeholder="Công ty ABC">
            </div>
            <div class="auth-field">
                <label for="organization_type">Loại hình</label>
                <select id="organization_type" name="organization_type" required>
                    <option value="">Chọn loại hình</option>
                    <option value="agency" {{ old('organization_type') === 'agency' ? 'selected' : '' }}>Đại lý quảng cáo</option>
                    <option value="brand" {{ old('organization_type') === 'brand' ? 'selected' : '' }}>Nhãn hàng</option>
                    <option value="client" {{ old('organization_type') === 'client' ? 'selected' : '' }}>Khách hàng</option>
                </select>
            </div>

            <label class="consent">
                <input type="checkbox" name="accept_privacy" value="1"
                       {{ old('accept_privacy') ? 'checked' : '' }} required>
                <span>
                    Tôi đã đọc và đồng ý với
                    <a href="{{ route('fp.policy', 'chinh-sach-bao-mat') }}" target="_blank" rel="noopener">Chính sách bảo mật thông tin</a>
                </span>
            </label>

            <button type="submit" class="btn btn-p auth-submit">Tạo tài khoản</button>
        </form>

        <div class="auth-footer">
            Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a>
        </div>
    </div>
</div>
@endsection
