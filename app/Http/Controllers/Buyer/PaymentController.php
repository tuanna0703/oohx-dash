<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\PolicyConsent;
use App\Services\PaymentService;
use App\Services\PolicyConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private PolicyConsentService $consents,
    ) {}

    /**
     * GET /booking/{campaign}/payment — Show payment page
     */
    public function show(Request $request, Campaign $campaign): View
    {
        $this->authorize($request, $campaign);

        abort_unless(
            in_array($campaign->status, ['approved', 'active']),
            403,
            'Campaign chưa được duyệt'
        );

        $summary = $this->paymentService->getSummary($campaign);
        $payments = $campaign->payments()->with('owner:id,name')->latest()->get();

        return view('buyer.booking.payment', [
            'campaign' => $campaign,
            'summary'  => $summary,
            'payments' => $payments,
            'byOwner'  => $this->paymentService->breakdownByOwner($campaign),
        ]);
    }

    /**
     * POST /booking/{campaign}/payment — Create payment (bank transfer)
     */
    public function process(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize($request, $campaign);

        $data = $request->validate([
            'method'   => ['required', 'in:bank_transfer,vnpay,momo'],
            'amount'   => ['nullable', 'numeric', 'min:1000'],
            // Người mua chuyển thẳng cho từng media owner, nên mỗi lần xác nhận
            // phải nói rõ là đã trả cho ai. exists+booking_lines: chỉ chấp nhận
            // owner thật sự có màn hình trong campaign này.
            'owner_id' => ['required', 'string', 'exists:owners,id'],

            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Bạn cần đồng ý với Quy chế hoạt động để xác nhận thanh toán.',
            'owner_id.required'     => 'Không xác định được media owner nhận khoản thanh toán này.',
        ]);

        abort_unless(
            $campaign->bookingLines()->where('owner_id', $data['owner_id'])->exists(),
            422,
            'Media owner này không có màn hình nào trong campaign'
        );

        $method = $data['method'];

        if ($method === 'vnpay') {
            // TODO: Phase 6.5 — VNPay integration
            return back()->withErrors(['method' => 'VNPay chưa được hỗ trợ. Vui lòng chọn chuyển khoản.']);
        }

        if ($method === 'momo') {
            return back()->withErrors(['method' => 'MoMo chưa được hỗ trợ. Vui lòng chọn chuyển khoản.']);
        }

        // Bank transfer — create pending payment
        $payment = $this->paymentService->createPayment(
            $campaign,
            'bank_transfer',
            $data['amount'] ?? null,
            $data['owner_id'],
        );

        $this->consents->record(
            ['terms'],
            PolicyConsent::CONTEXT_PAYMENT,
            $request,
            subjectId: $campaign->id,
        );

        return redirect()->route('buyer.payment.success', [
            'campaign' => $campaign,
            'payment'  => $payment,
        ]);
    }

    /**
     * GET /booking/{campaign}/payment/success — Payment submitted confirmation
     */
    public function success(Request $request, Campaign $campaign): View
    {
        $this->authorize($request, $campaign);

        $payment = $campaign->payments()->latest()->first();

        return view('buyer.booking.payment-success', [
            'campaign' => $campaign,
            'payment'  => $payment,
        ]);
    }

    private function authorize(Request $request, Campaign $campaign): void
    {
        abort_unless(
            $campaign->organization_id === $request->user()->current_organization_id,
            403
        );
    }
}
