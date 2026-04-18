<?php

namespace App\Support;

/**
 * Shared POI projection — used by both frontpage detail and admin POI snapshot view.
 *
 * Maps raw OSM POI elements to display-ready array with:
 *   - Material Symbols icon name (đồng bộ với UI)
 *   - VN-friendly category group + label
 *   - Distance from center (haversine)
 *
 * Single source of truth — sửa 1 chỗ, áp dụng cả 2 trang.
 */
class PoiProjection
{
    /**
     * Map raw OSM tag → [group_key, label_vn, material_icon, color]
     */
    public const GROUP_MAP = [
        // Food & beverage
        'cafe'           => ['food_bev',  'Cafe',                'local_cafe',         '#8B4513'],
        'restaurant'     => ['food_bev',  'Nhà hàng',            'restaurant',         '#E67E22'],
        'fast_food'      => ['food_bev',  'Đồ ăn nhanh',         'fastfood',           '#E74C3C'],
        'bar'            => ['food_bev',  'Bar',                 'local_bar',          '#9B59B6'],
        'pub'            => ['food_bev',  'Pub',                 'sports_bar',         '#9B59B6'],
        'food_court'     => ['food_bev',  'Food court',          'restaurant_menu',    '#E67E22'],
        'bakery'         => ['food_bev',  'Bakery',              'bakery_dining',      '#D35400'],
        // Education
        'school'         => ['education', 'Trường học',          'school',             '#3498DB'],
        'university'     => ['education', 'Đại học',             'cast_for_education', '#2980B9'],
        'college'        => ['education', 'Cao đẳng',            'cast_for_education', '#2980B9'],
        'kindergarten'   => ['education', 'Mầm non',             'child_care',         '#3498DB'],
        'language_school'=> ['education', 'Trung tâm ngoại ngữ', 'translate',          '#3498DB'],
        // Health
        'hospital'       => ['health',    'Bệnh viện',           'local_hospital',     '#E74C3C'],
        'clinic'         => ['health',    'Phòng khám',          'medical_services',   '#E91E63'],
        'pharmacy'       => ['health',    'Hiệu thuốc',          'medication',         '#27AE60'],
        'doctors'        => ['health',    'Bác sĩ',              'stethoscope',        '#E91E63'],
        // Finance
        'bank'           => ['finance',   'Ngân hàng',           'account_balance',    '#16A085'],
        'atm'            => ['finance',   'ATM',                 'local_atm',          '#16A085'],
        // Auto
        'fuel'           => ['auto',      'Cây xăng',            'local_gas_station',  '#F39C12'],
        'parking'        => ['auto',      'Bãi đậu xe',          'local_parking',      '#7F8C8D'],
        // Entertainment
        'cinema'         => ['entertain', 'Rạp chiếu phim',      'movie',              '#9B59B6'],
        'theatre'        => ['entertain', 'Nhà hát',             'theater_comedy',     '#9B59B6'],
        'nightclub'      => ['entertain', 'Nightclub',           'nightlife',          '#9B59B6'],
        // Lodging
        'hotel'          => ['lodging',   'Khách sạn',           'hotel',              '#34495E'],
        'guest_house'    => ['lodging',   'Guest house',         'house',              '#34495E'],
        'hostel'         => ['lodging',   'Hostel',              'bed',                '#34495E'],
        // Retail
        'mall'           => ['retail',    'Trung tâm thương mại','local_mall',         '#E67E22'],
        'department_store'=>['retail',    'Department store',    'store',              '#E67E22'],
        'supermarket'    => ['retail',    'Siêu thị',            'local_grocery_store','#27AE60'],
        'convenience'    => ['retail',    'Cửa hàng tiện lợi',   'convenience_store',  '#27AE60'],
        'marketplace'    => ['retail',    'Chợ',                 'storefront',         '#E67E22'],
        'clothes'        => ['retail',    'Thời trang',          'checkroom',          '#E91E63'],
        'shoes'          => ['retail',    'Giày dép',            'do_not_step',        '#E91E63'],
        'electronics'    => ['retail',    'Điện tử',             'devices',            '#34495E'],
        'mobile_phone'   => ['retail',    'Điện thoại',          'smartphone',         '#34495E'],
        'beauty'         => ['retail',    'Làm đẹp',             'face_retouching_natural', '#E91E63'],
        'cosmetics'      => ['retail',    'Mỹ phẩm',             'brush',              '#E91E63'],
        'hairdresser'    => ['retail',    'Tóc',                 'content_cut',        '#E91E63'],
        // Sports & leisure
        'gym'            => ['sports',    'Gym',                 'fitness_center',     '#16A085'],
        'fitness_centre' => ['sports',    'Fitness',             'fitness_center',     '#16A085'],
        'park'           => ['sports',    'Công viên',           'park',               '#27AE60'],
        'sports_centre'  => ['sports',    'Trung tâm thể thao',  'sports_soccer',      '#16A085'],
        'stadium'        => ['sports',    'Sân vận động',        'stadium',            '#16A085'],
        // Transit
        'bus_station'    => ['transit',   'Bến xe bus',          'directions_bus',     '#3498DB'],
        'station'        => ['transit',   'Trạm',                'train',              '#3498DB'],
        // Tourism
        'attraction'     => ['tourism',   'Điểm tham quan',      'attractions',        '#9B59B6'],
        'museum'         => ['tourism',   'Bảo tàng',            'museum',             '#9B59B6'],
        'gallery'        => ['tourism',   'Gallery',             'photo_library',      '#9B59B6'],
        'place_of_worship'=>['tourism',   'Nơi thờ tự',          'church',             '#7F8C8D'],
        // Office
        'office'         => ['office',    'Văn phòng',           'business',           '#34495E'],
        'company'        => ['office',    'Công ty',             'business_center',    '#34495E'],
        'coworking'      => ['office',    'Coworking',           'groups',             '#34495E'],
        'government'     => ['office',    'Cơ quan nhà nước',    'gavel',              '#7F8C8D'],
    ];

    /** Group key → [Material icon, label VN] */
    public const GROUP_LABELS = [
        'food_bev'  => ['restaurant',       'Ăn uống'],
        'education' => ['school',           'Giáo dục'],
        'health'    => ['local_hospital',   'Y tế'],
        'finance'   => ['account_balance',  'Tài chính'],
        'auto'      => ['local_gas_station','Giao thông xe'],
        'entertain' => ['movie',            'Giải trí'],
        'lodging'   => ['hotel',            'Lưu trú'],
        'retail'    => ['local_mall',       'Mua sắm'],
        'sports'    => ['fitness_center',   'Thể thao'],
        'transit'   => ['directions_bus',   'Vận tải công cộng'],
        'tourism'   => ['attractions',      'Du lịch'],
        'office'    => ['business',         'Văn phòng'],
        'other'     => ['place',            'Khác'],
    ];

    /**
     * Project + filter + sort raw OSM POI elements into display-ready array.
     *
     * @param  array $rawPois     Raw OSM elements (from PoiSnapshot::pois)
     * @param  float $centerLat   Center reference for distance calc
     * @param  float $centerLon
     * @param  int|null $limit    Optional cap (e.g. 60 for map perf)
     * @return list<array{id:string, name:string, lat:float, lon:float, group:string, gLabel:string, icon:string, color:string, tag:?string, dist:int}>
     */
    public static function project(array $rawPois, float $centerLat, float $centerLon, ?int $limit = null): array
    {
        $out = [];
        $idx = 0;
        foreach ($rawPois as $p) {
            $tags = $p['tags'] ?? [];
            $name = $tags['name'] ?? $tags['name:vi'] ?? null;
            if (! $name) continue;

            $pLat = $p['lat'] ?? ($p['center']['lat'] ?? null);
            $pLon = $p['lon'] ?? ($p['center']['lon'] ?? null);
            if (! $pLat || ! $pLon) continue;

            $rawTag = $tags['amenity']
                ?? $tags['shop']
                ?? $tags['leisure']
                ?? $tags['tourism']
                ?? $tags['office']
                ?? $tags['public_transport']
                ?? $tags['building']
                ?? null;

            $meta = self::GROUP_MAP[$rawTag] ?? ['other', $rawTag ?: 'POI', 'place', '#7F8C8D'];

            $out[] = [
                'id'    => 'p' . $idx++,
                'name'  => $name,
                'lat'   => (float) $pLat,
                'lon'   => (float) $pLon,
                'group' => $meta[0],
                'gLabel'=> $meta[1],
                'icon'  => $meta[2],
                'color' => $meta[3],
                'tag'   => $rawTag,
                'dist'  => self::haversineMeters($centerLat, $centerLon, (float) $pLat, (float) $pLon),
            ];
        }

        usort($out, fn ($a, $b) => $a['dist'] <=> $b['dist']);

        if ($limit !== null) {
            $out = array_slice($out, 0, $limit);
        }

        return $out;
    }

    /**
     * Group projected POIs by group key, ordered by count desc.
     *
     * @param  list<array> $projected  Output of self::project()
     * @return array<string, list<array>>
     */
    public static function groupByCategory(array $projected): array
    {
        $grouped = [];
        foreach ($projected as $p) {
            $grouped[$p['group']][] = $p;
        }
        uksort($grouped, fn ($a, $b) => count($grouped[$b]) <=> count($grouped[$a]));
        return $grouped;
    }

    private static function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return (int) round(2 * $R * asin(sqrt($a)));
    }
}
