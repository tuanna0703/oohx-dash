# Phase 5 — Reporting API
> **Status:** 🔲 Chưa bắt đầu
> **Phụ thuộc:** Phase 1, 2, 4 hoàn thành (cần `impression_logs`, `bookings`, `booking_line_items`)
> **Cập nhật:** 2026-03-21

## Mục tiêu
OOHX frontend xem được dữ liệu impression và doanh thu theo campaign / screen / city. Admin trong Filament có dashboard chart theo dõi tổng quan.

---

## Bối cảnh & quyết định thiết kế

- **Impressions** lấy từ `impression_logs` table (đã có sẵn, player ghi vào qua `POST /v1/player/impression`)
- **Revenue** aggregate từ `booking_line_items.price_vnd` join `bookings.status`
- **group_by**: `day` | `screen` | `city` | `month`
- Report query **không pre-aggregate** (volume chưa đủ lớn) — query live + cache 15 phút
- Mỗi api_client chỉ xem được data của chính mình (campaign_id thuộc client đó)

---

## Files sẽ tạo

```
app/Http/Controllers/Api/V1/ReportController.php
app/Services/ReportingService.php
app/Filament/Widgets/RevenueChartWidget.php
app/Filament/Widgets/ImpressionChartWidget.php
```

---

## Chi tiết từng endpoint

### `GET /v1/reports/impressions`
**Auth:** `auth:sanctum` + scope `reporting`

**Query params:**
| Param | Type | Bắt buộc | Ghi chú |
|---|---|---|---|
| `campaign_id` | string | ✅ | Filter theo campaign |
| `screen_id` | string | ❌ | Filter thêm theo screen |
| `date_from` | YYYY-MM-DD | ✅ | |
| `date_to` | YYYY-MM-DD | ✅ | Tối đa 90 ngày |
| `group_by` | `day\|screen\|city` | ❌ | Default: `day` |

**Validation:**
```php
'campaign_id' => ['required', 'string', 'exists:campaigns,campaign_id'],
'screen_id'   => ['sometimes', 'string'],
'date_from'   => ['required', 'date_format:Y-m-d'],
'date_to'     => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from',
                  'before_or_equal:' . now()->toDateString()],
'group_by'    => ['sometimes', 'in:day,screen,city'],
```

**Logic trong `ReportingService::impressions()`:**
1. Validate campaign thuộc `api_client_id` hiện tại
2. Query:
   ```sql
   SELECT
       DATE(il.played_at) as date,
       il.screen_id,
       si.city,
       COUNT(*) as plays,
       SUM(il.estimated_impressions) as impressions,
       AVG(il.completion_rate) as completion_rate
   FROM impression_logs il
   JOIN screens s ON s.id = il.screen_id
   JOIN screen_inventory si ON si.screen_id = s.id
   JOIN booking_line_items bli ON bli.screen_id = il.screen_id
   JOIN bookings b ON b.id = bli.booking_id
   WHERE b.campaign_id = (SELECT id FROM campaigns WHERE campaign_id = ?)
     AND il.played_at BETWEEN ? AND ?
   GROUP BY [group_by fields]
   ```
3. Tính `total_impressions`, `total_plays` từ aggregate
4. Format theo `group_by`

**Response 200:**
```json
{
  "campaign_id": "CMP-2026-001",
  "date_from": "2026-04-01",
  "date_to": "2026-04-30",
  "total_impressions": 2400000,
  "total_plays": 5760,
  "data": [
    {
      "date": "2026-04-01",
      "screen_id": "SCR-HN-001",
      "impressions": 80000,
      "plays": 192,
      "completion_rate": 0.97
    }
  ]
}
```

> **Lưu ý:** Cần verify `impression_logs` table có đủ các fields: `played_at`, `estimated_impressions`, `completion_rate`, `screen_id`. Đọc lại migration trước khi code.

---

### `GET /v1/reports/revenue`
**Auth:** `auth:sanctum` + scope `reporting`

**Query params:**
| Param | Type | Bắt buộc | Ghi chú |
|---|---|---|---|
| `owner_id` | string | ✅ | Filter theo owner (media owner xem doanh thu màn hình của mình) |
| `date_from` | YYYY-MM-DD | ✅ | |
| `date_to` | YYYY-MM-DD | ✅ | |
| `group_by` | `screen\|day\|month` | ❌ | Default: `screen` |

**Quyền truy cập:**
- `super_admin` → xem tất cả `owner_id`
- `owner_admin` → chỉ xem `owner_id` của mình
- `api_client` (OOHX) → không gọi endpoint này (hoặc chỉ thấy spending của mình)

> **Điểm cần confirm:** OOHX có cần xem revenue report không? Hay endpoint này chỉ dành cho media owner trong Filament? Nếu chỉ cho media owner thì có thể bỏ khỏi public API, giữ trong Filament thôi.

**Logic trong `ReportingService::revenue()`:**
1. Resolve `owner_id` → internal `Owner` model
2. Query:
   ```sql
   SELECT
       s.external_id as screen_id,
       COUNT(DISTINCT b.id) as bookings,
       SUM(bli.price_vnd) as revenue_vnd,
       (COUNT(DISTINCT CASE WHEN b.status IN ('approved','running','completed')
                            THEN DATE(bli.date_from) END)
        / DATEDIFF(?, ?)) as occupancy_rate
   FROM booking_line_items bli
   JOIN bookings b ON b.id = bli.booking_id
   JOIN screens s ON s.id = bli.screen_id
   WHERE s.owner_id = ?
     AND b.status IN ('approved','running','completed')
     AND bli.date_from >= ? AND bli.date_to <= ?
   GROUP BY [group_by fields]
   ```
3. Tính `total_revenue_vnd`, `total_bookings`

**Response 200:**
```json
{
  "owner_id": "OWN-001",
  "total_revenue_vnd": 180000000,
  "total_bookings": 12,
  "data": [
    {
      "screen_id": "SCR-HN-001",
      "bookings": 5,
      "revenue_vnd": 60000000,
      "occupancy_rate": 0.78
    }
  ]
}
```

---

### `ReportingService.php`

**Cache strategy:**
```php
// Cache key
$key = 'report:impressions:' . md5(serialize($params));
Cache::remember($key, now()->addMinutes(15), fn() => $this->queryImpressions($params));
```

**Cache invalidation:**
- Khi có booking mới hoặc booking status thay đổi → clear revenue cache của `owner_id` tương ứng
- Impression cache chỉ clear theo TTL (player log realtime không cần invalidate)

---

## Filament Widgets

### `RevenueChartWidget`
- Bar chart: doanh thu theo tháng/tuần
- Filter: owner, date range
- Data source: `ReportingService::revenue()`

### `ImpressionChartWidget`
- Line chart: impressions theo ngày
- Filter: campaign, screen
- Data source: `ReportingService::impressions()`

Cả hai widget dùng **Filament Charts** (built-in với Filament 3.x).

---

## Routes thêm vào `routes/api.php`
```php
Route::prefix('v1')->middleware(['auth:sanctum', 'ability:reporting'])->group(function () {
    Route::get('reports/impressions', [ReportController::class, 'impressions']);
    Route::get('reports/revenue',     [ReportController::class, 'revenue']);
});
```

---

## Điểm cần verify trước khi code

### `impression_logs` table structure
Đọc migration `0001_01_01_000011_create_impression_logs_table.php` để confirm:
- [ ] Column `played_at` hay `created_at` làm timestamp?
- [ ] Column `estimated_impressions` có tồn tại không?
- [ ] Column `completion_rate` có không hay cần tính từ data khác?
- [ ] Column `screen_id` là FK đến `screens.id` hay lưu `external_id`?

### Join impression_logs với booking
Cần xác định cách join `impression_logs` với `bookings` — qua `screen_id` + `played_at` trong date range của booking, hay có FK trực tiếp?

---

## Test cases
- [ ] `GET /v1/reports/impressions` với `campaign_id` của client khác → 403
- [ ] `GET /v1/reports/impressions?group_by=day` → data group theo ngày đúng
- [ ] `GET /v1/reports/impressions?group_by=screen` → data group theo screen đúng
- [ ] `date_to` > today → 422
- [ ] Date range > 90 ngày → 422
- [ ] `GET /v1/reports/revenue` với `owner_id` không có quyền → 403
- [ ] `group_by=month` → data aggregate đúng theo tháng
- [ ] Cache: 2 request giống nhau chỉ query DB 1 lần

---

## Checklist hoàn thành
- [ ] Verify `impression_logs` table fields trước khi code
- [ ] `ReportingService::impressions()` trả đúng group_by
- [ ] `ReportingService::revenue()` tính occupancy_rate đúng
- [ ] Cache 15 phút hoạt động
- [ ] Filament widgets hiển thị chart đúng
- [ ] Phân quyền theo owner/admin đúng
- [ ] Feature tests pass
