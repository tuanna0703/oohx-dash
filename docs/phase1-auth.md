# Phase 1 — Authentication
> **Status:** ✅ Hoàn thành — 2026-03-21
> **Cập nhật:** 2026-03-21

## Mục tiêu
Implement `POST /v1/auth/token` theo OAuth2 `client_credentials` flow để OOHX frontend lấy được Bearer token gọi các API còn lại.

---

## Bối cảnh & quyết định thiết kế

Hiện tại Laravel Sanctum đã có sẵn (`personal_access_tokens` table). Thay vì cài thêm `laravel/passport` (nặng, phức tạp hơn), ta sẽ **build lightweight client_credentials endpoint trên Sanctum**:

- Lưu `client_id` + `client_secret` (hashed) vào bảng `api_clients` mới
- Khi OOHX POST lên `/v1/auth/token`, validate credentials → tạo Sanctum token → trả về
- Token có TTL 1 giờ (tự động revoke bằng Sanctum `expiration` config)
- `scope` được lưu vào `abilities` của Sanctum token

---

## Database

### Migration: `create_api_clients_table`
```php
Schema::create('api_clients', function (Blueprint $table) {
    $table->id();
    $table->string('client_id')->unique();
    $table->string('client_secret'); // bcrypt hashed
    $table->string('name');          // mô tả, vd: "OOHX Marketplace"
    $table->json('scopes');          // ["inventory","booking","reporting"]
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

---

## Files sẽ tạo

```
app/Http/Controllers/Api/V1/AuthController.php
app/Http/Requests/AuthTokenRequest.php
app/Models/ApiClient.php
app/Http/Middleware/CheckTokenAbility.php
database/migrations/xxxx_create_api_clients_table.php
database/seeders/ApiClientSeeder.php
config/tapon.php                        (token TTL, allowed scopes)
```

---

## Chi tiết từng file

### `AuthController.php`
```
Method: POST /v1/auth/token
Auth:   Public (không cần token)
```

**Logic:**
1. Validate request body (xem Request bên dưới)
2. Tìm `ApiClient` theo `client_id`
3. Nếu không tìm thấy hoặc `!Hash::check($client_secret, $client->client_secret)` → 401
4. Nếu `client->active === false` → 401
5. Revoke token cũ của client này (tránh token tích lũy)
6. Tạo Sanctum token mới với `abilities = scopes` từ request (intersect với `client->scopes`)
7. Trả về response

**Response 200:**
```json
{
  "access_token": "<sanctum_token>",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope": "inventory booking reporting"
}
```

**Response 401:**
```json
{
  "error": "invalid_client",
  "error_description": "Client authentication failed"
}
```

---

### `AuthTokenRequest.php`
**Rules:**
```php
'client_id'     => ['required', 'string'],
'client_secret' => ['required', 'string'],
'grant_type'    => ['required', 'in:client_credentials'],
'scope'         => ['sometimes', 'string'],  // space-separated
```

---

### `ApiClient.php` (Model)
- `$hidden = ['client_secret']`
- Method `hasScope(string $scope): bool`
- Method `validateSecret(string $plain): bool` → `Hash::check()`

---

### `CheckTokenAbility.php` (Middleware)
- Dùng cho các route cần kiểm tra scope cụ thể
- Wrap Sanctum `checkAbilities` / `checkForAnyAbility`
- Trả 403 `forbidden` nếu token không có đủ ability

**Cách dùng trên route:**
```php
Route::middleware(['auth:sanctum', 'ability:inventory'])->group(...)
Route::middleware(['auth:sanctum', 'ability:booking'])->group(...)
```

---

### `ApiClientSeeder.php`
Seed 1 client cho OOHX:
```php
ApiClient::create([
    'client_id'     => 'oohx_marketplace',
    'client_secret' => Hash::make(env('OOHX_CLIENT_SECRET')),
    'name'          => 'OOHX Marketplace',
    'scopes'        => ['inventory', 'booking', 'reporting'],
    'active'        => true,
]);
```

---

### `config/tapon.php`
```php
return [
    'token_ttl_minutes' => env('TAPON_TOKEN_TTL', 60),
    'allowed_scopes'    => ['inventory', 'booking', 'reporting'],
];
```

---

## Routes thêm vào `routes/api.php`
```php
// Public — no auth
Route::prefix('v1')->group(function () {
    Route::post('auth/token', [AuthController::class, 'token']);
});
```

---

## Sanctum config
Thêm vào `config/sanctum.php`:
```php
'expiration' => 60, // minutes — token hết hạn sau 1 giờ
```

---

## Error response chuẩn
Tất cả error trong Phase 1 trả theo format:
```json
{
  "error": "invalid_client",
  "error_description": "Client authentication failed"
}
```
*(Khác với format chuẩn của các phase sau — auth error dùng OAuth2 format)*

---

## Test cases
- [ ] `POST /v1/auth/token` với đúng credentials → 200, nhận token hợp lệ
- [ ] `POST /v1/auth/token` với sai `client_secret` → 401
- [ ] `POST /v1/auth/token` với `client_id` không tồn tại → 401
- [ ] `POST /v1/auth/token` với `grant_type` khác `client_credentials` → 422
- [ ] Client bị `active=false` → 401
- [ ] Token nhận được gọi được `GET /v1/inventory/screens` (Phase 2) → 200
- [ ] Token hết hạn sau 1 giờ → 401

---

## Checklist hoàn thành
- [x] Migration chạy thành công
- [x] Seeder seed được ApiClient
- [x] `POST /v1/auth/token` trả đúng schema
- [x] Middleware `CheckTokenAbility` hoạt động đúng
- [x] Sanctum expiration config đã set
- [ ] Feature test pass — chưa viết test
