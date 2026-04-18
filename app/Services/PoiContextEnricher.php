<?php

namespace App\Services;

use App\Models\Screen;
use Illuminate\Support\Facades\Http;

/**
 * Enrich Inventory Intelligence cho 1 screen từ OSM POI + Claude Haiku inference.
 *
 * Pipeline:
 *   1. OSM Overpass query  → POIs trong bán kính
 *   2. Categorize + aggregate → features
 *   3. Claude Haiku inference → audience/time/nearby JSON
 *
 * Stateless service. Caller (Command hoặc Filament Action) tự quyết save DB.
 */
class PoiContextEnricher
{
    /** Cost per million tokens (Claude Haiku 4.5). */
    private const COST_IN_PER_M  = 1.0;
    private const COST_OUT_PER_M = 5.0;

    private const OVERPASS_ENDPOINT = 'https://overpass-api.de/api/interpreter';
    private const ANTHROPIC_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_MODEL = 'claude-haiku-4-5-20251001';

    /**
     * Enrich 1 screen. Returns full result, KHÔNG ghi DB.
     *
     * @return array{
     *   location: array,
     *   pois: array,
     *   features: array,
     *   ai: ?array,
     *   meta: array
     * }
     */
    public function enrichScreen(Screen $screen, int $radius = 500): array
    {
        $name    = $screen->name;
        $address = trim(($screen->site?->address ?? '') . ', ' . ($screen->site?->city ?? ''), ', ');
        $province = $screen->site?->city ?? '';
        $lat = (float) ($screen->site?->lat ?? 0);
        $lon = (float) ($screen->site?->lon ?? 0);

        if (! $lat || ! $lon) {
            throw new \InvalidArgumentException("Screen {$screen->id} không có lat/lon");
        }

        return $this->enrichByCoords($lat, $lon, $name, $address, $province, $radius);
    }

    public function enrichByCoords(
        float $lat,
        float $lon,
        string $name,
        string $address = '',
        string $province = '',
        int $radius = 500
    ): array {
        $startedAt = microtime(true);

        $pois     = $this->queryOverpass($lat, $lon, $radius);
        $features = $this->aggregate($pois, $lat, $lon);

        $apiKey = env('ANTHROPIC_API_KEY');
        $aiResult = null;
        $tokensIn = 0;
        $tokensOut = 0;

        if ($apiKey) {
            $prompt = $this->buildPrompt($features, $name, $address, $province);
            [$rawText, $tokensIn, $tokensOut] = $this->callClaude($apiKey, $prompt);
            $aiResult = $rawText ? $this->extractJson($rawText) : null;
        }

        $cost = ($tokensIn / 1_000_000) * self::COST_IN_PER_M
              + ($tokensOut / 1_000_000) * self::COST_OUT_PER_M;

        return [
            'location' => compact('lat', 'lon', 'name', 'address', 'province', 'radius'),
            'pois'     => $pois,
            'features' => $features,
            'ai'       => $aiResult,
            'meta'     => [
                'tokens_in'   => $tokensIn,
                'tokens_out'  => $tokensOut,
                'cost_usd'    => round($cost, 5),
                'latency_ms'  => (int) round((microtime(true) - $startedAt) * 1000),
                'has_api_key' => (bool) $apiKey,
                'enriched_at' => now()->toIso8601String(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // OSM Overpass
    // ─────────────────────────────────────────────────────────────────
    private function queryOverpass(float $lat, float $lon, int $radius): array
    {
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
            ->post(self::OVERPASS_ENDPOINT, ['data' => $query]);

        if (! $response->successful()) {
            throw new \RuntimeException('OSM Overpass error: HTTP ' . $response->status());
        }

        return $response->json('elements') ?? [];
    }

    // ─────────────────────────────────────────────────────────────────
    // Aggregation
    // ─────────────────────────────────────────────────────────────────
    private function categoryMap(): array
    {
        return [
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
    }

    private function aggregate(array $pois, float $centerLat, float $centerLon): array
    {
        $categoryMap = $this->categoryMap();
        $categories = [];
        $named = [];

        foreach ($pois as $poi) {
            $tags = $poi['tags'] ?? [];
            $name = $tags['name'] ?? $tags['name:vi'] ?? null;

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

            if ($name) {
                $named[] = [
                    'name'     => $name,
                    'category' => $cat,
                    'tag'      => $rawTag,
                    'dist_m'   => $dist ? (int) round($dist) : null,
                ];
            }
        }

        usort($named, fn ($a, $b) => ($a['dist_m'] ?? PHP_INT_MAX) <=> ($b['dist_m'] ?? PHP_INT_MAX));
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
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $R * asin(sqrt($a));
    }

    // ─────────────────────────────────────────────────────────────────
    // Prompt
    // ─────────────────────────────────────────────────────────────────
    private function buildPrompt(array $features, string $name, string $address, string $province): string
    {
        $catLines = [];
        foreach ($features['categories'] as $cat => $n) {
            $catLines[] = "- {$cat}: {$n}";
        }
        $catBlock = implode("\n", $catLines) ?: '(none)';

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
    {"category": "<F&B|Banking|Auto|Fashion|Tech|Auto|...>", "score": <0-10>, "reason": "<short>"}
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
    // Anthropic
    // ─────────────────────────────────────────────────────────────────
    private function callClaude(string $apiKey, string $prompt): array
    {
        $response = Http::timeout(60)
            ->withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post(self::ANTHROPIC_ENDPOINT, [
                'model'      => self::ANTHROPIC_MODEL,
                'max_tokens' => 2048,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic API error: HTTP ' . $response->status() . ' — ' . $response->body());
        }

        $body = $response->json();
        $text = $body['content'][0]['text'] ?? '';
        $usage = $body['usage'] ?? [];

        return [
            $text,
            (int) ($usage['input_tokens']  ?? 0),
            (int) ($usage['output_tokens'] ?? 0),
        ];
    }

    private function extractJson(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) return $decoded;
        }

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // Apply enrichment to screen + save
    // ─────────────────────────────────────────────────────────────────
    /**
     * Map AI output sang screen columns + save.
     * Set methodology note minh bạch là AI inference từ OSM.
     */
    public function applyToScreen(Screen $screen, array $enrichResult): void
    {
        $ai = $enrichResult['ai'] ?? null;
        if (! $ai) {
            throw new \RuntimeException('Không có AI output để apply');
        }

        $audience = $ai['audience_profile'] ?? null;
        $time     = $ai['time_performance'] ?? null;
        $nearby   = $ai['nearby_context'] ?? null;

        // Build nearby_context JSON khớp schema Phase 1
        $nearbyCtx = null;
        if ($nearby) {
            $nearbyCtx = [
                'brands'     => $nearby['anchor_brands'] ?? [],
                'landmarks'  => [], // anchor_brands đã gồm cả landmark trong AI output
                'highlights' => $nearby['highlights'] ?? '',
            ];
        }

        $note = sprintf(
            'AI inference từ %d POI OpenStreetMap (bán kính %dm), Claude Haiku %s. Confidence: %s.',
            $enrichResult['features']['total_pois'] ?? 0,
            $enrichResult['location']['radius'] ?? 500,
            now()->format('Y-m-d'),
            $ai['confidence'] ?? 'medium'
        );

        $screen->fill([
            'audience_profile'         => $audience,
            'time_performance'         => $time,
            'nearby_context'           => $nearbyCtx,
            'traffic_methodology_note' => $note,
        ])->saveQuietly();
    }
}
