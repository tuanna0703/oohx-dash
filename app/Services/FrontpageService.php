<?php

namespace App\Services;

use App\Models\Owner;
use App\Models\Screen;
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
            $totalScreens = Screen::where('active', true)->count();

            $totalCities = DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.active', true)
                ->whereNotNull('sites.city')
                ->where('sites.city', '!=', '')
                ->distinct()
                ->count('sites.city');

            $totalOwners = Screen::where('active', true)
                ->distinct()
                ->count('owner_id');

            return [
                'total_screens' => $totalScreens,
                'total_cities'  => $totalCities,
                'total_owners'  => $totalOwners,
            ];
        });
    }

    public function getVenueTypesWithCounts(): Collection
    {
        return Cache::remember('fp:venue_types', 1800, function () {
            $venueCounts = DB::table('screens')
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
                    '_sort' => $r->enumeration_id ?? PHP_INT_MAX,
                ])
                ->sortBy('_sort')
                ->map(fn ($v) => ['type' => $v['type'], 'label' => $v['label'], 'count' => $v['count']])
                ->values();

            return $venueCounts;
        });
    }

    public function getTopCities(int $limit = 8): Collection
    {
        return Cache::remember('fp:top_cities', 1800, function () use ($limit) {
            $rawCityCounts = DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.active', true)
                ->whereNotNull('sites.city')
                ->where('sites.city', '!=', '')
                ->selectRaw('sites.city, count(*) as count')
                ->groupBy('sites.city')
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            return $rawCityCounts->map(fn ($r) => [
                'code'  => $this->cityToSlug($r->city),
                'name'  => $r->city,
                'count' => (int) $r->count,
            ]);
        });
    }

    public function getLocationsByRegion(): array
    {
        return Cache::remember('fp:locations', 1800, function () {
            $regionConfig = config('regions');
            $cityToCode   = array_flip(self::CITY_SLUG_MAP);

            $rawProvinceCounts = DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->whereNotNull('sites.city')
                ->where('sites.city', '!=', '')
                ->selectRaw('sites.city, count(*) as count')
                ->groupBy('sites.city')
                ->get();

            $provinces = $rawProvinceCounts->map(function ($r) use ($cityToCode, $regionConfig) {
                $code   = $cityToCode[$r->city] ?? Str::slug($r->city);
                $region = null;
                foreach ($regionConfig as $rc => $cfg) {
                    if (in_array($code, $cfg['provinces'])) {
                        $region = $rc;
                        break;
                    }
                }
                return [
                    'code'   => $code,
                    'name'   => $r->city,
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
            return Screen::where('active', true)
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
                ->distinct('sites.city')
                ->count('sites.city');

            $owner->venue_types_list = DB::table('screens')
                ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
                ->where('screens.owner_id', $owner->id)
                ->where('screens.active', true)
                ->whereNotNull('screen_inventory.venue_type')
                ->distinct()
                ->pluck('screen_inventory.venue_type')
                ->sort()
                ->values()
                ->toArray();

            return $owner;
        });
    }

    public function getOwnerScreens(string $ownerId, int $perPage = 12): LengthAwarePaginator
    {
        return Screen::where('owner_id', $ownerId)
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
            return Screen::where('active', true)
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
            return Screen::where('active', true)
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
            ->with(['spec:screen_id,photo_url', 'inventory:screen_id,floor_cpm,venue_type', 'site:id,lat,lon,city,address'])
            ->get();
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
            ->selectRaw('screens.owner_id, count(distinct sites.city) as city_count')
            ->groupBy('screens.owner_id')
            ->pluck('city_count', 'owner_id');

        $venueTypesByOwner = DB::table('screens')
            ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
            ->whereIn('screens.owner_id', $ownerIds)
            ->where('screens.active', true)
            ->whereNotNull('screen_inventory.venue_type')
            ->selectRaw('screens.owner_id, screen_inventory.venue_type')
            ->distinct()
            ->get()
            ->groupBy('owner_id')
            ->map(fn ($rows) => $rows->pluck('venue_type')->sort()->values()->toArray());

        $owners->each(function ($owner) use ($cityCounts, $venueTypesByOwner) {
            $owner->city_count       = (int) ($cityCounts[$owner->id] ?? 0);
            $owner->venue_types_list = $venueTypesByOwner[$owner->id] ?? [];
        });
    }

    private function buildScreenQuery(Request $request)
    {
        return Screen::query()
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
                fn ($q) => $q->whereHas('site', fn ($sq) => $sq->whereIn('city', $this->expandCitySlugs($citySlugs)))
            )
            ->when(! empty($venueTypes = $this->resolveArrayParam($request, 'venue_type')),
                fn ($q) => $q->whereHas('inventory', fn ($iq) => $iq->whereIn('venue_type', $venueTypes))
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
                $q->whereHas('site', fn ($sq) => $sq->whereIn('city', $cityNames));
            });
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
            return array_values(array_filter($value));
        }
        if (is_string($value) && str_contains($value, '|')) {
            return explode('|', $value);
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
        return match ($request->input('sort')) {
            'price_asc'  => $query->orderByRaw('(SELECT floor_cpm FROM screen_inventory WHERE screen_inventory.screen_id = screens.id LIMIT 1) ASC'),
            'price_desc' => $query->orderByRaw('(SELECT floor_cpm FROM screen_inventory WHERE screen_inventory.screen_id = screens.id LIMIT 1) DESC'),
            'newest'     => $query->orderBy('updated_at', 'desc'),
            default      => $query->orderBy('screens.id', 'asc'),
        };
    }

    private function cityToSlug(string $cityName): string
    {
        $flipped = array_flip(self::CITY_SLUG_MAP);

        return $flipped[$cityName] ?? Str::slug($cityName);
    }
}
