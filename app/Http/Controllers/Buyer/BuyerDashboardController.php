<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $org  = $user->currentOrganization;

        $campaignCounts = [
            'draft'   => $org->campaigns()->where('status', 'draft')->count(),
            'pending' => $org->campaigns()->where('status', 'pending_approval')->count(),
            'active'  => $org->campaigns()->where('status', 'active')->count(),
            'total'   => $org->campaigns()->count(),
        ];

        $recentCampaigns = $org->campaigns()
            ->latest()
            ->take(5)
            ->get();

        return view('buyer.dashboard.index', [
            'org'              => $org,
            'campaignCounts'   => $campaignCounts,
            'recentCampaigns'  => $recentCampaigns,
        ]);
    }
}
