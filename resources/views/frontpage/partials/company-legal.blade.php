@php
    $co = config('policies.company');
@endphp
{{--
    Thông tin đơn vị đăng ký sàn TMĐT với Bộ Công Thương.
    Nội dung lấy nguyên văn theo yêu cầu review — sửa ở config/policies.php.
--}}
<div class="ft-legal">
    <div class="ft-legal-name">{{ $co['legal_name'] }}</div>
    <p>
        Mã số doanh nghiệp {{ $co['business_code'] }} do {{ $co['business_code_by'] }}
        cấp ngày {{ $co['business_code_on'] }}
    </p>
    <p>Địa chỉ: {{ $co['address'] }}</p>
    <p>Người đại diện theo pháp luật: {{ $co['legal_rep'] }}</p>
    <p>
        Đầu mối liên hệ, người đại diện được ủy quyền phối hợp với cơ quan nhà nước
        có thẩm quyền: ông {{ $co['authorized_contact'] }}
    </p>
    <p>Hotline: <a href="tel:{{ preg_replace('/\D/', '', $co['hotline']) }}">{{ $co['hotline'] }}</a></p>
    <p>Email: <a href="mailto:{{ $co['email'] }}">{{ $co['email'] }}</a></p>
</div>
