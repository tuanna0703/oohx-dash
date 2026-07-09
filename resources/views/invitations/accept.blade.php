<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lời mời tham gia {{ $tenant?->name ?? 'OOHX' }}</title>
    <style>
        :root { --primary: #2A4FF6; --bg: #f8fafc; --text: #1e293b; --muted: #64748b; --border: #e2e8f0; --error: #dc2626; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); padding: 2.5rem; max-width: 480px; width: 100%; }
        .header { text-align: center; margin-bottom: 2rem; }
        h1 { font-size: 1.5rem; margin: 0 0 0.5rem; }
        .sub { color: var(--muted); font-size: 0.95rem; margin: 0; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; background: rgba(42,79,246,0.1); color: var(--primary); font-size: 0.85rem; font-weight: 600; margin-top: 0.5rem; }
        .field { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; }
        input { width: 100%; padding: 0.7rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 1rem; }
        input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(42,79,246,0.15); }
        button { width: 100%; padding: 0.85rem; background: var(--primary); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #1d3acc; }
        .error { background: #fef2f2; color: var(--error); padding: 0.85rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; }
        .info { background: #eff6ff; color: #1e40af; padding: 0.85rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; }
        .meta { background: var(--bg); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .meta dt { color: var(--muted); margin-bottom: 0.15rem; }
        .meta dd { margin: 0 0 0.6rem; font-weight: 500; }
        .meta dd:last-child { margin-bottom: 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Lời mời tham gia</h1>
            <p class="sub"><strong>{{ $tenant?->name ?? 'tenant không xác định' }}</strong></p>
            <span class="badge">{{ $roleLabel }}</span>
        </div>

        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if ($isExistingUser)
            <div class="info">
                Email <strong>{{ $invitation->email }}</strong> đã có tài khoản OOHX. Bạn chỉ cần xác nhận và sẽ được thêm vào tenant này.
            </div>
        @endif

        <dl class="meta">
            <dt>Email</dt>
            <dd>{{ $invitation->email }}</dd>
            <dt>Hết hạn</dt>
            <dd>{{ $invitation->expires_at->format('d/m/Y H:i') }}</dd>
        </dl>

        <form method="POST" action="{{ route('invitations.accept.store', $invitation->token) }}">
            @csrf

            @unless ($isExistingUser)
                <div class="field">
                    <label for="name">Họ và tên</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Mật khẩu mới (tối thiểu 8 ký tự)</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>

                <div class="field">
                    <label for="password_confirmation">Xác nhận mật khẩu</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
                </div>
            @endunless

            <button type="submit">{{ $isExistingUser ? 'Chấp nhận lời mời' : 'Tạo tài khoản & tham gia' }}</button>
        </form>
    </div>
</body>
</html>
