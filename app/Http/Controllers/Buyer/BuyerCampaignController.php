<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $org = $request->user()->currentOrganization;

        $campaigns = $org->campaigns()
            ->latest()
            ->paginate(20);

        return view('buyer.dashboard.campaigns', [
            'campaigns' => $campaigns,
        ]);
    }

    public function show(Request $request, Campaign $campaign): View
    {
        abort_unless(
            $campaign->organization_id === $request->user()->current_organization_id,
            403
        );

        $campaign->load([
            'bookingLines.screen.spec',
            'bookingLines.screen.owner',
            'bookingLines.screen.site',
            'creatives',
            'payments',
            'activities',
        ]);

        return view('buyer.dashboard.campaign-detail', [
            'campaign' => $campaign,
        ]);
    }
}
