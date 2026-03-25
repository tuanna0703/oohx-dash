# TapON API — Yêu cầu cập nhật cho trang Browse OOHX

> **Mục đích:** Tài liệu này mô tả các thay đổi cần thực hiện trên backend Laravel (ssp.tapon.vn)
> để hỗ trợ đầy đủ tính năng bộ lọc và hiển thị bản đồ trên trang `/browse` của OOHX.
>
> **Ngày yêu cầu:** 2026-03-24

---

## Tổng quan thay đổi

| # | Thay đổi | Loại | Ưu tiên |
|---|---|---|---|
| 1 | `GET /inventory/screens` — hỗ trợ multi-value filter | Cập nhật endpoint có sẵn | 🔴 Cao |
| 2 | `GET /inventory/screens` — thêm param `orientation` | Cập nhật endpoint có sẵn | 🟡 Trung bình |
| 3 | `GET /inventory/screens` — thêm param `q` (text search) | Cập nhật endpoint có sẵn | 🟡 Trung bình |
| 4 | `GET /inventory/screens` — thêm param `sort` | Cập nhật endpoint có sẵn | 🟡 Trung bình |
| 5 | `GET /inventory/screens/map` — endpoint nhẹ cho bản đồ | Endpoint mới | 🔴 Cao |

---

## 1. `GET /inventory/screens` — Cập nhật query params

### 1.1 Multi-value filter (🔴 Ưu tiên cao)

Hiện tại mỗi param chỉ nhận một giá trị. OOHX cần lọc đồng thời nhiều city / venue_type / screen_type.

**Yêu cầu:** Hỗ trợ truyền mảng theo chuẩn PHP/Laravel `param[]=value`.

```
GET /inventory/screens?venue_type[]=mall&venue_type[]=fnb
GET /inventory/screens?city[]=hanoi&city[]=danang
GET /inventory/screens?screen_type[]=lcd&screen_type[]=led
```

**Logic Laravel (khuyến nghị):**
```php
// Chấp nhận cả string đơn lẫn array
$venueTypes = (array) $request->input('venue_type', []);
// SQL: WHERE venue_type IN (...)
$query->when(!empty($venueTypes), fn($q) => $q->whereIn('venue_type', $venueTypes));
```

**Tương thích ngược:** Vẫn giữ single-value hoạt động bình thường.
```
GET /inventory/screens?venue_type=mall   ← vẫn chạy được
GET /inventory/screens?venue_type[]=mall ← mới
```

---

### 1.2 Param `orientation` (🟡 Trung bình)

Lọc theo chiều màn hình.

```
GET /inventory/screens?orientation=landscape
GET /inventory/screens?orientation=portrait
GET /inventory/screens?orientation=square
GET /inventory/screens?orientation[]=landscape&orientation[]=portrait
```

**Giá trị hợp lệ:** `landscape` | `portrait` | `square`

**Mapping Laravel:** Cột `specs.orientation` (hoặc column riêng trong bảng screens).

---

### 1.3 Param `q` — Text search (🟡 Trung bình)

Tìm kiếm tự do theo tên màn hình hoặc địa chỉ.

```
GET /inventory/screens?q=Vincom
GET /inventory/screens?q=Cầu Giấy
```

**Logic Laravel:**
```php
$q = $request->input('q');
$query->when($q, fn($query) => $query->where(function ($sub) use ($q) {
    $sub->where('name', 'LIKE', "%{$q}%")
        ->orWhere('location_address', 'LIKE', "%{$q}%")
        ->orWhere('location_city', 'LIKE', "%{$q}%");
}));
```

**Lưu ý:** Không cần full-text search engine — LIKE là đủ với quy mô hiện tại.

---

### 1.4 Param `sort` — Sắp xếp (🟡 Trung bình)

```
GET /inventory/screens?sort=price_asc
GET /inventory/screens?sort=price_desc
GET /inventory/screens?sort=newest
```

**Giá trị hợp lệ và mapping:**

| Giá trị `sort` | Ý nghĩa | SQL |
|---|---|---|
| `price_asc` | Giá thấp nhất | `ORDER BY price_per_slot_vnd ASC` |
| `price_desc` | Giá cao nhất | `ORDER BY price_per_slot_vnd DESC` |
| `newest` | Mới cập nhật nhất | `ORDER BY updated_at DESC` |
| _(mặc định)_ | Không sắp xếp | `ORDER BY id ASC` hoặc tùy backend |

---

### Tổng hợp tất cả params của `GET /inventory/screens`

```
GET /api/v1/inventory/screens

Query params (tất cả optional):
  page          integer   default: 1
  limit         integer   default: 20, max: 100
  status        string    "active" | "inactive"   default: "active"
  city          string|string[]   hanoi | hcm | danang | haiphong | cantho
  venue_type    string|string[]   mall | outdoor | fnb | transit | office
  screen_type   string|string[]   lcd | led | billboard
  orientation   string|string[]   landscape | portrait | square
  q             string    tìm kiếm tự do
  sort          string    price_asc | price_desc | newest
  updated_after string    ISO8601, dùng cho webhook sync
```

**Response (không đổi):**
```json
{
  "total": 3644,
  "page": 1,
  "limit": 24,
  "data": [ /* TapOnScreen[] */ ]
}
```

---

## 2. `GET /inventory/screens/map` — Endpoint nhẹ cho bản đồ (🔴 Ưu tiên cao)

### Vấn đề hiện tại

Trang browse ở chế độ Map cần toàn bộ 3600+ màn hình để render markers trên bản đồ. Hiện tại OOHX phải:
1. Gọi page 1 để lấy `total`
2. Gọi song song ~36 pages (100 items/page)
3. Mỗi item chứa đầy đủ thông tin (specs, photos, operating_hours, v.v.) — **không cần cho bản đồ**

Điều này tốn băng thông và làm chậm tải trang.

### Giải pháp

Endpoint chuyên biệt trả về dữ liệu tối thiểu cần thiết để vẽ markers và hiển thị popup trên bản đồ.

```
GET /api/v1/inventory/screens/map
```

**Query params:** Giống hệt `GET /inventory/screens` nhưng **không có** `page` và `limit` (luôn trả toàn bộ).

```
GET /api/v1/inventory/screens/map?status=active
GET /api/v1/inventory/screens/map?city=hanoi&venue_type[]=mall&venue_type[]=outdoor
```

**Response — chỉ các field cần thiết:**
```json
{
  "total": 3644,
  "data": [
    {
      "screen_id": "abc-123",
      "name": "Billboard Cầu Giấy",
      "venue_type": "outdoor",
      "screen_type": "billboard",
      "size": "14×6m",
      "location": {
        "address": "Xuân Thủy, Cầu Giấy, Hà Nội",
        "lat": 21.038,
        "lng": 105.7992
      }
    }
  ]
}
```

**So sánh kích thước response:**

| Endpoint | Fields/item | ~Size (3644 items) |
|---|---|---|
| `GET /inventory/screens` (paginated ×36) | ~25 fields | ~4.5 MB |
| `GET /inventory/screens/map` | 6 fields | ~400 KB |

**Giảm ~90% payload**, tăng tốc đáng kể.

---

## 3. Ví dụ request thực tế từ OOHX Browse

### Chế độ Map (load toàn bộ)
```http
GET /api/v1/inventory/screens/map?status=active
Authorization: Bearer {token}
```

### Chế độ List — trang 1, không filter
```http
GET /api/v1/inventory/screens?status=active&page=1&limit=24&sort=newest
Authorization: Bearer {token}
```

### Chế độ List — filter nhiều venue
```http
GET /api/v1/inventory/screens?status=active&page=1&limit=24&venue_type[]=mall&venue_type[]=fnb&sort=price_asc
Authorization: Bearer {token}
```

### Chế độ List — filter kết hợp
```http
GET /api/v1/inventory/screens?status=active&page=2&limit=24&city[]=hanoi&city[]=danang&screen_type=lcd&orientation=landscape&q=Vincom
Authorization: Bearer {token}
```

---

## 4. Thứ tự triển khai khuyến nghị

```
Bước 1 — Multi-value filter cho city, venue_type, screen_type
  → Unblock toàn bộ bộ lọc chính của browse page

Bước 2 — Endpoint GET /inventory/screens/map
  → Giảm load time map view từ ~5s xuống ~0.5s

Bước 3 — Param orientation
  → Bật tính năng filter orientation đang bị ẩn trên UI

Bước 4 — Param q (text search)
  → Chuyển text search từ client-side sang server-side (chính xác hơn)

Bước 5 — Param sort
  → Kết nối dropdown sắp xếp trên UI
```

---

## 5. Thay đổi phía OOHX sau khi backend update

Khi backend hoàn thành, OOHX sẽ cập nhật:

1. **`lib/tapon/types.ts`** — Thêm `orientation`, `q`, `sort` vào `ScreenListParams`; thêm `MapScreenItem` type mới
2. **`lib/tapon/inventory.ts`** — Thêm function `getMapScreens()` gọi endpoint `/map`
3. **`app/api/screens/route.ts`** — Dùng `/map` endpoint thay vì `getAllScreens()` khi `all=true`
4. **`app/browse/page.tsx`** — Gửi `screen_type`, `orientation`, `q`, `sort` lên API; hỗ trợ multi-value

---

_Liên hệ dev OOHX để xác nhận schema response trước khi triển khai._
