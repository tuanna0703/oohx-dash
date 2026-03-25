# Phase 3 — Availability & Pricing API
> **Status:** 🔲 Chưa bắt đầu
> **Phụ thuộc:** Phase 1 + Phase 2 hoàn thành (cần `screens` + `booking_line_items` từ Phase 4 migration)
> **Cập nhật:** 2026-03-21

## Mục tiêu
Cho phép OOHX frontend check slot trống và xem bảng giá trước khi tạo booking.

---

## Bối cảnh & quyết định thiết kế

- **Availability** tính từ `booking_line_items` — những ngày nào screen đã có booking `approved/running` thì coi là booked
- **Pricing** aggregate từ `screen_inventory.price_per_slot_vnd` theo nhóm city/venue_type/screen_type
- Cả hai đều **cache** để giảm query nặng khi OOHX gọi nhiều (người dùng đang browse inventory)
- `booking_line_items` table được tạo ở Phase 4, nhưng Phase 3 chỉ cần **đọc** — có thể deploy Phase 3 trước Phase 4 booking feature, chỉ cần table tồn tại (dù rỗng)

---

## Files sẽ tạo

```
app/Http/Controllers/Api/V1/AvailabilityController.php
app/Http/Controllers/Api/V1/PricingController.php
app/Http/Requests/CheckAvailabilityRequest.php
app/Services/AvailabilityService.php
app/Services/PricingService.php
```

---

## Chi tiết từng file

### `AvailabilityController.php`

#### `POST /v1/availability/check`
**Auth:** `auth:sanctum` + scope `inventory`

**Request body:**
```json
{
  "screen_ids": ["SCR-HN-001", "SCR-HN-002"],
  "date_from": "2026-04-01",
  "date_to": "2026-04-30",
  "slots_per_day": 48,
  "time_range": {
    "from": "07:00",
    "to": "22:00"
  }
}
```

**Logic:** Delegate sang `AvailabilityService::check()`

**Response 200:** Array per screen (xem schema bên dưới)

---

### `CheckAvailabilityRequest.php`
**Rules:**
```php
'screen_ids'       => ['required', 'array', 'min:1', 'max:50'],
'screen_ids.*'     => ['required', 'string'],
'date_from'        => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
'date_to'          => ['required', 'date_format:Y-m-d', 'after:date_from'],
'slots_per_day'    => ['sometimes', 'integer', 'min:1', 'max:288'],
'time_range'       => ['sometimes', 'array'],
'time_range.from'  => ['required_with:time_range', 'date_format:H:i'],
'time_range.to'    => ['required_with:time_range', 'date_format:H:i', 'after:time_range.from'],
```

---

### `AvailabilityService.php`

**Method: `check(array $screenIds, string $dateFrom, string $dateTo, array $options): array`**

**Logic per screen:**
1. Tìm screen trong DB theo `external_id` hoặc `uuid`
2. Tính `total_days` = số ngày trong range
3. Query `booking_line_items`:
   ```sql
   SELECT DISTINCT DATE(date) as booked_date
   FROM booking_line_items bli
   JOIN bookings b ON b.id = bli.booking_id
   WHERE bli.screen_id = ?
     AND b.status IN ('pending_approval', 'approved', 'running')
     AND bli.date_from <= ? AND bli.date_to >= ?
   ```
4. `booked_dates` = danh sách ngày bị chiếm
5. Tính `available_slots`:
   - Nếu `slots_per_day` truyền vào: dùng làm total slots mỗi ngày
   - Else: dùng `screen.spec.slots_per_loop` × số loop/ngày ước tính từ `operating_hours`
   - Trừ đi slots đã bị book
6. `estimated_price_vnd` = `available_slots × screen.inventory.price_per_slot_vnd`

**Cache key:** `availability:{sha1(sort($screen_ids))}-{$dateFrom}-{$dateTo}` — TTL 5 phút

**Response schema per screen:**
```json
{
  "screen_id": "SCR-HN-001",
  "available": true,
  "available_slots": 42,
  "total_slots": 48,
  "booked_dates": ["2026-04-10", "2026-04-11"],
  "estimated_price_vnd": 21000000
}
```
*`available: false` khi `available_slots === 0`*

---

### `PricingController.php`

#### `GET /v1/pricing/rate-card`
**Auth:** `auth:sanctum` + scope `inventory`

**Query params:**
```
city=hanoi
venue_type=mall
screen_type=lcd
```
*(tất cả optional — không truyền thì trả tất cả)*

**Logic:** Delegate sang `PricingService::getRateCard()`

**Response 200:**
```json
{
  "currency": "VND",
  "data": [
    {
      "screen_type": "lcd",
      "venue_type": "mall",
      "city": "hanoi",
      "price_per_slot_vnd": 500000,
      "price_per_day_vnd": 8000000,
      "price_per_week_vnd": 52000000,
      "price_per_month_vnd": 200000000,
      "min_booking_days": 7,
      "peak_multiplier": 1.5,
      "peak_hours": ["11:00-13:00", "17:00-21:00"]
    }
  ]
}
```

---

### `PricingService.php`

**Method: `getRateCard(array $filters): array`**

**Logic:**
1. Query `screen_inventory` join `screen_specs` join `screens` (only active)
2. Group by `city`, `venue_type`, `screen_type`
3. `price_per_slot_vnd` = AVG hoặc MIN của group (cần confirm — dùng MIN để transparent với buyer)
4. Tính các mức giá từ `price_per_slot_vnd`:
   - `price_per_day_vnd` = `price_per_slot_vnd × slots_per_loop × avg_loops_per_day`
   - `price_per_week_vnd` = `price_per_day_vnd × 7`
   - `price_per_month_vnd` = `price_per_day_vnd × 30`
5. `peak_multiplier` và `peak_hours` — lấy từ `screen_impression_multipliers` nếu có, hoặc hardcode default 1.5

**Cache key:** `rate-card:{city}-{venue_type}-{screen_type}` — TTL 1 giờ

> **Quyết định cần confirm:** Dùng MIN hay AVG cho `price_per_slot_vnd` khi group?
> - MIN: buyer thấy giá rẻ nhất của nhóm → có thể gây confusion khi book thực tế
> - AVG: đại diện hơn nhưng không chính xác cho từng screen
> - **Đề xuất:** Trả thêm field `price_range: { min, max }` để OOHX hiển thị "từ X đến Y"

---

## Routes thêm vào `routes/api.php`
```php
Route::prefix('v1')->middleware(['auth:sanctum', 'ability:inventory'])->group(function () {
    // ... Phase 2 routes ...
    Route::post('availability/check',  [AvailabilityController::class, 'check']);
    Route::get('pricing/rate-card',    [PricingController::class, 'rateCard']);
});
```

---

## Dependency với Phase 4
Phase 3 cần table `booking_line_items` để tính booked slots. Hai cách xử lý:
1. **Deploy cùng lúc với Phase 4** (đơn giản nhất)
2. **Deploy Phase 3 trước Phase 4**: query `booking_line_items` sẽ trả rỗng → `available_slots = total_slots` (hoàn toàn hợp lý khi chưa có booking nào)

→ **Chọn cách 2**: Phase 3 có thể release độc lập, availability sẽ tự cập nhật khi Phase 4 có booking.

---

## Test cases
- [ ] `POST /v1/availability/check` với screen không tồn tại → trả `available: false` hoặc bỏ qua (confirm expected behavior)
- [ ] Screen chưa có booking nào → `available: true`, `booked_dates: []`
- [ ] Screen đã fully booked → `available: false`, `available_slots: 0`
- [ ] Screen partially booked → `booked_dates` đúng, `available_slots` tính đúng
- [ ] `date_from >= date_to` → 422 validation error
- [ ] `screen_ids` > 50 items → 422
- [ ] `GET /v1/pricing/rate-card?city=hanoi&venue_type=mall` → trả đúng group
- [ ] Cache hoạt động: 2 request giống nhau không gọi DB lần 2
- [ ] Cache invalidate sau khi có booking mới (nếu implement cache busting)

---

## Checklist hoàn thành
- [ ] `AvailabilityService` tính đúng available_slots
- [ ] `AvailabilityService` tính đúng booked_dates
- [ ] `PricingService` aggregate đúng theo filter
- [ ] Cache TTL hoạt động đúng
- [ ] Validation errors trả đúng format chuẩn
- [ ] Feature tests pass
