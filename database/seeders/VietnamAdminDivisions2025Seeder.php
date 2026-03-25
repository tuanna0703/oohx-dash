<?php

namespace Database\Seeders;

use App\Models\VietnamProvince;
use App\Models\VietnamRegion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed 8 vùng kinh tế-xã hội và 34 tỉnh/thành sau cải cách hành chính 01/07/2025.
 * Nguồn: Nghị quyết 202/2025/NQ-QH15, hiệu lực 01/07/2025.
 *
 * Chạy: php artisan db:seed --class=VietnamAdminDivisions2025Seeder
 *
 * Sau khi seed tỉnh, chạy tiếp để lấy 3321 phường/xã:
 *   php artisan vietnam:fetch --only=communes
 */
class VietnamAdminDivisions2025Seeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        VietnamProvince::truncate();
        VietnamRegion::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── 8 Vùng kinh tế-xã hội ────────────────────────────────────────────
        $regions = [
            ['code' => 'TAY_BAC',   'name' => 'Tây Bắc',                  'full_name' => 'Vùng Tây Bắc',                               'sort' => 1],
            ['code' => 'DONG_BAC',  'name' => 'Đông Bắc',                 'full_name' => 'Vùng Đông Bắc',                              'sort' => 2],
            ['code' => 'DBSH',      'name' => 'Đồng bằng sông Hồng',     'full_name' => 'Vùng Đồng bằng sông Hồng',                  'sort' => 3],
            ['code' => 'BTB',       'name' => 'Bắc Trung Bộ',            'full_name' => 'Vùng Bắc Trung Bộ',                         'sort' => 4],
            ['code' => 'DHNTB',     'name' => 'Duyên hải Nam Trung Bộ', 'full_name' => 'Vùng Duyên hải Nam Trung Bộ',               'sort' => 5],
            ['code' => 'TAY_NGUYEN','name' => 'Tây Nguyên',               'full_name' => 'Vùng Tây Nguyên',                           'sort' => 6],
            ['code' => 'DNB',       'name' => 'Đông Nam Bộ',              'full_name' => 'Vùng Đông Nam Bộ',                          'sort' => 7],
            ['code' => 'DBSCL',     'name' => 'Đồng bằng sông Cửu Long','full_name' => 'Vùng Đồng bằng sông Cửu Long',              'sort' => 8],
        ];

        foreach ($regions as $r) {
            VietnamRegion::create($r);
        }

        $this->command->info('✓ Đã seed 8 vùng kinh tế-xã hội.');

        // ── Map code → region_id ──────────────────────────────────────────────
        $rid = VietnamRegion::pluck('id', 'code');

        // ── 34 Tỉnh/Thành sau cải cách 01/07/2025 ────────────────────────────
        // Nguồn: Nghị quyết 202/2025/NQ-QH15
        // Mã tỉnh theo quy định mới của Bộ Nội vụ / Tổng cục Thống kê
        $provinces = [
            // ── Vùng Tây Bắc ─────────────────────────────────────────────────
            // Điện Biên + Lai Châu (sáp nhập)
            ['code'=>'11','name'=>'Điện Biên',   'full_name'=>'Tỉnh Điện Biên',         'type'=>'tinh',      'region_id'=>$rid['TAY_BAC'],
             'region'=>'Tây Bắc', 'note'=>'Điện Biên + Lai Châu'],
            // Lai Châu đã sáp nhập vào Điện Biên → không còn
            // Sơn La giữ nguyên
            ['code'=>'14','name'=>'Sơn La',      'full_name'=>'Tỉnh Sơn La',            'type'=>'tinh',      'region_id'=>$rid['TAY_BAC'],
             'region'=>'Tây Bắc', 'note'=>'Giữ nguyên'],
            // Lào Cai + Yên Bái (sáp nhập)
            ['code'=>'15','name'=>'Lào Cai',     'full_name'=>'Tỉnh Lào Cai',           'type'=>'tinh',      'region_id'=>$rid['TAY_BAC'],
             'region'=>'Tây Bắc', 'note'=>'Lào Cai + Yên Bái'],

            // ── Vùng Đông Bắc ────────────────────────────────────────────────
            // Cao Bằng + Bắc Kạn (sáp nhập)
            ['code'=>'04','name'=>'Cao Bằng',    'full_name'=>'Tỉnh Cao Bằng',          'type'=>'tinh',      'region_id'=>$rid['DONG_BAC'],
             'region'=>'Đông Bắc','note'=>'Cao Bằng + Bắc Kạn'],
            // Tuyên Quang + Hà Giang (sáp nhập)
            ['code'=>'08','name'=>'Tuyên Quang', 'full_name'=>'Tỉnh Tuyên Quang',       'type'=>'tinh',      'region_id'=>$rid['DONG_BAC'],
             'region'=>'Đông Bắc','note'=>'Tuyên Quang + Hà Giang'],
            // Thái Nguyên + Lạng Sơn (sáp nhập)
            ['code'=>'19','name'=>'Thái Nguyên', 'full_name'=>'Tỉnh Thái Nguyên',       'type'=>'tinh',      'region_id'=>$rid['DONG_BAC'],
             'region'=>'Đông Bắc','note'=>'Thái Nguyên + Lạng Sơn'],
            // Quảng Ninh + Bắc Giang (sáp nhập)
            ['code'=>'22','name'=>'Quảng Ninh',  'full_name'=>'Tỉnh Quảng Ninh',        'type'=>'tinh',      'region_id'=>$rid['DONG_BAC'],
             'region'=>'Đông Bắc','note'=>'Quảng Ninh + Bắc Giang'],
            // Phú Thọ + Vĩnh Phúc (sáp nhập)
            ['code'=>'25','name'=>'Phú Thọ',     'full_name'=>'Tỉnh Phú Thọ',           'type'=>'tinh',      'region_id'=>$rid['DONG_BAC'],
             'region'=>'Đông Bắc','note'=>'Phú Thọ + Vĩnh Phúc'],

            // ── Vùng Đồng bằng sông Hồng ─────────────────────────────────────
            // Hà Nội + Hòa Bình (sáp nhập)
            ['code'=>'01','name'=>'Hà Nội',      'full_name'=>'Thành phố Hà Nội',       'type'=>'thanh_pho', 'region_id'=>$rid['DBSH'],
             'region'=>'Đồng bằng sông Hồng','note'=>'Hà Nội + Hòa Bình'],
            // Bắc Ninh + Hải Dương (sáp nhập)
            ['code'=>'24','name'=>'Bắc Ninh',    'full_name'=>'Tỉnh Bắc Ninh',          'type'=>'tinh',      'region_id'=>$rid['DBSH'],
             'region'=>'Đồng bằng sông Hồng','note'=>'Bắc Ninh + Hải Dương'],
            // Hải Phòng + Thái Bình (sáp nhập)
            ['code'=>'31','name'=>'Hải Phòng',   'full_name'=>'Thành phố Hải Phòng',    'type'=>'thanh_pho', 'region_id'=>$rid['DBSH'],
             'region'=>'Đồng bằng sông Hồng','note'=>'Hải Phòng + Thái Bình'],
            // Hưng Yên + Hà Nam (sáp nhập)
            ['code'=>'33','name'=>'Hưng Yên',    'full_name'=>'Tỉnh Hưng Yên',          'type'=>'tinh',      'region_id'=>$rid['DBSH'],
             'region'=>'Đồng bằng sông Hồng','note'=>'Hưng Yên + Hà Nam'],
            // Ninh Bình + Nam Định (sáp nhập)
            ['code'=>'37','name'=>'Ninh Bình',   'full_name'=>'Tỉnh Ninh Bình',         'type'=>'tinh',      'region_id'=>$rid['DBSH'],
             'region'=>'Đồng bằng sông Hồng','note'=>'Ninh Bình + Nam Định'],

            // ── Vùng Bắc Trung Bộ ────────────────────────────────────────────
            // Thanh Hóa + Nghệ An (sáp nhập)
            ['code'=>'38','name'=>'Thanh Hóa',   'full_name'=>'Tỉnh Thanh Hóa',         'type'=>'tinh',      'region_id'=>$rid['BTB'],
             'region'=>'Bắc Trung Bộ','note'=>'Thanh Hóa + Nghệ An'],
            // Hà Tĩnh + Quảng Bình (sáp nhập)
            ['code'=>'42','name'=>'Hà Tĩnh',    'full_name'=>'Tỉnh Hà Tĩnh',           'type'=>'tinh',      'region_id'=>$rid['BTB'],
             'region'=>'Bắc Trung Bộ','note'=>'Hà Tĩnh + Quảng Bình'],
            // Quảng Trị + Thừa Thiên Huế (sáp nhập) → Huế
            ['code'=>'44','name'=>'Quảng Trị',   'full_name'=>'Tỉnh Quảng Trị',         'type'=>'tinh',      'region_id'=>$rid['BTB'],
             'region'=>'Bắc Trung Bộ','note'=>'Quảng Trị + Thừa Thiên Huế'],
            // Thành phố Huế (trực thuộc TW từ 2024)
            ['code'=>'46','name'=>'Huế',         'full_name'=>'Thành phố Huế',          'type'=>'thanh_pho', 'region_id'=>$rid['BTB'],
             'region'=>'Bắc Trung Bộ','note'=>'Thành phố trực thuộc TW từ 2024'],
            // Nghệ An đã sáp nhập vào Thanh Hóa → không còn riêng

            // ── Vùng Duyên hải Nam Trung Bộ ──────────────────────────────────
            // Đà Nẵng + Quảng Nam (sáp nhập)
            ['code'=>'48','name'=>'Đà Nẵng',    'full_name'=>'Thành phố Đà Nẵng',      'type'=>'thanh_pho', 'region_id'=>$rid['DHNTB'],
             'region'=>'Duyên hải Nam Trung Bộ','note'=>'Đà Nẵng + Quảng Nam'],
            // Quảng Ngãi + Bình Định (sáp nhập)
            ['code'=>'51','name'=>'Quảng Ngãi', 'full_name'=>'Tỉnh Quảng Ngãi',        'type'=>'tinh',      'region_id'=>$rid['DHNTB'],
             'region'=>'Duyên hải Nam Trung Bộ','note'=>'Quảng Ngãi + Bình Định'],
            // Khánh Hòa + Phú Yên (sáp nhập)
            ['code'=>'56','name'=>'Khánh Hòa',  'full_name'=>'Tỉnh Khánh Hòa',         'type'=>'tinh',      'region_id'=>$rid['DHNTB'],
             'region'=>'Duyên hải Nam Trung Bộ','note'=>'Khánh Hòa + Phú Yên'],

            // ── Vùng Tây Nguyên ───────────────────────────────────────────────
            // Gia Lai + Kon Tum (sáp nhập)
            ['code'=>'52','name'=>'Gia Lai',    'full_name'=>'Tỉnh Gia Lai',            'type'=>'tinh',      'region_id'=>$rid['TAY_NGUYEN'],
             'region'=>'Tây Nguyên','note'=>'Gia Lai + Kon Tum'],
            // Đắk Lắk + Đắk Nông (sáp nhập)
            ['code'=>'66','name'=>'Đắk Lắk',   'full_name'=>'Tỉnh Đắk Lắk',           'type'=>'tinh',      'region_id'=>$rid['TAY_NGUYEN'],
             'region'=>'Tây Nguyên','note'=>'Đắk Lắk + Đắk Nông'],
            // Lâm Đồng + Bình Thuận + Ninh Thuận (sáp nhập)
            ['code'=>'68','name'=>'Lâm Đồng',  'full_name'=>'Tỉnh Lâm Đồng',          'type'=>'tinh',      'region_id'=>$rid['TAY_NGUYEN'],
             'region'=>'Tây Nguyên','note'=>'Lâm Đồng + Bình Thuận + Ninh Thuận'],

            // ── Vùng Đông Nam Bộ ──────────────────────────────────────────────
            // Đồng Nai + Bà Rịa-Vũng Tàu (sáp nhập)
            ['code'=>'75','name'=>'Đồng Nai',   'full_name'=>'Tỉnh Đồng Nai',          'type'=>'tinh',      'region_id'=>$rid['DNB'],
             'region'=>'Đông Nam Bộ','note'=>'Đồng Nai + Bà Rịa - Vũng Tàu'],
            // TP HCM + Bình Dương (sáp nhập)
            ['code'=>'79','name'=>'Hồ Chí Minh','full_name'=>'Thành phố Hồ Chí Minh', 'type'=>'thanh_pho', 'region_id'=>$rid['DNB'],
             'region'=>'Đông Nam Bộ','note'=>'TP HCM + Bình Dương'],
            // Tây Ninh + Bình Phước (sáp nhập)
            ['code'=>'80','name'=>'Tây Ninh',   'full_name'=>'Tỉnh Tây Ninh',          'type'=>'tinh',      'region_id'=>$rid['DNB'],
             'region'=>'Đông Nam Bộ','note'=>'Tây Ninh + Bình Phước'],

            // ── Vùng Đồng bằng sông Cửu Long ────────────────────────────────
            // Đồng Tháp + Tiền Giang + Bến Tre (sáp nhập)
            ['code'=>'82','name'=>'Đồng Tháp',  'full_name'=>'Tỉnh Đồng Tháp',         'type'=>'tinh',      'region_id'=>$rid['DBSCL'],
             'region'=>'Đồng bằng sông Cửu Long','note'=>'Đồng Tháp + Tiền Giang + Bến Tre'],
            // Vĩnh Long + Trà Vinh + Hậu Giang (sáp nhập)
            ['code'=>'86','name'=>'Vĩnh Long',  'full_name'=>'Tỉnh Vĩnh Long',         'type'=>'tinh',      'region_id'=>$rid['DBSCL'],
             'region'=>'Đồng bằng sông Cửu Long','note'=>'Vĩnh Long + Trà Vinh + Hậu Giang'],
            // An Giang + Kiên Giang (sáp nhập)
            ['code'=>'91','name'=>'An Giang',   'full_name'=>'Tỉnh An Giang',           'type'=>'tinh',      'region_id'=>$rid['DBSCL'],
             'region'=>'Đồng bằng sông Cửu Long','note'=>'An Giang + Kiên Giang'],
            // Cần Thơ + Sóc Trăng (sáp nhập)
            ['code'=>'92','name'=>'Cần Thơ',    'full_name'=>'Thành phố Cần Thơ',       'type'=>'thanh_pho', 'region_id'=>$rid['DBSCL'],
             'region'=>'Đồng bằng sông Cửu Long','note'=>'Cần Thơ + Sóc Trăng'],
            // Cà Mau + Bạc Liêu (sáp nhập)
            ['code'=>'96','name'=>'Cà Mau',     'full_name'=>'Tỉnh Cà Mau',             'type'=>'tinh',      'region_id'=>$rid['DBSCL'],
             'region'=>'Đồng bằng sông Cửu Long','note'=>'Cà Mau + Bạc Liêu'],
        ];

        foreach ($provinces as $p) {
            unset($p['note']); // note chỉ để comment, không lưu DB
            VietnamProvince::create($p);
        }

        $this->command->info('✓ Đã seed ' . count($provinces) . ' tỉnh/thành phố (2025).');
        $this->command->line('');
        $this->command->line('  Bước tiếp theo — fetch 3321 phường/xã:');
        $this->command->line('  <fg=yellow>php artisan vietnam:fetch --only=communes</>');
    }
}
