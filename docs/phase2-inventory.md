# Phase 2 — Inventory API
> **Status:** 🔲 Chưa bắt đầu
> **Cập nhật:** 2026-03-21
> **Depends on:** Phase 1 (token auth)

## Mục tiêu
Expose inventory màn hình DOOH ra ngoài cho OOHX frontend. Data đã có sẵn trong DB — phase này chỉ cần **transform đúng schema TapON spec** và thêm webhook subscription.

---

## Endpoints sẽ build

```
GET  /api/v1/inventory/screens            — danh sách màn hình (có filter + phân trang)
GET  /api/v1/inventory/screens/:screen_id — chi tiết 1 màn hình
POST /api/v1/inventory/webhook/register   — đăng ký webhook nhận inventory update
```

Tất cả 3 routes đều yêu cầu `auth:sanctum` + scope `inventory`.

---

## Mapping DB → TapON Schema

| TapON field | Nguồn trong DB |
|---|---|
| `screen_id` | `screens.uuid` |
| `name` | `screens.name` |
| `owner_id` | `screens.owner_id` |
| `owner_name` | `owners.name` |
| `screen_type` | `screen_inventory.venue_type` → detect từ specs (logic bên dưới) |
| `venue_type` | `screen_inventory.venue_type` |
| `location.address` | `sites.address` |
| `location.city` | `sites.city` |
| `location.district` | `sites.region` |
| `location.lat` | `sites.lat` |
| `location.lng` | `sites.lon` |
| `specs.width_px` | `screen_specs.width_px` |
| `specs.height_px` | `screen_specs.height_px` |
| `specs.width_m` | `screen_specs.width_cm / 100` |
| `specs.height_m` | `screen_specs.height_cm / 100` |
| `specs.orientation` | `screen_specs.orientation` (computed attribute) |
| `specs.resolution` | `screen_specs.resolution_preset` |
| `slot_duration_sec` | `screen_inventory.spot_length` |
| `slots_per_loop` | `screen_inventory.loop_length / spot_length` (tính toán) |
| `operating_hours` | `screen_inventory.operating_hours` (transform format) |
| `price_per_slot_vnd` | `screen_inventory.floor_cpm` (VND) |
| `min_booking_days` | mặc định 7 (configurable) |
| `photos` | `screen_specs.photo_url` → wrap thành array |
| `status` | `screens.active` → `active/inactive` |
| `updated_at` | `screens.updated_at` |

---

## Files sẽ tạo

```
app/Http/Controllers/Api/V1/InventoryController.php
app/Http/Resources/Api/V1/ScreenResource.php
app/Http/Resources/Api/V1/ScreenCollection.php
app/Models/WebhookSubscription.php
app/Observers/ScreenObserver.php
app/Jobs/SendWebhookJob.php
app/Http/Controllers/Api/V1/WebhookController.php
app/Http/Requests/RegisterWebhookRequest.php
database/migrations/xxxx_create_webhook_subscriptions_table.php
```

---

## Chi tiết từng file

### `InventoryController.php`

#### `GET /v1/inventory/screens`

**Query params hỗ trợ:**
```
page=1
limit=50            (max 100)
city=hanoi|hcm|danang
venue_type=mall|outdoor|fnb|transit|office
screen_type=lcd|led|billboard
status=active|inactive
updated_after=2026-01-01T00:00:00Z
```

**Logic:**
1. Query `Screen` với eager load: `owner`, `site`, `spec`, `inventory`
2. Apply filters:
   - `city` → `sites.city LIKE %value%`
   - `venue_type` → `screen_inventory.venue_type`
   - `status` → `screens.active`
   - `updated_after` → `screens.updated_at > ?`
   - `screen_type` → detect từ `screen_specs` (xem bên dưới)
3. Paginate theo `limit` (default 50, max 100)
4. Transform qua `ScreenCollection` → `ScreenResource`

**Response 200:**
```json
{
  "total": 1240,
  "page": 1,
  "limit": 50,
  "data": [ ...ScreenResource... ]
}
```

---

#### `GET /v1/inventory/screens/:screen_id`

- `screen_id` map với `screens.uuid`
- 404 đúng format nếu không tìm thấy:
```json
{
  "error": "screen_not_found",
  "message": "Screen SCR-HN-001 does not exist"
}
```

---

### `ScreenResource.php`

Transform 1 Screen model → TapON schema. Các điểm cần xử lý:

**operating_hours transform:**
DB lưu dạng:
```json
{ "mon": { "open": "08:00", "close": "22:00" }, "tue": "closed" }
```
TapON cần:
```json
{ "open": "08:00", "close": "22:00", "days": ["mon","tue","wed"] }
```
Logic: lấy giờ open/close từ ngày đầu tiên không `closed`, gom các ngày không `closed` vào `days[]`.

**screen_type detect:**
- Nếu `width_cm >= 300` hoặc `height_cm >= 300` → `billboard`
- Nếu `venue_type` là `outdoor` → `led`
- Còn lại → `lcd`

**slots_per_loop:**
- Nếu `spot_length` > 0: `(int) round(loop_length / spot_length)`
- Fallback: 8

**price_per_slot_vnd:**
- Lấy `floor_cpm` (đã lưu VND trong DB)
- Nếu null → 0

**photos:**
- `photo_url` là string → wrap thành `[$photo_url]` nếu không null, còn không thì `[]`

---

### `WebhookSubscription` model + migration

**Migration `webhook_subscriptions`:**
```php
$table->id();
$table->string('url');
$table->json('events');           // ["screen.created","screen.updated","screen.deactivated"]
$table->string('secret');         // plain text — dùng để ký HMAC
$table->string('webhook_id')->unique(); // "WH-xxx"
$table->enum('status', ['active','inactive'])->default('active');
$table->timestamps();
```

**Response khi đăng ký:**
```json
{
  "webhook_id": "WH-001",
  "status": "active"
}
```

---

### `ScreenObserver.php`

Lắng nghe 3 events:
- `created` → dispatch `SendWebhookJob` với event `screen.created`
- `updated` → detect `$screen->getDirty()` → dispatch `screen.updated` với `changed_fields`
- `deleted` (soft delete) → dispatch `screen.deactivated`

Đăng ký Observer trong `AppServiceProvider::boot()`.

---

### `SendWebhookJob.php`

**Payload gửi đi:**
```json
{
  "event": "screen.updated",
  "timestamp": "2026-03-15T08:30:00Z",
  "data": {
    "screen_id": "uuid-xxx",
    "changed_fields": ["price_per_slot_vnd", "status"]
  }
}
```

**Logic:**
1. Lấy tất cả `WebhookSubscription` có `status=active` và `events` chứa event này
2. Với mỗi subscriber: ký payload bằng `HMAC-SHA256` với `subscription.secret`
3. POST payload đến `subscription.url` với header `X-TapON-Signature: sha256=<hmac>`
4. Timeout 10s, retry 3 lần với exponential backoff nếu fail

---

### Routes thêm vào `routes/api.php`

```php
Route::prefix('v1')->middleware(['auth:sanctum', 'ability:inventory'])->group(function () {
    // Inventory
    Route::get('inventory/screens',             [InventoryController::class, 'index']);
    Route::get('inventory/screens/{screen_id}', [InventoryController::class, 'show']);
    Route::post('inventory/webhook/register',   [WebhookController::class, 'register']);
});
```

---

## Test cases

- [ ] `GET /v1/inventory/screens` không có token → 401
- [ ] `GET /v1/inventory/screens` token thiếu scope `inventory` → 403
- [ ] `GET /v1/inventory/screens` → 200, đúng schema TapON
- [ ] `GET /v1/inventory/screens?city=hanoi` → chỉ trả screens ở Hà Nội
- [ ] `GET /v1/inventory/screens?status=active` → chỉ trả screens active
- [ ] `GET /v1/inventory/screens?limit=5` → trả đúng 5 items, total đúng
- [ ] `GET /v1/inventory/screens/:uuid` → 200 đúng schema
- [ ] `GET /v1/inventory/screens/invalid-id` → 404 đúng format
- [ ] `POST /v1/inventory/webhook/register` → 200, nhận `webhook_id`
- [ ] Screen update → webhook được gửi đến URL đã đăng ký

---

## Checklist hoàn thành

- [ ] Migration `webhook_subscriptions` chạy thành công
- [ ] `GET /v1/inventory/screens` trả đúng TapON schema
- [ ] `GET /v1/inventory/screens/:id` hoạt động
- [ ] Filter `city`, `venue_type`, `status`, `updated_after` hoạt động
- [ ] Phân trang đúng
- [ ] Webhook register lưu vào DB
- [ ] `ScreenObserver` dispatch job khi screen thay đổi
- [ ] `SendWebhookJob` ký HMAC và POST đúng payload
