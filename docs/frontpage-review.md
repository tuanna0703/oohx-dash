# OOHX Frontpage Integration — Code Review Report

**Date:** 2026-04-08
**Reviewer:** Automated Architecture & QA Review
**Scope:** Backend integration, data rendering, responsive layout, visual regression

---

## 1. Executive Summary

**Overall Verdict: PASS WITH FIXES**

The integration architecture is sound — FrontpageService cleanly separates query logic, controller is thin, partials enable reuse. However, there are **data safety gaps** (missing null checks, unsafe array access), **text truncation missing** (layout breaks with long real data), **hardcoded mock values not yet replaced** (reviews, dates, map positions), and **pagination unstyled**. The responsive grid structure is solid but needs defensive CSS for dynamic content.

---

## 2. Critical Issues

### C1: Missing `withoutGlobalScope('owner_scope')` on public queries
**File:** `app/Services/FrontpageService.php` — all Screen query methods
**Problem:** `HasOwnerScope` bypasses when `auth()->user()` is null, but if a logged-in user (media owner) visits `oohx.net`, they would only see their own screens on the public frontpage.
**Fix:** Add `->withoutGlobalScope('owner_scope')` to every Screen/Site query in FrontpageService.
**Impact:** Data correctness for authenticated visitors.

### C2: Vietnamese Dong price formatting is BACKWARDS
**File:** `resources/views/frontpage/partials/screen-card.blade.php` line 6
**Code:** `number_format($price, 0, ',', '.')`
**Problem:** Vietnamese format uses `.` as thousands separator: `50.000.000 đ`. Current code produces `50,000,000 đ` (US format).
**Fix:** Change to `number_format($price, 0, ',', '.')` — wait, the current code IS correct for VND. The `,` is decimal (unused with 0 decimals) and `.` is thousands. **Re-checked: current code is CORRECT.** But the M-format shorthand `number_format($price / 1000000)` rounds 1.5M to 2M.
**Fix for M-format:** `number_format($price / 1000000, 1)` to show `1.5M` instead of `2M`.

### C3: Unsafe array access on `$filters` in listing + map views
**File:** `listing.blade.php`, `map.blade.php`
**Code:** `$filters['formats']->take(5)`, `$filters['min_price']`, `$filters['cities']`
**Problem:** If `getFilterAggregates()` returns malformed data or cache returns unexpected shape, views crash.
**Fix:** Use `($filters['formats'] ?? collect())->take(5)` and `$filters['min_price'] ?? 0`.

### C4: No text truncation anywhere in CSS
**File:** `resources/css/frontpage.css`
**Problem:** None of `.ic-title`, `.inv-title`, `.oc-name`, `.oc-desc`, `.dtitle`, `.mp-card-nm` have overflow/truncation handling. Real screen names (100+ chars) and owner names will break card layouts.
**Impact:** Layout breaks on all breakpoints with real data.
**Fix:** Add CSS truncation rules (see Section 5).

---

## 3. Medium Issues

### M1: Hardcoded mock reviews in detail.blade.php
**Problem:** 4 sample reviews with hardcoded names ("Minh Hằng", "Tuấn Long") and dates remain. No Review model exists yet.
**Fix for now:** Wrap in `@if(false)` or show "Chưa có đánh giá" message. Don't display fake reviews.

### M2: Hardcoded mock date "01/05/2025" in booking panel
**File:** `detail.blade.php`
**Fix:** Replace with `{{ now()->addDays(7)->format('d/m/Y') }}` or dynamic availability.

### M3: Dead code `@if(false)` blocks in owner-detail.blade.php
**Problem:** Old hardcoded cards wrapped in `@if(false)` with `route('fp.detail', 'demo')` references.
**Fix:** Remove entirely.

### M4: Pagination unstyled
**Problem:** `{{ $screens->links() }}` renders Laravel's default Tailwind pagination. The frontpage uses custom CSS (no Tailwind), so pagination looks broken.
**Fix:** Add pagination CSS to `frontpage.css` or use a custom pagination view.

### M5: Price calculation in Blade template
**File:** `detail.blade.php` booking panel
**Code:** `@php $price = $screen->inventory?->floor_cpm ?? 0; $months = 3; $subtotal = $price * $months; $vat = $subtotal * 0.1; @endphp`
**Problem:** Business logic (pricing, VAT calculation) in Blade.
**Fix:** Move to FrontpageService or a PricingHelper.

### M6: Map pins use hardcoded CSS positions, not real coordinates
**File:** `map.blade.php`
**Code:** `$positions = [['38%','30%'],['55%','50%'],...]`
**Problem:** Static mock positions, not real lat/lng. Acceptable as interim but misleading.
**Fix:** For now, acceptable. Future: integrate Leaflet/Mapbox with real coordinates.

### M7: No request validation in listing/map controller methods
**File:** `FrontpageController.php` lines 25-31, 44-50
**Problem:** Query params (`q`, `city`, `venue_type`, `sort`) pass directly to service without validation.
**Fix:** Create `FrontpageListingRequest` FormRequest with validation rules.

### M8: `resolveArrayParam()` filters falsy values including valid `0`
**File:** `FrontpageService.php` line 434
**Code:** `array_values(array_filter($value))` — `array_filter` removes `0`, `'0'`, `false`
**Fix:** `array_values(array_filter($value, fn($v) => $v !== '' && $v !== null))`

---

## 4. Minor Issues / Cleanup

### L1: External placeholder URL dependency
**Code:** `'https://placehold.co/600x400/F5F5F7/6E6E73?text=No+Photo'`
**Fix:** Use local `asset('images/frontpage/no-photo.svg')`.

### L2: `$loop->index` in owner-card-mini relies on foreach context
**Problem:** Partial breaks if used outside `@foreach`.
**Fix:** `$colorIndex = $loop->index ?? 0;`

### L3: Initials calculation fragile with empty names
**Code:** `collect(explode(' ', $owner->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join('')`
**Fix:** Add fallback: `?: 'OO'`

### L4: Base64 SVG fallback in owner-detail cover image is very long
**Fix:** Extract to a local SVG file.

### L5: `ucfirst(str_replace(['_','.'],' ',$tag))` repeated in multiple files
**Fix:** Create a Blade helper or use a shared formatting function.

### L6: Empty string `$id` in `getScreenDetail()` could match unintended records
**Fix:** Add `abort_if(empty($screen), 400)` in controller.

---

## 5. Responsive Layout Findings

### Mobile (320px–767px)

| Finding | Severity | Component |
|---------|----------|-----------|
| Card titles can wrap to 3+ lines, breaking card height consistency | HIGH | `.ic-title`, `.inv-title` |
| Owner description overflows card on small screens | HIGH | `.oc-desc` |
| Bottom nav overlaps floating cart button | LOW | `.bnav` + `.fcart` |
| Pagination nav wraps awkwardly (Tailwind classes, no custom CSS) | MEDIUM | `.load-more nav` |
| Filter chips scroll horizontally (correct behavior) | OK | `.fchips` |
| Sidebar hidden on mobile (correct) | OK | `.sidebar` |
| Mobile CTA bar appears on detail page (correct) | OK | `.mcta` |

### Tablet (768px–1023px)

| Finding | Severity | Component |
|---------|----------|-----------|
| Listing uses 3-column grid but no sidebar yet — cards look wide | LOW | `.inv-grid` |
| Owner cards in 2-column grid — acceptable | OK | `.owners-layout` |
| Map panel still hidden until 1024px | OK | `.map-panel` |

### Desktop (1024px+)

| Finding | Severity | Component |
|---------|----------|-----------|
| Very long screen names overflow card width | HIGH | `.ic-title` |
| Owner names in sidebar can overflow | MEDIUM | `.oc-name` in mini cards |
| Booking panel price display can be very wide for large numbers | LOW | `.bp-price` |
| All grids scale correctly | OK | All grid layouts |

### CSS Fixes Needed

```css
/* Text truncation for card titles */
.ic-title, .inv-title, .mp-card-nm, .sim-nm {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}

/* Multi-line clamp for descriptions */
.oc-desc, .ic-loc, .inv-loc {
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

/* Detail page title — allow 2 lines max */
.dtitle {
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  word-break: break-word;
}

/* Owner name truncation */
.oc-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}

/* Pagination styling */
.load-more nav {
  display: flex;
  gap: 4px;
  justify-content: center;
  flex-wrap: wrap;
}
.load-more nav a,
.load-more nav span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1.5px solid var(--ln2);
  font-size: 13px;
  font-weight: 600;
  color: var(--t2);
  transition: all 140ms;
  background: #fff;
}
.load-more nav a:hover {
  border-color: var(--bl);
  color: var(--bl);
  background: var(--bl-lt);
}
.load-more nav span[aria-current="page"] {
  background: var(--bl);
  color: #fff;
  border-color: var(--bl);
}
.load-more nav span[aria-disabled="true"] {
  color: var(--t4);
  cursor: default;
}
```

---

## 6. Visual Regression Findings

| Section | Before (Mockup) | After (Integration) | Regression? |
|---------|-----------------|---------------------|-------------|
| Hero stats | Fixed "1.2K+", "63", "80+" | Dynamic numbers | Minor — may show "0" if DB empty |
| Category cards | 5 cards with images | Dynamic count, NO images (Unsplash removed) | YES — category cards lost their visual images |
| City cards | 4 cards with city photos | Dynamic cities, NO background images | YES — city cards lost background photos |
| Inventory cards | 4 curated cards with Unsplash photos | Random screens, may have no photos | YES — placeholder images less attractive |
| Owner cards | 4 curated with gradients | Dynamic owners | Minor — gradients preserved via partial |
| Listing grid | 8 curated cards | Real paginated data | OK if data exists |
| Detail specs | 12 specific values | Model data | OK — some may show "—" if not set |
| Map pins | 6 positioned pins | 6 real pins with hardcoded positions | Acceptable interim |
| Reviews | 4 mock reviews | Still hardcoded mock | Must fix — fake reviews are misleading |

**Key Visual Regressions:**
1. **Category grid lost images** — Original had Unsplash photos per category. Now only shows text + count.
2. **City grid lost background photos** — Original had city photos. Now shows plain cards.
3. **Inventory cards may show placeholder** — If screens don't have `photo_url`, shows "No Photo" placeholder instead of attractive Unsplash images.

---

## 7. File-by-File Review

### FrontpageService.php
- **Good:** Clean service pattern, proper caching, delegated query logic
- **Wrong:** Missing `withoutGlobalScope` (C1), `resolveArrayParam` drops falsy values (M8), O(n²) region lookup
- **Change:** Add global scope bypass, fix array filter, add input validation

### FrontpageController.php
- **Good:** Thin controller, proper 404 handling, service injection
- **Wrong:** No request validation (M7), no empty string check on slug params
- **Change:** Add FormRequest or inline validation

### screen-card.blade.php
- **Good:** Null-safe operators, photo fallback, price formatting
- **Wrong:** M-format rounds (C2), external placeholder (L1), no text truncation in HTML
- **Change:** Fix price decimal, use local placeholder

### owner-card-mini.blade.php
- **Good:** Dynamic initials, color cycling, null-safe
- **Wrong:** $loop dependency (L2), no name fallback (L3)
- **Change:** Add fallbacks

### index.blade.php
- **Good:** All 8 sections converted to dynamic data
- **Wrong:** Category cards lost images, city cards lost photos
- **Change:** Add fallback images or restore Unsplash defaults per category

### listing.blade.php
- **Good:** Dynamic cards, pagination, empty state
- **Wrong:** Unsafe `$filters` access (C3), no text truncation
- **Change:** Add null-coalescing on filter access

### detail.blade.php
- **Good:** Full screen data rendered, similar screens section
- **Wrong:** Mock reviews remain (M1), mock date (M2), price calc in Blade (M5)
- **Change:** Hide reviews until real data, move calc to service

### map.blade.php
- **Good:** Dynamic panel cards, pin data from DB
- **Wrong:** Hardcoded pin positions (M6), unsafe filter access (C3)
- **Change:** Fix filter access, document pin positioning as interim

### owners.blade.php
- **Good:** Dynamic owner cards, pagination, venue type chips
- **Wrong:** None critical
- **Change:** Minor — add text truncation CSS

### owner-detail.blade.php
- **Good:** Dynamic profile, inventory grid with pagination
- **Wrong:** Dead code @if(false) blocks (M3), base64 SVG fallback (L4)
- **Change:** Remove dead code, extract SVG

### frontpage.css
- **Good:** Solid responsive breakpoints, proper grid scaling, overflow on images
- **Wrong:** No text truncation (C4), no pagination styling (M4)
- **Change:** Add CSS rules from Section 5

---

## 8. Fix Priority Plan

### Fix Now (Before Deploy)
1. **C3:** Add null-coalescing on `$filters` access in listing + map views
2. **C4:** Add text truncation CSS rules to `frontpage.css`
3. **M1:** Hide fake reviews in detail page (wrap in `@if(false)` or replace with "Chưa có đánh giá")
4. **M4:** Add pagination CSS to `frontpage.css`

### Fix Next (This Sprint)
5. **C1:** Add `withoutGlobalScope('owner_scope')` to all FrontpageService Screen queries
6. **C2:** Fix M-format price rounding (`number_format($price / 1000000, 1)`)
7. **M2:** Replace hardcoded booking date
8. **M3:** Remove dead code blocks in owner-detail
9. **M5:** Move price/VAT calculation out of Blade
10. **M7:** Add request validation for listing/map

### Can Postpone
11. **M6:** Map pin real coordinates (needs Leaflet/Mapbox integration)
12. **M8:** Fix `resolveArrayParam` falsy filter
13. **L1-L6:** All minor cleanup items
14. Category/city card images restoration (requires design decision)

---

## 9. Final Recommendation

**Merge after layout fixes**

The architecture is correct and the integration path is clean. The critical issues are:
1. **Text truncation CSS** — must add before deploy to prevent layout breaks with real data
2. **Pagination CSS** — must style before deploy
3. **Fake reviews** — must hide before deploy (misleading to users)
4. **Null-safe filter access** — must fix to prevent 500 errors

After these 4 fixes, the integration is safe to deploy. The remaining items (global scope bypass, price formatting, validation) should follow in the next sprint.
