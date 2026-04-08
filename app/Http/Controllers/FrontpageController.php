<?php

namespace App\Http\Controllers;

use App\Services\FrontpageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FrontpageController extends Controller
{
    public function __construct(private FrontpageService $fp) {}

    public function index(): View
    {
        return view('frontpage.index', [
            'stats'             => $this->fp->getHeroStats(),
            'venueTypes'        => $this->fp->getVenueTypesWithCounts(),
            'topCities'         => $this->fp->getTopCities(),
            'featuredScreens'   => $this->fp->getFeaturedScreens(4),
            'featuredOwners'    => $this->fp->getFeaturedOwners(6),
            'locationsByRegion' => $this->fp->getLocationsByRegion(),
        ]);
    }

    public function listing(Request $request): View
    {
        return view('frontpage.listing', [
            'screens' => $this->fp->getScreensPaginated($request),
            'filters' => $this->fp->getFilterAggregates(),
        ]);
    }

    public function detail(string $screen): View
    {
        $screenModel = $this->fp->getScreenDetail($screen);
        abort_unless($screenModel, 404);

        return view('frontpage.detail', [
            'screen'         => $screenModel,
            'similarScreens' => $this->fp->getSimilarScreens($screenModel),
        ]);
    }

    public function map(Request $request): View
    {
        return view('frontpage.map', [
            'pins'    => $this->fp->getMapPins($request),
            'filters' => $this->fp->getFilterAggregates(),
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
        ]);
    }
}
