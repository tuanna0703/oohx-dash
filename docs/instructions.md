# TapON SSP — API Integration Guide for OOHX Frontend

> **Version:** 1.0
> **Base URL:** `https://ssp.tapon.vn/api/v1`
> **Cập nhật:** 2026-03-21

---

## 1. Kiến trúc tổng quan

```
Browser (oohx.net)
    │
    ▼
OOHX Backend (Next.js API Route / BFF)   ← giữ client_secret ở đây
    │  Authorization: Bearer <token>
    ▼
ssp.tapon.vn/api/v1                      ← TapON SSP API
    │
    ▼
Database (screens, bookings, reports)
```

> ⚠️ **Bắt buộc:** `client_secret` KHÔNG được để ở browser/client-side code.
> Tất cả request đến `ssp.tapon.vn` phải đi qua OOHX backend server.

---

## 2. Authentication

### 2.1 Lấy access token

```
POST https://ssp.tapon.vn/api/v1/auth/token
Content-Type: application/json
```

**Request body:**
```json
{
  "client_id": "oohx_marketplace",
  "client_secret": "<TAPON_CLIENT_SECRET>",
  "grant_type": "client_credentials",
  "scope": "inventory booking reporting"
}
```

**Response 200:**
```json
{
  "access_token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope": "inventory booking reporting"
}
```

**Response 401 (sai credentials):**
```json
{
  "error": "invalid_client",
  "error_description": "Client authentication failed"
}
```

### 2.2 Dùng token

Gắn token vào header mỗi request:
```
Authorization: Bearer <access_token>
```

### 2.3 Token management

- Token hết hạn sau **3600 giây (1 giờ)**
- Nên cache token và refresh trước khi hết hạn (~60 giây)
- Nếu nhận `401` từ bất kỳ endpoint nào → gọi lại `/auth/token` để lấy token mới

---

## 3. Endpoints hiện có (Phase 1 & 2)

### 3.1 Danh sách màn hình

```
GET /inventory/screens
Authorization: Bearer <token>
```

**Query params (tất cả optional):**

| Param | Kiểu | Ví dụ | Mô tả |
|---|---|---|---|
| `page` | int | `1` | Trang hiện tại |
| `limit` | int (max 100) | `50` | Số items/trang |
| `city` | string | `hanoi` | Lọc theo thành phố |
| `venue_type` | string | `mall` | Lọc theo loại địa điểm |
| `screen_type` | string | `lcd` | Lọc theo loại màn hình |
| `status` | `active\|inactive` | `active` | Lọc theo trạng thái |
| `updated_after` | ISO8601 | `2026-03-01T00:00:00Z` | Incremental sync |

**Giá trị `venue_type`:** `mall` | `outdoor` | `fnb` | `transit` | `office`

**Giá trị `screen_type`:** `lcd` | `led` | `billboard`

**Response 200:**
```json
{
  "total": 1240,
  "page": 1,
  "limit": 50,
  "data": [
    {
      "screen_id": "SCR-HN-001",
      "name": "Màn hình Vincom Bà Triệu - Tầng 1",
      "owner_id": "OWN-01J...",
      "owner_name": "VinMedia",
      "screen_type": "lcd",
      "venue_type": "mall",
      "location": {
        "address": "191 Bà Triệu, Hai Bà Trưng",
        "city": "hanoi",
        "district": "Hai Bà Trưng",
        "lat": 21.0122,
        "lng": 105.8412
      },
      "specs": {
        "width_px": 1920,
        "height_px": 1080,
        "width_m": 2.0,
        "height_m": 1.1,
        "orientation": "landscape",
        "resolution": "1080p"
      },
      "slot_duration_sec": 15,
      "slots_per_loop": 8,
      "operating_hours": {
        "open": "08:00",
        "close": "22:00",
        "days": ["mon","tue","wed","thu","fri","sat","sun"]
      },
      "price_per_slot_vnd": 500000,
      "min_booking_days": 7,
      "photos": ["https://cdn.tapon.vn/screens/SCR-HN-001/photo1.jpg"],
      "status": "active",
      "updated_at": "2026-03-01T10:00:00Z"
    }
  ]
}
```

---

### 3.2 Chi tiết một màn hình

```
GET /inventory/screens/:screen_id
Authorization: Bearer <token>
```

`:screen_id` = giá trị `screen_id` từ response danh sách.

**Response 200:** Object screen (cùng schema với item trong `data[]` ở trên)

**Response 404:**
```json
{
  "error": "screen_not_found",
  "message": "Screen SCR-HN-001 does not exist"
}
```

---

### 3.3 Đăng ký webhook nhận inventory update

```
POST /inventory/webhook/register
Authorization: Bearer <token>
Content-Type: application/json
```

**Request body:**
```json
{
  "url": "https://oohx.net/api/webhooks/tapon/inventory",
  "events": ["screen.created", "screen.updated", "screen.deactivated"],
  "secret": "YOUR_WEBHOOK_SECRET_MIN_16_CHARS"
}
```

**Response 200:**
```json
{
  "webhook_id": "WH-A3F9K2",
  "status": "active"
}
```

**Webhook payload TapON sẽ gửi về `url`:**
```json
{
  "event": "screen.updated",
  "timestamp": "2026-03-15T08:30:00Z",
  "data": {
    "screen_id": "SCR-HN-001",
    "changed_fields": ["price_per_slot_vnd", "status"]
  }
}
```

**Verify webhook:** Header `X-TapOn-Signature: sha256=<hmac>` — verify bằng:
```typescript
const expectedSig = 'sha256=' + createHmac('sha256', YOUR_WEBHOOK_SECRET)
  .update(rawBody)
  .digest('hex')
const isValid = expectedSig === req.headers['x-tapon-signature']
```

---

## 4. Endpoints sắp có

| Endpoint | Phase | Dự kiến |
|---|---|---|
| `POST /availability/check` | Phase 3 | Tuần tới |
| `GET /pricing/rate-card` | Phase 3 | Tuần tới |
| `POST /campaigns` | Phase 4 | Sắp có |
| `POST /creatives/upload` | Phase 4 | Sắp có |
| `POST /bookings` | Phase 4 | Sắp có |
| `GET /bookings/:id` | Phase 4 | Sắp có |
| `POST /bookings/:id/cancel` | Phase 4 | Sắp có |
| `GET /reports/impressions` | Phase 5 | Sắp có |
| `GET /reports/revenue` | Phase 5 | Sắp có |

---

## 5. Error Response chuẩn

Tất cả lỗi (trừ auth) trả theo format:
```json
{
  "error": "validation_error",
  "message": "date_from must be before date_to",
  "code": 400,
  "details": [
    {
      "field": "date_from",
      "issue": "invalid_format",
      "expected": "YYYY-MM-DD"
    }
  ]
}
```

| HTTP | error | Ý nghĩa |
|---|---|---|
| 400 | `validation_error` | Payload sai định dạng |
| 401 | `unauthorized` | Token không hợp lệ / hết hạn |
| 403 | `forbidden` | Token thiếu scope |
| 404 | `not_found` / `screen_not_found` | Resource không tồn tại |
| 409 | `conflict` | Slot đã bị book |
| 429 | `rate_limited` | Quá nhiều request |
| 500 | `server_error` | Lỗi server |

---

## 6. Code mẫu (TypeScript / Next.js)

### `lib/tapon/client.ts`

```typescript
const SSP_BASE = 'https://ssp.tapon.vn/api/v1'

interface TokenCache {
  token: string
  expiresAt: number
}

let _tokenCache: TokenCache | null = null

async function fetchToken(): Promise<string> {
  // Còn hơn 60 giây thì tái sử dụng
  if (_tokenCache && _tokenCache.expiresAt - Date.now() > 60_000) {
    return _tokenCache.token
  }

  const res = await fetch(`${SSP_BASE}/auth/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      client_id:     'oohx_marketplace',
      client_secret: process.env.TAPON_CLIENT_SECRET!, // server-side only
      grant_type:    'client_credentials',
      scope:         'inventory booking reporting',
    }),
  })

  if (!res.ok) throw new Error(`TapON auth failed: ${res.status}`)

  const data = await res.json()
  _tokenCache = {
    token:     data.access_token,
    expiresAt: Date.now() + data.expires_in * 1000,
  }

  return _tokenCache.token
}

export async function sspFetch(
  path: string,
  init: RequestInit = {},
  retried = false,
): Promise<Response> {
  const token = await fetchToken()

  const res = await fetch(`${SSP_BASE}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      ...(init.headers as Record<string, string>),
    },
  })

  // Token hết hạn → clear cache, retry 1 lần
  if (res.status === 401 && !retried) {
    _tokenCache = null
    return sspFetch(path, init, true)
  }

  return res
}
```

### `lib/tapon/inventory.ts`

```typescript
import { sspFetch } from './client'
import type { TapOnScreen } from './types'

interface ScreenListResponse {
  total: number
  page: number
  limit: number
  data: TapOnScreen[]
}

export async function getScreens(params?: {
  page?: number
  limit?: number
  city?: string
  venue_type?: string
  screen_type?: string
  status?: 'active' | 'inactive'
  updated_after?: string
}): Promise<ScreenListResponse> {
  const qs = new URLSearchParams()
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined) qs.set(k, String(v))
    })
  }

  const res = await sspFetch(`/inventory/screens?${qs}`)
  if (!res.ok) throw new Error(`getScreens failed: ${res.status}`)
  return res.json()
}

export async function getScreen(screenId: string): Promise<TapOnScreen> {
  const res = await sspFetch(`/inventory/screens/${screenId}`)
  if (res.status === 404) throw new Error('screen_not_found')
  if (!res.ok) throw new Error(`getScreen failed: ${res.status}`)
  return res.json()
}
```

### `lib/tapon/types.ts`

```typescript
export interface TapOnScreen {
  screen_id: string
  name: string
  owner_id: string
  owner_name: string
  screen_type: 'lcd' | 'led' | 'billboard'
  venue_type: 'mall' | 'outdoor' | 'fnb' | 'transit' | 'office'
  location: {
    address: string
    city: string
    district: string
    lat: number
    lng: number
  }
  specs: {
    width_px: number
    height_px: number
    width_m: number | null
    height_m: number | null
    orientation: 'landscape' | 'portrait' | 'square'
    resolution: string | null
  }
  slot_duration_sec: number
  slots_per_loop: number
  operating_hours: {
    open: string
    close: string
    days: string[]
  }
  price_per_slot_vnd: number
  min_booking_days: number
  photos: string[]
  status: 'active' | 'inactive'
  updated_at: string
}

export interface TapOnApiError {
  error: string
  message: string
  code: number
  details?: Array<{
    field: string
    issue: string
    expected?: string
  }>
}
```

### Next.js API Route mẫu — `app/api/screens/route.ts`

```typescript
import { getScreens } from '@/lib/tapon/inventory'
import { NextRequest, NextResponse } from 'next/server'

export async function GET(req: NextRequest) {
  try {
    const { searchParams } = req.nextUrl

    const data = await getScreens({
      page:        Number(searchParams.get('page') ?? 1),
      limit:       Number(searchParams.get('limit') ?? 20),
      city:        searchParams.get('city') ?? undefined,
      venue_type:  searchParams.get('venue_type') ?? undefined,
      status:      'active',
    })

    return NextResponse.json(data)
  } catch (err) {
    return NextResponse.json({ error: 'Failed to load screens' }, { status: 500 })
  }
}
```

---

## 7. Environment variables cần thiết (OOHX .env)

```bash
# Chỉ dùng ở server-side (Next.js: không có prefix NEXT_PUBLIC_)
TAPON_CLIENT_SECRET=<lấy từ TapON team>
TAPON_BASE_URL=https://ssp.tapon.vn/api/v1

# Nếu dùng webhook
TAPON_WEBHOOK_SECRET=<tự đặt, min 16 ký tự, gửi lại khi register webhook>
```

---

## 8. Checklist tích hợp cho OOHX dev

- [ ] Thêm `TAPON_CLIENT_SECRET` vào `.env` (server-side, không commit git)
- [ ] Tạo `lib/tapon/client.ts` với token auto-refresh
- [ ] Tạo `lib/tapon/inventory.ts` với `getScreens()` + `getScreen()`
- [ ] Tạo Next.js API routes làm proxy (`/api/screens`, `/api/screens/:id`)
- [ ] Hiển thị danh sách screens với filter city / venue_type
- [ ] Hiển thị map với lat/lng từ `location`
- [ ] (Sau Phase 3) Tích hợp availability check trước khi cho user chọn ngày
- [ ] (Sau Phase 4) Tích hợp booking flow
- [ ] (Webhook) Đăng ký webhook để sync inventory realtime

---

## 9. Liên hệ & hỗ trợ

Mọi thắc mắc về API liên hệ TapON backend team.
Báo lỗi kèm theo: `endpoint`, `request body`, `response body`, `timestamp`.
