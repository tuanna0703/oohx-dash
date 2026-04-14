@extends('frontpage.layouts.app', ['activeNav' => '', 'bodyClass' => 'page-auth', 'hideFooter' => true])

@section('title', 'Đăng nhập | OOHX')

@section('content')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <h1 class="auth-title">Đăng nhập</h1>
            <p class="auth-sub">Đăng nhập để quản lý campaign và booking</p>
        </div>

        @if($errors->any())
        <div class="auth-error">
            @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
            @endforeach
        </div>
        @endif

        <form method="POST" action="/login" class="auth-form">
            @csrf
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="you@company.com">
            </div>
            <div class="auth-field">
                <label for="password">Mật khẩu</label>
                <input type="password" id="password" name="password" required placeholder="Mật khẩu của bạn">
            </div>
            <div class="auth-row">
                <label class="auth-check">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                    <span>Nhớ đăng nhập</span>
                </label>
                <a href="#" class="auth-link">Quên mật khẩu?</a>
            </div>
            <button type="submit" class="btn btn-p auth-submit">Đăng nhập</button>
        </form>

        <div class="auth-footer">
            Chưa có tài khoản? <a href="{{ route('buyer.register') }}">Đăng ký miễn phí</a>
        </div>
    </div>
</div>
@endsection
