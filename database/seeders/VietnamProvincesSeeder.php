<?php

namespace Database\Seeders;

use App\Models\VietnamProvince;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu 63 tỉnh/thành phố Việt Nam (trước cải cách hành chính 2025).
 * Sau cải cách (01/07/2025), admin có thể:
 *   1. Dùng command: php artisan vietnam:import --type=provinces --file=provinces.xlsx
 *   2. Hoặc chỉnh sửa thủ công tại /admin/vietnam-provinces
 */
class VietnamProvincesSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            // ── Đồng bằng sông Hồng ──────────────────────────────────────────
            ['code' => '01', 'name' => 'Hà Nội',       'full_name' => 'Thành phố Hà Nội',       'type' => 'thanh_pho', 'region' => 'Đồng bằng sông Hồng'],
            ['code' => '30', 'name' => 'Hải Dương',    'full_name' => 'Tỉnh Hải Dương',          'type' => 'tinh',      'region' => 'Đồng bằng sông Hồng'],
            ['code' => '31', 'name' => 'Hải Phòng',    'full_name' => 'Thành phố Hải Phòng',     'type' => 'thanh_pho', 'region' => 'Đồng bằng sông Hồng'],
            ['code' => '33', 'name' => 'Hưng Yên',     'full_name' => 'Tỉnh Hưng Yên',           'type' => 'tinh',      'region' => 'Đồng bằng sông Hồng'],
            ['code' => '34', 'name' => 'Thái Bình',    'full_name' => 'Tỉnh Thái Bình',          'type' => 'tinh',      'region' => 'Đồng bằng sông Hồng'],
            ['code' => '35', 'name' => 'Hà Nam',       'full_name' => 'Tỉnh Hà Nam',             'type' => 'tinh',      'region' => 'Đồng bằng sông Hồng'],
            ['code' => '36', 'name' => 'Nam Định',     'full_name' => 'Tỉnh Nam Định',           'type' => 'tinh',      'region' => 'Đồng bằng sông Hồng'],
            ['code' => '37', 'name' => 'Ninh Bình',    'full_name' => 'Tỉnh Ninh Bình',          'type' => 'tinh',      'region' => 'Đồng bằng sông Hồng'],
            ['code' => '26', 'name' => 'Vĩnh Phúc',   'full_name' => 'Tỉnh Vĩnh Phúc',         'type' => 'tinh',      'region' => 'Đồng bằng sông Hồng'],

            // ── Trung du và miền núi phía Bắc ────────────────────────────────
            ['code' => '02', 'name' => 'Hà Giang',     'full_name' => 'Tỉnh Hà Giang',           'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '04', 'name' => 'Cao Bằng',     'full_name' => 'Tỉnh Cao Bằng',           'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '06', 'name' => 'Bắc Kạn',     'full_name' => 'Tỉnh Bắc Kạn',            'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '08', 'name' => 'Tuyên Quang',  'full_name' => 'Tỉnh Tuyên Quang',        'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '10', 'name' => 'Lào Cai',      'full_name' => 'Tỉnh Lào Cai',            'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '11', 'name' => 'Điện Biên',    'full_name' => 'Tỉnh Điện Biên',          'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '12', 'name' => 'Lai Châu',     'full_name' => 'Tỉnh Lai Châu',           'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '14', 'name' => 'Sơn La',       'full_name' => 'Tỉnh Sơn La',             'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '15', 'name' => 'Yên Bái',      'full_name' => 'Tỉnh Yên Bái',            'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '17', 'name' => 'Hòa Bình',     'full_name' => 'Tỉnh Hòa Bình',           'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '19', 'name' => 'Thái Nguyên',  'full_name' => 'Tỉnh Thái Nguyên',        'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '20', 'name' => 'Lạng Sơn',    'full_name' => 'Tỉnh Lạng Sơn',           'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '22', 'name' => 'Quảng Ninh',  'full_name' => 'Tỉnh Quảng Ninh',         'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '24', 'name' => 'Bắc Giang',   'full_name' => 'Tỉnh Bắc Giang',          'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '25', 'name' => 'Phú Thọ',     'full_name' => 'Tỉnh Phú Thọ',            'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],
            ['code' => '27', 'name' => 'Bắc Ninh',    'full_name' => 'Tỉnh Bắc Ninh',           'type' => 'tinh',      'region' => 'Trung du và miền núi phía Bắc'],

            // ── Bắc Trung Bộ và duyên hải miền Trung ─────────────────────────
            ['code' => '38', 'name' => 'Thanh Hóa',   'full_name' => 'Tỉnh Thanh Hóa',          'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '40', 'name' => 'Nghệ An',     'full_name' => 'Tỉnh Nghệ An',            'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '42', 'name' => 'Hà Tĩnh',    'full_name' => 'Tỉnh Hà Tĩnh',            'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '44', 'name' => 'Quảng Bình', 'full_name' => 'Tỉnh Quảng Bình',         'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '45', 'name' => 'Quảng Trị',  'full_name' => 'Tỉnh Quảng Trị',          'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '46', 'name' => 'Thừa Thiên Huế', 'full_name' => 'Tỉnh Thừa Thiên Huế', 'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '48', 'name' => 'Đà Nẵng',    'full_name' => 'Thành phố Đà Nẵng',       'type' => 'thanh_pho', 'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '49', 'name' => 'Quảng Nam',  'full_name' => 'Tỉnh Quảng Nam',          'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '51', 'name' => 'Quảng Ngãi', 'full_name' => 'Tỉnh Quảng Ngãi',         'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '52', 'name' => 'Bình Định',  'full_name' => 'Tỉnh Bình Định',          'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '54', 'name' => 'Phú Yên',    'full_name' => 'Tỉnh Phú Yên',            'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '56', 'name' => 'Khánh Hòa',  'full_name' => 'Tỉnh Khánh Hòa',          'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '58', 'name' => 'Ninh Thuận', 'full_name' => 'Tỉnh Ninh Thuận',         'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],
            ['code' => '60', 'name' => 'Bình Thuận', 'full_name' => 'Tỉnh Bình Thuận',         'type' => 'tinh',      'region' => 'Bắc Trung Bộ và duyên hải miền Trung'],

            // ── Tây Nguyên ────────────────────────────────────────────────────
            ['code' => '62', 'name' => 'Kon Tum',    'full_name' => 'Tỉnh Kon Tum',             'type' => 'tinh',      'region' => 'Tây Nguyên'],
            ['code' => '64', 'name' => 'Gia Lai',    'full_name' => 'Tỉnh Gia Lai',             'type' => 'tinh',      'region' => 'Tây Nguyên'],
            ['code' => '66', 'name' => 'Đắk Lắk',  'full_name' => 'Tỉnh Đắk Lắk',            'type' => 'tinh',      'region' => 'Tây Nguyên'],
            ['code' => '67', 'name' => 'Đắk Nông',  'full_name' => 'Tỉnh Đắk Nông',           'type' => 'tinh',      'region' => 'Tây Nguyên'],
            ['code' => '68', 'name' => 'Lâm Đồng',  'full_name' => 'Tỉnh Lâm Đồng',           'type' => 'tinh',      'region' => 'Tây Nguyên'],

            // ── Đông Nam Bộ ───────────────────────────────────────────────────
            ['code' => '70', 'name' => 'Bình Phước', 'full_name' => 'Tỉnh Bình Phước',         'type' => 'tinh',      'region' => 'Đông Nam Bộ'],
            ['code' => '72', 'name' => 'Tây Ninh',   'full_name' => 'Tỉnh Tây Ninh',           'type' => 'tinh',      'region' => 'Đông Nam Bộ'],
            ['code' => '74', 'name' => 'Bình Dương', 'full_name' => 'Tỉnh Bình Dương',         'type' => 'tinh',      'region' => 'Đông Nam Bộ'],
            ['code' => '75', 'name' => 'Đồng Nai',  'full_name' => 'Tỉnh Đồng Nai',           'type' => 'tinh',      'region' => 'Đông Nam Bộ'],
            ['code' => '77', 'name' => 'Bà Rịa - Vũng Tàu', 'full_name' => 'Tỉnh Bà Rịa - Vũng Tàu', 'type' => 'tinh', 'region' => 'Đông Nam Bộ'],
            ['code' => '79', 'name' => 'Hồ Chí Minh', 'full_name' => 'Thành phố Hồ Chí Minh', 'type' => 'thanh_pho', 'region' => 'Đông Nam Bộ'],

            // ── Đồng bằng sông Cửu Long ───────────────────────────────────────
            ['code' => '80', 'name' => 'Long An',    'full_name' => 'Tỉnh Long An',             'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '82', 'name' => 'Tiền Giang', 'full_name' => 'Tỉnh Tiền Giang',         'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '83', 'name' => 'Bến Tre',   'full_name' => 'Tỉnh Bến Tre',             'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '84', 'name' => 'Trà Vinh',  'full_name' => 'Tỉnh Trà Vinh',            'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '86', 'name' => 'Vĩnh Long', 'full_name' => 'Tỉnh Vĩnh Long',           'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '87', 'name' => 'Đồng Tháp', 'full_name' => 'Tỉnh Đồng Tháp',          'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '89', 'name' => 'An Giang',  'full_name' => 'Tỉnh An Giang',            'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '91', 'name' => 'Kiên Giang', 'full_name' => 'Tỉnh Kiên Giang',         'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '92', 'name' => 'Cần Thơ',   'full_name' => 'Thành phố Cần Thơ',        'type' => 'thanh_pho', 'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '93', 'name' => 'Hậu Giang', 'full_name' => 'Tỉnh Hậu Giang',           'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '94', 'name' => 'Sóc Trăng', 'full_name' => 'Tỉnh Sóc Trăng',          'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '95', 'name' => 'Bạc Liêu',  'full_name' => 'Tỉnh Bạc Liêu',           'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
            ['code' => '96', 'name' => 'Cà Mau',    'full_name' => 'Tỉnh Cà Mau',              'type' => 'tinh',      'region' => 'Đồng bằng sông Cửu Long'],
        ];

        foreach ($provinces as $p) {
            VietnamProvince::updateOrCreate(['code' => $p['code']], $p);
        }

        $this->command->info('✓ Đã seed ' . count($provinces) . ' tỉnh/thành phố.');
        $this->command->warn('  Sau cải cách hành chính 01/07/2025, chạy: php artisan vietnam:import --type=provinces --file=provinces.xlsx');
    }
}
