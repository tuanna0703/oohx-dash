# Frontpage Integration Log

## Overview
Integrate OOHX frontpage Blade mockup pages with real database data via `FrontpageService`.

**Architecture:** SSR via Eloquent (NOT internal API calls). `FrontpageService` encapsulates all queries with caching.

---

## Completed

### 2026-04-08: FrontpageService + Controller + Partials

**Files created:**
- `app/Services/FrontpageService.php` — All query logic for frontpage (15 methods)
  - Phase 1 (Homepage): `getHeroStats()`, `getVenueTypesWithCounts()`, `getTopCities()`, `getLocationsByRegion()`, `getFeaturedScreens()`, `getFeaturedOwners()`
  - Phase 2 (Owners): `getOwnersPaginated()`, `getOwnerBySlug()`, `getOwnerScreens()`
  - Phase 3 (Listing): `getScreensPaginated()`, `getFilterAggregates()`
  - Phase 4 (Detail): `getScreenDetail()`, `getSimilarScreens()`
  - Phase 5 (Map): `getMapPins()`
  - Private helpers: `buildScreenQuery()`, `expandCitySlugs()`, `resolveArrayParam()`, `applyScreenTypeFilter()`, `applyOrientationFilter()`, `applySort()`, `loadOwnerExtras()`, `cityToSlug()`

- `resources/views/frontpage/partials/screen-card.blade.php` — Reusable screen inventory card
  - Receives `$screen` model with spec, inventory, owner, site relations
  - Handles null-safe photo fallback, price formatting, size calculation

- `resources/views/frontpage/partials/owner-card-mini.blade.php` — Reusable owner mini card
  - Receives `$owner` model with screen_count, city_count, venue_types_list
  - Auto-generates initials and color gradient from owner name

**Files updated:**
- `app/Http/Controllers/FrontpageController.php` — Injected `FrontpageService`, all 8 methods now pass real data to views
  - `index()` → 6 data variables (stats, venueTypes, topCities, featuredScreens, featuredOwners, locationsByRegion)
  - `listing(Request)` → screens (paginated), filters
  - `detail(string)` → screen model, similarScreens (abort 404 if not found)
  - `map(Request)` → pins, filters
  - `owners(Request)` → owners (paginated), venueTypes
  - `ownerDetail(string)` → owner model, ownerScreens (abort 404 if not found)
  - `booking()`, `agency()` → unchanged (static content)

### Caching Strategy

| Method | Cache Key | TTL |
|--------|-----------|-----|
| `getHeroStats()` | `fp:hero_stats` | 30 min |
| `getVenueTypesWithCounts()` | `fp:venue_types` | 30 min |
| `getTopCities()` | `fp:top_cities` | 30 min |
| `getLocationsByRegion()` | `fp:locations` | 30 min |
| `getFeaturedScreens()` | `fp:featured_screens` | 15 min |
| `getFeaturedOwners()` | `fp:featured_owners` | 30 min |
| `getOwnerBySlug()` | `fp:owner:{slug}` | 15 min |
| `getScreenDetail()` | `fp:screen:{id}` | 5 min |
| `getSimilarScreens()` | `fp:similar:{id}` | 10 min |
| `getFilterAggregates()` | `fp:filters` | 30 min |
| Paginated methods | No cache | — |

### 2026-04-08: Blade Template Updates — All 6 Pages

**index.blade.php** (Homepage):
- Hero badge: `1,248` → `{{ number_format($stats['total_screens']) }}`
- Hero stats: `1.2K+`, `63`, `80+` → dynamic `$stats` values
- Location mega dropdown: 3 hardcoded regions → `@foreach($locationsByRegion)` loop
- Type dropdown: 6 hardcoded cards → `@foreach($venueTypes->take(6))` loop
- Category grid: 8 hardcoded cards → `@foreach($venueTypes->take(5))` loop
- City grid: 4 hardcoded cards → `@foreach($topCities)` loop with dynamic `city-card--lg`
- Inventory cards: 4 hardcoded articles → `@forelse($featuredScreens)` with `screen-card` partial
- Owner cards: 4 hardcoded divs → `@foreach($featuredOwners)` with `owner-card-mini` partial

**owners.blade.php**:
- Total count: `80` → `{{ $owners->total() }}`
- Category chips: hardcoded → `@foreach($venueTypes->take(6))` loop
- Owner cards: 6 hardcoded → `@foreach($owners)` loop with dynamic data
- Pagination: static button → `{{ $owners->links() }}`

**owner-detail.blade.php**:
- Profile: hardcoded name/stats → `$owner->name`, `$owner->screen_count`, `$owner->city_count`
- About: hardcoded text → `$owner->about`
- Inventory grid: hardcoded cards → `@foreach($ownerScreens)` with `screen-card` partial + pagination
- Contact: hardcoded → `$owner->phone`, `$owner->email`, `$owner->website`

**listing.blade.php**:
- Result count: `1,248` → `{{ $screens->total() }}`
- Filter chips: hardcoded → `@foreach($filters['formats']->take(5))`
- Sidebar formats: hardcoded → `@foreach($filters['formats'])`
- Sidebar cities: hardcoded → `@foreach($filters['cities']->take(8))`
- Budget range: hardcoded → `$filters['min_price']`, `$filters['max_price']`
- Inventory cards: 8 hardcoded → `@forelse($screens)` loop with inline card HTML
- Pagination: static button → `{{ $screens->withQueryString()->links() }}`
- Empty state added for no results

**detail.blade.php**:
- Title: hardcoded → `{{ $screen->name }}`
- Location: hardcoded → `{{ $screen->site?->city }}`
- Owner info: hardcoded → `$screen->owner->*` with route link
- Stats: hardcoded → model data (traffic, size, etc.)
- Specs grid: 12 hardcoded values → model data
- Gallery: Unsplash → `$screen->spec?->photo_url`
- Booking price: hardcoded → `$screen->inventory?->floor_cpm` with dynamic VAT calc
- Similar screens: 4 hardcoded → `@foreach($similarScreens)` loop

**map.blade.php**:
- Panel cards: 9 hardcoded → `@foreach($pins->take(20))` loop
- Filter chips: hardcoded → `@foreach($filters['formats']->take(3))`
- Result counts: `1,248` → `{{ $pins->count() }}`
- Map pins: 6 hardcoded → `@foreach($pins->take(6))` with static CSS positioning + dynamic price

**Unchanged pages:** `booking.blade.php`, `agency.blade.php` (static content, no data needed)

---

## Design Decisions

1. **SSR via Eloquent, not API** — Same app, avoid HTTP overhead. API is for external consumers.
2. **HasOwnerScope safe** — `auth()->user()` returns null on public frontpage, scope auto-skips (line 14 of HasOwnerScope.php).
3. **Query patterns duplicated from InventoryController** — Intentional. Don't modify existing API controller. Can extract to shared trait later.
4. **Partials for @foreach loops** — Dense single-line HTML is fragile. Extracted repeating blocks into partials for safe iteration.
5. **Null-safe accessors** — All Blade variables use `$screen->spec?->photo_url ?? fallback` pattern for missing data.
