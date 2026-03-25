# TapON SSP — API Build Roadmap
> AdTRUE SSP (Laravel 12) — xây dựng API cho OOHX Frontend

**Cập nhật lần cuối:** 2026-03-21
**Mục tiêu:** Build toàn bộ API endpoints trên `ssp.tapon.vn` để OOHX frontend tìm kiếm màn hình, check giá, tạo campaign và booking DOOH.

---

## Tổng quan kiến trúc

```
OOHX Frontend (oohx.net)
        │
        ▼ HTTPS / Bearer Token
AdTRUE SSP — ssp.tapon.vn/v1   ◄── đây là thứ ta build
        │
        ▼
Local Database (screens, campaigns, bookings, ...)
```

AdTRUE SSP **là** TapON SSP backend. Không có external API nào cần gọi ra ngoài — toàn bộ data (inventory, booking, reporting) nằm trong local DB đã có sẵn.

---

## Trạng thái hiện tại (đã có)

| Thành phần | Tình trạng |
|---|---|
| `Screen`, `ScreenSpec`, `ScreenInventory` models | ✅ Có sẵn |
| `Owner`, `Site` models | ✅ Có sẵn |
| Sanctum token auth | ✅ Có sẵn |
| Filament admin panel | ✅ Có sẵn |
| Player heartbeat / impression log | ✅ Có sẵn |
| OAuth2 `client_credentials` endpoint | ❌ Chưa có |
| Inventory API (expose screens ra ngoài) | ❌ Chưa có |
| Availability & Pricing API | ❌ Chưa có |
| Campaign / Booking / Creative models & API | ❌ Chưa có |
| Reporting API | ❌ Chưa có |

---

## Lộ trình triển khai

### Phase 1 — Authentication
> **Mục tiêu:** OOHX frontend lấy được Bearer token để gọi các API còn lại
> **Ước tính:** 1 ngày

#### Việc cần làm

- [ ] **1.1** Implement `POST /v1/auth/token` — OAuth2 `client_credentials` flow
  - Validate `client_id`, `client_secret` từ bảng `oauth_clients` (hoặc config)
  - Trả về `access_token` (JWT hoặc Sanctum token), `expires_in`, `scope`
  - Handle lỗi 401 `invalid_client`

- [ ] **1.2** Tạo middleware `CheckApiScope` — validate scope trên token (`inventory`, `booking`, `reporting`)

- [ ] **1.3** Lưu `client_id` / `client_secret` của OOHX vào DB hoặc config (seeder)

- [ ] **1.4** Test: gọi `POST /v1/auth/token` → nhận token → gọi được `GET /v1/inventory/screens`

#### Files tạo mới
```
app/Http/Controllers/Api/V1/AuthController.php
app/Http/Middleware/CheckApiScope.php
database/seeders/OauthClientSeeder.php
```

---

### Phase 2 — Inventory API
> **Mục tiêu:** OOHX frontend lấy được danh sách và chi tiết màn hình DOOH
> **Ước tính:** 1-2 ngày

Data đã có trong DB (`screens`, `screen_specs`, `screen_inventory`). Phase này chỉ cần **expose** ra theo đúng schema TapON spec.

#### Việc cần làm

- [ ] **2.1** `GET /v1/inventory/screens` — danh sách màn hình có phân trang
  - Query params: `page`, `limit`, `city`, `venue_type`, `screen_type`, `status`, `updated_after`
  - Map `Screen` + `ScreenSpec` + `ScreenInventory` + `Owner` → TapOnScreen response schema
  - Dùng `ScreenResource` (API Resource) để transform

- [ ] **2.2** `GET /v1/inventory/screens/:screen_id` — chi tiết 1 màn hình
  - `screen_id` map với `screens.external_id` hoặc `screens.uuid`
  - Trả 404 đúng format nếu không tìm thấy

- [ ] **2.3** `POST /v1/inventory/webhook/register` — OOHX đăng ký webhook nhận inventory update
  - Lưu URL + events + secret vào bảng `webhook_subscriptions`
  - Khi screen được update (observer), dispatch job gửi payload đến URL đã đăng ký
  - Ký payload bằng HMAC-SHA256 với secret

- [ ] **2.4** Migration: tạo bảng `webhook_subscriptions`
  ```
  id, url, events (json), secret, status, created_at
  ```

- [ ] **2.5** `ScreenObserver` — lắng nghe `created/updated/deleted`, dispatch `SendWebhookJob`

#### Files tạo mới
```
app/Http/Controllers/Api/V1/InventoryController.php
app/Http/Resources/Api/ScreenResource.php
app/Http/Resources/Api/ScreenCollection.php
app/Models/WebhookSubscription.php
app/Observers/ScreenObserver.php
app/Jobs/SendWebhookJob.php
database/migrations/xxxx_create_webhook_subscriptions_table.php
```

---

### Phase 3 — Availability & Pricing API
> **Mục tiêu:** OOHX check được slot trống và xem bảng giá trước khi booking
> **Ước tính:** 1-2 ngày

#### Việc cần làm

- [ ] **3.1** `POST /v1/availability/check`
  - Nhận `screen_ids[]`, `date_from`, `date_to`, `slots_per_day`, `time_range`
  - Query `booking_line_items` để tìm ngày đã bị book cho từng screen
  - Tính `available_slots`, `booked_dates`, `estimated_price_vnd`
  - Trả đúng schema spec (kể cả `available: false` nếu fully booked)

- [ ] **3.2** `GET /v1/pricing/rate-card`
  - Query params: `city`, `venue_type`, `screen_type`
  - Lấy từ `screen_inventory.price_per_slot_vnd` aggregate theo nhóm
  - Tính `price_per_day`, `price_per_week`, `price_per_month`, `peak_multiplier`
  - Cache 1 giờ

- [ ] **3.3** Request validation cho availability check:
  - `screen_ids`: array, min 1, max 50
  - `date_from` < `date_to`
  - `time_range.from` < `time_range.to` nếu có

#### Files tạo mới
```
app/Http/Controllers/Api/V1/AvailabilityController.php
app/Http/Controllers/Api/V1/PricingController.php
app/Http/Requests/CheckAvailabilityRequest.php
app/Services/AvailabilityService.php
app/Services/PricingService.php
```

---

### Phase 4 — Campaign & Booking
> **Mục tiêu:** OOHX tạo campaign, upload creative, đặt booking, huỷ booking
> **Ước tính:** 3-4 ngày

Đây là phase lớn nhất, cần tạo mới hoàn toàn domain Campaign / Booking.

#### Việc cần làm

- [ ] **4.1** Migrations:
  ```
  campaigns          — id, name, advertiser_name, advertiser_industry, advertiser_email,
                       date_from, date_to, budget_vnd, objective, target_cities (json),
                       target_venue_types (json), notes, status, created_by, timestamps
  creatives          — id, campaign_id, url, creative_type, duration_sec,
                       width_px, height_px, file_size_mb, format, status, timestamps
  bookings           — id, campaign_id, creative_id, status, total_price_vnd,
                       payment_method, notes, expires_at, approved_at, cancelled_at,
                       refund_vnd, timestamps
  booking_line_items — id, booking_id, screen_id, date_from, date_to,
                       slots_per_loop, time_range (json), status, price_vnd, timestamps
  ```

- [ ] **4.2** Models: `Campaign`, `Creative`, `Booking`, `BookingLineItem`
  - `Campaign` → hasMany `Booking`, hasMany `Creative`
  - `Booking` → belongsTo `Campaign`, belongsTo `Creative`, hasMany `BookingLineItem`
  - `BookingLineItem` → belongsTo `Booking`, belongsTo `Screen`
  - Status enum cho Booking: `pending_approval | approved | rejected | running | completed | cancelled`

- [ ] **4.3** `POST /v1/campaigns` — tạo campaign mới
  - Validate budget, date range, cities, venue_types
  - Trả về `campaign_id`, `status: draft`

- [ ] **4.4** `POST /v1/creatives/upload` — upload file quảng cáo
  - `multipart/form-data`: `campaign_id`, `file`, `creative_type`, `duration_sec`
  - Validate: max file size, đúng format (mp4/jpg/png), campaign phải tồn tại
  - Lưu file vào storage, trả về URL
  - Status ban đầu: `processing` → queue job chuyển sang `ready` sau khi validate xong

- [ ] **4.5** `POST /v1/bookings` — đặt booking
  - Validate: campaign + creative tồn tại, screen_ids valid, date range hợp lệ
  - Check conflict: không cho book nếu screen đã có booking `approved/running` cùng slot
  - Tạo `Booking` + các `BookingLineItem`
  - Set `expires_at` = now + 4 ngày (theo spec)
  - Trả về booking đầy đủ với line items

- [ ] **4.6** `GET /v1/bookings/:booking_id` — lấy trạng thái booking

- [ ] **4.7** `POST /v1/bookings/:booking_id/cancel` — huỷ booking
  - Chỉ cho huỷ khi status là `pending_approval` hoặc `approved`
  - Tính `refund_vnd` theo business rule
  - Update status tất cả line items → `cancelled`

- [ ] **4.8** Admin workflow trong Filament:
  - `BookingResource`: list + detail + action Approve / Reject booking
  - `CampaignResource`: list + detail
  - Khi admin Approve/Reject → update status → gửi webhook ra OOHX

- [ ] **4.9** Webhook gửi về OOHX khi `booking.status_changed`
  - Dùng `SendWebhookJob` từ Phase 2
  - Payload: `booking_id`, `old_status`, `new_status`, `line_items[]`

#### Files tạo mới
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

### Phase 5 — Reporting API
> **Mục tiêu:** OOHX xem được impression và doanh thu theo campaign / screen
> **Ước tính:** 1-2 ngày

#### Việc cần làm

- [ ] **5.1** `GET /v1/reports/impressions`
  - Query params: `campaign_id`, `screen_id` (optional), `date_from`, `date_to`, `group_by`
  - Join `impression_logs` với `booking_line_items` + `bookings` theo `campaign_id`
  - Group by `day | screen | city` theo param
  - Tính `total_impressions`, `total_plays`, `completion_rate`

- [ ] **5.2** `GET /v1/reports/revenue`
  - Query params: `owner_id`, `date_from`, `date_to`, `group_by`
  - Aggregate `booking_line_items.price_vnd` group theo screen/day/month
  - Tính `occupancy_rate` = booked_slots / total_available_slots

- [ ] **5.3** Cache report queries 15 phút (key = hash của query params)

- [ ] **5.4** Filament dashboard widget: biểu đồ impression / doanh thu theo ngày

#### Files tạo mới
```
app/Http/Controllers/Api/V1/ReportController.php
app/Services/ReportingService.php
app/Filament/Widgets/RevenueChartWidget.php
app/Filament/Widgets/ImpressionChartWidget.php
```

---

## Dependency Map

```
Phase 1 (Auth)
    └── Phase 2 (Inventory API)
            └── Phase 3 (Availability & Pricing)
                    └── Phase 4 (Campaign & Booking)
                            └── Phase 5 (Reporting)
```

---

## Route map tổng hợp

```
POST   /v1/auth/token

GET    /v1/inventory/screens
GET    /v1/inventory/screens/:screen_id
POST   /v1/inventory/webhook/register

POST   /v1/availability/check
GET    /v1/pricing/rate-card

POST   /v1/campaigns
POST   /v1/creatives/upload
POST   /v1/bookings
GET    /v1/bookings/:booking_id
POST   /v1/bookings/:booking_id/cancel

GET    /v1/reports/impressions
GET    /v1/reports/revenue
```

---

## Checklist kỹ thuật

### Auth & Security
- [ ] OOHX token không bao giờ có quyền ghi vào Owner / Screen data (chỉ read inventory + CRUD campaign/booking của chính mình)
- [ ] Webhook payload ký bằng HMAC-SHA256 với secret của từng subscriber
- [ ] Rate limiting: 100 req/min cho availability/check, 20 req/min cho upload creative
- [ ] File upload: whitelist MIME type, giới hạn 50MB

### Reliability
- [ ] `CreateBooking` có idempotency key để tránh double-booking khi OOHX retry
- [ ] Booking conflict check chạy trong DB transaction với row-level lock
- [ ] Webhook delivery retry: 3 lần với exponential backoff, log failed delivery

### Testing
- [ ] Feature test cho tất cả endpoints (happy path + validation errors)
- [ ] Test booking conflict: 2 request đồng thời cho cùng slot chỉ 1 thành công
- [ ] Test webhook signature validation

---

## Thứ tự bắt đầu

**Ngày 1:** Phase 1 — Auth endpoint
**Ngày 2-3:** Phase 2 — Inventory API + webhook subscription
**Ngày 4:** Phase 3 — Availability & Pricing
**Ngày 5-8:** Phase 4 — Campaign / Creative / Booking (phần lớn nhất)
**Ngày 9:** Phase 5 — Reporting + Filament widgets

---

## Quyết định kiến trúc

| Vấn đề | Quyết định | Lý do |
|---|---|---|
| Auth: Sanctum hay custom JWT? | **Sanctum** (đã có) | Không cần thêm dependency, Sanctum đủ cho client_credentials flow |
| Booking conflict check: app layer hay DB? | **DB transaction + lock** | Tránh race condition khi concurrent request |
| Creative storage: local hay S3? | **S3 / compatible** | File video có thể lớn, cần CDN để serve |
| Webhook delivery: sync hay queue? | **Queue (Job)** | Không block request, retry được khi subscriber down |
| Report: query live hay pre-aggregate? | **Query live + cache** | Volume chưa đủ lớn để cần pre-aggregate table riêng |

---

*Cập nhật file này sau mỗi phase hoàn thành. Đánh dấu `[x]` các task đã xong.*
