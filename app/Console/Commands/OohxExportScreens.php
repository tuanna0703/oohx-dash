<?php

namespace App\Console\Commands;

use App\Models\Screen;
use Illuminate\Console\Command;

/**
 * Export active screens từ Laravel → JSON payload cho OOHX Data Engine.
 *
 * Output: JSON array, mỗi screen có các field theo schema của ingest-screens.
 * REQUIRED fields: external_id (uuid), indoor_outdoor, lat, lon.
 *
 * Chỉ export screens có:
 *   - active = true
 *   - deleted_at = null (soft delete respected)
 *   - site.lat / site.lon hợp lệ (khác null, khác 0)
 */
class OohxExportScreens extends Command
{
    protected $signature   = 'oohx:export-screens
                              {--out=storage/app/oohx/screens.json : Relative path to write JSON}';
    protected $description = 'Export active screens → JSON cho Data Engine ingest.';

    /**
     * Laravel placement_zone → Data Engine zone_type.
     *   DE accepts: entrance, checkout, escalator, food_court, cinema_corridor,
     *               inside_aisle, roadside, facade
     *   Laravel placement_zone: entrance, checkout, escalator, food_court,
     *                            facade, lobby, parking, other
     */
    private const ZONE_MAP = [
        'entrance'   => 'entrance',
        'checkout'   => 'checkout',
        'escalator'  => 'escalator',
        'food_court' => 'food_court',
        'facade'     => 'facade',
        'lobby'      => 'entrance',    // lobby ≈ entrance flow
        'parking'    => 'roadside',    // parking ≈ outdoor traffic
    ];

    /**
     * Normalize city name cho Data Engine config.
     * DE config có key cụ thể: Hanoi, HCMC, Ho Chi Minh City, Danang, ...
     */
    private const CITY_MAP = [
        'Hà Nội'              => 'Hanoi',
        'Ha Noi'              => 'Hanoi',
        'hanoi'               => 'Hanoi',
        'Hồ Chí Minh'         => 'HCMC',
        'TP. Hồ Chí Minh'     => 'HCMC',
        'TPHCM'               => 'HCMC',
        'Ho Chi Minh'         => 'HCMC',
        'Ho Chi Minh City'    => 'HCMC',
        'Saigon'              => 'HCMC',
        'Đà Nẵng'             => 'Danang',
        'Da Nang'             => 'Danang',
    ];

    public function handle(): int
    {
        $out = $this->option('out');
        $abs = base_path($out);
        @mkdir(dirname($abs), 0755, true);

        $query = Screen::query()
            ->where('active', true)
            ->whereHas('site', fn ($q) => $q->whereNotNull('lat')->whereNotNull('lon')
                ->where('lat', '!=', 0)->where('lon', '!=', 0))
            ->with(['site', 'owner', 'spec', 'inventory.vnCategory']);

        $total = $query->count();
        $this->info("📦 Exporting {$total} active screens → {$out}");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $payload = [];
        $query->chunkById(500, function ($chunk) use (&$payload, $bar) {
            foreach ($chunk as $screen) {
                $payload[] = $this->projectScreen($screen);
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine();

        file_put_contents($abs, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info("✔ Wrote " . count($payload) . " screens to {$out}");
        $this->line("   File size: " . number_format(filesize($abs) / 1024, 1) . " KB");

        return self::SUCCESS;
    }

    /**
     * Project 1 Screen → payload dict theo schema Data Engine.
     */
    private function projectScreen(Screen $s): array
    {
        $site = $s->site;
        $spec = $s->spec;
        $cat  = $s->inventory?->vnCategory;

        $zoneType       = self::ZONE_MAP[$s->placement_zone] ?? null;
        $indoorOutdoor  = $this->deriveIndoorOutdoor($s, $cat);
        $screenSize     = $this->deriveScreenSize($spec);
        $city           = $this->normalizeCity($site?->city);

        return array_filter([
            // REQUIRED
            'external_id'       => $s->uuid,
            'indoor_outdoor'    => $indoorOutdoor,
            'lat'               => (float) $site->lat,
            'lon'               => (float) $site->lon,

            // OPTIONAL (skip null via array_filter)
            'name'              => $s->name,
            'media_owner_name'  => $s->owner?->name,
            'venue_name'        => $site?->name,
            'venue_type'        => $cat?->slug,
            'zone_type'         => $zoneType,
            'screen_type'       => 'LED', // default — chưa có trong schema Laravel
            'screen_size'       => $screenSize,
            'orientation'       => $s->orientation,
            'city'              => $city,
            'district'          => $site?->region,
            'ward'              => null, // có thể mở rộng nếu sites.commune_id populated
            'address'           => $site?->address,
            'status'            => $s->active ? 'active' : 'inactive',
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Derive indoor/outdoor từ placement_zone + venue category.
     * Default 'outdoor' nếu không xác định được.
     */
    private function deriveIndoorOutdoor(Screen $s, $cat): string
    {
        $zone = $s->placement_zone;

        // Placement zones chỉ xuất hiện indoor:
        if (in_array($zone, ['entrance', 'checkout', 'escalator', 'food_court', 'lobby'], true)) {
            return 'indoor';
        }
        // Facade/parking typically outdoor
        if (in_array($zone, ['facade', 'parking'], true)) {
            return 'outdoor';
        }

        // Fallback theo venue category slug
        $slug = $cat?->slug ?? '';
        $indoorSlugs = ['mall', 'mall-dept-store', 'airport', 'office-building', 'hospital',
                        'supermarket', 'convenience-store', 'gym', 'cinema', 'restaurant',
                        'cafe', 'university', 'school'];
        foreach ($indoorSlugs as $needle) {
            if (str_contains($slug, $needle)) return 'indoor';
        }

        return 'outdoor';
    }

    private function deriveScreenSize($spec): ?string
    {
        if (! $spec) return null;
        $w = (float) ($spec->width_cm ?? 0);
        $h = (float) ($spec->height_cm ?? 0);
        if ($w <= 0 || $h <= 0) return null;

        return round($w / 100, 1) . 'x' . round($h / 100, 1) . 'm';
    }

    private function normalizeCity(?string $city): ?string
    {
        if (! $city) return null;
        $trim = trim($city);
        return self::CITY_MAP[$trim] ?? $trim;
    }
}
