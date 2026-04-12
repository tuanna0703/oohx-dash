@extends('frontpage.layouts.app', ['activeNav' => 'dashboard', 'bodyClass' => ''])

@section('title', 'Thanh toán — {{ $campaign->name }} | OOHX')

@section('content')
<div class="w" style="padding-top:24px;padding-bottom:64px">

    <h1 class="wz-title">Thanh toán</h1>
    <p class="wz-sub">{{ $campaign->name }} &middot; {{ $campaign->code }}</p>

    @if($errors->any())
    <div class="auth-error" style="margin-bottom:16px">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="wz-layout">
        <div class="wz-main">
            {{-- Payment summary --}}
            <div class="wz-card">
                <div class="wz-card-title">Chi tiết thanh toán</div>
                <div class="pay-rows">
                    <div class="pay-row"><span>Tổng chi phí booking</span><span>{{ number_format($summary['total_cost'], 0, ',', '.') }} ₫</span></div>
                    <div class="pay-row"><span>VAT (10%)</span><span>{{ number_format($summary['vat'], 0, ',', '.') }} ₫</span></div>
                    <div class="pay-row pay-row-total"><span>Tổng cộng</span><span>{{ number_format($summary['total_cost_vat'], 0, ',', '.') }} ₫</span></div>
                    @if($summary['total_paid'] > 0)
                    <div class="pay-row" style="color:var(--grn)"><span>Đã thanh toán</span><span>-{{ number_format($summary['total_paid'], 0, ',', '.') }} ₫</span></div>
                    @endif
                    @if($summary['pending'] > 0)
                    <div class="pay-row" style="color:var(--org)"><span>Đang xử lý</span><span>-{{ number_format($summary['pending'], 0, ',', '.') }} ₫</span></div>
                    @endif
                    @if($summary['remaining'] > 0)
                    <div class="pay-row pay-row-total" style="color:var(--bl)"><span>Còn lại</span><span>{{ number_format($summary['remaining'], 0, ',', '.') }} ₫</span></div>
                    @endif
                </div>
            </div>

            @if($summary['is_fully_paid'])
            <div class="wz-card" style="margin-top:16px;text-align:center;padding:32px">
                <svg viewBox="0 0 24 24" fill="var(--grn)" style="width:48px;height:48px;margin-bottom:12px"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <div style="font-size:18px;font-weight:700;color:var(--t1)">Đã thanh toán đủ</div>
                <div style="font-size:13px;color:var(--t3);margin-top:6px">Campaign sẽ tự động kích hoạt</div>
            </div>
            @else
            {{-- Payment method selection --}}
            <div class="wz-card" style="margin-top:16px">
                <div class="wz-card-title">Chọn phương thức thanh toán</div>
                <form method="POST" action="{{ route('buyer.payment.process', $campaign) }}">
                    @csrf
                    <input type="hidden" name="amount" value="{{ $summary['remaining'] }}">

                    <div class="pay-methods">
                        <label class="pay-method on">
                            <input type="radio" name="method" value="bank_transfer" checked>
                            <div class="pay-method-icon">
                                <svg viewBox="0 0 24 24" fill="var(--bl)" style="width:24px;height:24px"><path d="M4 10h3v7H4zm6 0h3v7h-3zm-8 9h20v3H2zm14-9h3v7h-3zM12 1L2 6v2h20V6L12 1z"/></svg>
                            </div>
                            <div>
                                <div class="pay-method-title">Chuyển khoản ngân hàng</div>
                                <div class="pay-method-sub">Xác nhận trong 1-2 ngày làm việc</div>
                            </div>
                        </label>
                        <label class="pay-method pay-method-disabled">
                            <input type="radio" name="method" value="vnpay" disabled>
                            <div class="pay-method-icon">
                                <svg viewBox="0 0 24 24" fill="var(--t4)" style="width:24px;height:24px"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                            </div>
                            <div>
                                <div class="pay-method-title">VNPay <span class="badge b-gray" style="font-size:10px;margin-left:4px">Sắp ra mắt</span></div>
                                <div class="pay-method-sub">Thanh toán online qua VNPay</div>
                            </div>
                        </label>
                    </div>

                    {{-- Bank transfer info --}}
                    <div class="pay-bank-info">
                        <div class="pay-bank-title">Thông tin chuyển khoản</div>
                        <div class="pay-bank-row"><span>Ngân hàng</span><span>Vietcombank (VCB)</span></div>
                        <div class="pay-bank-row"><span>Số tài khoản</span><span style="font-weight:700;letter-spacing:1px">1234 5678 9012</span></div>
                        <div class="pay-bank-row"><span>Chủ tài khoản</span><span>CONG TY OOHX VIETNAM</span></div>
                        <div class="pay-bank-row"><span>Nội dung CK</span><span style="font-weight:700;color:var(--bl)">{{ $campaign->code }}</span></div>
                        <div class="pay-bank-row"><span>Số tiền</span><span style="font-weight:700">{{ number_format($summary['remaining'], 0, ',', '.') }} ₫</span></div>
                    </div>

                    <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;border-radius:10px;height:48px;margin-top:16px;font-size:15px">
                        Xác nhận đã chuyển khoản
                    </button>
                </form>
            </div>
            @endif

            {{-- Payment history --}}
            @if($payments->isNotEmpty())
            <div class="wz-card" style="margin-top:16px">
                <div class="wz-card-title">Lịch sử thanh toán</div>
                <div class="pay-history">
                    @foreach($payments as $p)
                    <div class="pay-history-row">
                        <div>
                            <div style="font-weight:600;color:var(--t1);font-size:13px">{{ $p->invoice_number ?? $p->transaction_ref }}</div>
                            <div style="font-size:11px;color:var(--t4)">{{ ucfirst(str_replace('_', ' ', $p->method)) }} &middot; {{ $p->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-weight:700;color:var(--t1)">{{ number_format($p->amount, 0, ',', '.') }} ₫</div>
                            @php
                                $pc = ['pending'=>'b-org','processing'=>'b-org','completed'=>'b-grn','failed'=>'b-red','refunded'=>'b-gray'];
                                $pl = ['pending'=>'Chờ xác nhận','processing'=>'Đang xử lý','completed'=>'Thành công','failed'=>'Thất bại','refunded'=>'Hoàn tiền'];
                            @endphp
                            <span class="badge {{ $pc[$p->status] ?? 'b-gray' }}" style="font-size:10px">{{ $pl[$p->status] ?? $p->status }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="wz-sidebar">
            <div class="wz-actions-card">
                <div style="font-size:14px;font-weight:700;color:var(--t1);margin-bottom:8px">{{ $campaign->name }}</div>
                <div style="font-size:12px;color:var(--t4);margin-bottom:16px">{{ $campaign->bookingLines()->count() }} màn hình &middot; {{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}</div>
                <a href="{{ route('buyer.campaigns.show', $campaign) }}" class="btn btn-s" style="width:100%;justify-content:center;border-radius:10px">Xem Campaign</a>
            </div>
            <div style="margin-top:12px;padding:14px;background:var(--bg2);border-radius:12px;font-size:12px;color:var(--t3);line-height:1.6">
                <svg viewBox="0 0 24 24" fill="var(--grn)" style="width:14px;height:14px;vertical-align:middle"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                Thanh toán bảo mật bởi OOHX. Campaign sẽ tự động kích hoạt sau khi xác nhận thanh toán.
            </div>
        </div>
    </div>
</div>
@endsection
