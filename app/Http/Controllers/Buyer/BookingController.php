<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Creative;
use App\Services\AvailabilityService;
use App\Services\CampaignService;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private CampaignService $campaignService,
        private CartService $cartService,
        private AvailabilityService $availabilityService,
    ) {}

    /**
     * Step 1: GET /booking/create — Campaign info form
     */
    public function create(Request $request): View|RedirectResponse
    {
        $cart = $this->cartService->getOrCreateCart($request->user());
        $items = $cart->items()->with(['screen.spec', 'screen.inventory', 'screen.owner'])->get();

        if ($items->isEmpty()) {
            return redirect()->route('buyer.cart')->withErrors(['cart' => 'Plan trống. Thêm màn hình trước khi tạo campaign.']);
        }

        return view('buyer.booking.create', [
            'cart'  => $cart,
            'items' => $items,
        ]);
    }

    /**
     * Step 1: POST /booking/create — Store campaign
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'brand_name'   => ['nullable', 'string', 'max:255'],
            'category'     => ['nullable', 'string', 'max:100'],
            'objectives'   => ['nullable', 'array'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $org = $user->currentOrganization;
        $cart = $this->cartService->getOrCreateCart($user);

        abort_unless($cart->items()->exists(), 422, 'Plan trống');

        $campaign = $this->campaignService->createFromCart($org, $user, $cart, $data);

        return redirect()->route('buyer.booking.creative', $campaign);
    }

    /**
     * Step 2: GET /booking/{campaign}/creative — Upload creatives
     */
    public function creative(Request $request, Campaign $campaign): View
    {
        $this->authorizeCampaign($request, $campaign);

        $lines = $campaign->bookingLines()
            ->with(['screen.spec'])
            ->get();

        $creatives = $campaign->creatives()->get();

        return view('buyer.booking.creative', [
            'campaign'  => $campaign,
            'lines'     => $lines,
            'creatives' => $creatives,
        ]);
    }

    /**
     * Step 2: POST /booking/{campaign}/creative — Upload file
     */
    public function uploadCreative(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,mp4,webm', 'max:51200'], // 50MB
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store('creatives/' . $campaign->id, 'public');
        $isVideo = in_array($file->getClientOriginalExtension(), ['mp4', 'webm']);

        Creative::create([
            'campaign_id'     => $campaign->id,
            'organization_id' => $campaign->organization_id,
            'name'            => $request->input('name', $file->getClientOriginalName()),
            'type'            => $isVideo ? 'video' : 'image',
            'file_path'       => $path,
            'file_size'       => $file->getSize(),
            'status'          => 'pending_review',
        ]);

        return redirect()->route('buyer.booking.creative', $campaign)->with('success', 'Creative đã được upload');
    }

    /**
     * Step 3: GET /booking/{campaign}/review — Review & submit
     */
    public function review(Request $request, Campaign $campaign): View
    {
        $this->authorizeCampaign($request, $campaign);

        $lines = $campaign->bookingLines()
            ->with(['screen.spec', 'screen.inventory', 'screen.owner', 'screen.site'])
            ->get();

        $creatives = $campaign->creatives()->get();
        $conflicts = $this->availabilityService->validateCampaign($campaign->id);

        return view('buyer.booking.review', [
            'campaign'  => $campaign,
            'lines'     => $lines,
            'creatives' => $creatives,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Step 3: POST /booking/{campaign}/submit — Submit for approval
     */
    public function submit(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $conflicts = $this->availabilityService->validateCampaign($campaign->id);
        if (! empty($conflicts)) {
            return back()->withErrors(['conflicts' => 'Có ' . count($conflicts) . ' màn hình bị xung đột SOV. Vui lòng điều chỉnh.']);
        }

        $this->campaignService->submit($campaign, $request->user());

        return redirect()->route('buyer.campaigns.show', $campaign)->with('success', 'Campaign đã được gửi chờ duyệt!');
    }

    /**
     * Ensure user owns the campaign.
     */
    private function authorizeCampaign(Request $request, Campaign $campaign): void
    {
        abort_unless(
            $campaign->organization_id === $request->user()->current_organization_id,
            403,
            'Bạn không có quyền truy cập campaign này'
        );
    }
}
