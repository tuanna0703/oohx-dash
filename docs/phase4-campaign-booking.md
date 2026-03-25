# Phase 4 — Campaign & Booking
> **Status:** 🔲 Chưa bắt đầu
> **Phụ thuộc:** Phase 1, 2, 3 hoàn thành
> **Cập nhật:** 2026-03-21

## Mục tiêu
OOHX frontend tạo campaign, upload creative, đặt booking màn hình, theo dõi trạng thái và huỷ nếu cần. Admin trong Filament duyệt/từ chối booking và OOHX nhận notification qua webhook.

---

## Bối cảnh & quyết định thiết kế

- **Campaign** = đầu mục của 1 chiến dịch quảng cáo (1 advertiser, 1 time range, 1 budget)
- **Creative** = file video/ảnh gắn với campaign
- **Booking** = 1 lần đặt lịch cụ thể (1 campaign + 1 creative + N màn hình)
- **BookingLineItem** = từng màn hình trong booking

**Luồng chính:**
```
OOHX tạo Campaign
    → Upload Creative
    → POST /bookings (chọn screens + date range)
    → status = pending_approval
    → Admin trong Filament Approve / Reject
    → webhook gửi về OOHX với new_status
    → (nếu approved) status chuyển running khi đến ngày
    → status chuyển completed khi hết ngày
```

---

## Database

### Migration: `create_campaigns_table`
```php
Schema::create('campaigns', function (Blueprint $table) {
    $table->id();
    $table->string('campaign_id')->unique(); // "CMP-2026-001", auto-generate
    $table->foreignId('api_client_id')->constrained('api_clients');
    $table->string('name');
    $table->string('advertiser_name');
    $table->string('advertiser_industry')->nullable();
    $table->string('advertiser_contact_email');
    $table->date('date_from');
    $table->date('date_to');
    $table->unsignedBigInteger('budget_vnd');
    $table->string('objective')->nullable(); // brand_awareness, etc.
    $table->json('target_cities')->nullable();
    $table->json('target_venue_types')->nullable();
    $table->text('notes')->nullable();
    $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
    $table->timestamps();
    $table->softDeletes();
});
```

### Migration: `create_creatives_table`
```php
Schema::create('creatives', function (Blueprint $table) {
    $table->id();
    $table->string('creative_id')->unique(); // "CRE-001", auto-generate
    $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
    $table->string('url');                   // CDN URL sau khi upload
    $table->string('storage_path');          // internal path
    $table->enum('creative_type', ['video', 'image']);
    $table->unsignedSmallInteger('duration_sec')->nullable();
    $table->unsignedSmallInteger('width_px')->nullable();
    $table->unsignedSmallInteger('height_px')->nullable();
    $table->decimal('file_size_mb', 8, 2)->nullable();
    $table->string('format')->nullable();    // mp4, jpg, png
    $table->enum('status', ['processing', 'ready', 'rejected'])->default('processing');
    $table->string('rejection_reason')->nullable();
    $table->json('raw_meta')->nullable();    // metadata từ ffprobe/exif
    $table->timestamps();
});
```

### Migration: `create_bookings_table`
```php
Schema::create('bookings', function (Blueprint $table) {
    $table->id();
    $table->string('booking_id')->unique(); // "BK-2026-001", auto-generate
    $table->foreignId('campaign_id')->constrained('campaigns');
    $table->foreignId('creative_id')->constrained('creatives');
    $table->enum('status', [
        'pending_approval','approved','rejected','running','completed','cancelled'
    ])->default('pending_approval');
    $table->enum('payment_method', ['bank_transfer','credit_card','wallet'])->nullable();
    $table->unsignedBigInteger('total_price_vnd')->default(0);
    $table->text('notes')->nullable();
    $table->string('cancel_reason')->nullable();
    $table->unsignedBigInteger('refund_vnd')->nullable();
    $table->string('idempotency_key')->unique()->nullable(); // tránh double submit
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
});
```

### Migration: `create_booking_line_items_table`
```php
Schema::create('booking_line_items', function (Blueprint $table) {
    $table->id();
    $table->string('line_item_id')->unique(); // "LI-001", auto-generate
    $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
    $table->foreignId('screen_id')->constrained('screens');
    $table->date('date_from');
    $table->date('date_to');
    $table->unsignedTinyInteger('slots_per_loop')->default(1);
    $table->json('time_range')->nullable(); // {"from":"08:00","to":"22:00"}
    $table->enum('status', [
        'pending','approved','rejected','running','completed','cancelled'
    ])->default('pending');
    $table->unsignedBigInteger('price_vnd')->default(0);
    $table->timestamps();
});
```

---

## Models

### `Campaign.php`
```php
// Relationships
hasMany(Booking::class)
hasMany(Creative::class)
belongsTo(ApiClient::class)

// Auto-generate campaign_id
protected static function booted(): void {
    static::creating(function ($m) {
        $m->campaign_id = 'CMP-' . now()->year . '-' . str_pad(static::count() + 1, 3, '0', STR_PAD_LEFT);
    });
}
```

### `Creative.php`
```php
belongsTo(Campaign::class)
hasMany(Booking::class)

// Scope
scopeReady($query) → where('status', 'ready')
```

### `Booking.php`
```php
belongsTo(Campaign::class)
belongsTo(Creative::class)
hasMany(BookingLineItem::class)

// Helpers
isEditable(): bool → status in ['pending_approval']
isCancellable(): bool → status in ['pending_approval', 'approved']
```

### `BookingLineItem.php`
```php
belongsTo(Booking::class)
belongsTo(Screen::class)
```

---

## Files sẽ tạo

```
app/Models/Campaign.php
app/Models/Creative.php
app/Models/Booking.php
app/Models/BookingLineItem.php
app/Http/Controllers/Api/V1/CampaignController.php
app/Http/Controllers/Api/V1/CreativeController.php
app/Http/Controllers/Api/V1/BookingController.php
app/Http/Requests/CreateCampaignRequest.php
app/Http/Requests/CreateBookingRequest.php
app/Http/Resources/Api/BookingResource.php
app/Services/BookingService.php
app/Jobs/ProcessCreativeJob.php
app/Filament/Resources/CampaignResource.php
app/Filament/Resources/BookingResource.php
database/migrations/xxxx_create_campaigns_table.php
database/migrations/xxxx_create_creatives_table.php
database/migrations/xxxx_create_bookings_table.php
database/migrations/xxxx_create_booking_line_items_table.php
```

---

## Chi tiết từng endpoint

### `POST /v1/campaigns`
**Auth:** `auth:sanctum` + scope `booking`

**Validation (`CreateCampaignRequest`):**
```php
'name'                     => ['required', 'string', 'max:255'],
'advertiser.name'          => ['required', 'string'],
'advertiser.industry'      => ['sometimes', 'string'],
'advertiser.contact_email' => ['required', 'email'],
'date_from'                => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
'date_to'                  => ['required', 'date_format:Y-m-d', 'after:date_from'],
'budget_vnd'               => ['required', 'integer', 'min:1000000'],
'objective'                => ['sometimes', 'string'],
'target_cities'            => ['sometimes', 'array'],
'target_venue_types'       => ['sometimes', 'array'],
'notes'                    => ['sometimes', 'nullable', 'string'],
```

**Logic:**
1. Tạo `Campaign` với `api_client_id = auth()->user()->id` (nếu dùng ApiClient làm user)
2. Trả `campaign_id` + `status: draft`

---

### `POST /v1/creatives/upload`
**Auth:** `auth:sanctum` + scope `booking`
**Content-Type:** `multipart/form-data`

**Validation:**
```php
'campaign_id'   => ['required', 'string', 'exists:campaigns,campaign_id'],
'file'          => ['required', 'file', 'max:51200', 'mimes:mp4,jpg,jpeg,png,gif'],
'creative_type' => ['required', 'in:video,image'],
'duration_sec'  => ['required_if:creative_type,video', 'integer', 'min:1', 'max:60'],
```

**Logic:**
1. Validate campaign thuộc về `api_client_id` hiện tại
2. Store file: `Storage::disk('s3')->put("creatives/{$creative_id}.{$ext}", $file)`
3. Tạo `Creative` với `status: processing`
4. Dispatch `ProcessCreativeJob` (đọc metadata, validate dimension/duration)
5. Trả ngay response với `status: processing`

---

### `ProcessCreativeJob.php`
**Queue:** `creatives`

**Logic:**
1. Đọc file metadata (ffprobe cho video, getimagesize cho image)
2. Validate: đúng dimension, duration không quá 60s, file không corrupt
3. Nếu ok: update `status = ready`, lưu `width_px`, `height_px`, `duration_sec`, `file_size_mb`, `format`
4. Nếu fail: update `status = rejected`, lưu `rejection_reason`

---

### `POST /v1/bookings`
**Auth:** `auth:sanctum` + scope `booking`

**Validation (`CreateBookingRequest`):**
```php
'campaign_id'                   => ['required', 'string', 'exists:campaigns,campaign_id'],
'creative_id'                   => ['required', 'string', 'exists:creatives,creative_id'],
'line_items'                    => ['required', 'array', 'min:1', 'max:20'],
'line_items.*.screen_id'        => ['required', 'string'],
'line_items.*.date_from'        => ['required', 'date_format:Y-m-d'],
'line_items.*.date_to'          => ['required', 'date_format:Y-m-d', 'after:line_items.*.date_from'],
'line_items.*.slots_per_loop'   => ['required', 'integer', 'min:1'],
'line_items.*.time_range'       => ['sometimes', 'array'],
'payment_method'                => ['required', 'in:bank_transfer,credit_card,wallet'],
'notes'                         => ['sometimes', 'nullable', 'string'],
```

**Logic trong `BookingService::create()`:**
1. Validate campaign + creative thuộc `api_client_id` hiện tại
2. Validate creative `status === 'ready'`
3. Resolve `screen_id` → `Screen` model (tìm theo `external_id`)
4. **Conflict check** (trong DB transaction với lock):
   ```sql
   SELECT COUNT(*) FROM booking_line_items bli
   JOIN bookings b ON b.id = bli.booking_id
   WHERE bli.screen_id = ?
     AND b.status IN ('pending_approval','approved','running')
     AND bli.date_from <= ? AND bli.date_to >= ?
   FOR UPDATE
   ```
   Nếu có conflict → 409 `conflict` error
5. Tính `price_vnd` mỗi line item: `slots_per_loop × days × price_per_slot_vnd`
6. Tính `total_price_vnd`
7. Tạo `Booking` + `BookingLineItem[]` trong transaction
8. Set `expires_at = now()->addDays(4)`
9. Dispatch `SendWebhookJob` với event `booking.status_changed` (pending_approval)

**Idempotency:** Header `X-Idempotency-Key` (optional) — nếu có, lưu vào `bookings.idempotency_key`, lần sau trả lại booking cũ thay vì tạo mới.

**Response 201:**
```json
{
  "booking_id": "BK-2026-001",
  "campaign_id": "CMP-2026-001",
  "status": "pending_approval",
  "total_price_vnd": 180000000,
  "line_items": [...],
  "created_at": "...",
  "expires_at": "..."
}
```

**Response 409 (conflict):**
```json
{
  "error": "conflict",
  "message": "Screen SCR-HN-001 is already booked for 2026-04-10",
  "code": 409
}
```

---

### `GET /v1/bookings/:booking_id`
**Auth:** `auth:sanctum` + scope `booking`

- Validate booking thuộc `api_client_id` hiện tại
- Trả `BookingResource` đầy đủ

---

### `POST /v1/bookings/:booking_id/cancel`
**Auth:** `auth:sanctum` + scope `booking`

**Request:** `{ "reason": "Budget cut" }`

**Logic:**
1. Validate booking thuộc client hiện tại
2. Check `isCancellable()` — nếu không → 422
3. Update `status = cancelled`, `cancel_reason`, `cancelled_at`
4. Update tất cả line items `status = cancelled`
5. Tính `refund_vnd` (logic: 100% refund nếu huỷ trước 48h, 50% nếu sau — cần confirm business rule)
6. Dispatch webhook `booking.status_changed`

---

## Filament Admin

### `BookingResource`
- **List:** booking_id, campaign, status (badge màu), total_price, created_at, expires_at
- **Detail:** đầy đủ thông tin + line items table
- **Actions:**
  - `Approve` → update status `approved`, update line items, gửi webhook
  - `Reject` → modal nhập lý do, update status `rejected`, gửi webhook
- **Filter:** theo status, date range, api_client

### `CampaignResource`
- **List:** campaign_id, name, advertiser, date range, budget, status
- **Detail:** thông tin campaign + related bookings

---

## Webhook payload khi booking thay đổi
Gửi đến tất cả `WebhookSubscription` có event `booking.status_changed` của `api_client_id` tương ứng:
```json
{
  "event": "booking.status_changed",
  "timestamp": "2026-03-22T09:00:00Z",
  "data": {
    "booking_id": "BK-2026-001",
    "old_status": "pending_approval",
    "new_status": "approved",
    "line_items": [
      { "line_item_id": "LI-001", "screen_id": "SCR-HN-001", "status": "approved" }
    ]
  }
}
```

---

## Routes thêm vào `routes/api.php`
```php
Route::prefix('v1')->middleware(['auth:sanctum', 'ability:booking'])->group(function () {
    Route::post('campaigns',                      [CampaignController::class, 'store']);
    Route::post('creatives/upload',               [CreativeController::class, 'upload']);
    Route::post('bookings',                       [BookingController::class, 'store']);
    Route::get('bookings/{booking_id}',           [BookingController::class, 'show']);
    Route::post('bookings/{booking_id}/cancel',   [BookingController::class, 'cancel']);
});
```

---

## Điểm cần confirm trước khi code
- [ ] Business rule refund khi huỷ booking (100% / 50% / 0%?)
- [ ] Ai trigger status `running` → `completed`? Scheduled job hay manual?
- [ ] `campaign_id` format: prefix `CMP-YYYY-` + sequential number có đúng không?
- [ ] File upload storage: S3 hay local? CDN URL pattern thế nào?
- [ ] Có cần approve từng `BookingLineItem` riêng lẻ hay approve cả `Booking`?

---

## Test cases
- [ ] `POST /v1/campaigns` tạo campaign thành công
- [ ] `POST /v1/creatives/upload` upload file → `status: processing` → Job chạy → `status: ready`
- [ ] `POST /v1/bookings` tạo booking thành công, line items đúng, giá đúng
- [ ] `POST /v1/bookings` conflict: screen đã book → 409
- [ ] `POST /v1/bookings` concurrent requests cùng screen → chỉ 1 thành công (race condition test)
- [ ] `POST /v1/bookings` với `creative_id` status `processing` → 422
- [ ] `POST /v1/bookings` với `X-Idempotency-Key` → retry trả booking cũ
- [ ] Admin Approve → status `approved`, webhook gửi đúng
- [ ] Admin Reject → status `rejected`, webhook gửi đúng
- [ ] `POST /v1/bookings/:id/cancel` khi status `completed` → 422
- [ ] Cancel refund tính đúng

---

## Checklist hoàn thành
- [ ] 4 migrations chạy thành công
- [ ] 4 models với relationships đúng
- [ ] `BookingService` conflict check hoạt động
- [ ] Creative upload + ProcessCreativeJob hoạt động
- [ ] Filament Approve/Reject action hoạt động
- [ ] Webhook gửi khi status thay đổi
- [ ] Idempotency key hoạt động
- [ ] Feature tests pass
