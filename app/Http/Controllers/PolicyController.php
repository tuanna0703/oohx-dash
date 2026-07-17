<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Các trang chính sách bắt buộc của sàn TMĐT.
 *
 * Nội dung nằm trong Blade, siêu dữ liệu (tiêu đề, phiên bản) nằm trong
 * config/policies.php. Cố ý không đưa vào DB + trình soạn thảo: bản chính sách
 * nào đang có hiệu lực là chuyện phải truy được bằng lịch sử git, không phải một
 * hàng trong bảng mà ai đó sửa xong không còn dấu vết.
 */
class PolicyController extends Controller
{
    public function show(string $slug): View
    {
        $page = config("policies.pages.{$slug}");

        if (! $page) {
            throw new NotFoundHttpException("Không có trang chính sách '{$slug}'");
        }

        return view($page['view'], ['page' => $page, 'slug' => $slug]);
    }
}
