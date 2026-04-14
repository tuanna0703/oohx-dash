<?php

namespace App\Services;

use App\Models\Network;
use App\Models\Owner;
use App\Models\Screen;
use App\Models\Site;
use App\Models\VenueCategory;
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
                ->whereNull('screens.deleted_at')
                ->whereNull('sites.deleted_at')
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
            return DB::table('screens')
                ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
                ->join('venue_categories', 'screen_inventory.vn_category_id', '=', 'venue_categories.id')
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->where('venue_categories.is_active', true)
                ->selectRaw('
                    venue_categories.slug as type,
                    COALESCE(venue_categories.name_vi, venue_categories.name) as label,
                    venue_categories.icon,
                    venue_categories.thumb,
                    venue_categories.sort_order,
                    count(*) as count
                ')
                ->groupBy('venue_categories.slug', 'venue_categories.name_vi', 'venue_categories.name', 'venue_categories.icon', 'venue_categories.thumb', 'venue_categories.sort_order')
                ->orderBy('venue_categories.sort_order')
                ->get()
                ->map(fn ($r) => [
                    'type'  => $r->type,
                    'label' => $r->label,
                    'icon'  => $r->icon ?? 'tv',
                    'thumb' => $r->thumb ? asset('storage/' . $r->thumb) : null,
                    'count' => (int) $r->count,
                ]);
        });
    }

    /**
     * Cached lookup: vn_category_id → VN category name.
     * Used by Blade views to display VN label for individual screens.
     */
    public function getVnCategoryLabels(): array
    {
        return Cache::remember('fp:vn_category_labels', 3600, fn () =>
            DB::table('venue_categories')
                ->where('is_active', true)
                ->pluck(DB::raw('COALESCE(name_vi, name)'), 'id')
                ->all()
        );
    }

    /**
     * Cached lookup: vn_category_id → slug.
     * Used for map pin filtering.
     */
    public function getVnCategorySlugs(): array
    {
        return Cache::remember('fp:vn_category_slugs', 3600, fn () =>
            DB::table('venue_categories')
                ->where('is_active', true)
                ->pluck('slug', 'id')
                ->all()
        );
    }

    /**
     * Cached lookup: vn_category_id → icon (Material Icons name).
     */
    public function getVnCategoryIcons(): array
    {
        return Cache::remember('fp:vn_category_icons', 3600, fn () =>
            DB::table('venue_categories')
                ->where('is_active', true)
                ->whereNotNull('icon')
                ->pluck('icon', 'id')
                ->all()
        );
    }

    public function getTopCities(int $limit = 8): Collection
    {
        return Cache::remember('fp:top_cities', 1800, function () use ($limit) {
            // sites.city may store "Hà Nội > Phường Dương Nội" — extract province part
            $rawCityCounts = DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->whereNull('sites.deleted_at')
                ->whereNotNull('sites.city')
                ->where('sites.city', '!=', '')
                ->selectRaw("TRIM(SUBSTRING_INDEX(sites.city, '>', 1)) as province, count(*) as count")
                ->groupByRaw("TRIM(SUBSTRING_INDEX(sites.city, '>', 1))")
                ->orderByDesc('count')
                ->limit($limit)
                ->get();

            // Load province photos
            $provincePhotos = DB::table('vietnam_provinces')
                ->whereNotNull('photo_url')
                ->pluck('photo_url', 'name');

            return $rawCityCounts->map(fn ($r) => [
                'code'  => $this->cityToSlug($r->province),
                'name'  => $r->province,
                'count' => (int) $r->count,
                'photo' => isset($provincePhotos[$r->province])
                    ? asset('storage/' . $provincePhotos[$r->province])
                    : null,
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
                ->whereHas('spec', fn ($q) => $q->where(fn ($sq) =>
                    $sq->whereNotNull('photos')->where('photos', '!=', '[]')
                       ->orWhere(fn ($sq2) => $sq2->whereNotNull('photo_url')->where('photo_url', '!=', ''))
                ))
                ->whereHas('inventory', fn ($q) => $q->where('floor_cpm', '>', 0))
                ->with([
                    'spec:screen_id,photo_url,photos,width_cm,height_cm',
                    'inventory:screen_id,floor_cpm,floor_cpm_currency,venue_type,vn_category_id',
                    'owner:id,name,slug',
                    'site:id,network_id,name,city,address',
                    'site.network:id,name',
                    'products:id,slug,name,total_units,listing_mode',
                ])
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
                ->with(['province:id,full_name', 'commune:id,full_name'])
                ->withCount(['screens as screen_count' => fn ($q) => $q->where('active', true)])
                ->first();

            if (! $owner) {
                return null;
            }

            $owner->city_count = (int) DB::table('screens')
                ->join('sites', 'screens.site_id', '=', 'sites.id')
                ->where('screens.owner_id', $owner->id)
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->whereNull('sites.deleted_at')
                ->whereNotNull('sites.city')
                ->selectRaw("COUNT(DISTINCT TRIM(SUBSTRING_INDEX(sites.city, '>', 1))) as cnt")
                ->value('cnt');

            $owner->venue_types_list = DB::table('screens')
                ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
                ->join('venue_categories', 'screen_inventory.vn_category_id', '=', 'venue_categories.id')
                ->where('screens.owner_id', $owner->id)
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->selectRaw('DISTINCT COALESCE(venue_categories.name_vi, venue_categories.name) as label')
                ->orderBy('label')
                ->pluck('label')
                ->values()
                ->toArray();

            return $owner;
        });
    }

    public function getOwnerScreens(string $ownerId, Request $request = null, int $perPage = 12): LengthAwarePaginator
    {
        $query = Screen::withoutGlobalScope('owner_scope')
            ->where('owner_id', $ownerId)
            ->where('active', true);

        // Apply filters if request provided
        if ($request) {
            // City
            if (! empty($citySlugs = $this->resolveArrayParam($request, 'city'))) {
                $cityNames = $this->expandCitySlugs($citySlugs);
                $query->whereHas('site', function ($sq) use ($cityNames) {
                    $sq->where(function ($or) use ($cityNames) {
                        foreach ($cityNames as $name) {
                            $or->orWhere('city', 'LIKE', $name . '%');
                        }
                    });
                });
            }
            // Region
            if (! empty($regionCodes = $this->resolveArrayParam($request, 'region'))) {
                $regionConfig = config('regions');
                $provinceCodes = collect($regionCodes)
                    ->flatMap(fn ($r) => $regionConfig[$r]['provinces'] ?? [])
                    ->unique()->values()->all();
                $cityNames = array_map(fn ($p) => self::CITY_SLUG_MAP[$p] ?? $p, $provinceCodes);
                $query->whereHas('site', function ($sq) use ($cityNames) {
                    $sq->where(function ($or) use ($cityNames) {
                        foreach ($cityNames as $name) {
                            $or->orWhere('city', 'LIKE', $name . '%');
                        }
                    });
                });
            }
            // Network
            if (! empty($networks = $this->resolveArrayParam($request, 'network'))) {
                $query->whereHas('site.network', fn ($nq) => $nq->whereIn('code', $networks));
            }
            // Venue type
            if (! empty($venueTypes = $this->resolveArrayParam($request, 'venue_type'))) {
                $catIds = DB::table('venue_categories')->whereIn('slug', $venueTypes)->pluck('id');
                if ($catIds->isNotEmpty()) {
                    $query->whereHas('inventory', fn ($iq) => $iq->whereIn('vn_category_id', $catIds));
                }
            }
            // Search
            if ($request->filled('q')) {
                $search = $request->input('q');
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', "%{$search}%")
                        ->orWhereHas('site', fn ($sq) => $sq->where('address', 'LIKE', "%{$search}%"));
                });
            }
        }

        return $query->with([
                'spec:screen_id,photo_url,photos,width_cm,height_cm',
                'inventory:screen_id,floor_cpm,floor_cpm_currency,venue_type,vn_category_id',
                'owner:id,name,slug',
                'site:id,network_id,name,city,address',
                'site.network:id,name',
                'products:id,slug,name,total_units,listing_mode',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get filter aggregates scoped to a specific owner (for owner detail page).
     */
    public function getOwnerFilterAggregates(string $ownerId): array
    {
        return Cache::remember("fp:owner_filters:{$ownerId}", 900, function () use ($ownerId) {
            $networks = DB::table('networks')
                ->join('sites', 'networks.id', '=', 'sites.network_id')
                ->join('screens', 'sites.id', '=', 'screens.site_id')
                ->where('screens.owner_id', $ownerId)
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->whereNull('networks.deleted_at')
                ->selectRaw('networks.id, networks.code, networks.name, COUNT(DISTINCT screens.id) as count')
                ->groupBy('networks.id', 'networks.code', 'networks.name')
                ->orderByDesc('count')
                ->get();

            $formats = DB::table('screen_inventory')
                ->join('screens', 'screen_inventory.screen_id', '=', 'screens.id')
                ->join('venue_categories', 'screen_inventory.vn_category_id', '=', 'venue_categories.id')
                ->where('screens.owner_id', $ownerId)
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->where('venue_categories.is_active', true)
                ->selectRaw('venue_categories.id, venue_categories.slug as type, COALESCE(venue_categories.name_vi, venue_categories.name) as label, COUNT(*) as count')
                ->groupBy('venue_categories.id', 'venue_categories.slug', 'label')
                ->orderByDesc('count')
                ->get();

            return [
                'networks' => $networks,
                'formats'  => $formats,
            ];
        });
    }

    // ── Phase 3: Listing (multi-level browse) ───────────────

    /**
     * Get networks with aggregated stats for group=network view.
     */
    public function getNetworksPaginated(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = Network::withoutGlobalScope('owner_scope')
            ->where('status', 'active')
            ->withCount(['screens as screen_count' => fn ($q) => $q->where('active', true)])
            ->with(['owner:id,name,slug']);

        // Search
        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Owner filter
        if (! empty($ownerSlugs = $this->resolveArrayParam($request, 'owner'))) {
            $query->whereHas('owner', fn ($oq) => $oq->whereIn('slug', $ownerSlugs));
        }

        // Enrich: site_count, cities list
        $query->withCount('sites')
            ->having('screen_count', '>', 0)
            ->orderByDesc('screen_count');

        return $query->paginate($perPage);
    }

    /**
     * Get sites with aggregated stats for group=site view.
     */
    public function getSitesPaginated(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = Site::withoutGlobalScope('owner_scope')
            ->where('status', 'active')
            ->withCount(['screens as screen_count' => fn ($q) => $q->where('active', true)])
            ->with(['owner:id,name,slug', 'network:id,name']);

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('address', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        if (! empty($ownerSlugs = $this->resolveArrayParam($request, 'owner'))) {
            $query->whereHas('owner', fn ($oq) => $oq->whereIn('slug', $ownerSlugs));
        }

        if (! empty($networks = $this->resolveArrayParam($request, 'network'))) {
            $query->whereHas('network', fn ($nq) => $nq->whereIn('code', $networks));
        }

        if (! empty($citySlugs = array_unique(array_merge(
            $this->resolveArrayParam($request, 'city'),
            $this->resolveArrayParam($request, 'province')
        )))) {
            $cityNames = $this->expandCitySlugs($citySlugs);
            $query->where(function ($q) use ($cityNames) {
                foreach ($cityNames as $name) {
                    $q->orWhere('city', 'LIKE', $name . '%');
                }
            });
        }

        $query->having('screen_count', '>', 0)
            ->orderByDesc('screen_count');

        return $query->paginate($perPage);
    }

    public function getScreensPaginated(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->buildScreenQuery($request)
            ->with([
                'spec:screen_id,photo_url,photos,width_cm,height_cm',
                'inventory:screen_id,floor_cpm,floor_cpm_currency,venue_type,vn_category_id',
                'owner:id,name,slug',
                'site:id,network_id,name,city,address',
                'site.network:id,name',
                'products:id,slug,name,total_units,listing_mode',
            ]);

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
                ->whereNull('screens.deleted_at')
                ->where('screen_inventory.floor_cpm', '>', 0)
                ->selectRaw('MIN(screen_inventory.floor_cpm) as min_price, MAX(screen_inventory.floor_cpm) as max_price')
                ->first();

            // Networks with screen counts
            $networks = DB::table('networks')
                ->join('sites', 'networks.id', '=', 'sites.network_id')
                ->join('screens', 'sites.id', '=', 'screens.site_id')
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->whereNull('sites.deleted_at')
                ->whereNull('networks.deleted_at')
                ->where('networks.status', 'active')
                ->selectRaw('networks.id, networks.code, networks.name, COUNT(DISTINCT screens.id) as count')
                ->groupBy('networks.id', 'networks.code', 'networks.name')
                ->orderByDesc('count')
                ->limit(30)
                ->get();

            // Owners with screen counts
            $owners = DB::table('owners')
                ->join('screens', 'owners.id', '=', 'screens.owner_id')
                ->where('screens.active', true)
                ->whereNull('screens.deleted_at')
                ->whereNull('owners.deleted_at')
                ->where('owners.status', 'active')
                ->selectRaw('owners.id, owners.slug, owners.name, COUNT(DISTINCT screens.id) as count')
                ->groupBy('owners.id', 'owners.slug', 'owners.name')
                ->orderByDesc('count')
                ->limit(20)
                ->get();

            return [
                'formats'   => $formats,
                'cities'    => $cities,
                'networks'  => $networks,
                'owners'    => $owners,
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
                ->with([
                    'spec:screen_id,photo_url,photos,width_px,height_px,width_cm,height_cm,allow_image,allow_video',
                    'inventory:screen_id,floor_cpm,floor_cpm_currency,venue_type,vn_category_id',
                    'owner:id,name,slug',
                    'site:id,network_id,name,city,address,lat,lon',
                    'site.network:id,name',
                ])
                ->where(function ($q) use ($id) {
                    $q->where('slug', $id)
                      ->orWhere('id', $id)
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
                ->with([
                    'spec:screen_id,photo_url,photos,width_cm,height_cm',
                    'inventory:screen_id,floor_cpm,floor_cpm_currency,venue_type,vn_category_id',
                    'owner:id,name,slug',
                    'site:id,network_id,name,city,address',
                    'site.network:id,name',
                ])
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
            ->with([
                'spec:screen_id,photo_url,photos',
                'inventory:screen_id,floor_cpm,venue_type,vn_category_id',
                'owner:id,name,slug',
                'site:id,network_id,name,lat,lon,city,address',
                'site.network:id,name',
                'products:id,slug,name,total_units,listing_mode',
            ])
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
            ->with(['spec:screen_id,photo_url,photos', 'inventory:screen_id,floor_cpm,venue_type,vn_category_id', 'owner:id,name,slug', 'site:id,network_id,name,lat,lon,city,address', 'site.network:id,name'])
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
            'photo' => $s->spec?->photo ?? '',
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
            ->whereNull('screens.deleted_at')
            ->whereNull('sites.deleted_at')
            ->whereNotNull('sites.city')
            ->where('sites.city', '!=', '')
            ->selectRaw("screens.owner_id, COUNT(DISTINCT TRIM(SUBSTRING_INDEX(sites.city, '>', 1))) as city_count")
            ->groupBy('screens.owner_id')
            ->pluck('city_count', 'owner_id');

        $venueTypesByOwner = DB::table('screens')
            ->join('screen_inventory', 'screens.id', '=', 'screen_inventory.screen_id')
            ->join('venue_categories', 'screen_inventory.vn_category_id', '=', 'venue_categories.id')
            ->whereIn('screens.owner_id', $ownerIds)
            ->where('screens.active', true)
            ->whereNull('screens.deleted_at')
            ->selectRaw('screens.owner_id, COALESCE(venue_categories.name_vi, venue_categories.name) as label')
            ->distinct()
            ->get()
            ->groupBy('owner_id')
            ->map(fn ($rows) => $rows->pluck('label')->sort()->values()->toArray());

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
                fn ($q) => $q->whereHas('inventory', function ($iq) use ($venueTypes) {
                    // Resolve VN category slugs to IDs
                    $catIds = DB::table('venue_categories')->whereIn('slug', $venueTypes)->pluck('id');
                    if ($catIds->isNotEmpty()) {
                        $iq->whereIn('vn_category_id', $catIds);
                    } else {
                        // Fallback: raw venue_type match
                        $iq->whereIn('venue_type', $venueTypes);
                    }
                })
            )
            ->when(! empty($screenTypes = $this->resolveArrayParam($request, 'screen_type')),
                fn ($q) => $this->applyScreenTypeFilter($q, $screenTypes)
            )
            ->when(! empty($orientations = $this->resolveArrayParam($request, 'orientation')),
                fn ($q) => $this->applyOrientationFilter($q, $orientations)
            )
            ->when(! empty($networks = $this->resolveArrayParam($request, 'network')),
                fn ($q) => $q->whereHas('site.network', fn ($nq) => $nq->whereIn('code', $networks))
            )
            ->when(! empty($ownerSlugs = $this->resolveArrayParam($request, 'owner')),
                fn ($q) => $q->whereHas('owner', fn ($oq) => $oq->whereIn('slug', $ownerSlugs))
            )
            ->when($request->filled('min_price'),
                fn ($q) => $q->whereHas('inventory', fn ($iq) => $iq->where('floor_cpm', '>=', $request->input('min_price')))
            )
            ->when($request->filled('max_price'),
                fn ($q) => $q->whereHas('inventory', fn ($iq) => $iq->where('floor_cpm', '<=', $request->input('max_price')))
            )
            ->when($request->filled('site'),
                fn ($q) => $q->where('site_id', $request->input('site'))
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
            'newest' => $query->orderBy('screens.created_at', 'desc'),
            default  => $query->orderBy('screens.created_at', 'desc'),
        };
    }

    private function cityToSlug(string $cityName): string
    {
        $flipped = array_flip(self::CITY_SLUG_MAP);

        return $flipped[$cityName] ?? Str::slug($cityName);
    }
}
