# Kế hoạch triển khai: Inventory API Update — Phase 2

> **Yêu cầu gốc:** `docs/api_tapon_update_4_browse.md`
> **Ngày lập:** 2026-03-24
> **Ưu tiên:** 🔴 Cao (unblock OOHX Browse page)

---

## Phân tích hiện trạng

### Điểm xuất phát

| Thành phần | File | Ghi chú |
|---|---|---|
| Route | `routes/api.php:20-22` | Chỉ có `index` và `show`, chưa có `/map` |
| Controller | `app/Http/Controllers/Api/V1/InventoryController.php` | 67 dòng, filter đơn giản |
| Resource | `app/Http/Resources/Api/ScreenResource.php` | ~25 fields, đủ cho list |
| Collection | `app/Http/Resources/Api/ScreenCollection.php` | Wrapper `{total, page, limit, data}` |
| Tests | `tests/Feature/ExampleTest.php` | Chỉ có placeholder |

### Gap so với yêu cầu

| Yêu cầu | Hiện trạng | Vấn đề |
|---|---|---|
| Multi-value `city[]`, `venue_type[]`, `screen_type[]` | Dùng pipe-separated (`city=hanoi\|hcm`) | Không tương thích với chuẩn PHP array `param[]=val` |
| Param `orientation` | Không tồn tại | `orientation` là computed PHP attribute, không có DB column |
| Param `q` (text search) | Không tồn tại | Cần LIKE trên `screens.name`, `sites.address`, `sites.city` |
| Param `sort` | Không tồn tại | Cần ORDER BY trên `floor_cpm` hoặc `updated_at` |
| `GET /inventory/screens/map` | Không tồn tại | Endpoint mới, resource mới, không có `page`/`limit` |

### Ghi chú kỹ thuật quan trọng

1. **`orientation` không có DB column** — phải filter bằng WHERE trên `screen_specs`:
   - `landscape`: `width_px > height_px`
   - `portrait`: `width_px < height_px`
   - `square`: `width_px = height_px`

2. **`screen_type` không có DB column** — derived từ `spec.width_cm/height_cm` + `inventory.venue_type`:
   - `billboard`: `width_cm >= 300 OR height_cm >= 300`
   - `led`: `venue_type = 'outdoor'` AND không phải billboard
   - `lcd`: không phải outdoor và không phải billboard

3. **Multi-value backward compat** — phải hỗ trợ ĐỒNG THỜI:
   - `city=hanoi` (cũ)
   - `city=hanoi|hcm` (cũ, pipe-separated)
   - `city[]=hanoi&city[]=hcm` (mới, PHP array)

4. **`/map` endpoint không phân trang** — trả toàn bộ, chỉ 6 fields/item, dự kiến ~400KB cho 3600+ screens.

---

## Các bước triển khai

### Bước 1 — Multi-value filter: `city`, `venue_type`, `screen_type` (🔴 Cao)

**Mục tiêu:** Unblock bộ lọc chính của Browse page.

**File thay đổi:** `app/Http/Controllers/Api/V1/InventoryController.php`

**Cách giải quyết multi-value:**
```php
// Hàm helper nội bộ (private method trong controller)
private function resolveArrayParam(Request $request, string $key): array
{
    // Ưu tiên array syntax: city[]=hanoi&city[]=hcm
    $value = $request->input($key);

    if (is_array($value)) {
        return array_filter($value);  // city[]=hanoi&city[]=hcm
    }

    if (is_string($value) && str_contains($value, '|')) {
        return explode('|', $value);  // city=hanoi|hcm (legacy)
    }

    if (is_string($value) && $value !== '') {
        return [$value];              // city=hanoi (single value)
    }

    return [];
}
```

**Logic filter city:**
```php
->when(!empty($cities = $this->resolveArrayParam($request, 'city')),
    fn($q) => $q->whereHas('site', fn($sq) => $sq->whereIn('city', $cities))
)
```

**Logic filter venue_type:**
```php
->when(!empty($venueTypes = $this->resolveArrayParam($request, 'venue_type')),
    fn($q) => $q->whereHas('inventory', fn($iq) => $iq->whereIn('venue_type', $venueTypes))
)
```

**Logic filter screen_type (multi-value):**
```php
->when(!empty($screenTypes = $this->resolveArrayParam($request, 'screen_type')),
    function ($q) use ($screenTypes) {
        $q->where(function ($or) use ($screenTypes) {
            foreach ($screenTypes as $type) {
                $or->orWhere(function ($sub) use ($type) {
                    if ($type === 'led') {
                        $sub->whereHas('inventory', fn($iq) => $iq->where('venue_type', 'outdoor'))
                            ->whereHas('spec', fn($sq) => $sq->where('width_cm', '<', 300)->where('height_cm', '<', 300));
                    } elseif ($type === 'billboard') {
                        $sub->whereHas('spec', fn($sq) => $sq->where('width_cm', '>=', 300)->orWhere('height_cm', '>=', 300));
                    } else { // lcd
                        $sub->whereHas('inventory', fn($iq) => $iq->where('venue_type', '!=', 'outdoor'))
                            ->whereHas('spec', fn($sq) => $sq->where('width_cm', '<', 300)->where('height_cm', '<', 300));
                    }
                });
            }
        });
    }
)
```

**Tests cần viết:**
- `GET /inventory/screens?city[]=hanoi&city[]=danang` → chỉ trả screens thuộc 2 thành phố
- `GET /inventory/screens?city=hanoi|danang` → backward compat, kết quả giống trên
- `GET /inventory/screens?city=hanoi` → single value vẫn chạy
- `GET /inventory/screens?screen_type[]=lcd&screen_type[]=led` → multi screen_type

---

### Bước 2 — Endpoint `GET /inventory/screens/map` (🔴 Cao)

**Mục tiêu:** Giảm payload bản đồ từ ~4.5MB xuống ~400KB.

**File thay đổi:**
1. `routes/api.php` — thêm route mới
2. `app/Http/Controllers/Api/V1/InventoryController.php` — thêm method `map()`
3. `app/Http/Resources/Api/ScreenMapResource.php` — tạo mới
4. `app/Http/Resources/Api/ScreenMapCollection.php` — tạo mới

#### 2a. Route

```php
// routes/api.php — trong group middleware 'ability:inventory'
// QUAN TRỌNG: /map phải đặt TRƯỚC /{screen_id} để không bị route conflict
Route::get('inventory/screens/map',          [InventoryController::class, 'map']);
Route::get('inventory/screens',              [InventoryController::class, 'index']);
Route::get('inventory/screens/{screen_id}',  [InventoryController::class, 'show']);
```

#### 2b. Controller method `map()`

```php
public function map(Request $request): ScreenMapCollection
{
    // Áp dụng cùng filter logic như index(), nhưng:
    // - Eager load chỉ: spec, inventory, site (bỏ owner)
    // - Không paginate — lấy toàn bộ
    // - Không có param page, limit

    $query = Screen::with(['spec', 'inventory', 'site'])
        ->when(/* status filter — giống index() */)
        ->when(/* updated_after — giống index() */)
        ->when(/* city — giống index() */)
        ->when(/* venue_type — giống index() */)
        ->when(/* screen_type — giống index() */)
        ->when(/* orientation — giống Bước 3 */)
        ->when(/* q — giống Bước 4 */);

    // Không paginate — lấy all
    $screens = $query->get();

    return new ScreenMapCollection($screens);
}
```

**Lưu ý hiệu suất:** Với 3600+ screens, `->get()` sẽ load toàn bộ vào memory. Với 6 fields/item và eager loading chọn lọc, đây là chấp nhận được. Nếu scale lên 10k+ screens trong tương lai, cần xem xét `->cursor()` hoặc chunked response.

#### 2c. ScreenMapResource

```php
// app/Http/Resources/Api/ScreenMapResource.php
namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreenMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $spec      = $this->spec;
        $inventory = $this->inventory;
        $site      = $this->site;

        return [
            'screen_id'   => $this->external_id ?? $this->uuid,
            'name'        => $this->name,
            'venue_type'  => $inventory?->venue_type,
            'screen_type' => $this->resolveScreenType($spec, $inventory),
            'size'        => $this->resolveSize($spec),
            'location'    => [
                'address' => $site?->address,
                'lat'     => $site ? (float) $site->lat : null,
                'lng'     => $site ? (float) $site->lon : null,
            ],
        ];
    }

    private function resolveScreenType($spec, $inventory): string
    {
        // Giữ nguyên logic từ ScreenResource
        $widthCm  = $spec?->width_cm ?? 0;
        $heightCm = $spec?->height_cm ?? 0;
        if ($widthCm >= 300 || $heightCm >= 300) return 'billboard';
        if ($inventory?->venue_type === 'outdoor') return 'led';
        return 'lcd';
    }

    private function resolveSize($spec): ?string
    {
        if (!$spec?->width_cm || !$spec?->height_cm) return null;
        $w = round($spec->width_cm / 100, 1);
        $h = round($spec->height_cm / 100, 1);
        return "{$w}×{$h}m";
    }
}
```

#### 2d. ScreenMapCollection

```php
// app/Http/Resources/Api/ScreenMapCollection.php
namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ScreenMapCollection extends ResourceCollection
{
    public $collects = ScreenMapResource::class;

    public function toArray(Request $request): array
    {
        return [
            'total' => $this->collection->count(),
            'data'  => $this->collection,
        ];
    }
}
```

**Tests cần viết:**
- `GET /inventory/screens/map` → trả `{total, data[]}`, không có `page`/`limit`
- Mỗi item chỉ có 6 fields: `screen_id`, `name`, `venue_type`, `screen_type`, `size`, `location`
- Filter params hoạt động: `/map?city[]=hanoi`
- Route conflict: `/map` không bị match bởi `/{screen_id}`

---

### Bước 3 — Param `orientation` (🟡 Trung bình)

**File thay đổi:** `app/Http/Controllers/Api/V1/InventoryController.php`

**Logic filter (dùng WHERE trên DB, không dùng computed attribute):**
```php
->when(!empty($orientations = $this->resolveArrayParam($request, 'orientation')),
    function ($q) use ($orientations) {
        $q->whereHas('spec', function ($sq) use ($orientations) {
            $sq->where(function ($or) use ($orientations) {
                foreach ($orientations as $o) {
                    $or->orWhere(function ($sub) use ($o) {
                        match ($o) {
                            'landscape' => $sub->whereColumn('width_px', '>', 'height_px'),
                            'portrait'  => $sub->whereColumn('width_px', '<', 'height_px'),
                            'square'    => $sub->whereColumn('width_px', '=', 'height_px'),
                            default     => null,
                        };
                    });
                }
            });
        });
    }
)
```

**Validation:** Chỉ accept `landscape|portrait|square`. Giá trị khác → bỏ qua (không throw error).

**Tests cần viết:**
- `/inventory/screens?orientation=landscape` → chỉ trả screens có width_px > height_px
- `/inventory/screens?orientation[]=landscape&orientation[]=portrait` → multi-value
- `/inventory/screens?orientation=invalid` → trả tất cả (không filter, không error)
- Áp dụng tương tự cho `/map` endpoint

---

### Bước 4 — Param `q` text search (🟡 Trung bình)

**File thay đổi:** `app/Http/Controllers/Api/V1/InventoryController.php`

**Logic:**
```php
->when($request->filled('q'), function ($q) use ($request) {
    $search = $request->input('q');
    $q->where(function ($sub) use ($search) {
        $sub->where('name', 'LIKE', "%{$search}%")
            ->orWhereHas('site', fn($sq) => $sq->where('address', 'LIKE', "%{$search}%")
                ->orWhere('city', 'LIKE', "%{$search}%"));
    });
})
```

**Lưu ý:** Không cần sanitize thêm — Laravel's query builder tự escape LIKE params. Tuy nhiên cần validate max length để tránh performance issue.

**Validation thêm vào đầu method:**
```php
$request->validate(['q' => 'sometimes|string|max:100']);
```

**Tests cần viết:**
- `/inventory/screens?q=Vincom` → chỉ trả screens có "Vincom" trong tên hoặc địa chỉ
- `/inventory/screens?q=Cầu+Giấy` → unicode search hoạt động
- `/inventory/screens?q=` → không filter (filled() trả false)

---

### Bước 5 — Param `sort` (🟡 Trung bình)

**File thay đổi:** `app/Http/Controllers/Api/V1/InventoryController.php`

**Logic (đặt TRƯỚC `->paginate()`):**
```php
->when($request->filled('sort'), function ($q) use ($request) {
    match ($request->input('sort')) {
        'price_asc'  => $q->join('screen_inventory as si_sort', 'screens.id', '=', 'si_sort.screen_id')
                          ->orderBy('si_sort.floor_cpm', 'asc'),
        'price_desc' => $q->join('screen_inventory as si_sort', 'screens.id', '=', 'si_sort.screen_id')
                          ->orderBy('si_sort.floor_cpm', 'desc'),
        'newest'     => $q->orderBy('updated_at', 'desc'),
        default      => $q->orderBy('id', 'asc'),
    };
})
->when(!$request->filled('sort'), fn($q) => $q->orderBy('id', 'asc'))
```

**Lưu ý JOIN:** Vì dùng `JOIN` khi sort theo price, cần `->select('screens.*')` để tránh column ambiguity với eager loading. Hoặc dùng subquery sort thay vì JOIN:

```php
// Cách an toàn hơn dùng subquery (không JOIN):
'price_asc' => $q->orderByRaw('(
    SELECT floor_cpm FROM screen_inventory
    WHERE screen_inventory.screen_id = screens.id
    LIMIT 1
) ASC'),
```

**Tests cần viết:**
- `/inventory/screens?sort=price_asc` → item đầu có `price_per_slot_vnd` thấp nhất
- `/inventory/screens?sort=newest` → item đầu có `updated_at` mới nhất
- `/inventory/screens?sort=invalid` → dùng default sort, không error

---

## Tổng hợp files cần thay đổi/tạo mới

| Action | File | Lý do |
|---|---|---|
| **Sửa** | `routes/api.php` | Thêm route `/inventory/screens/map`, sắp xếp lại thứ tự |
| **Sửa** | `app/Http/Controllers/Api/V1/InventoryController.php` | Thêm helper `resolveArrayParam()`, update `index()`, thêm `map()` |
| **Tạo** | `app/Http/Resources/Api/ScreenMapResource.php` | Resource nhẹ cho map endpoint |
| **Tạo** | `app/Http/Resources/Api/ScreenMapCollection.php` | Collection wrapper không có page/limit |
| **Tạo** | `tests/Feature/Api/InventoryScreensFilterTest.php` | Tests cho multi-value, orientation, q, sort |
| **Tạo** | `tests/Feature/Api/InventoryScreensMapTest.php` | Tests cho /map endpoint |

---

## Thứ tự thực hiện

```
1. Bước 1 — Multi-value filter (city, venue_type, screen_type)
   Prerequisite: không có
   Estimate: ~1h

2. Bước 2 — /map endpoint
   Prerequisite: Bước 1 (để /map có cùng filter logic)
   Estimate: ~1.5h (bao gồm 2 resource file mới)

3. Bước 3 — orientation filter
   Prerequisite: Bước 1 (helper resolveArrayParam đã có)
   Estimate: ~30m

4. Bước 4 — q text search
   Prerequisite: không có
   Estimate: ~30m

5. Bước 5 — sort
   Prerequisite: không có
   Estimate: ~30m

6. Tests cho tất cả các bước
   Prerequisite: Bước 1-5 done
   Estimate: ~2h
```

---

## Risks & Lưu ý

| Risk | Mức độ | Mitigation |
|---|---|---|
| Route conflict `/inventory/screens/map` vs `/{screen_id}` | 🔴 Cao | Đặt route `/map` TRƯỚC route `/{screen_id}` trong `routes/api.php` |
| JOIN cho sort price gây column ambiguity | 🟡 Trung bình | Dùng subquery thay vì JOIN |
| `->get()` trong `/map` với 3600+ records | 🟡 Trung bình | OK ở quy mô hiện tại, document limit, cân nhắc cache nếu cần |
| `orientation` filter không có DB index trên `width_px/height_px` | 🟡 Trung bình | Index đã có chưa? Nếu chưa, thêm migration hoặc accept slow query |
| Backward compat pipe-separated format | 🟢 Thấp | `resolveArrayParam()` handle đủ 3 case |
| LIKE query với unicode tiếng Việt | 🟢 Thấp | MySQL `utf8mb4_unicode_ci` collation handle được |

---

## Chưa làm trong phase này

- Cache layer cho `/map` endpoint (cân nhắc sau khi đo performance thực tế)
- Rate limiting cho `/map` (trả toàn bộ data, có thể bị abuse)
- Full-text search engine (Elasticsearch, Meilisearch) — LIKE đủ dùng hiện tại
- Thêm index DB cho `width_px`, `height_px` nếu orientation filter chậm

---

_Xác nhận schema response với dev OOHX trước khi deploy production._
