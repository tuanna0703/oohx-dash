# Phase 2 — Inventory API
> **Status:** ✅ Hoàn thành — 2026-03-21
> **Phụ thuộc:** Phase 1 hoàn thành
> **Cập nhật:** 2026-03-21

## Mục tiêu
Expose danh sách màn hình DOOH và webhook subscription ra ngoài cho OOHX frontend. Data đã có sẵn trong DB — phase này chỉ build API layer và transform response đúng schema.

---

## Bối cảnh & quyết định thiết kế

- `Screen`, `ScreenSpec`, `ScreenInventory`, `Owner`, `Site` đã có đầy đủ
- `screen_id` trong TapON spec map với `screens.external_id` (đã có field này)
- Nếu `external_id` null thì dùng `screens.uuid` làm fallback
- Webhook subscription lưu local — khi screen thay đổi, `ScreenObserver` dispatch job gửi đi

---

## Database

### Migration: `create_webhook_subscriptions_table`
```php
Schema::create('webhook_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->string('webhook_id')->unique(); // "WH-001", auto-generate
    $table->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
    $table->string('url');
    $table->json('events');   // ["screen.created","screen.updated","screen.deactivated"]
    $table->string('secret'); // plain text — dùng để ký payload, OOHX giữ để verify
    $table->enum('status', ['active', 'inactive'])->default('active');
    $table->timestamps();
});
```

---

## Files sẽ tạo

```
app/Http/Controllers/Api/V1/InventoryController.php
app/Http/Controllers/Api/V1/WebhookController.php
app/Http/Resources/Api/ScreenResource.php
app/Http/Resources/Api/ScreenCollection.php
app/Models/WebhookSubscription.php
app/Observers/ScreenObserver.php
app/Jobs/SendWebhookJob.php
```

---

## Chi tiết từng file

### `InventoryController.php`

#### `GET /v1/inventory/screens`
**Auth:** `auth:sanctum` + scope `inventory`

**Query params được hỗ trợ:**
| Param | Type | Map tới |
|---|---|---|
| `page` | int | paginate |
| `limit` | int, max 100, default 50 | paginate |
| `city` | string (pipe-separated) | `screen_inventory.city` |
| `venue_type` | string (pipe-separated) | `screen_inventory.venue_type` |
| `screen_type` | string (pipe-separated) | `screen_specs.screen_type` |
| `status` | `active\|inactive` | `screens.active` |
| `updated_after` | ISO8601 datetime | `screens.updated_at` |

**Logic:**
```
Screen::with(['spec', 'inventory', 'owner', 'site'])
    ->filter($request)
    ->paginate($limit)
```

**Response 200:**
```json
{
  "total": 1240,
  "page": 1,
  "limit": 50,
  "data": [ <ScreenResource>... ]
}
```

---

#### `GET /v1/inventory/screens/:screen_id`
**Auth:** `auth:sanctum` + scope `inventory`

- Tìm theo `external_id = $screen_id` trước, fallback `uuid = $screen_id`
- Nếu không tìm thấy → 404 `screen_not_found`

**Response 404:**
```json
{
  "error": "screen_not_found",
  "message": "Screen SCR-HN-001 does not exist"
}
```

---

### `ScreenResource.php`
Transform `Screen` model → TapON screen schema:

```php
[
    'screen_id'          => $this->external_id ?? $this->uuid,
    'name'               => $this->name,
    'owner_id'           => $this->owner->external_id ?? 'OWN-'.$this->owner_id,
    'owner_name'         => $this->owner->name,
    'screen_type'        => $this->spec->screen_type,
    'venue_type'         => $this->inventory->venue_type,
    'location'           => [
        'address'  => $this->site->address,
        'city'     => $this->inventory->city,
        'district' => $this->site->district,
        'lat'      => $this->site->lat,
        'lng'      => $this->site->lng,
    ],
    'specs'              => [
        'width_px'    => $this->spec->width_px,
        'height_px'   => $this->spec->height_px,
        'width_m'     => $this->spec->width_m,
        'height_m'    => $this->spec->height_m,
        'orientation' => $this->spec->orientation,
        'resolution'  => $this->spec->resolution,
    ],
    'slot_duration_sec'  => $this->spec->slot_duration_sec,
    'slots_per_loop'     => $this->spec->slots_per_loop,
    'operating_hours'    => $this->inventory->operating_hours,
    'price_per_slot_vnd' => $this->inventory->price_per_slot_vnd,
    'min_booking_days'   => $this->inventory->min_booking_days ?? 7,
    'photos'             => $this->inventory->photos ?? [],
    'status'             => $this->active ? 'active' : 'inactive',
    'updated_at'         => $this->updated_at->toIso8601String(),
]
```

> **Lưu ý:** Một số field (`district`, `lat`, `lng`, `resolution`...) cần verify xem đang nằm ở column nào trong `sites` / `screen_specs` / `screen_inventory`. Sẽ confirm khi đọc migration trước khi code.

---

### `WebhookController.php`

#### `POST /v1/inventory/webhook/register`
**Auth:** `auth:sanctum` + scope `inventory`

**Request body:**
```json
{
  "url": "https://oohx.net/api/webhooks/tapon/inventory",
  "events": ["screen.created", "screen.updated", "screen.deactivated"],
  "secret": "OOHX_WEBHOOK_SECRET"
}
```

**Validation:**
- `url`: required, url format, reachable (optional: HTTP HEAD check)
- `events`: array, each item in `['screen.created','screen.updated','screen.deactivated']`
- `secret`: required, min 16 chars

**Logic:**
1. Upsert `WebhookSubscription` theo `api_client_id` + `url` (1 client 1 URL = 1 subscription)
2. Generate `webhook_id` = `'WH-' . strtoupper(Str::random(6))`
3. Trả về `webhook_id` + `status`

**Response 200:**
```json
{
  "webhook_id": "WH-A3F9K2",
  "status": "active"
}
```

---

### `ScreenObserver.php`
Đăng ký trong `AppServiceProvider`:
```php
Screen::observe(ScreenObserver::class);
```

**Events lắng nghe:**
| Eloquent event | Webhook event |
|---|---|
| `created` | `screen.created` |
| `updated` | `screen.updated` |
| `deleted` / `active→false` | `screen.deactivated` |

**Logic trong mỗi event:**
```php
WebhookSubscription::active()
    ->whereJsonContains('events', 'screen.updated')
    ->get()
    ->each(fn($sub) => SendWebhookJob::dispatch($sub, 'screen.updated', [
        'screen_id'     => $screen->external_id ?? $screen->uuid,
        'changed_fields' => array_keys($screen->getDirty()),
    ]));
```

---

### `SendWebhookJob.php`
**Queue:** `webhooks` (tách riêng để không ảnh hưởng main queue)

**Logic:**
1. Build payload:
   ```json
   {
     "event": "screen.updated",
     "timestamp": "<now ISO8601>",
     "data": { ... }
   }
   ```
2. Ký payload: `hash_hmac('sha256', json_encode($payload), $subscription->secret)`
3. HTTP POST đến `$subscription->url` với header:
   ```
   X-TapOn-Signature: sha256=<hmac>
   X-TapOn-Event: screen.updated
   Content-Type: application/json
   ```
4. Nếu response không phải 2xx: retry 3 lần với backoff 30s / 5m / 30m
5. Sau 3 lần fail: log error, mark subscription `status=inactive`

---

## Routes thêm vào `routes/api.php`
```php
Route::prefix('v1')->middleware(['auth:sanctum', 'ability:inventory'])->group(function () {
    Route::get('inventory/screens',              [InventoryController::class, 'index']);
    Route::get('inventory/screens/{screen_id}',  [InventoryController::class, 'show']);
    Route::post('inventory/webhook/register',    [WebhookController::class, 'register']);
});
```

---

## Mapping field cần verify trước khi code

Trước khi triển khai, đọc lại các migration để confirm field nằm ở đâu:

| TapON field | Model cần verify |
|---|---|
| `location.district` | `sites` hay `screen_inventory`? |
| `location.lat`, `location.lng` | `sites`? |
| `specs.width_m`, `height_m` | `screen_specs`? |
| `specs.resolution` | `screen_specs`? |
| `operating_hours` format | `screen_inventory.operating_hours` — JSON structure? |
| `photos` | `screen_inventory` hay bảng riêng? |
| `min_booking_days` | có column này chưa? |

---

## Test cases
- [ ] `GET /v1/inventory/screens` không có token → 401
- [ ] `GET /v1/inventory/screens` với token scope `booking` (không có `inventory`) → 403
- [ ] `GET /v1/inventory/screens` trả đúng schema, pagination đúng
- [ ] Filter `?city=hanoi` chỉ trả screens ở Hà Nội
- [ ] Filter `?status=active` không trả screens inactive
- [ ] Filter `?updated_after=2026-03-01T00:00:00Z` chỉ trả screens update sau ngày đó
- [ ] `GET /v1/inventory/screens/SCR-HN-001` trả đúng screen
- [ ] `GET /v1/inventory/screens/NOT_EXIST` → 404 đúng format
- [ ] `POST /v1/inventory/webhook/register` → lưu subscription, trả webhook_id
- [ ] Update screen trong DB → `ScreenObserver` dispatch `SendWebhookJob`
- [ ] `SendWebhookJob` gửi đúng payload với HMAC header

---

## Checklist hoàn thành
- [x] Migration webhook_subscriptions chạy thành công
- [x] `ScreenResource` transform đúng tất cả fields
- [x] Pagination hoạt động đúng
- [x] Tất cả filter params hoạt động
- [x] Webhook registration hoạt động
- [x] Observer + Job dispatch đúng
- [x] HMAC signature đúng
- [ ] Feature tests pass — chưa viết test
