<?php

namespace App\Http\Controllers;

use App\Http\Requests\FrontpageListingRequest;
use App\Services\FrontpageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrontpageController extends Controller
{
    public function __construct(private FrontpageService $fp) {}

    public function index(Request $request): View|JsonResponse
    {
        $citySlug = $request->input('_map_city', $request->cookie('oohx_city', 'hanoi'));

        if ($request->ajax() && $request->has('_map_city')) {
            return response()->json($this->fp->getHomepageMapPins($citySlug, 50));
        }

        $mapData = $this->fp->getHomepageMapPins($citySlug, 50);

        return view('frontpage.index', [
            'stats'             => $this->fp->getHeroStats(),
            'venueTypes'        => $this->fp->getVenueTypesWithCounts(),
            'topCities'         => $this->fp->getTopCities(),
            'featuredScreens'   => $this->fp->getFeaturedScreens(4),
            'featuredOwners'    => $this->fp->getFeaturedOwners(6),
            'locationsByRegion' => $this->fp->getLocationsByRegion(),
            'filters'           => $this->fp->getFilterAggregates(),
            'mapData'           => $mapData,
            'vnCatLabels'       => $this->fp->getVnCategoryLabels(),
        ]);
    }

    public function listing(FrontpageListingRequest $request): View
    {
        return view('frontpage.listing', [
            'screens'          => $this->fp->getScreensPaginated($request),
            'filters'          => $this->fp->getFilterAggregates(),
            'vnCatLabels'      => $this->fp->getVnCategoryLabels(),
            'locationsByRegion' => $this->fp->getLocationsByRegion(),
        ]);
    }

    public function detail(string $screen): View
    {
        $screenModel = $this->fp->getScreenDetail($screen);
        abort_unless($screenModel, 404);

        return view('frontpage.detail', [
            'screen'         => $screenModel,
            'similarScreens' => $this->fp->getSimilarScreens($screenModel),
            'vnCatLabels'    => $this->fp->getVnCategoryLabels(),
        ]);
    }

    public function map(FrontpageListingRequest $request): View
    {
        $vnCatLabels = $this->fp->getVnCategoryLabels();
        $vnCatSlugs  = $this->fp->getVnCategorySlugs();
        $pins = $this->fp->getMapPins($request);

        $pinsJson = $pins->map(function ($p) use ($vnCatLabels, $vnCatSlugs) {
            $catId = $p->inventory?->vn_category_id;
            $product = $p->relationLoaded('products') ? $p->products->first() : null;
            return [
                'id'        => $p->uuid ?? $p->id,
                'name'      => $p->name,
                'lat'       => (float) ($p->site?->lat ?? 0),
                'lng'       => (float) ($p->site?->lon ?? 0),
                'city'      => $p->site?->city ?? '',
                'addr'      => $p->site?->address ?? '',
                'photo'     => $p->spec?->photo ?? '',
                'price'     => (float) ($p->inventory?->floor_cpm ?? 0),
                'type'      => $vnCatSlugs[$catId] ?? '',
                'typeLabel' => $vnCatLabels[$catId] ?? '',
                'product'   => $product ? [
                    'slug'        => $product->slug,
                    'name'        => $product->name,
                    'total_units' => $product->total_units,
                    'can_buy_individual' => $product->allowsIndividual(),
                ] : null,
            ];
        })->filter(fn ($p) => $p['lat'] != 0 && $p['lng'] != 0)->values();

        return view('frontpage.map', [
            'pins'              => $pins,
            'pinsJson'          => $pinsJson,
            'filters'           => $this->fp->getFilterAggregates(),
            'locationsByRegion' => $this->fp->getLocationsByRegion(),
            'vnCatLabels'       => $vnCatLabels,
        ]);
    }

    public function booking(): View
    {
        return view('frontpage.booking');
    }

    public function agency(): View
    {
        return view('frontpage.agency');
    }

    public function owners(Request $request): View
    {
        return view('frontpage.owners', [
            'owners'     => $this->fp->getOwnersPaginated($request),
            'venueTypes' => $this->fp->getVenueTypesWithCounts(),
        ]);
    }

    public function ownerDetail(Request $request, string $owner): View
    {
        $ownerModel = $this->fp->getOwnerBySlug($owner);
        abort_unless($ownerModel, 404);

        return view('frontpage.owner-detail', [
            'owner'            => $ownerModel,
            'ownerScreens'     => $this->fp->getOwnerScreens($ownerModel->id, $request),
            'filters'          => $this->fp->getOwnerFilterAggregates($ownerModel->id),
            'locationsByRegion' => $this->fp->getLocationsByRegion(),
            'vnCatLabels'      => $this->fp->getVnCategoryLabels(),
        ]);
    }
}
