<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FrontpageController extends Controller
{
    public function index(): View
    {
        return view('frontpage.index');
    }

    public function listing(): View
    {
        return view('frontpage.listing');
    }

    public function detail(string $screen): View
    {
        return view('frontpage.detail', ['screenId' => $screen]);
    }

    public function map(): View
    {
        return view('frontpage.map');
    }

    public function booking(): View
    {
        return view('frontpage.booking');
    }

    public function agency(): View
    {
        return view('frontpage.agency');
    }

    public function owners(): View
    {
        return view('frontpage.owners');
    }

    public function ownerDetail(string $owner): View
    {
        return view('frontpage.owner-detail', ['ownerSlug' => $owner]);
    }
}
