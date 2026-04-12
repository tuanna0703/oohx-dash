@extends('frontpage.layouts.app', ['activeNav' => '', 'bodyClass' => ''])

@section('title', 'Tạo Campaign | OOHX')

@section('content')
<div class="w" style="padding-top:24px;padding-bottom:64px">

    {{-- Wizard steps indicator --}}
    <div class="wz-steps">
        <div class="wz-step on"><div class="wz-step-n">1</div><div class="wz-step-l">Thông tin</div></div>
        <div class="wz-step-line"></div>
        <div class="wz-step"><div class="wz-step-n">2</div><div class="wz-step-l">Creative</div></div>
        <div class="wz-step-line"></div>
        <div class="wz-step"><div class="wz-step-n">3</div><div class="wz-step-l">Xác nhận</div></div>
    </div>

    <div class="wz-layout">
        {{-- Main form --}}
        <div class="wz-main">
            <h1 class="wz-title">Thông tin Campaign</h1>
            <p class="wz-sub">Nhập thông tin cơ bản về campaign của bạn</p>

            @if($errors->any())
            <div class="auth-error" style="margin-bottom:16px">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('buyer.booking.store') }}" class="wz-form">
                @csrf
                <div class="wz-field">
                    <label>Tên campaign <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="VD: Honda Civic Launch Q2 2026">
                </div>
                <div class="wz-row">
                    <div class="wz-field">
                        <label>Brand / Nhãn hàng</label>
                        <input type="text" name="brand_name" value="{{ old('brand_name') }}" placeholder="VD: Honda Vietnam">
                    </div>
                    <div class="wz-field">
                        <label>Ngành hàng</label>
                        <select name="category">
                            <option value="">Chọn ngành hàng</option>
                            <option value="automotive" {{ old('category') === 'automotive' ? 'selected' : '' }}>Ô tô / Xe máy</option>
                            <option value="fmcg" {{ old('category') === 'fmcg' ? 'selected' : '' }}>FMCG</option>
                            <option value="tech" {{ old('category') === 'tech' ? 'selected' : '' }}>Công nghệ</option>
                            <option value="finance" {{ old('category') === 'finance' ? 'selected' : '' }}>Tài chính / Ngân hàng</option>
                            <option value="retail" {{ old('category') === 'retail' ? 'selected' : '' }}>Bán lẻ</option>
                            <option value="fnb" {{ old('category') === 'fnb' ? 'selected' : '' }}>F&B</option>
                            <option value="real_estate" {{ old('category') === 'real_estate' ? 'selected' : '' }}>Bất động sản</option>
                            <option value="entertainment" {{ old('category') === 'entertainment' ? 'selected' : '' }}>Giải trí</option>
                            <option value="other" {{ old('category') === 'other' ? 'selected' : '' }}>Khác</option>
                        </select>
                    </div>
                </div>
                <div class="wz-field">
                    <label>Budget dự kiến (VNĐ)</label>
                    <input type="number" name="total_budget" value="{{ old('total_budget') }}" placeholder="VD: 50000000" min="0">
                </div>
                <div class="wz-field">
                    <label>Ghi chú</label>
                    <textarea name="notes" rows="3" placeholder="Mục tiêu campaign, yêu cầu đặc biệt...">{{ old('notes') }}</textarea>
                </div>

                <div class="wz-actions">
                    <a href="{{ route('buyer.cart') }}" class="btn btn-s">Quay lại Plan</a>
                    <button type="submit" class="btn btn-p">Tiếp tục <svg viewBox="0 0 24 24" fill="#fff" style="width:16px;height:16px"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></button>
                </div>
            </form>
        </div>

        {{-- Sidebar: plan summary --}}
        <div class="wz-sidebar">
            <div class="cart-summary">
                <div class="cart-summary-title">Plan của bạn</div>
                @foreach($items as $item)
                <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--ln2);font-size:12px">
                    <img src="{{ $item->screen->spec?->photo ?? '' }}" style="width:40px;height:40px;border-radius:6px;object-fit:cover;background:var(--bg2)">
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:600;color:var(--t1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $item->screen->name }}</div>
                        <div style="color:var(--t4)">{{ $item->start_date->format('d/m') }} → {{ $item->end_date->format('d/m') }}</div>
                    </div>
                    <div style="font-weight:700;color:var(--t1);white-space:nowrap">{{ number_format($item->estimated_cost, 0, ',', '.') }}₫</div>
                </div>
                @endforeach
                <div class="cart-summary-total">
                    <span>Tổng ước tính</span>
                    <span>{{ number_format($items->sum('estimated_cost'), 0, ',', '.') }} ₫</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
