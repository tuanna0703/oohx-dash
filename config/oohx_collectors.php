<?php

/**
 * OOHX Data Engine — Collectors metadata (Phase 2.C).
 *
 * Source of truth cho Overview Page UI (cards, trigger buttons, staleness badges).
 * Khi Phase 2.D ship 2 collector mới (osm_roads, worldpop_population), chỉ cần
 * thêm entry ở dưới — UI tự generate card mới, không code thay đổi.
 *
 * Fields:
 *   - display_name, description, provider, cost, rate_limit — UI labels
 *   - cadence_hours: chu kỳ chạy (dùng tính staleness)
 *   - supports_city: dropdown city có cho phép override?
 *   - supports_bbox: advanced bbox input có hiển thị?
 *   - cache_ttl_hours: informational — collector tự quản cache
 *   - expected_runtime_seconds: hint cho trigger confirm modal
 *   - icon: heroicon hoặc emoji cho card header
 *   - color: Filament color name cho card accent
 */

return [

    'overpass_poi' => [
        'display_name'  => 'OpenStreetMap POI (Overpass API)',
        'description'   => 'Fetch POI nodes by category within city bbox. '
                         . 'Upsert into source.pois by osm_id.',
        'provider'      => 'Overpass API',
        'cost'          => 'free',
        'rate_limit'    => '10,000 queries/day per IP',
        'cadence_hours' => 168, // weekly
        'supports_city' => true,
        'supports_bbox' => true,
        'cache_ttl_hours' => 24,
        'expected_runtime_seconds' => 90,
        'icon'          => 'heroicon-o-map-pin',
        'color'         => 'primary',
    ],

    'open_meteo_weather' => [
        'display_name'  => 'Weather snapshot (Open-Meteo)',
        'description'   => 'Current + hourly weather for city centroid. '
                         . 'Insert into source.weather_snapshots.',
        'provider'      => 'Open-Meteo',
        'cost'          => 'free (no API key)',
        'rate_limit'    => '10,000 calls/day',
        'cadence_hours' => 6,
        'supports_city' => true,
        'supports_bbox' => false,
        'cache_ttl_hours' => 0, // never cache weather
        'expected_runtime_seconds' => 3,
        'icon'          => 'heroicon-o-cloud',
        'color'         => 'info',
    ],

    // ── Placeholder cho Phase 2.D (chưa ship) ────────────────────────
    // 'osm_roads' => [
    //     'display_name' => 'OSM Roads (osm2pgsql)',
    //     'cadence_hours' => 720, // monthly
    //     ...
    // ],
    // 'worldpop_population' => [
    //     'display_name' => 'WorldPop Population (raster → H3)',
    //     'cadence_hours' => 8760, // yearly
    //     ...
    // ],

    // ── Cities built-in (handoff §2.5) — có default bbox/centroid bên Python ──
    // City khác collector vẫn chấp nhận nhưng fallback bbox từ AVG screen lat/lon;
    // Laravel UI validate có ≥1 active screen trước khi trigger.
    'builtin_cities' => [
        'Hanoi',
        'HCMC',
        'Da Nang',
        'Hai Phong',
        'Can Tho',
        'Ninh Bình',
    ],
];
