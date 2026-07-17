<?php

/**
 * Legal pages and the company identity shown to the public.
 *
 * `version` is not decoration: it is stamped onto every consent we record, so a
 * dispute can be answered with the exact text the user agreed to. Bump it in the
 * same commit that changes the corresponding Blade file, never on its own and
 * never afterwards — a consent row pointing at a version whose wording has since
 * moved proves nothing.
 */
return [

    // Đơn vị đăng ký sàn với Bộ Công Thương. Hiển thị dưới chân trang.
    'company' => [
        'legal_name'        => 'CÔNG TY TNHH TRUEVIEW',
        'business_code'     => '0109944503',
        'business_code_by'  => 'Sở Tài Chính thành phố Hà Nội',
        'business_code_on'  => '24/3/2022',
        'address'           => 'Số 110 đường Lạc Long Quân, Phường Tây Hồ, Thành phố Hà Nội, Việt Nam.',
        'legal_rep'         => 'NGUYỄN ANH TUẤN',
        'authorized_contact' => 'NGUYỄN ANH TUẤN',
        'hotline'           => '0943668996',
        'email'             => 'tuan.nguyen@attvietnam.vn',
    ],

    /**
     * Website chưa hoàn tất đăng ký với Bộ Công Thương. Đặt false ngay khi có
     * xác nhận đăng ký — banner thử nghiệm sẽ tự tắt trên toàn site.
     */
    'trial_mode' => env('OOHX_TRIAL_MODE', true),

    'pages' => [
        'quy-che-hoat-dong' => [
            'key'     => 'terms',
            'title'   => 'Quy chế hoạt động',
            'view'    => 'frontpage.policies.terms',
            'version' => '0.1-draft',
            'effective_from' => null, // chưa ban hành — nội dung còn là bản nháp
        ],
        'chinh-sach-bao-mat' => [
            'key'     => 'privacy',
            'title'   => 'Chính sách bảo mật',
            'view'    => 'frontpage.policies.privacy',
            'version' => '0.1-draft',
            'effective_from' => null,
        ],
        'giai-quyet-tranh-chap' => [
            'key'     => 'disputes',
            'title'   => 'Cơ chế giải quyết tranh chấp, khiếu nại, phản ánh',
            'view'    => 'frontpage.policies.disputes',
            'version' => '0.1-draft',
            'effective_from' => null,
        ],
    ],
];
