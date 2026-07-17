@extends('frontpage.layouts.app')

@section('title', $page['title'] . ' – OOHX')

@section('content')
    <x-policy-shell :page="$page">
        {{--
            KHUNG MỤC — nội dung chính thức phía đăng ký sàn sẽ gửi sau.
            Khi dán nội dung thật vào, nhớ bump `version` và đặt `effective_from`
            trong config/policies.php ở CÙNG commit đó. Phiên bản được đóng dấu vào
            từng bản ghi chấp thuận của người dùng; đổi chữ mà không đổi version thì
            bản ghi ấy không còn chứng minh được điều gì.
        --}}

        <h2>1. Nguyên tắc chung</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>2. Quy định chung</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>3. Quy trình giao dịch</h2>
        <p>
            Nội dung đang hoàn thiện. Phần này sẽ mô tả các bước từ tìm kiếm màn hình,
            thêm vào giỏ, gửi booking, chờ media owner phê duyệt, đến thanh toán và
            cung cấp dịch vụ.
        </p>

        <h2>4. Quy trình thanh toán</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>5. Đảm bảo an toàn giao dịch</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>6. Quyền và nghĩa vụ của sàn</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>7. Quyền và nghĩa vụ của người bán (media owner)</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>8. Quyền và nghĩa vụ của người mua</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>9. Quản lý thông tin trên sàn</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>10. Cơ chế giải quyết tranh chấp, khiếu nại</h2>
        <p>
            Xem chi tiết tại
            <a href="{{ route('fp.policy', 'giai-quyet-tranh-chap') }}">Cơ chế giải quyết tranh chấp, khiếu nại, phản ánh</a>.
        </p>

        <h2>11. Chính sách bảo vệ thông tin cá nhân</h2>
        <p>
            Xem chi tiết tại
            <a href="{{ route('fp.policy', 'chinh-sach-bao-mat') }}">Chính sách bảo mật</a>.
        </p>

        <h2>12. Điều khoản áp dụng</h2>
        <p>Nội dung đang hoàn thiện.</p>
    </x-policy-shell>
@endsection
