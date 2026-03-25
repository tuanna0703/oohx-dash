# TapON API — Browse Filter Sidebar (v1)

> **Mục đích:** Tài liệu các endpoint mới và mở rộng cần build trên Laravel để cấp dữ liệu cho filter sidebar trên trang Browse của OOHX.
>
> **Ngày yêu cầu:** 2026-03-24
> **Base URL:** `https://ssp.tapon.vn/api/v1`
> **Auth:** Bearer Token (Sanctum), scope `inventory`

---

## Mục lục

1. [Tổng quan thay đổi](#1-tổng-quan-thay-đổi)
2. [GET /inventory/networks](#2-get-inventorynetworks) *(mới)*
3. [GET /inventory/locations](#3-get-inventorylocations) *(mới)*
4. [GET /inventory/owners — mở rộng](#4-get-inventoryowners--mở-rộng) *(thêm param `q`)*
5. [GET /inventory/screens — mở rộng](#5-get-inventoryscreens--và-get-inventoryscreensmap--mở-rộng) *(thêm 4 filter params)*
6. [Sơ đồ tổng hợp tất cả filter params](#6-sơ-đồ-tổng-hợp-tất-cả-filter-params)
7. [Ghi chú triển khai (cho Laravel developer)](#7-ghi-chú-triển-khai)

---

## 1. Tổng quan thay đổi

| Endpoint | Loại thay đổi | Mô tả |
|---|---|---|
| `GET /inventory/networks` | **Mới** | Danh sách chuỗi/network có màn hình trong inventory |
| `GET /inventory/locations` | **Mới** | Dữ liệu địa lý phân cấp: miền → tỉnh → quận/huyện |
| `GET /inventory/owners` | **Mở rộng** | Thêm param `q` để tìm kiếm theo tên owner |
| `GET /inventory/screens` | **Mở rộng** | Thêm 4 filter params: `network[]`, `owner[]`, `district[]`, `region[]` |
| `GET /inventory/screens/map` | **Mở rộng** | Cùng 4 filter params như `/screens` |

> **Quy tắc chung:** Khi không có dữ liệu, tất cả endpoint trả về mảng rỗng `[]` hoặc `{ "data": [] }`. Tuyệt đối không trả về dữ liệu mock/hardcoded khi thực tế không có bản ghi nào.

---

## 2. GET /inventory/networks

Trả về danh sách các chuỗi/network (ví dụ: Winmart+, Go! Mall, AEON Mall) hiện đang có màn hình active trong inventory. Dùng để render filter "Chuỗi/Network" trên Browse sidebar.

```
GET /api/v1/inventory/networks
Authorization: Bearer {token}
```

**Query params:** Không có

**Cache:** 30 phút server-side (invalidate khi có screen được thêm mới hoặc đổi trạng thái)

### Response 200

```json
{
  "data": [
    { "code": "winmart-plus",  "name": "Winmart+",    "screen_count": 320 },
    { "code": "go-mall",       "name": "Go! Mall",    "screen_count": 185 },
    { "code": "aeon-mall",     "name": "AEON Mall",   "screen_count": 140 },
    { "code": "lotte-mart",    "name": "Lotte Mart",  "screen_count": 98  },
    { "code": "bigc",          "name": "Big C",       "screen_count": 76  },
    { "code": "coopmart",      "name": "Co.opmart",   "screen_count": 64  }
  ]
}
```

### Mô tả các field

| Field | Type | Ghi chú |
|---|---|---|
| `code` | string | Slug dùng làm giá trị trong param `network[]` khi filter màn hình |
| `name` | string | Tên hiển thị của chuỗi/network trên UI |
| `screen_count` | integer | Số màn hình active thuộc network này |

### Quy tắc trả về

- Chỉ trả về network có ít nhất **1 màn hình active** (`status = active`).
- Kết quả sắp xếp theo `screen_count DESC`.
- Nếu không có network nào, trả về `{ "data": [] }` — không dùng dữ liệu mẫu.

### Response 401

```json
{ "message": "Unauthenticated." }
```

---

## 3. GET /inventory/locations

Trả về dữ liệu địa lý phân cấp 3 tầng: **miền → tỉnh/thành phố → quận/huyện**. Chỉ bao gồm các địa danh có ít nhất 1 màn hình active. Dùng để render filter địa lý mở rộng trên Browse sidebar.

```
GET /api/v1/inventory/locations
Authorization: Bearer {token}
```

**Query params:** Không có

**Cache:** 30 phút server-side

### Response 200

```json
{
  "regions": [
    { "code": "north",   "name": "Miền Bắc", "count": 2450 },
    { "code": "central", "name": "Miền Trung", "count": 520 },
    { "code": "south",   "name": "Miền Nam",  "count": 1874 }
  ],
  "provinces": [
    { "code": "hanoi",    "name": "Hà Nội",           "region": "north",   "count": 1800 },
    { "code": "haiphong", "name": "Hải Phòng",         "region": "north",   "count": 320  },
    { "code": "danang",   "name": "Đà Nẵng",           "region": "central", "count": 420  },
    { "code": "hcm",      "name": "TP. Hồ Chí Minh",  "region": "south",   "count": 1400 },
    { "code": "cantho",   "name": "Cần Thơ",           "region": "south",   "count": 280  }
  ],
  "districts": [
    { "code": "hoan-kiem",    "name": "Hoàn Kiếm",    "province": "hanoi",    "count": 240 },
    { "code": "dong-da",      "name": "Đống Đa",       "province": "hanoi",    "count": 210 },
    { "code": "cau-giay",     "name": "Cầu Giấy",      "province": "hanoi",    "count": 185 },
    { "code": "thanh-xuan",   "name": "Thanh Xuân",    "province": "hanoi",    "count": 162 },
    { "code": "quan-1",       "name": "Quận 1",        "province": "hcm",      "count": 310 },
    { "code": "quan-3",       "name": "Quận 3",        "province": "hcm",      "count": 198 },
    { "code": "binh-thanh",   "name": "Bình Thạnh",    "province": "hcm",      "count": 175 },
    { "code": "hai-chau",     "name": "Hải Châu",      "province": "danang",   "count": 220 },
    { "code": "son-tra",      "name": "Sơn Trà",       "province": "danang",   "count": 145 },
    { "code": "ninh-kieu",    "name": "Ninh Kiều",     "province": "cantho",   "count": 195 }
  ]
}
```

### Mô tả các field

**`regions[]`**

| Field | Type | Ghi chú |
|---|---|---|
| `code` | string | `north` \| `central` \| `south` — dùng làm giá trị trong param `region[]` |
| `name` | string | Tên hiển thị: "Miền Bắc" / "Miền Trung" / "Miền Nam" |
| `count` | integer | Tổng số màn hình active trong toàn bộ miền |

**`provinces[]`**

| Field | Type | Ghi chú |
|---|---|---|
| `code` | string | Slug tỉnh — ánh xạ 1-1 với giá trị param `city[]` hiện tại (backward compatible) |
| `name` | string | Tên tỉnh/thành phố tiếng Việt |
| `region` | string | Foreign key → `regions[].code` |
| `count` | integer | Số màn hình active trong tỉnh |

**`districts[]`**

| Field | Type | Ghi chú |
|---|---|---|
| `code` | string | Slug quận/huyện — dùng làm giá trị trong param `district[]` |
| `name` | string | Tên quận/huyện tiếng Việt |
| `province` | string | Foreign key → `provinces[].code` |
| `count` | integer | Số màn hình active trong quận/huyện |

### Quy tắc trả về

- Một tỉnh chỉ xuất hiện trong `provinces[]` nếu có ít nhất 1 màn hình active.
- Một quận/huyện chỉ xuất hiện trong `districts[]` nếu có ít nhất 1 màn hình active.
- Một miền chỉ xuất hiện trong `regions[]` nếu có ít nhất 1 tỉnh active.
- `provinces[].count` = tổng cộng từ tất cả quận/huyện con. `regions[].count` = tổng cộng từ tất cả tỉnh con.
- Nếu không có dữ liệu, ba mảng đều trả về rỗng — không dùng dữ liệu hardcoded.

### Response 401

```json
{ "message": "Unauthenticated." }
```

---

## 4. GET /inventory/owners — mở rộng

Mở rộng endpoint hiện có để hỗ trợ tìm kiếm theo tên, phục vụ filter sidebar "Chọn đơn vị sở hữu" (owner search combobox).

```
GET /api/v1/inventory/owners
Authorization: Bearer {token}
```

### Query params (đầy đủ sau khi mở rộng)

| Param | Type | Default | Ghi chú |
|---|---|---|---|
| `page` | integer | 1 | |
| `limit` | integer | 20 | Max: 100 |
| `featured` | boolean | — | `true` → chỉ trả featured owners |
| `q` | string | — | **[MỚI]** Tìm kiếm theo tên owner. Max 100 ký tự. Case-insensitive, partial match (`LIKE %q%`). |

**Ví dụ — tìm kiếm theo tên:**
```
GET /api/v1/inventory/owners?q=Vingroup&limit=10
```

**Ví dụ — danh sách đầy đủ (không đổi so với trước):**
```
GET /api/v1/inventory/owners?limit=50&page=1
```

**Ví dụ — featured cho homepage:**
```
GET /api/v1/inventory/owners?featured=true&limit=6
```

### Cache

- Khi **không có `q`**: cache 10 phút (hành vi cũ giữ nguyên).
- Khi **có `q`**: cache **2 phút** (kết quả search thay đổi nhanh hơn do dữ liệu owner có thể được cập nhật).

### Response 200

Cấu trúc response không thay đổi so với hiện tại:

```json
{
  "total": 3,
  "page": 1,
  "limit": 10,
  "data": [
    {
      "owner_id": "vingroup-media",
      "name": "Vingroup Media",
      "tagline": "Hệ thống màn hình trong Vincom Center toàn quốc",
      "logo_url": "https://cdn.tapon.vn/owners/vingroup-media/logo.png",
      "cover_url": "https://cdn.tapon.vn/owners/vingroup-media/cover.jpg",
      "screen_count": 340,
      "city_count": 12,
      "venue_types": ["mall", "outdoor"],
      "website": "https://vingroup.net",
      "email": "contact@vingroup.net",
      "phone": "+84 24 3974 9999",
      "founded": 2010,
      "featured": true,
      "headquarters": { "lat": 21.028, "lng": 105.834 }
    }
  ]
}
```

> `owner_id` = slug của owner. Giá trị này được dùng làm giá trị trong param `owner[]` khi filter màn hình.

### Xử lý lỗi

**Response 422 — `q` quá dài:**
```json
{
  "message": "The q field must not be greater than 100 characters.",
  "errors": { "q": ["The q field must not be greater than 100 characters."] }
}
```

**Response 401:**
```json
{ "message": "Unauthenticated." }
```

---

## 5. GET /inventory/screens và GET /inventory/screens/map — mở rộng

Thêm 4 filter param mới vào cả hai endpoint. Tất cả đều hỗ trợ cú pháp multi-value `param[]=val1&param[]=val2`.

```
GET /api/v1/inventory/screens
GET /api/v1/inventory/screens/map
Authorization: Bearer {token}
```

### Các param mới (thêm vào danh sách param hiện có)

| Param | Type | Ghi chú |
|---|---|---|
| `network[]` | string[] | Lọc theo network code (giá trị từ `GET /inventory/networks → data[].code`). Multi-value. |
| `owner[]` | string[] | Lọc theo owner slug (giá trị từ `GET /inventory/owners → data[].owner_id`). Multi-value. |
| `district[]` | string[] | Lọc theo district code (giá trị từ `GET /inventory/locations → districts[].code`). Multi-value. |
| `region[]` | string[] | Lọc theo miền: `north` \| `central` \| `south`. Multi-value. |

### Ví dụ request — kết hợp nhiều filter

```
GET /api/v1/inventory/screens?city[]=hanoi&city[]=hcm&network[]=winmart-plus&network[]=aeon-mall&sort=price_asc&page=1&limit=24
```

```
GET /api/v1/inventory/screens?region[]=north&district[]=cau-giay&district[]=dong-da&venue_type[]=mall&screen_type[]=lcd
```

```
GET /api/v1/inventory/screens?owner[]=vingroup-media&owner[]=adtrue&sort=newest&limit=50
```

```
GET /api/v1/inventory/screens/map?region[]=south&network[]=go-mall&venue_type[]=mall
```

### Hành vi kết hợp filter (AND logic)

- Tất cả các filter param kết hợp với nhau theo logic **AND**: màn hình phải thỏa mãn tất cả điều kiện đang được set.
- Trong một param multi-value (ví dụ `city[]=hanoi&city[]=hcm`), logic là **OR**: màn hình thuộc một trong các giá trị đều được trả về.
- Ví dụ: `region[]=north&district[]=quan-1` sẽ không trả về kết quả vì `quan-1` thuộc miền Nam — không có màn hình nào thỏa đồng thời cả hai.

### Lưu ý backward compatibility

- Các param hiện có (`city[]`, `venue_type[]`, `screen_type[]`, `orientation[]`, `q`, `sort`, v.v.) không thay đổi.
- `city[]` và `province[]` là **tương đương nhau** — `province[]` được chấp nhận như alias. Xem thêm ở [mục 7](#7-ghi-chú-triển-khai).
- Response structure không thay đổi — chỉ tập dữ liệu trả về được lọc thêm.

### Response 200

Cấu trúc response giữ nguyên như hiện tại. Xem `GET /inventory/screens` trong `tapon_api_list.md`.

### Response 422 — giá trị không hợp lệ

```json
{
  "message": "The selected region is invalid.",
  "errors": {
    "region.0": ["The selected region is invalid."]
  }
}
```

> Validate `region[]` nhận đúng 3 giá trị: `north`, `central`, `south`. Các param còn lại (`network[]`, `owner[]`, `district[]`) không cần validate enum — nếu code không tồn tại thì trả về kết quả rỗng, không báo lỗi.

---

## 6. Sơ đồ tổng hợp tất cả filter params

Danh sách đầy đủ tất cả filter params được hỗ trợ trên `GET /inventory/screens` và `GET /inventory/screens/map` sau khi mở rộng:

```
GET /api/v1/inventory/screens
  ?page={integer}
  &limit={integer}
  &status={active|inactive}
  &q={string}
  &sort={price_asc|price_desc|newest}

  # Địa lý
  &city[]={hanoi|hcm|danang|haiphong|cantho}     ← hiện có (= province[])
  &region[]={north|central|south}                 ← MỚI
  &district[]={slug}                              ← MỚI

  # Loại màn hình
  &venue_type[]={mall|outdoor|fnb|transit|office}
  &screen_type[]={lcd|led|billboard}
  &orientation[]={landscape|portrait|square}

  # Đơn vị & chuỗi
  &network[]={network-code}                       ← MỚI
  &owner[]={owner-slug}                           ← MỚI

  # Sync
  &updated_after={ISO8601}
```

---

## 7. Ghi chú triển khai

Phần này dành cho Laravel developer. Giải thích cách lưu trữ và derive các field mới.

---

### 7.1 Network codes — lưu trữ thế nào?

**Khuyến nghị: Tạo bảng `networks` riêng.**

```sql
CREATE TABLE networks (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(100) NOT NULL UNIQUE,  -- slug, vd: "winmart-plus"
    name        VARCHAR(255) NOT NULL,         -- tên hiển thị, vd: "Winmart+"
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP
);
```

Bảng `screens` thêm foreign key:

```sql
ALTER TABLE screens ADD COLUMN network_code VARCHAR(100) NULL;
ALTER TABLE screens ADD CONSTRAINT fk_screens_network
    FOREIGN KEY (network_code) REFERENCES networks(code) ON DELETE SET NULL;
```

- `code` nên được sinh ra bằng `Str::slug($name)` khi tạo mới.
- Không nên derive network từ pattern matching trên tên màn hình — dễ sai và khó maintain.
- Endpoint `GET /inventory/networks` chỉ query `networks` JOIN `screens WHERE status = active`, đếm count, rồi loại bỏ network có count = 0.

---

### 7.2 District codes — lưu trữ thế nào?

**Khuyến nghị: Thêm cột `location_district_code` vào bảng `screens`.**

```sql
ALTER TABLE screens ADD COLUMN location_district_code VARCHAR(100) NULL;
```

- Giá trị là slug tiếng Việt không dấu, ví dụ: `"cau-giay"`, `"quan-1"`, `"hai-chau"`.
- Sinh ra bằng `Str::slug($district_name)` lúc import hoặc lúc nhập liệu.
- Nếu hệ thống có sẵn cột `location_district` (tên tiếng Việt), có thể tạo cột code bằng migration + computed:

```php
// Migration tạo cột code từ cột tên hiện có
Screen::whereNotNull('location_district')
      ->whereNull('location_district_code')
      ->eachById(function ($screen) {
          $screen->update([
              'location_district_code' => Str::slug($screen->location_district),
          ]);
      });
```

- Endpoint `GET /inventory/locations` derive `districts[]` bằng cách GROUP BY `location_district_code`, `location_district` (tên), `location_city` (province), đếm count — chỉ với `status = active`.

---

### 7.3 Ánh xạ miền — tỉnh nào thuộc miền nào?

Ánh xạ tĩnh trong code Laravel (không cần bảng DB). Gợi ý dùng enum hoặc config array:

```php
// config/regions.php
return [
    'north' => [
        'name'      => 'Miền Bắc',
        'provinces' => [
            'hanoi', 'haiphong', 'quangninh', 'bacninh', 'bacgiang',
            'vinhphuc', 'hungyen', 'haiduong', 'thaibinh', 'namdinh',
            'ninhbinh', 'hatinh', 'thanhhoa', 'laocai', 'yenbai',
            'tuyenquang', 'hagiang', 'caobang', 'langson', 'backan',
            'thainguyen', 'phutho', 'hoabinh', 'sonla', 'dienbien',
            'laichau',
        ],
    ],
    'central' => [
        'name'      => 'Miền Trung',
        'provinces' => [
            'danang', 'hue', 'quangnam', 'quangngai', 'binhdinh',
            'phuyen', 'khanhhoa', 'ninhthuan', 'binhthuan', 'kontum',
            'gialai', 'daklak', 'daknong', 'lamdong', 'quangbinh',
            'quangtri',
        ],
    ],
    'south' => [
        'name'      => 'Miền Nam',
        'provinces' => [
            'hcm', 'cantho', 'binhduong', 'dongnai', 'bariavungtau',
            'longan', 'tiengiang', 'bentre', 'travinh', 'vinhlong',
            'dongthap', 'angiang', 'kiengiang', 'haugiang', 'soctrang',
            'baclieu', 'camau', 'tayninh', 'binhphuoc',
        ],
    ],
];
```

Khi filter `region[]=north`, Laravel resolve thành danh sách province codes rồi dùng `whereIn('location_city', $provinceCodes)`.

---

### 7.4 Backward compatibility: `city[]` vs `province[]`

Param `city[]` (hiện có) và `province[]` (tên mới ngữ nghĩa hơn) đều phải được chấp nhận và xử lý như nhau.

```php
// Trong FormRequest hoặc Controller
$cities = array_merge(
    $request->input('city', []),
    $request->input('province', []),  // alias
);
$cities = array_unique(array_filter($cities));
```

- Frontend mới sẽ dùng `province[]` cho nhất quán với `region[]` và `district[]`.
- Frontend cũ dùng `city[]` — không được break.
- Tài liệu OOHX BFF sẽ map `province[]` → `city[]` hoặc dùng trực tiếp `province[]` tùy theo phiên bản Laravel nào được deploy trước.

---

### 7.5 Cache strategy

| Endpoint | TTL | Invalidation |
|---|---|---|
| `GET /inventory/networks` | 30 phút | Khi screen được tạo mới, soft-deleted, hoặc đổi `status`/`network_code` |
| `GET /inventory/locations` | 30 phút | Khi screen được tạo mới, soft-deleted, hoặc đổi `location_district_code`/`location_city` |
| `GET /inventory/owners` (không có `q`) | 10 phút | Giữ nguyên hành vi cũ |
| `GET /inventory/owners` (có `q`) | 2 phút | Kết quả có thể stale nhanh hơn |
| `GET /inventory/screens` + new params | Không cache | Giữ nguyên hành vi cũ |

Cache key cho `networks` và `locations` không cần param vì endpoint không nhận query params — dùng key cố định, ví dụ `inventory:networks` và `inventory:locations`.

---

### 7.6 Không dùng dữ liệu mock

Tất cả endpoint trong tài liệu này phải trả về dữ liệu thực từ database.

- `GET /inventory/networks`: nếu chưa có bảng `networks` hoặc chưa có dữ liệu → trả về `{ "data": [] }`.
- `GET /inventory/locations`: nếu chưa có `location_district_code` → trả về `{ "regions": [...], "provinces": [...], "districts": [] }` với chỉ regions và provinces được derive từ `location_city`.
- Không bao giờ trả về danh sách hardcoded trong code PHP để "cho UI chạy được".

---

## OOHX BFF — Mapping routes cần tạo

Sau khi Laravel build xong, OOHX BFF sẽ tạo proxy routes tương ứng:

| OOHX BFF | → TapON | Cache TTL (BFF) |
|---|---|---|
| `GET /api/networks` | `GET /inventory/networks` | 30 phút |
| `GET /api/locations` | `GET /inventory/locations` | 30 phút |
| `GET /api/owners?q=...` | `GET /inventory/owners?q=...` | 2 phút |
| `GET /api/owners` | `GET /inventory/owners` | 10 phút |
| `GET /api/screens?network[]=...&owner[]=...&district[]=...&region[]=...` | `GET /inventory/screens` (same params) | Không cache |
| `GET /api/screens/map?network[]=...` | `GET /inventory/screens/map` (same params) | Không cache |

---

## Thứ tự triển khai khuyến nghị

```
Bước 1 — Chuẩn bị DB
  → Tạo bảng networks
  → Thêm cột location_district_code vào screens
  → Thêm cột network_code vào screens
  → Seed dữ liệu networks từ dữ liệu thực

Bước 2 — GET /inventory/networks
  → Unblock filter "Chuỗi/Network" trên Browse sidebar

Bước 3 — GET /inventory/locations
  → Unblock filter "Khu vực / Quận huyện" trên Browse sidebar

Bước 4 — Mở rộng GET /inventory/owners (thêm param q)
  → Unblock owner search combobox trên Browse sidebar

Bước 5 — Mở rộng GET /inventory/screens + /map (thêm 4 params)
  → Hoàn thiện filter logic phía backend
```

---

_Liên hệ dev OOHX sau khi hoàn thành từng bước để tích hợp vào BFF proxy và cập nhật Browse filter sidebar._
