<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lời mời không khả dụng</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); padding: 2.5rem; max-width: 460px; text-align: center; }
        h1 { font-size: 1.4rem; margin: 0 0 1rem; }
        p { color: #64748b; margin: 0 0 1.5rem; }
        a { color: #2A4FF6; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Lời mời không khả dụng</h1>
        <p>{{ $message }}</p>
        <p><a href="https://{{ config('domains.dash', 'dash.oohx.net') }}/admin/login">Tới trang đăng nhập</a></p>
    </div>
</body>
</html>
