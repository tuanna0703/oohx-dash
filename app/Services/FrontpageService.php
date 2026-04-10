<?php

namespace App\Services;

use App\Models\Owner;
use App\Models\Screen;
use App\Models\VenueCategory;
use App\Models\VenueType;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FrontpageService
{
    private const CITY_SLUG_MAP = [
        'hanoi'    => 'Hà Nội',
        'hcm'      => 'Hồ Chí Minh',
        'danang'   => 'Đà Nẵng',
        'haiphong' => 'Hải Phòng',
        'cantho'   => 'Cần Thơ',
    ];

    private const CITY_DISPLAY_NAMES = [
        'hanoi'    => 'Hà Nội',
        'hcm'      => 'TP. Hồ Chí Minh',
        'danang'   => 'Đà Nẵng',
        'haiphong' => 'Hải Phòng',
        'cantho'   => 'Cần Thơ',
    ];

    // ── Phase 1: Homepage ─────────────────────────────────

    public function getHeroStats(): array
    {
        return Cache::remember('fp:hero_stats', 1800, function () {
            $totalScreens = Screen::withoutGlobalScope('owner_scope')->where('active', true)->count();

            $totalCities = DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.active', true)
                ->whereNotNull('sites.city')
                ->where('sites.city', '!=', '')
                ->selectRaw("COUNT(DISTINCT TRIM(SUBSTRING_INDEX(sites.city, '>', 1))) as cnt")
                ->value('cnt');

            $totalOwners = Screen::withoutGlobalScope('owner_scope')->where('active', true)
                ->distinct()
                ->count('owner_id');

            return [
                'total_screens' => $totalScreens,
                'total_cities'  => $totalCities,
                'total_owners'  => $totalOwners,
            ];
        });
    }

    /**
     * Get venue types grouped by VN category (12 categories).
     * Used for frontpage filters — simpler than raw OpenOOH types.
     */
    public function getVenueTypesWithCounts(): Collection
    {
        return Cache::remember('fp:venue_types', 1800, function () {
            // Get raw counts per screen_inventory.venue_type
            $rawCounts = DB::table('screens')
                ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
                ->where('screens.active', true)
                ->whereNotNull('screen_inventory.venue_type')
                ->where('screen_inventory.venue_type', '!=', '')
                ->selectRaw('screen_inventory.venue_type, count(*) as cnt')
                ->groupBy('screen_inventory.venue_type')
                ->pluck('cnt', 'venue_type');

            // Use slug mapping to group by VN category
            $slugs = $this->getVenueTypeSlugs();
            $labels = $this->getVenueTypeLabels();

            // Load category metadata
            $categories = DB::table('venue_categories')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('slug');

            // Aggregate counts by VN category slug
            $grouped = [];
            foreach ($rawCounts as $rawType => $count) {
                $slug = $slugs[$rawType] ?? null;
                if ($slug && isset($categories[$slug])) {
                    if (! isset($grouped[$slug])) {
                        $cat = $categories[$slug];
                        $grouped[$slug] = [
                            'type'  => $slug,
                            'label' => $cat->name_vi ?: $cat->name,
                            'icon'  => $cat->icon ?? 'tv',
                            'thumb' => $cat->thumb ? asset('storage/' . $cat->thumb) : null,
                            'count' => 0,
                            '_sort' => $cat->sort_order,
                        ];
                    }
                    $grouped[$slug]['count'] += $count;
                } else {
                    // Unmapped — show as-is
                    $grouped[$rawType] = [
                        'type'  => $rawType,
                        'label' => $labels[$rawType] ?? ucfirst(str_replace(['_', '.'], ' ', $rawType)),
                        'icon'  => 'tv',
                        'thumb' => null,
                        'count' => (int) $count,
                        '_sort' => 999,
                    ];
                }
            }

            return collect($grouped)
                ->sortBy('_sort')
                ->map(fn ($v) => ['type' => $v['type'], 'label' => $v['label'], 'icon' => $v['icon'], 'thumb' => $v['thumb'], 'count' => $v['count']])
                ->values();
        });
    }

    /**
     * Build complete mapping: any raw venue_type value → VN category info.
     * Handles both OpenOOH string_values ("retail.convenience_store")
     * and Hivestack format ("RETAIL: Convenience Stores") stored in screen_inventory.
     */
    private function buildVenueTypeMappings(): array
    {
        return Cache::remember('fp:venue_type_map', 3600, function () {
            // 1. Map by string_value (OpenOOH dot format)
            $byStringValue = DB::table('venue_types')
                ->join('venue_categories', 'venue_types.vn_category_id', '=', 'venue_categories.id')
                ->whereNotNull('venue_types.string_value')
                ->select('venue_types.string_value', 'venue_categories.slug', 'venue_categories.name_vi', 'venue_categories.name', 'venue_categories.icon')
                ->get();

            $labels = [];
            $slugs  = [];
            foreach ($byStringValue as $row) {
                $label = $row->name_vi ?: $row->name;
                $labels[$row->string_value] = $label;
                $slugs[$row->string_value]  = $row->slug;
            }

            // 2. Map Hivestack-format values from screen_inventory that don't match string_value
            //    e.g. "RETAIL: Convenience Stores" → match by fuzzy against venue_types.venue_type
            $unmappedTypes = DB::table('screen_inventory')
                ->whereNotNull('venue_type')
                ->where('venue_type', '!=', '')
                ->whereNotIn('venue_type', array_keys($labels))
                ->distinct()
                ->pluck('venue_type');

            foreach ($unmappedTypes as $rawType) {
                // Extract category part: "RETAIL: Convenience Stores" → "convenience stores"
                $parts    = array_map('trim', explode(':', $rawType, 2));
                $catPart  = strtolower($parts[0] ?? '');
                $subPart  = strtolower($parts[1] ?? '');

                // Try to match via venue_types.category (case-insensitive)
                $match = DB::table('venue_types')
                    ->join('venue_categories', 'venue_types.vn_category_id', '=', 'venue_categories.id')
                    ->whereRaw('LOWER(venue_types.category) = ?', [$catPart])
                    ->select('venue_categories.slug', 'venue_categories.name_vi', 'venue_categories.name')
                    ->first();

                // Special case: "Malls" → Mall category
                if (! $match && str_contains($catPart, 'mall')) {
                    $match = DB::table('venue_categories')->where('slug', 'mall')
                        ->select('slug', 'name_vi', 'name')->first();
                }

                if ($match) {
                    $labels[$rawType] = $match->name_vi ?: $match->name;
                    $slugs[$rawType]  = $match->slug;
                }
            }

            return ['labels' => $labels, 'slugs' => $slugs];
        });
    }

    /**
     * Cached lookup map: raw venue_type → VN category slug.
     */
    public function getVenueTypeSlugs(): array
    {
        return $this->buildVenueTypeMappings()['slugs'];
    }

    /**
     * Cached lookup map: raw venue_type → VN category name.
     * e.g. 'transit.airports' → 'Giao thông', 'RETAIL: Convenience Stores' → 'Bán lẻ'
     */
    public function getVenueTypeLabels(): array
    {
        return $this->buildVenueTypeMappings()['labels'];
    }

    /**
     * Get raw OpenOOH venue types (for API/programmatic use).
     */
    public function getRawVenueTypesWithCounts(): Collection
    {
        return Cache::remember('fp:venue_types_raw', 1800, function () {
            return DB::table('screens')
                ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
                ->leftJoin('venue_types', 'screen_inventory.venue_type', '=', 'venue_types.string_value')
                ->where('screens.active', true)
                ->whereNotNull('screen_inventory.venue_type')
                ->selectRaw('screen_inventory.venue_type as type, venue_types.venue_type as taxonomy_label, venue_types.enumeration_id, count(*) as count')
                ->groupBy('screen_inventory.venue_type', 'venue_types.venue_type', 'venue_types.enumeration_id')
                ->get()
                ->map(fn ($r) => [
                    'type'  => $r->type,
                    'label' => $r->taxonomy_label ?? ucfirst(str_replace(['_', '.'], ' ', $r->type)),
                    'count' => (int) $r->count,
                ])
                ->values();
        });
    }

    public function getTopCities(int $limit = 8): Collection
    {
        return Cache::remember('fp:top_cities', 1800, function () use ($limit) {
            // sites.city may store "Hà Nội > Phường Dương Nội" — extract province part
            $rawCityCounts = DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.active', true)
                ->whereNotNull('sites.city')
                ->where('sites.city', '!=', '')
                ->selectRaw("TRIM(SUBSTRING_INDEX(sites.city, '>', 1)) as province, count(*) as count")
                ->groupByRaw("TRIM(SUBSTRING_INDEX(sites.city, '>', 1))")
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return $rawCityCounts->map(fn ($r) => [
                'code'  => $this->cityToSlug($r->province),
                'name'  => $r->province,
                'count' => (int) $r->count,
            ]);
        });
    }

    public function getLocationsByRegion(): array
    {
        return Cache::remember('fp:locations', 1800, function () {
            $regionConfig = config('regions');
            $cityToCode   = array_flip(self::CITY_SLUG_MAP);

            // Extract province from "Hà Nội > Phường..." format
            $rawProvinceCounts = DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->whereNotNull('sites.city')
                ->where('sites.city', '!=', '')
                ->selectRaw("TRIM(SUBSTRING_INDEX(sites.city, '>', 1)) as province, count(*) as count")
                ->groupByRaw("TRIM(SUBSTRING_INDEX(sites.city, '>', 1))")
                ->get();

            $provinces = $rawProvinceCounts->map(function ($r) use ($cityToCode, $regionConfig) {
                $code   = $cityToCode[$r->province] ?? Str::slug($r->province);
                $region = null;
                foreach ($regionConfig as $rc => $cfg) {
                    if (in_array($code, $cfg['provinces'])) {
                        $region = $rc;
                        break;
                    }
                }
                return [
                    'code'   => $code,
                    'name'   => $r->province,
                    'region' => $region,
                    'count'  => (int) $r->count,
                ];
            })->filter(fn ($p) => $p['region'] !== null)->values();

            $grouped = [];
            foreach ($regionConfig as $regionCode => $cfg) {
                $regionProvinces = $provinces->where('region', $regionCode)->sortByDesc('count')->values();
                if ($regionProvinces->isNotEmpty()) {
                    $grouped[$cfg['name']] = $regionProvinces->toArray();
                }
            }

            return $grouped;
        });
    }

    public function getFeaturedScreens(int $limit = 4): Collection
    {
        return Cache::remember('fp:featured_screens', 900, function () use ($limit) {
            return Screen::withoutGlobalScope('owner_scope')->where('active', true)
                ->whereHas('spec', fn ($q) => $q->whereNotNull('photo_url')->where('photo_url', '!=', ''))
                ->whereHas('inventory', fn ($q) => $q->where('floor_cpm', '>', 0))
                ->with(['spec', 'inventory', 'owner', 'site'])
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        });
    }

    public function getFeaturedOwners(int $limit = 6): Collection
    {
        return Cache::remember('fp:featured_owners', 1800, function () use ($limit) {
            $owners = Owner::where('status', 'active')
                ->where('featured', true)
                ->withCount(['screens as screen_count' => fn ($q) => $q->where('active', true)])
                ->orderByDesc('screen_count')
                ->limit($limit)
                ->get();

            if ($owners->isEmpty()) {
                $owners = Owner::where('status', 'active')
                    ->withCount(['screens as screen_count' => fn ($q) => $q->where('active', true)])
                    ->having('screen_count', '>', 0)
                    ->orderByDesc('screen_count')
                    ->limit($limit)
                    ->get();
            }

            $this->loadOwnerExtras($owners);

            return $owners;
        });
    }

    // ── Phase 2: Owners ───────────────────────────────────

    public function getOwnersPaginated(Request $request, int $perPage = 12): LengthAwarePaginator
    {
        $owners = Owner::query()
            ->where('status', 'active')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'LIKE', '%' . $request->input('q') . '%'))
            ->when($request->filled('type'), fn ($q) => $q->whereHas('screens', function ($sq) use ($request) {
                $sq->where('active', true)
                    ->whereHas('inventory', fn ($iq) => $iq->where('venue_type', $request->input('type')));
            }))
            ->withCount(['screens as screen_count' => fn ($q) => $q->where('active', true)])
            ->orderByDesc('featured')
            ->orderByDesc('screen_count')
            ->paginate($perPage);

        $this->loadOwnerExtras($owners);

        return $owners;
    }

    public function getOwnerBySlug(string $slug): ?Owner
    {
        return Cache::remember("fp:owner:{$slug}", 900, function () use ($slug) {
            $owner = Owner::where('slug', $slug)
                ->where('status', 'active')
                ->withCount(['screens as screen_count' => fn ($q) => $q->where('active', true)])
                ->first();

            if (! $owner) {
                return null;
            }

            $owner->city_count = (int) DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.owner_id', $owner->id)
                ->where('screens.active', true)
                ->whereNotNull('sites.city')
                ->selectRaw("COUNT(DISTINCT TRIM(SUBSTRING_INDEX(sites.city, '>', 1))) as cnt")
                ->value('cnt');

            $labels = $this->getVenueTypeLabels();
            $owner->venue_types_list = DB::table('screens')
                ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
                ->where('screens.owner_id', $owner->id)
                ->where('screens.active', true)
                ->whereNotNull('screen_inventory.venue_type')
                ->where('screen_inventory.venue_type', '!=', '')
                ->distinct()
                ->pluck('screen_inventory.venue_type')
                ->map(fn ($raw) => $labels[$raw] ?? ucfirst(str_replace(['_', '.'], ' ', $raw)))
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            return $owner;
        });
    }

    public function getOwnerScreens(string $ownerId, int $perPage = 12): LengthAwarePaginator
    {
        return Screen::withoutGlobalScope('owner_scope')->where('owner_id', $ownerId)
            ->where('active', true)
            ->with(['spec', 'inventory', 'site'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    // ── Phase 3: Listing ──────────────────────────────────

    public function getScreensPaginated(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->buildScreenQuery($request)
            ->with(['spec', 'inventory', 'owner', 'site']);

        $query = $this->applySort($query, $request);

        return $query->paginate($perPage);
    }

    public function getFilterAggregates(): array
    {
        return Cache::remember('fp:filters', 1800, function () {
            $formats = $this->getVenueTypesWithCounts();

            $cities = $this->getTopCities(20);

            $priceRange = DB::table('screen_inventory')
                ->join('screens', 'screen_inventory.screen_id', '=', 'screens.id')
                ->where('screens.active', true)
                ->where('screen_inventory.floor_cpm', '>', 0)
                ->selectRaw('MIN(screen_inventory.floor_cpm) as min_price, MAX(screen_inventory.floor_cpm) as max_price')
                ->first();

            return [
                'formats'   => $formats,
                'cities'    => $cities,
                'min_price' => (float) ($priceRange->min_price ?? 0),
                'max_price' => (float) ($priceRange->max_price ?? 0),
            ];
        });
    }

    // ── Phase 4: Detail ───────────────────────────────────

    public function getScreenDetail(string $id): ?Screen
    {
        return Cache::remember("fp:screen:{$id}", 300, function () use ($id) {
            return Screen::withoutGlobalScope('owner_scope')->where('active', true)
                ->with(['spec', 'inventory', 'owner', 'site'])
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)
                      ->orWhere('uuid', $id)
                      ->orWhere('external_id', $id);
                })
                ->first();
        });
    }

    public function getSimilarScreens(Screen $screen, int $limit = 4): Collection
    {
        return Cache::remember("fp:similar:{$screen->id}", 600, function () use ($screen, $limit) {
            return Screen::withoutGlobalScope('owner_scope')->where('active', true)
                ->where('id', '!=', $screen->id)
                ->where(function ($q) use ($screen) {
                    $q->whereHas('site', fn ($sq) => $sq->where('city', $screen->site?->city))
                      ->orWhereHas('inventory', fn ($iq) => $iq->where('venue_type', $screen->inventory?->venue_type));
                })
                ->with(['spec', 'inventory', 'site'])
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        });
    }

    // ── Phase 5: Map ──────────────────────────────────────

    public function getMapPins(Request $request): Collection
    {
        return $this->buildScreenQuery($request)
            ->whereHas('site', fn ($q) => $q->whereNotNull('lat')->whereNotNull('lon')->where('lat', '!=', 0)->where('lon', '!=', 0))
            ->with(['spec:screen_id,photo_url', 'inventory:screen_id,floor_cpm,venue_type', 'site:id,lat,lon,city,address'])
            ->get();
    }

    /**
     * Get pins for the homepage mini-map, scoped to a city.
     * Returns lightweight data for up to $limit screens.
     */
    public function getHomepageMapPins(string $citySlug = 'hanoi', int $limit = 50): array
    {
        $cityName = $this->resolveCityName($citySlug);

        $query = Screen::withoutGlobalScope('owner_scope')
            ->where('active', true)
            ->whereHas('site', fn ($q) => $q->whereNotNull('lat')->whereNotNull('lon')
                ->where('lat', '!=', 0)->where('lon', '!=', 0));

        if ($cityName) {
            $this->applyCityFilter($query, $cityName);
        }

        $screens = $query
            ->with(['spec:screen_id,photo_url', 'inventory:screen_id,floor_cpm,venue_type', 'site:id,lat,lon,city,address'])
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        $pins = $screens->map(fn ($s) => [
            'id'    => $s->uuid ?? $s->id,
            'name'  => $s->name,
            'lat'   => (float) ($s->site?->lat ?? 0),
            'lng'   => (float) ($s->site?->lon ?? 0),
            'city'  => $s->site?->city ?? '',
            'addr'  => $s->site?->address ?? '',
            'photo' => $s->spec?->photo_url ?? '',
            'price' => (float) ($s->inventory?->floor_cpm ?? 0),
            'type'  => $s->inventory?->venue_type ?? '',
        ])->filter(fn ($p) => $p['lat'] != 0 && $p['lng'] != 0)->values();

        return [
            'pins'      => $pins,
            'total'     => $this->getHomepageMapCount($citySlug),
            'citySlug'  => $citySlug,
            'cityName'  => $cityName ?? 'Toàn quốc',
        ];
    }

    /**
     * Resolve city slug to province name for LIKE filtering.
     * sites.city stores "Hà Nội > Phường Dương Nội" — we match by province prefix.
     * Returns null for 'all' (no filter = nationwide).
     */
    private function resolveCityName(string $slug): ?string
    {
        if ($slug === 'all') {
            return null;
        }

        // Check hardcoded map first
        if (isset(self::CITY_SLUG_MAP[$slug])) {
            return self::CITY_SLUG_MAP[$slug];
        }

        // Fallback: find province name from DB by matching slug
        $province = DB::table('sites')
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->selectRaw("DISTINCT TRIM(SUBSTRING_INDEX(city, '>', 1)) as province")
            ->get()
            ->first(fn ($r) => Str::slug($r->province) === $slug);

        return $province?->province;
    }

    /**
     * Apply city/province filter using LIKE prefix match.
     * Handles "Hà Nội > Phường..." format in sites.city.
     */
    private function applyCityFilter($query, string $cityName)
    {
        return $query->whereHas('site', fn ($q) => $q->where('city', 'LIKE', $cityName . '%'));
    }

    private function getHomepageMapCount(string $citySlug): int
    {
        return Cache::remember("fp:map_count:{$citySlug}", 1800, function () use ($citySlug) {
            $cityName = $this->resolveCityName($citySlug);

            $query = Screen::withoutGlobalScope('owner_scope')
                ->where('active', true)
                ->whereHas('site', fn ($q) => $q->whereNotNull('lat')->whereNotNull('lon')
                    ->where('lat', '!=', 0)->where('lon', '!=', 0));

            if ($cityName) {
                $this->applyCityFilter($query, $cityName);
            }

            return $query->count();
        });
    }

    // ── Private helpers ───────────────────────────────────

    private function loadOwnerExtras($owners): void
    {
        $ownerIds = $owners->pluck('id');
        if ($ownerIds->isEmpty()) {
            return;
        }

        $cityCounts = DB::table('screens')
            ->join('sites', 'screens.site_id', '=', 'sites.id')
            ->whereIn('screens.owner_id', $ownerIds)
            ->where('screens.active', true)
            ->whereNotNull('sites.city')
            ->where('sites.city', '!=', '')
            ->selectRaw("screens.owner_id, COUNT(DISTINCT TRIM(SUBSTRING_INDEX(sites.city, '>', 1))) as city_count")
            ->groupBy('screens.owner_id')
            ->pluck('city_count', 'owner_id');

        $labels = $this->getVenueTypeLabels();
        $venueTypesByOwner = DB::table('screens')
            ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
            ->whereIn('screens.owner_id', $ownerIds)
            ->where('screens.active', true)
            ->whereNotNull('screen_inventory.venue_type')
            ->where('screen_inventory.venue_type', '!=', '')
            ->selectRaw('screens.owner_id, screen_inventory.venue_type')
            ->distinct()
            ->get()
            ->groupBy('owner_id')
            ->map(fn ($rows) => $rows->pluck('venue_type')
                ->map(fn ($raw) => $labels[$raw] ?? ucfirst(str_replace(['_', '.'], ' ', $raw)))
                ->unique()->sort()->values()->toArray()
            );

        $owners->each(function ($owner) use ($cityCounts, $venueTypesByOwner) {
            $owner->city_count       = (int) ($cityCounts[$owner->id] ?? 0);
            $owner->venue_types_list = $venueTypesByOwner[$owner->id] ?? [];
        });
    }

    private function buildScreenQuery(Request $request)
    {
        return Screen::withoutGlobalScope('owner_scope')
            ->where('active', true)
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = $request->input('q');
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', "%{$search}%")
                        ->orWhereHas('site', fn ($sq) => $sq->where('address', 'LIKE', "%{$search}%")
                            ->orWhere('city', 'LIKE', "%{$search}%"));
                });
            })
            ->when(! empty($citySlugs = array_unique(array_merge(
                $this->resolveArrayParam($request, 'city'),
                $this->resolveArrayParam($request, 'province')
            ))),
                fn ($q) => $q->whereHas('site', function ($sq) use ($citySlugs) {
                    $cityNames = $this->expandCitySlugs($citySlugs);
                    $sq->where(function ($or) use ($cityNames) {
                        foreach ($cityNames as $name) {
                            $or->orWhere('city', 'LIKE', $name . '%');
                        }
                    });
                })
            )
            ->when(! empty($venueTypes = $this->resolveArrayParam($request, 'venue_type')),
                fn ($q) => $q->whereHas('inventory', fn ($iq) => $iq->whereIn('venue_type', $this->resolveVenueTypeFilter($venueTypes)))
            )
            ->when(! empty($screenTypes = $this->resolveArrayParam($request, 'screen_type')),
                fn ($q) => $this->applyScreenTypeFilter($q, $screenTypes)
            )
            ->when(! empty($orientations = $this->resolveArrayParam($request, 'orientation')),
                fn ($q) => $this->applyOrientationFilter($q, $orientations)
            )
            ->when(! empty($networks = $this->resolveArrayParam($request, 'network')),
                fn ($q) => $q->whereIn('network_code', $networks)
            )
            ->when(! empty($ownerSlugs = $this->resolveArrayParam($request, 'owner')),
                fn ($q) => $q->whereHas('owner', fn ($oq) => $oq->whereIn('slug', $ownerSlugs))
            )
            ->when(! empty($districtCodes = $this->resolveArrayParam($request, 'district')),
                fn ($q) => $q->whereIn('location_district_code', $districtCodes)
            )
            ->when(! empty($regionCodes = $this->resolveArrayParam($request, 'region')), function ($q) use ($regionCodes) {
                $validRegions = array_intersect($regionCodes, ['north', 'central', 'south']);
                if (empty($validRegions)) {
                    return;
                }
                $regionConfig  = config('regions');
                $provinceCodes = collect($validRegions)
                    ->flatMap(fn ($r) => $regionConfig[$r]['provinces'] ?? [])
                    ->unique()->values()->all();
                $cityNames = array_map(fn ($p) => self::CITY_SLUG_MAP[$p] ?? $p, $provinceCodes);
                $q->whereHas('site', function ($sq) use ($cityNames) {
                    $sq->where(function ($or) use ($cityNames) {
                        foreach ($cityNames as $name) {
                            $or->orWhere('city', 'LIKE', $name . '%');
                        }
                    });
                });
            });
    }

    /**
     * Resolve venue type filter values.
     * Accepts both VN category slugs ("mall", "transit") and raw string_values.
     * Returns array of screen_inventory.venue_type values to match.
     */
    private function resolveVenueTypeFilter(array $types): array
    {
        $resolved = [];
        foreach ($types as $type) {
            // Check if it's a VN category slug
            $stringValues = DB::table('venue_types')
                ->join('venue_categories', 'venue_types.vn_category_id', '=', 'venue_categories.id')
                ->where('venue_categories.slug', $type)
                ->whereNotNull('venue_types.string_value')
                ->pluck('venue_types.string_value')
                ->all();

            if (! empty($stringValues)) {
                $resolved = array_merge($resolved, $stringValues);
            } else {
                // Raw string_value — pass through
                $resolved[] = $type;
            }
        }

        return array_unique($resolved);
    }

    private function expandCitySlugs(array $slugs): array
    {
        return collect($slugs)
            ->flatMap(fn ($slug) => array_unique([
                self::CITY_SLUG_MAP[$slug] ?? $slug,
                $slug,
            ]))
            ->unique()
            ->values()
            ->all();
    }

    private function resolveArrayParam(Request $request, string $key): array
    {
        $value = $request->input($key);

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($v) => $v !== '' && $v !== null));
        }
        if (is_string($value) && str_contains($value, '|')) {
            return array_values(array_filter(explode('|', $value), fn ($v) => $v !== ''));
        }
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        return [];
    }

    private function applyScreenTypeFilter($query, array $types)
    {
        return $query->where(function ($or) use ($types) {
            foreach ($types as $type) {
                $or->orWhere(function ($sub) use ($type) {
                    if ($type === 'billboard') {
                        $sub->whereHas('spec', fn ($sq) => $sq->where('width_cm', '>=', 300)->orWhere('height_cm', '>=', 300));
                    } elseif ($type === 'led') {
                        $sub->whereHas('inventory', fn ($iq) => $iq->where('venue_type', 'outdoor'))
                            ->whereHas('spec', fn ($sq) => $sq->where('width_cm', '<', 300)->where('height_cm', '<', 300));
                    } else {
                        $sub->whereHas('inventory', fn ($iq) => $iq->where('venue_type', '!=', 'outdoor'))
                            ->whereHas('spec', fn ($sq) => $sq->where('width_cm', '<', 300)->where('height_cm', '<', 300));
                    }
                });
            }
        });
    }

    private function applyOrientationFilter($query, array $orientations)
    {
        $valid = array_intersect($orientations, ['landscape', 'portrait', 'square']);
        if (empty($valid)) {
            return $query;
        }

        return $query->whereHas('spec', function ($sq) use ($valid) {
            $sq->where(function ($or) use ($valid) {
                foreach ($valid as $o) {
                    $or->orWhere(function ($sub) use ($o) {
                        match ($o) {
                            'landscape' => $sub->whereColumn('width_px', '>', 'height_px'),
                            'portrait'  => $sub->whereColumn('width_px', '<', 'height_px'),
                            'square'    => $sub->whereColumn('width_px', '=', 'height_px'),
                        };
                    });
                }
            });
        });
    }

    private function applySort($query, Request $request)
    {
        $sort = $request->input('sort');

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $query->leftJoin('screen_inventory as sort_inv', 'screens.id', '=', 'sort_inv.screen_id')
                  ->orderBy('sort_inv.floor_cpm', $sort === 'price_asc' ? 'asc' : 'desc')
                  ->select('screens.*');

            return $query;
        }

        return match ($sort) {
            'newest' => $query->orderBy('screens.updated_at', 'desc'),
            default  => $query->orderBy('screens.id', 'asc'),
        };
    }

    private function cityToSlug(string $cityName): string
    {
        $flipped = array_flip(self::CITY_SLUG_MAP);

        return $flipped[$cityName] ?? Str::slug($cityName);
    }
}
