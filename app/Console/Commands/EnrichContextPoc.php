<?php

namespace App\Console\Commands;

use App\Models\Screen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * POC — Enrich Inventory Intelligence cho 1 screen từ OSM POI + Claude Haiku.
 *
 * Chạy:
 *   php artisan oohx:enrich-context-poc {screen_id}
 *   php artisan oohx:enrich-context-poc --lat=21.008 --lon=105.841 --name="Test site"
 *
 * Output: in-console JSON, KHÔNG ghi DB. Dành cho validate quality trước khi scale.
 */
class EnrichContextPoc extends Command
{
    protected $signature = 'oohx:enrich-context-poc
                            {screen? : Screen ID (optional, ưu tiên hơn lat/lon)}
                            {--lat= : Latitude (nếu không có screen)}
                            {--lon= : Longitude (nếu không có screen)}
                            {--name= : Site name for prompt context}
                            {--radius=500 : Bán kính query OSM (meters)}
                            {--save-raw= : Save raw response to file path}';

    protected $description = 'POC: enrich screen context từ OSM + Claude Haiku';

    public function handle(): int
    {
        // ── Resolve location ─────────────────────────────────────────
        [$lat, $lon, $name, $address, $province] = $this->resolveLocation();
        if (! $lat || ! $lon) {
            $this->error('Cần screen ID hoặc --lat + --lon');
            return self::FAILURE;
        }

        $radius = (int) $this->option('radius');
        $this->info("📍 {$name}");
        $this->line("   {$address}");
        $this->line("   lat={$lat} lon={$lon} radius={$radius}m");
        $this->newLine();

        // ── 1. Fetch POIs from OSM Overpass ──────────────────────────
        $this->info('1/3 Querying OSM Overpass...');
        $pois = $this->queryOverpass($lat, $lon, $radius);
        $this->line('   → ' . count($pois) . ' POIs found');

        // ── 2. Aggregate features ────────────────────────────────────
        $this->info('2/3 Aggregating features...');
        $features = $this->aggregate($pois, $lat, $lon);
        $this->line('   → categories: ' . count($features['categories']));
        $this->line('   → named POIs: ' . count($features['named']));

        // Print human summary for manual sanity check
        $this->printFeatureSummary($features);

        // Save raw if requested
        if ($savePath = $this->option('save-raw')) {
            file_put_contents($savePath, json_encode([
                'location' => compact('lat', 'lon', 'name', 'address', 'province'),
                'pois'     => $pois,
                'features' => $features,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("   → raw saved to {$savePath}");
        }

        // ── 3. Send to Claude Haiku ──────────────────────────────────
        $this->newLine();
        $this->info('3/3 Calling Claude Haiku for inference...');
        $apiKey = env('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            $this->error('ANTHROPIC_API_KEY chưa có trong .env. Thêm vào rồi chạy lại.');
            return self::FAILURE;
        }

        $prompt = $this->buildPrompt($features, $name, $address, $province);
        $this->line('   → prompt length: ' . number_format(strlen($prompt)) . ' chars');

        $response = $this->callClaude($apiKey, $prompt);
        if (! $response) {
            return self::FAILURE;
        }

        // ── Output ───────────────────────────────────────────────────
        $this->newLine();
        $this->line('<fg=cyan>══════════ AI INFERENCE OUTPUT ══════════</>');
        $this->line($response);
        $this->newLine();

        $parsed = $this->extractJson($response);
        if ($parsed) {
            $this->info('✔ Output valid JSON');
            $this->newLine();
            $this->line('<fg=yellow>Parsed structure:</>');
            $this->line(json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->warn('⚠ Output không parse được thành JSON. Cần adjust prompt.');
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────
    // Location resolver
    // ─────────────────────────────────────────────────────────────────
    private function resolveLocation(): array
    {
        if ($screenId = $this->argument('screen')) {
            $screen = Screen::with('site.commune.province')->find($screenId);
            if (! $screen) {
                $this->error("Screen {$screenId} not found");
                return [null, null, null, null, null];
            }
            return [
                (float) $screen->site?->lat,
                (float) $screen->site?->lon,
                $screen->name,
                trim(($screen->site?->address ?? '') . ', ' . ($screen->site?->city ?? '')),
                $screen->site?->city ?? '',
            ];
        }

        return [
            (float) $this->option('lat'),
            (float) $this->option('lon'),
            $this->option('name') ?? 'Unnamed site',
            '',
            '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // OSM Overpass query
    // ─────────────────────────────────────────────────────────────────
    private function queryOverpass(float $lat, float $lon, int $radius): array
    {
        // Query: tất cả node có amenity, shop, leisure, tourism, office, building
        // (bao gồm cả way để bắt building lớn, nhưng giới hạn để response không phình)
        $query = <<<OQL
[out:json][timeout:30];
(
  node["amenity"](around:{$radius},{$lat},{$lon});
  node["shop"](around:{$radius},{$lat},{$lon});
  node["leisure"](around:{$radius},{$lat},{$lon});
  node["tourism"](around:{$radius},{$lat},{$lon});
  node["office"](around:{$radius},{$lat},{$lon});
  node["public_transport"](around:{$radius},{$lat},{$lon});
  way["amenity"~"^(mall|marketplace|hospital|university|school|college)$"](around:{$radius},{$lat},{$lon});
  way["building"~"^(commercial|retail|office|hotel|hospital|school|university|mall)$"](around:{$radius},{$lat},{$lon});
);
out tags center 200;
OQL;

        $response = Http::timeout(40)
            ->asForm()
            ->post('https://overpass-api.de/api/interpreter', ['data' => $query]);

        if (! $response->successful()) {
            $this->error('OSM Overpass error: HTTP ' . $response->status());
            return [];
        }

        return $response->json('elements') ?? [];
    }

    // ─────────────────────────────────────────────────────────────────
    // Feature aggregation
    // ─────────────────────────────────────────────────────────────────
    private function aggregate(array $pois, float $centerLat, float $centerLon): array
    {
        $categoryMap = [
            'amenity' => [
                'cafe' => 'cafe', 'restaurant' => 'restaurant', 'fast_food' => 'fast_food',
                'bar' => 'bar', 'pub' => 'bar', 'food_court' => 'restaurant',
                'school' => 'school', 'university' => 'university', 'college' => 'university',
                'kindergarten' => 'school', 'language_school' => 'school',
                'hospital' => 'hospital', 'clinic' => 'clinic', 'pharmacy' => 'pharmacy', 'doctors' => 'clinic',
                'bank' => 'bank', 'atm' => 'atm',
                'fuel' => 'fuel', 'parking' => 'parking',
                'cinema' => 'entertainment', 'theatre' => 'entertainment', 'nightclub' => 'entertainment',
                'place_of_worship' => 'religion',
                'marketplace' => 'market',
                'bus_station' => 'transit', 'taxi' => 'transit',
                'gym' => 'fitness', 'fitness_centre' => 'fitness',
                'hotel' => 'hotel',
            ],
            'shop' => [
                'supermarket' => 'supermarket', 'convenience' => 'convenience',
                'mall' => 'mall', 'department_store' => 'mall',
                'clothes' => 'fashion', 'shoes' => 'fashion', 'jewelry' => 'fashion',
                'electronics' => 'electronics', 'mobile_phone' => 'electronics', 'computer' => 'electronics',
                'beauty' => 'beauty', 'hairdresser' => 'beauty', 'cosmetics' => 'beauty',
                'bakery' => 'food_retail', 'butcher' => 'food_retail', 'greengrocer' => 'food_retail',
                'optician' => 'health_retail',
                'bicycle' => 'sports', 'sports' => 'sports',
                'books' => 'books', 'stationery' => 'books',
                'toys' => 'kids', 'baby_goods' => 'kids',
                'car' => 'auto', 'car_repair' => 'auto', 'motorcycle' => 'auto',
            ],
            'leisure' => [
                'park' => 'park', 'garden' => 'park', 'playground' => 'park',
                'sports_centre' => 'sports_venue', 'stadium' => 'sports_venue', 'pitch' => 'sports_venue',
                'swimming_pool' => 'sports_venue',
            ],
            'tourism' => [
                'hotel' => 'hotel', 'guest_house' => 'hotel', 'hostel' => 'hotel', 'apartment' => 'hotel',
                'attraction' => 'attraction', 'museum' => 'attraction', 'gallery' => 'attraction',
            ],
            'office' => [
                'company' => 'office', 'coworking' => 'office', 'government' => 'gov_office',
                'estate_agent' => 'office', 'lawyer' => 'office', 'accountant' => 'office',
            ],
            'public_transport' => [
                'station' => 'transit', 'stop_position' => 'transit', 'platform' => 'transit',
            ],
            'building' => [
                'commercial' => 'commercial_bldg', 'retail' => 'commercial_bldg',
                'office' => 'office_bldg',
                'hotel' => 'hotel', 'hospital' => 'hospital',
                'school' => 'school', 'university' => 'university',
                'mall' => 'mall',
            ],
        ];

        $categories = [];
        $named      = [];

        foreach ($pois as $poi) {
            $tags = $poi['tags'] ?? [];
            $name = $tags['name'] ?? $tags['name:vi'] ?? null;

            // Distance
            $pLat = $poi['lat'] ?? ($poi['center']['lat'] ?? null);
            $pLon = $poi['lon'] ?? ($poi['center']['lon'] ?? null);
            $dist = ($pLat && $pLon) ? $this->haversine($centerLat, $centerLon, $pLat, $pLon) : null;

            $cat = null;
            $rawTag = null;
            foreach ($categoryMap as $tagKey => $valueMap) {
                if (isset($tags[$tagKey])) {
                    $rawTag = $tags[$tagKey];
                    if (isset($valueMap[$rawTag])) {
                        $cat = $valueMap[$rawTag];
                        break;
                    }
                }
            }
            if (! $cat) continue;

            $categories[$cat] = ($categories[$cat] ?? 0) + 1;

            // Track named POIs (signal cao hơn anonymous count)
            if ($name) {
                $named[] = [
                    'name'     => $name,
                    'category' => $cat,
                    'tag'      => $rawTag,
                    'dist_m'   => $dist ? (int) round($dist) : null,
                ];
            }
        }

        // Sort named by distance
        usort($named, fn ($a, $b) => ($a['dist_m'] ?? PHP_INT_MAX) <=> ($b['dist_m'] ?? PHP_INT_MAX));

        // Cap named to top 30
        $named = array_slice($named, 0, 30);

        arsort($categories);

        return [
            'total_pois' => array_sum($categories),
            'categories' => $categories,
            'named'      => $named,
        ];
    }

    private function haversine($lat1, $lon1, $lat2, $lon2): float
    {
        $R = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    private function printFeatureSummary(array $features): void
    {
        $this->newLine();
        $this->line('<fg=yellow>POI categories (top 15):</>');
        $rows = [];
        foreach (array_slice($features['categories'], 0, 15, true) as $cat => $n) {
            $rows[] = [$cat, $n];
        }
        $this->table(['Category', 'Count'], $rows);

        if (! empty($features['named'])) {
            $this->line('<fg=yellow>Named POIs (top 10 nearest):</>');
            $rows = [];
            foreach (array_slice($features['named'], 0, 10) as $p) {
                $rows[] = [$p['name'], $p['category'], $p['dist_m'] . 'm'];
            }
            $this->table(['Name', 'Category', 'Distance'], $rows);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Prompt builder
    // ─────────────────────────────────────────────────────────────────
    private function buildPrompt(array $features, string $name, string $address, string $province): string
    {
        $catLines = [];
        foreach ($features['categories'] as $cat => $n) {
            $catLines[] = "- {$cat}: {$n}";
        }
        $catBlock = implode("\n", $catLines);

        $namedLines = [];
        foreach (array_slice($features['named'], 0, 20) as $p) {
            $namedLines[] = "- {$p['name']} ({$p['category']}, ~{$p['dist_m']}m)";
        }
        $namedBlock = $namedLines ? implode("\n", $namedLines) : '(none with names)';

        return <<<PROMPT
Bạn là chuyên gia phân tích context OOH/DOOH tại Việt Nam, hiểu rõ urban demographics & consumer behavior từng vùng đô thị.

Cho biển quảng cáo tại:
- Tên: {$name}
- Địa chỉ: {$address}
- Tỉnh/TP: {$province}

POI trong bán kính 500m (từ OpenStreetMap):

Tổng: {$features['total_pois']} POIs
Categories:
{$catBlock}

Named POIs gần nhất:
{$namedBlock}

Dựa vào pattern POI trên, suy luận audience profile + behavior. Chú ý đặc thù VN:
- F&B chains (Highlands, Phúc Long, Starbucks) = signal middle-class urban
- Trà sữa nhiều = Gen Z dominant
- Office buildings + bank cluster = white-collar daytime
- School/university = student/parent traffic theo lịch học
- Hospital cluster = mixed audience, dwell time cao
- Mall/supermarket = family weekend peak
- Transit = commuter morning/evening peak

Trả về CHỈ MỘT JSON object (không markdown fence, không text khác), schema chính xác:

{
  "audience_profile": {
    "male_pct": <int 0-100>,
    "female_pct": <int 0-100>,
    "age_18_24_pct": <int 0-100>,
    "age_25_34_pct": <int 0-100>,
    "age_35_44_pct": <int 0-100>,
    "age_45_plus_pct": <int 0-100>,
    "income_tier": "<low|mid|high>",
    "lifestyle_tags": [<3-5 short tags tiếng Việt>],
    "source_note": "<1 câu giải thích từ POI nào suy ra>"
  },
  "time_performance": {
    "peak_hour_start": "<HH:MM>",
    "peak_hour_end": "<HH:MM>",
    "best_day": "<mon|tue|wed|thu|fri|sat|sun>",
    "morning_pct": <int 0-100>,
    "afternoon_pct": <int 0-100>,
    "evening_pct": <int 0-100>,
    "rationale": "<1 câu giải thích peak từ POI nào>"
  },
  "advertiser_fit": [
    {"category": "<F&B|Banking|Auto|...>", "score": <0-10>, "reason": "<short>"}
  ],
  "nearby_context": {
    "highlights": "<2-3 câu marketing copy về vị trí này, tiếng Việt natural>",
    "anchor_brands": [<top 5 brand/landmark mạnh nhất>]
  },
  "confidence": "<low|medium|high>",
  "uncertainty_notes": [<1-3 điểm chưa chắc cần verify>]
}

QUAN TRỌNG: % age tổng = 100, % gender tổng = 100, % time-of-day tổng = 100. Không có markdown, không có text trước/sau JSON.
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────
    // Anthropic API call
    // ─────────────────────────────────────────────────────────────────
    private function callClaude(string $apiKey, string $prompt): ?string
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 2048,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            $this->error('Anthropic API error: HTTP ' . $response->status());
            $this->line($response->body());
            return null;
        }

        $body = $response->json();
        $text = $body['content'][0]['text'] ?? null;

        // Log usage
        if ($usage = $body['usage'] ?? null) {
            $inTokens  = $usage['input_tokens']  ?? 0;
            $outTokens = $usage['output_tokens'] ?? 0;
            // Haiku 4.5: $1/M input, $5/M output
            $cost = ($inTokens / 1_000_000) * 1.0 + ($outTokens / 1_000_000) * 5.0;
            $this->line('   → tokens: ' . $inTokens . ' in / ' . $outTokens . ' out · cost ≈ $' . number_format($cost, 4));
        }

        return $text;
    }

    // ─────────────────────────────────────────────────────────────────
    // JSON extraction (handles markdown fence + extra text)
    // ─────────────────────────────────────────────────────────────────
    private function extractJson(string $text): ?array
    {
        // Try direct parse
        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        // Strip markdown fence
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) return $decoded;
        }

        // Fallback: find first { to last }
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }
}
