# TapON Homepage API — Tổng kết triển khai & Guideline Frontend

> **Hoàn thành:** 2026-03-24
> **Backend:** Laravel (TapON SSP — `ssp.tapon.vn`)
> **Frontend mục tiêu:** OOHX (`oohx.net`)

---

## Tổng quan: Những gì đã build

Tất cả 3 API theo yêu cầu trong `api_tapon_update_homepage_v1.md` đã được triển khai, có test đầy đủ và deploy sẵn sàng.

| API | Endpoint | Status |
|---|---|---|
| Stats tổng hợp | `GET /api/v1/inventory/stats` | ✅ Done |
| Danh sách owners | `GET /api/v1/inventory/owners` | ✅ Done |
| Chi tiết owner | `GET /api/v1/inventory/owners/{slug}` | ✅ Done |

**Test coverage:** 58 tests / 183 assertions — tất cả pass.

---

## Auth

Tất cả endpoint đều yêu cầu Bearer token với scope `inventory`.

```http
Authorization: Bearer {access_token}
```

Lấy token:
```http
POST /api/v1/auth/token
Content-Type: application/json

{
  "client_id": "your_client_id",
  "client_secret": "your_client_secret",
  "grant_type": "client_credentials",
  "scope": "inventory"
}
```

---

## API 1 — `GET /inventory/stats`

### Request

```http
GET /api/v1/inventory/stats
Authorization: Bearer {token}
```

Không có query params.

### Response thực tế

```json
{
  "total_screens": 3644,
  "total_cities": 5,
  "total_owners": 42,
  "venues": [
    { "type": "mall",          "label": "Retail",        "count": 1200 },
    { "type": "outdoor",       "label": "Outdoor",       "count": 800  },
    { "type": "fnb",           "label": "F&B",           "count": 650  },
    { "type": "transit",       "label": "Transit",       "count": 400  },
    { "type": "office",        "label": "Office",        "count": 350  },
    { "type": "entertainment", "label": "Entertainment", "count": 244  }
  ],
  "cities": [
    { "code": "hanoi",    "name": "Hà Nội",          "count": 1500 },
    { "code": "hcm",      "name": "TP. Hồ Chí Minh", "count": 1200 },
    { "code": "danang",   "name": "Đà Nẵng",          "count": 400  },
    { "code": "haiphong", "name": "Hải Phòng",        "count": 300  },
    { "code": "cantho",   "name": "Cần Thơ",          "count": 244  }
  ]
}
```

### Lưu ý quan trọng cho frontend

- **`cities` luôn trả về đúng 5 thành phố** (hanoi, hcm, danang, haiphong, cantho), kể cả khi `count = 0`. Frontend không cần guard null.
- **`venues` chỉ trả về loại đang có dữ liệu** — nếu không có màn hình nào với `venue_type = entertainment` thì key đó sẽ vắng mặt. Frontend nên fallback về 0 nếu cần hiển thị đủ 6 loại.
- **Cache 30 phút** — dữ liệu không real-time. Thích hợp cho Stats Strip, không dùng làm badge count realtime.
- **`total_screens` / `total_cities` / `total_owners`** chỉ đếm screens có `active = true`.

### Mapping UI → field

| Khối UI trên homepage | Field cần dùng |
|---|---|
| Stats Strip "12,000+ Màn hình" | `total_screens` |
| Stats Strip "34 Tỉnh thành" | `total_cities` |
| Stats Strip "50+ Media owners" | `total_owners` |
| Dropdown Địa điểm (Hero search) | `cities[]` — `name` làm label, `code` làm value khi search, `count` hiển thị phụ |
| Section Venue Types (Retail 320...) | `venues[]` — `label` làm tiêu đề, `count` làm số |

---

## API 2 — `GET /inventory/owners`

### Request

```http
GET /api/v1/inventory/owners?featured=true&limit=6
Authorization: Bearer {token}
```

### Query params

| Param | Type | Default | Ghi chú |
|---|---|---|---|
| `featured` | boolean | — | Truyền `true` để lấy only featured owners (cho homepage) |
| `limit` | integer | 20 | Max 100 |
| `page` | integer | 1 | |

### Response thực tế

```json
{
  "total": 42,
  "page": 1,
  "limit": 6,
  "data": [
    {
      "owner_id": "adtrue-media",
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
      "headquarters": { "lat": 21.0285, "lng": 105.8542 }
    }
  ]
}
```

### Lưu ý quan trọng cho frontend

- **`owner_id` = slug** (string), không phải UUID/integer. Dùng làm URL param: `/owners/{owner_id}`.
- **Chỉ trả về owners có `status = active`** — không cần filter thêm phía frontend.
- **`logo_url`, `cover_url`, `tagline`, `headquarters`** có thể `null` nếu owner chưa điền. Frontend phải xử lý null:
  - `logo_url = null` → dùng avatar fallback (tên viết tắt / icon mặc định)
  - `headquarters = null` → ẩn map mini
- **`venue_types`** là array string đã dedup và sorted. Có thể map trực tiếp sang chip tags.
- **`founded`** là integer (năm), vd: `2014`. Nullable.
- **`screen_count`** chỉ đếm screens `active = true` — số hiển thị đã filtered.

### Cách dùng cho từng trang

```
Homepage (section Featured Owners):
  GET /inventory/owners?featured=true&limit=6

Trang /owners (liệt kê tất cả):
  GET /inventory/owners?limit=50&page=1
```

---

## API 3 — `GET /inventory/owners/{slug}`

### Request

```http
GET /api/v1/inventory/owners/adtrue-media
Authorization: Bearer {token}
```

### Response thực tế

Giống 1 item trong `data[]` của API 2, **cộng thêm field `about`**:

```json
{
  "owner_id": "adtrue-media",
  "name": "AdTRUE Media",
  "tagline": "Hệ thống Billboard & OOH lớn nhất miền Bắc",
  "about": "Mô tả dài về công ty...",
  "logo_url": "https://...",
  "cover_url": "https://...",
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

### Error 404

```json
HTTP 404
{ "error": "Owner not found" }
```

### Lưu ý quan trọng cho frontend

- **`about`** là field chỉ có ở detail, **không có trong list**. Đây là rich text/long description.
- **404 xảy ra khi:** slug không tồn tại, hoặc owner có `status != active` (suspended/pending). Frontend nên redirect về `/owners` hoặc hiển thị 404 page.
- **`owner_id` trong response = slug trong URL** — có thể dùng để canonical URL.

---

## API hiện có (không cần build thêm)

### Inventory Preview 3 cards

```http
GET /api/v1/inventory/screens?limit=3&sort=newest
Authorization: Bearer {token}
```

API này đã có từ trước. Dùng trực tiếp cho section "Inventory Preview" trên homepage.

---

## Guideline tích hợp BFF (OOHX Next.js)

### Cấu trúc đề xuất

```
OOHX BFF route               TapON endpoint               Cache TTL
─────────────────────────    ─────────────────────────    ──────────
GET /api/stats               GET /inventory/stats         30 phút
GET /api/owners              GET /inventory/owners        10 phút
GET /api/owners/:slug        GET /inventory/owners/:slug  10 phút
GET /api/screens             GET /inventory/screens       5 phút
```

### Ví dụ Next.js Route Handler

```ts
// app/api/stats/route.ts
import { NextResponse } from 'next/server'

const SSP_BASE = process.env.SSP_API_URL   // 'https://ssp.tapon.vn/api/v1'
const SSP_TOKEN = process.env.SSP_API_TOKEN

export async function GET() {
  const res = await fetch(`${SSP_BASE}/inventory/stats`, {
    headers: { Authorization: `Bearer ${SSP_TOKEN}` },
    next: { revalidate: 1800 },  // 30 phút — khớp với server cache
  })

  if (!res.ok) return NextResponse.json({ error: 'upstream_error' }, { status: 502 })

  const data = await res.json()
  return NextResponse.json(data, {
    headers: { 'Cache-Control': 'public, s-maxage=1800, stale-while-revalidate=300' }
  })
}
```

```ts
// app/api/owners/route.ts
export async function GET(request: Request) {
  const { searchParams } = new URL(request.url)
  const featured = searchParams.get('featured') ?? ''
  const limit = searchParams.get('limit') ?? '20'

  const url = new URL(`${SSP_BASE}/inventory/owners`)
  if (featured) url.searchParams.set('featured', featured)
  url.searchParams.set('limit', limit)

  const res = await fetch(url.toString(), {
    headers: { Authorization: `Bearer ${SSP_TOKEN}` },
    next: { revalidate: 600 },  // 10 phút
  })

  const data = await res.json()
  return NextResponse.json(data)
}
```

```ts
// app/api/owners/[slug]/route.ts
export async function GET(_req: Request, { params }: { params: { slug: string } }) {
  const res = await fetch(`${SSP_BASE}/inventory/owners/${params.slug}`, {
    headers: { Authorization: `Bearer ${SSP_TOKEN}` },
    next: { revalidate: 600 },
  })

  if (res.status === 404) {
    return NextResponse.json({ error: 'not_found' }, { status: 404 })
  }

  const data = await res.json()
  return NextResponse.json(data)
}
```

### TypeScript types

```ts
// types/inventory.ts

export interface InventoryStats {
  total_screens: number
  total_cities:  number
  total_owners:  number
  venues: {
    type:  string
    label: string
    count: number
  }[]
  cities: {
    code:  string
    name:  string
    count: number
  }[]
}

export interface Owner {
  owner_id:    string        // slug — dùng cho URL
  name:        string
  tagline:     string | null
  logo_url:    string | null
  cover_url:   string | null
  screen_count: number
  city_count:   number
  venue_types:  string[]
  website:     string | null
  email:       string | null
  phone:       string | null
  founded:     number | null
  featured:    boolean
  headquarters: { lat: number; lng: number } | null
}

export interface OwnerDetail extends Owner {
  about: string | null       // Chỉ có ở GET /owners/:slug
}

export interface OwnersResponse {
  total: number
  page:  number
  limit: number
  data:  Owner[]
}
```

---

## Checklist tích hợp Frontend

- [ ] Tạo `.env.local`: `SSP_API_URL`, `SSP_API_TOKEN`
- [ ] Tạo BFF route `/api/stats` → proxy `GET /inventory/stats`
- [ ] Tạo BFF route `/api/owners` → proxy `GET /inventory/owners`
- [ ] Tạo BFF route `/api/owners/[slug]` → proxy `GET /inventory/owners/:slug`
- [ ] Homepage — Stats Strip: replace mock với `stats.total_screens`, `total_cities`, `total_owners`
- [ ] Homepage — Location dropdown: dùng `stats.cities[]` (5 thành phố, `code` làm query param khi search)
- [ ] Homepage — Venue Types section: dùng `stats.venues[]` (label + count)
- [ ] Homepage — Featured Owners: gọi `/api/owners?featured=true&limit=6`
- [ ] Xử lý null: `logo_url`, `cover_url`, `tagline`, `headquarters`, `about`, `founded`
- [ ] Trang `/owners/[slug]`: gọi `/api/owners/:slug`, hiển thị 404 page nếu nhận HTTP 404
- [ ] Inventory Preview 3 cards: gọi `/api/v1/inventory/screens?limit=3&sort=newest` (đã có sẵn)

---

## Những gì KHÔNG thay đổi / vẫn giữ static

| Khối UI | Lý do giữ static |
|---|---|
| Dropdown "Loại màn hình" (LCD/LED/Billboard) | Enum cố định, không từ DB |
| Dropdown "Venue type" (Mall/Outdoor...) | Enum cố định |
| Section "Campaign trong 3 bước" | Marketing copy |
| Section "Tại sao OOHX" | Marketing copy |
| "Phí đăng ký: 0đ" | Marketing copy |

---

## Liên hệ & môi trường

| | Staging | Production |
|---|---|---|
| TapON SSP | `staging.ssp.tapon.vn` | `ssp.tapon.vn` |
| Base URL | `/api/v1/inventory/...` | `/api/v1/inventory/...` |
| Token | Lấy từ `POST /api/v1/auth/token` | Lấy từ `POST /api/v1/auth/token` |
