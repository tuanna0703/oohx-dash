# TapON API — Yêu cầu cho trang chủ OOHX

> **Mục đích:** Các endpoint cần build trên Laravel để cấp dữ liệu thực cho trang chủ `oohx.net`.
> Hiện tại trang chủ đang dùng toàn bộ dữ liệu mock cứng trong `lib/data.ts`.
>
> **Ngày yêu cầu:** 2026-03-24

---

## Mapping: Khối UI → API cần thiết

| Khối trên trang chủ | Dữ liệu cần | Nguồn hiện tại | API mới |
|---|---|---|---|
| Stats Strip (12,000+ màn hình, 34 tỉnh...) | Tổng số screens, cities, owners | Mock cứng | `GET /inventory/stats` |
| Search → dropdown Địa điểm | Danh sách tỉnh thành + số màn hình | Mock 20 tỉnh cứng | Dùng từ `GET /inventory/stats` |
| Inventory Preview (3 cards) | 3 screens nổi bật | Mock cứng | `GET /inventory/screens?limit=3&sort=newest` *(đã có)* |
| Venue Types (Retail 320, Outdoor 185...) | Số màn hình theo loại venue | Mock cứng | Dùng từ `GET /inventory/stats` |
| Featured Media Owners | Danh sách owners + số màn hình | Mock cứng | `GET /inventory/owners` |

---

## API 1 — `GET /inventory/stats`

**Mục đích:** Một request duy nhất trả về toàn bộ aggregate data cần cho trang chủ.
Cached tốt (dữ liệu thay đổi chậm) — TTL gợi ý 30 phút.

```
GET /api/v1/inventory/stats
Authorization: Bearer {token}
```

**Không có query params.**

### Response

```json
{
  "total_screens": 12548,
  "total_cities": 34,
  "total_owners": 52,
  "venues": [
    { "type": "mall",          "label": "Retail",        "count": 3842 },
    { "type": "outdoor",       "label": "Outdoor",       "count": 2156 },
    { "type": "fnb",           "label": "F&B",           "count": 2890 },
    { "type": "transit",       "label": "Transit",       "count": 1204 },
    { "type": "office",        "label": "Office",        "count": 1876 },
    { "type": "entertainment", "label": "Entertainment", "count": 580  }
  ],
  "cities": [
    { "code": "hanoi",     "name": "Hà Nội",              "count": 4820 },
    { "code": "hcm",       "name": "TP. Hồ Chí Minh",    "count": 4250 },
    { "code": "danang",    "name": "Đà Nẵng",             "count": 1180 },
    { "code": "haiphong",  "name": "Hải Phòng",           "count": 620  },
    { "code": "cantho",    "name": "Cần Thơ",             "count": 480  }
  ]
}
```

### Giải thích từng field

| Field | Dùng ở đâu trên UI |
|---|---|
| `total_screens` | Stats strip: "12,000+ Màn hình" |
| `total_cities` | Stats strip: "34 Tỉnh thành" |
| `total_owners` | Stats strip: "50+ Media owners" |
| `venues[].count` | Section "Phủ sóng mọi điểm chạm" — số dưới mỗi venue card |
| `cities[]` | Dropdown Địa điểm trong Hero search — tên tỉnh + số màn hình |

### Logic Laravel (gợi ý)

```php
// Tất cả query chỉ đếm screens đang active
$base = Screen::where('status', 'active');

return [
    'total_screens' => $base->count(),
    'total_cities'  => $base->distinct('location_city')->count('location_city'),
    'total_owners'  => $base->distinct('owner_id')->count('owner_id'),
    'venues'        => $base->selectRaw('venue_type, count(*) as count')
                            ->groupBy('venue_type')
                            ->get()
                            ->map(fn($r) => [
                                'type'  => $r->venue_type,
                                'label' => VenueLabels::get($r->venue_type),
                                'count' => $r->count,
                            ]),
    'cities'        => $base->selectRaw('location_city as code, count(*) as count')
                            ->groupBy('location_city')
                            ->orderByDesc('count')
                            ->get()
                            ->map(fn($r) => [
                                'code'  => $r->code,
                                'name'  => CityLabels::get($r->code),
                                'count' => $r->count,
                            ]),
];
```

---

## API 2 — `GET /inventory/owners`

**Mục đích:** Danh sách media owners để hiển thị ở section "Đối tác nổi bật" và trang `/owners`.

```
GET /api/v1/inventory/owners
Authorization: Bearer {token}
```

### Query params (tất cả optional)

| Param | Type | Mô tả |
|---|---|---|
| `featured` | boolean | `true` → chỉ trả owners được đánh dấu nổi bật (cho homepage) |
| `limit` | integer | Số lượng tối đa, default: 20 |
| `page` | integer | Phân trang, default: 1 |

**Ví dụ dùng cho homepage:**
```
GET /api/v1/inventory/owners?featured=true&limit=6
```

**Ví dụ dùng cho trang `/owners` (tất cả):**
```
GET /api/v1/inventory/owners?limit=50
```

### Response

```json
{
  "total": 52,
  "page": 1,
  "limit": 6,
  "data": [
    {
      "owner_id": "adtrue",
      "name": "AdTRUE Media",
      "tagline": "Hệ thống Billboard & OOH lớn nhất miền Bắc",
      "logo_url": "https://cdn.tapon.vn/owners/adtrue/logo.png",
      "cover_url": "https://cdn.tapon.vn/owners/adtrue/cover.jpg",
      "screen_count": 95,
      "city_count": 12,
      "venue_types": ["outdoor", "transit"],
      "website": "adtrue.vn",
      "email": "contact@adtrue.vn",
      "phone": "+84 24 3888 9999",
      "founded": 2014,
      "featured": true,
      "headquarters": {
        "lat": 21.0285,
        "lng": 105.8542
      }
    }
  ]
}
```

### Giải thích từng field

| Field | Bắt buộc | Dùng ở đâu |
|---|---|---|
| `owner_id` | ✅ | Key cho URL `/owners/:owner_id` |
| `name` | ✅ | Tên hiển thị |
| `tagline` | ✅ | Mô tả ngắn dưới tên |
| `logo_url` | ✅ (nullable) | Avatar/emoji fallback nếu null |
| `screen_count` | ✅ | "95 màn hình" |
| `city_count` | ✅ | "12 tỉnh thành" |
| `venue_types` | ✅ | Chip tags loại venue |
| `featured` | ✅ | Filter cho homepage |
| `cover_url` | ☐ optional | Cover image trang detail owner |
| `website`, `email`, `phone`, `founded` | ☐ optional | Trang detail owner |
| `headquarters.lat/lng` | ☐ optional | Map mini trên trang detail owner |

---

## API 3 — `GET /inventory/owners/:owner_id`

**Mục đích:** Chi tiết một owner — dùng cho trang `/owners/:slug`.

```
GET /api/v1/inventory/owners/{owner_id}
Authorization: Bearer {token}
```

### Response

Giống 1 item trong `data[]` của API 2, nhưng có thêm field `about`:

```json
{
  "owner_id": "adtrue",
  "name": "AdTRUE Media",
  "tagline": "Hệ thống Billboard & OOH lớn nhất miền Bắc",
  "about": "Mô tả dài về công ty...",
  "logo_url": "https://cdn.tapon.vn/owners/adtrue/logo.png",
  "cover_url": "https://cdn.tapon.vn/owners/adtrue/cover.jpg",
  "screen_count": 95,
  "city_count": 12,
  "venue_types": ["outdoor", "transit"],
  "website": "adtrue.vn",
  "email": "contact@adtrue.vn",
  "phone": "+84 24 3888 9999",
  "founded": 2014,
  "featured": true,
  "headquarters": { "lat": 21.0285, "lng": 105.8542 }
}
```

**Error nếu không tìm thấy:**
```json
HTTP 404
{ "error": "Owner not found" }
```

---

## OOHX BFF — Các route cần tạo

Sau khi Laravel build xong, OOHX sẽ tạo các proxy route tương ứng:

| OOHX BFF | → TapON | Cache TTL |
|---|---|---|
| `GET /api/stats` | `GET /inventory/stats` | 30 phút |
| `GET /api/owners` | `GET /inventory/owners` | 10 phút |
| `GET /api/owners/:id` | `GET /inventory/owners/:id` | 10 phút |

---

## Thứ tự triển khai khuyến nghị

```
Bước 1 — GET /inventory/stats
  → Unblock Stats Strip + Venue Types + Location dropdown

Bước 2 — GET /inventory/owners  +  GET /inventory/owners/:id
  → Unblock Featured Media Owners + trang /owners

Bước 3 — (Không cần build thêm)
  → Inventory Preview 3 cards: dùng GET /inventory/screens?limit=3&sort=newest đã có
```

---

## Những gì KHÔNG cần API mới

| Khối | Lý do |
|---|---|
| Dropdown "Loại màn hình" (LCD/LED/Billboard) | Giá trị tĩnh, không thay đổi |
| Dropdown "Venue type" (Mall/Outdoor/F&B...) | Giá trị tĩnh, không thay đổi |
| "Phí đăng ký: 0đ" | Static marketing copy |
| Section "Campaign trong 3 bước" | Static content |
| Section "Tại sao OOHX" | Static content |
| Inventory Preview (3 cards) | Dùng `GET /inventory/screens?limit=3&sort=newest` *(đã có)* |

---

_Liên hệ dev OOHX sau khi hoàn thành để tích hợp vào BFF proxy và cập nhật trang chủ._
