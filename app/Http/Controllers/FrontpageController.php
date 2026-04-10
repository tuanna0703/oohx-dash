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

        // AJAX request from city picker → return JSON only
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
            'mapData'           => $mapData,
            'venueLabels'       => $this->fp->getVenueTypeLabels(),
        ]);
    }

    public function listing(FrontpageListingRequest $request): View
    {
        return view('frontpage.listing', [
            'screens'     => $this->fp->getScreensPaginated($request),
            'filters'     => $this->fp->getFilterAggregates(),
            'venueLabels' => $this->fp->getVenueTypeLabels(),
        ]);
    }

    public function detail(string $screen): View
    {
        $screenModel = $this->fp->getScreenDetail($screen);
        abort_unless($screenModel, 404);

        return view('frontpage.detail', [
            'screen'         => $screenModel,
            'similarScreens' => $this->fp->getSimilarScreens($screenModel),
            'venueLabels'    => $this->fp->getVenueTypeLabels(),
        ]);
    }

    public function map(FrontpageListingRequest $request): View
    {
        $venueLabels = $this->fp->getVenueTypeLabels();
        $pins = $this->fp->getMapPins($request);

        $typeToSlug = $this->fp->getVenueTypeSlugs();

        $pinsJson = $pins->map(function ($p) use ($venueLabels, $typeToSlug) {
            $rawType = $p->inventory?->venue_type ?? '';
            return [
                'id'    => $p->uuid ?? $p->id,
                'name'  => $p->name,
                'lat'   => (float) ($p->site?->lat ?? 0),
                'lng'   => (float) ($p->site?->lon ?? 0),
                'city'  => $p->site?->city ?? '',
                'addr'  => $p->site?->address ?? '',
                'photo' => $p->spec?->photo_url ?? '',
                'price' => (float) ($p->inventory?->floor_cpm ?? 0),
                'type'  => $typeToSlug[$rawType] ?? $rawType,
                'typeLabel' => $venueLabels[$rawType] ?? ucfirst(str_replace(['_', '.'], ' ', $rawType)),
            ];
        })->filter(function ($p) {
            return $p['lat'] != 0 && $p['lng'] != 0;
        })->values();

        return view('frontpage.map', [
            'pins'        => $pins,
            'pinsJson'    => $pinsJson,
            'filters'     => $this->fp->getFilterAggregates(),
            'venueLabels' => $venueLabels,
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

    public function ownerDetail(string $owner): View
    {
        $ownerModel = $this->fp->getOwnerBySlug($owner);
        abort_unless($ownerModel, 404);

        return view('frontpage.owner-detail', [
            'owner'        => $ownerModel,
            'ownerScreens' => $this->fp->getOwnerScreens($ownerModel->id),
            'venueLabels'  => $this->fp->getVenueTypeLabels(),
        ]);
    }
}
