@extends('frontpage.layouts.app')

@section('title', $page['title'] . ' – OOHX')

@section('content')
    <x-policy-shell :page="$page">
        {{--
            KHUNG MỤC — nội dung chính thức phía đăng ký sàn sẽ gửi sau.
            Bump `version` + đặt `effective_from` trong config/policies.php ở cùng commit.
        --}}

        <h2>1. Nguyên tắc giải quyết</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>2. Kênh tiếp nhận</h2>
        <p>Sàn tiếp nhận tranh chấp, khiếu nại và phản ánh qua các kênh sau:</p>
        <ul>
            <li>
                Biểu mẫu trực tuyến:
                <a href="{{ route('fp.reflections.create') }}">Tiếp nhận phản ánh</a>
            </li>
            <li>Hotline: {{ config('policies.company.hotline') }}</li>
            <li>
                Email:
                <a href="mailto:{{ config('policies.company.email') }}">{{ config('policies.company.email') }}</a>
            </li>
            <li>Địa chỉ: {{ config('policies.company.address') }}</li>
        </ul>

        <h2>3. Quy trình và thời hạn xử lý</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>4. Trách nhiệm của các bên</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>5. Trường hợp không đạt được thỏa thuận</h2>
        <p>Nội dung đang hoàn thiện.</p>

        <h2>6. Công khai kết quả xử lý phản ánh</h2>
        <p>
            Kết quả xử lý các phản ánh của tổ chức xã hội được công bố tại
            <a href="{{ route('fp.reflections.index') }}">Danh sách phản ánh của TCXH</a>.
        </p>
    </x-policy-shell>
@endsection
