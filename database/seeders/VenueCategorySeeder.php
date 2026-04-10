<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenueCategorySeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Seed 12 VN DOOH categories ──
        $categories = [
            ['id' => 1,  'name' => 'Retail',           'slug' => 'retail',          'name_vi' => 'Bán lẻ',                   'description' => 'Siêu thị, cửa hàng tiện lợi, chuỗi bán lẻ',       'icon' => 'storefront',      'sort_order' => 1],
            ['id' => 2,  'name' => 'Mall',             'slug' => 'mall',            'name_vi' => 'Trung tâm thương mại',     'description' => 'TTTM và tổ hợp thương mại',                        'icon' => 'local_mall',      'sort_order' => 2],
            ['id' => 3,  'name' => 'Transit',          'slug' => 'transit',         'name_vi' => 'Giao thông',               'description' => 'Sân bay, ga tàu điện, bến xe, nhà ga',             'icon' => 'directions_bus',  'sort_order' => 3],
            ['id' => 4,  'name' => 'Roadside',         'slug' => 'roadside',        'name_vi' => 'Ngoài trời',               'description' => 'Billboard, LED ngoài trời ven đường',              'icon' => 'signpost',        'sort_order' => 4],
            ['id' => 5,  'name' => 'Office',           'slug' => 'office',          'name_vi' => 'Văn phòng',                'description' => 'Tòa nhà văn phòng, coworking space',               'icon' => 'business',        'sort_order' => 5],
            ['id' => 6,  'name' => 'Residential',      'slug' => 'residential',     'name_vi' => 'Khu dân cư',               'description' => 'Chung cư, khu dân cư',                             'icon' => 'apartment',       'sort_order' => 6],
            ['id' => 7,  'name' => 'Food & Beverage',  'slug' => 'food-beverage',   'name_vi' => 'Ẩm thực',                  'description' => 'Nhà hàng, quán cà phê, food court',                'icon' => 'restaurant',      'sort_order' => 7],
            ['id' => 8,  'name' => 'Healthcare',       'slug' => 'healthcare',      'name_vi' => 'Y tế',                     'description' => 'Bệnh viện, phòng khám, trung tâm y tế',            'icon' => 'local_hospital',  'sort_order' => 8],
            ['id' => 9,  'name' => 'Education',        'slug' => 'education',       'name_vi' => 'Giáo dục',                 'description' => 'Đại học, trường học, trung tâm đào tạo',           'icon' => 'school',          'sort_order' => 9],
            ['id' => 10, 'name' => 'Hospitality',      'slug' => 'hospitality',     'name_vi' => 'Khách sạn',                'description' => 'Khách sạn, resort, căn hộ dịch vụ',                'icon' => 'hotel',           'sort_order' => 10],
            ['id' => 11, 'name' => 'Entertainment',    'slug' => 'entertainment',   'name_vi' => 'Giải trí',                 'description' => 'Rạp chiếu phim, khu vui chơi, sự kiện',            'icon' => 'theaters',        'sort_order' => 11],
            ['id' => 12, 'name' => 'Sports & Fitness', 'slug' => 'sports-fitness',  'name_vi' => 'Thể thao',                 'description' => 'Phòng gym, sân vận động, khu thể thao',            'icon' => 'fitness_center',  'sort_order' => 12],
        ];

        foreach ($categories as $cat) {
            DB::table('venue_categories')->updateOrInsert(
                ['id' => $cat['id']],
                array_merge($cat, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // ── 2. Map existing venue_types → vn_category_id ──
        // Map by OpenOOH category name → VN category ID
        $categoryMapping = [
            // Transit (ID 3)
            'Transit'      => 3,

            // Retail (ID 1) — general retail
            'Retail'       => 1,

            // Mall (ID 2) — subset of Retail in OpenOOH
            // handled separately below

            // Roadside/Outdoor (ID 4)
            'Outdoor'      => 4,
            'Billboards'   => 4,

            // Office (ID 5)
            'Office Buildings' => 5,

            // Residential (ID 6)
            'Residential'  => 6,

            // Food & Beverage (ID 7)
            'Dining'       => 7,

            // Healthcare (ID 8)
            'Point of Care' => 8,
            'Health & Beauty' => 8,

            // Education (ID 9)
            'Education'    => 9,

            // Hospitality (ID 10)
            'Hospitality'  => 10,
            'Lodging'      => 10,

            // Entertainment (ID 11)
            'Entertainment' => 11,

            // Sports & Fitness (ID 12)
            'Recreation'   => 12,
            'Sports'       => 12,
        ];

        foreach ($categoryMapping as $openOohCategory => $vnCategoryId) {
            DB::table('venue_types')
                ->where('category', $openOohCategory)
                ->update(['vn_category_id' => $vnCategoryId]);
        }

        // Special case: Mall — OpenOOH stores malls under "Retail" category with "Mall" subcategory
        DB::table('venue_types')
            ->where('category', 'Retail')
            ->where(function ($q) {
                $q->where('subcategory', 'LIKE', '%Mall%')
                  ->orWhere('subcategory', 'LIKE', '%Malls%')
                  ->orWhere('venue_type', 'LIKE', '%Mall%');
            })
            ->update(['vn_category_id' => 2]); // Mall

        // Anything still unmapped in Retail → Retail (1)
        DB::table('venue_types')
            ->where('category', 'Retail')
            ->whereNull('vn_category_id')
            ->update(['vn_category_id' => 1]);

        // Catch-all: any venue_type still unmapped → try matching by string_value prefix
        $prefixMapping = [
            'transit'       => 3,
            'retail.malls'  => 2,
            'retail'        => 1,
            'outdoor'       => 4,
            'roadside'      => 4,
            'office'        => 5,
            'residential'   => 6,
            'dining'        => 7,
            'food'          => 7,
            'point_of_care' => 8,
            'health'        => 8,
            'education'     => 9,
            'hospitality'   => 10,
            'lodging'       => 10,
            'entertainment' => 11,
            'recreation'    => 12,
        ];

        $unmapped = DB::table('venue_types')->whereNull('vn_category_id')->get();
        foreach ($unmapped as $vt) {
            $sv = strtolower($vt->string_value ?? '');
            foreach ($prefixMapping as $prefix => $catId) {
                if (str_starts_with($sv, $prefix)) {
                    DB::table('venue_types')->where('id', $vt->id)->update(['vn_category_id' => $catId]);
                    break;
                }
            }
        }
    }
}
