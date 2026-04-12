@extends('frontpage.layouts.app', ['activeNav' => 'dashboard', 'bodyClass' => ''])

@section('title', 'Cài đặt | OOHX')

@section('content')
<div class="w" style="padding-top:24px;padding-bottom:64px;max-width:640px">

    <h1 class="buyer-welcome-title" style="margin-bottom:24px">Cài đặt</h1>

    @if(session('success'))
    <div class="cart-alert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="auth-error" style="margin-bottom:16px">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    {{-- Profile --}}
    <div class="wz-card" style="margin-bottom:16px">
        <div class="wz-card-title">Thông tin cá nhân</div>
        <form method="POST" action="{{ route('buyer.settings.profile') }}" class="wz-form">
            @csrf @method('PUT')
            <div class="wz-field">
                <label>Họ và tên</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
            </div>
            <div class="wz-field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
            </div>
            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-p btn-sm">Lưu</button>
            </div>
        </form>
    </div>

    {{-- Password --}}
    <div class="wz-card" style="margin-bottom:16px">
        <div class="wz-card-title">Đổi mật khẩu</div>
        <form method="POST" action="{{ route('buyer.settings.password') }}" class="wz-form">
            @csrf @method('PUT')
            <div class="wz-field">
                <label>Mật khẩu hiện tại</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="wz-field">
                <label>Mật khẩu mới</label>
                <input type="password" name="password" required placeholder="Tối thiểu 8 ký tự">
            </div>
            <div class="wz-field">
                <label>Xác nhận mật khẩu mới</label>
                <input type="password" name="password_confirmation" required>
            </div>
            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-s btn-sm">Đổi mật khẩu</button>
            </div>
        </form>
    </div>

    {{-- Organization --}}
    <div class="wz-card">
        <div class="wz-card-title">Thông tin tổ chức</div>
        <form method="POST" action="{{ route('buyer.settings.organization') }}" class="wz-form">
            @csrf @method('PUT')
            <div class="wz-field">
                <label>Tên công ty / Agency</label>
                <input type="text" name="name" value="{{ old('name', $org->name) }}" required>
            </div>
            <div class="wz-row">
                <div class="wz-field">
                    <label>Email thanh toán</label>
                    <input type="email" name="billing_email" value="{{ old('billing_email', $org->billing_email) }}" placeholder="billing@company.com">
                </div>
                <div class="wz-field">
                    <label>Số điện thoại</label>
                    <input type="text" name="billing_phone" value="{{ old('billing_phone', $org->billing_phone) }}" placeholder="024-xxxx-xxxx">
                </div>
            </div>
            <div class="wz-row">
                <div class="wz-field">
                    <label>Mã số thuế</label>
                    <input type="text" name="tax_id" value="{{ old('tax_id', $org->tax_id) }}" placeholder="0123456789">
                </div>
                <div class="wz-field">
                    <label>Website</label>
                    <input type="url" name="website" value="{{ old('website', $org->website) }}" placeholder="https://company.com">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="btn btn-p btn-sm">Lưu</button>
            </div>
        </form>
    </div>
</div>
@endsection
