@extends('frontpage.layouts.app', ['activeNav' => 'dashboard', 'bodyClass' => ''])

@section('title', 'Thanh toán thành công | OOHX')

@section('content')
<div class="w" style="padding-top:48px;padding-bottom:64px">
    <div style="max-width:520px;margin:0 auto;text-align:center">
        <svg viewBox="0 0 24 24" fill="var(--grn)" style="width:64px;height:64px;margin-bottom:16px"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>

        <h1 style="font-size:24px;font-weight:800;color:var(--t1);margin-bottom:8px">Yêu cầu thanh toán đã gửi!</h1>
        <p style="font-size:15px;color:var(--t3);line-height:1.6;margin-bottom:24px">
            Chúng tôi đã nhận được thông báo chuyển khoản của bạn. Thanh toán sẽ được xác nhận trong 1-2 ngày làm việc.
        </p>

        @if($payment)
        <div style="background:var(--bg2);border-radius:14px;padding:20px;margin-bottom:24px;text-align:left">
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid var(--ln2)">
                <span style="color:var(--t4)">Mã giao dịch</span>
                <span style="font-weight:700;color:var(--t1)">{{ $payment->transaction_ref }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid var(--ln2)">
                <span style="color:var(--t4)">Số hoá đơn</span>
                <span style="font-weight:700;color:var(--t1)">{{ $payment->invoice_number }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid var(--ln2)">
                <span style="color:var(--t4)">Số tiền</span>
                <span style="font-weight:700;color:var(--t1)">{{ number_format($payment->amount, 0, ',', '.') }} ₫</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0">
                <span style="color:var(--t4)">Trạng thái</span>
                <span class="badge b-org">Chờ xác nhận</span>
            </div>
        </div>
        @endif

        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a href="{{ route('buyer.campaigns.show', $campaign) }}" class="btn btn-p btn-sm">Xem Campaign</a>
            <a href="{{ route('buyer.dashboard') }}" class="btn btn-s btn-sm">Bảng điều khiển</a>
        </div>
    </div>
</div>
@endsection
