@extends('frontpage.layouts.app')

@section('title', $page['title'] . ' – OOHX')

@section('content')
    <x-policy-shell :page="$page">
        {{--
            KHUNG MỤC — nội dung chính thức phía đăng ký sàn sẽ gửi sau.
            Bump `version` + đặt `effective_from` trong config/policies.php ở cùng
            commit khi dán nội dung thật.

            Các mục dưới đây bám theo hồ sơ đã khai với Bộ Công Thương (mục 8 và 11):
            HTTPS/TLS toàn site, mật khẩu băm Argon2ID, dữ liệu cá nhân mã hoá
            AES-256, phân quyền theo vai trò, ghi log truy cập, tuân thủ Nghị định
            13/2023/NĐ-CP. Nội dung viết ra phải khớp với những gì hệ thống thực sự
            làm — khai một đằng làm một nẻo là rủi ro khi hậu kiểm.
        --}}

        <h2>1. Mục đích và phạm vi thu thập thông tin</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>2. Phạm vi sử dụng thông tin</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>3. Thời gian lưu trữ thông tin</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>4. Những người hoặc tổ chức có thể được tiếp cận thông tin</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>5. Đơn vị thu thập và quản lý thông tin cá nhân</h2>
        @include('frontpage.partials.company-legal')

        <h2>6. Phương thức và công cụ để người dùng tiếp cận và chỉnh sửa dữ liệu</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>7. Cam kết bảo mật thông tin cá nhân</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>8. Cơ chế tiếp nhận và giải quyết khiếu nại</h2>
        <p>
            Người dùng gửi khiếu nại qua
            <a href="{{ route('fp.reflections.create') }}">biểu mẫu tiếp nhận phản ánh</a>,
            hoặc theo thông tin liên hệ nêu tại mục 5.
        </p>
    </x-policy-shell>
@endsection
