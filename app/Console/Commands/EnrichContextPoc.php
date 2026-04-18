<?php

namespace App\Console\Commands;

use App\Models\Screen;
use App\Services\PoiContextEnricher;
use Illuminate\Console\Command;

/**
 * POC / batch CLI — Enrich Inventory Intelligence cho 1 hoặc N screens.
 *
 * Chạy:
 *   php artisan oohx:enrich-context-poc {screen_id}                 # 1 screen, dry-run
 *   php artisan oohx:enrich-context-poc --lat=21.008 --lon=105.841  # ad-hoc lat/lon
 *   php artisan oohx:enrich-context-poc {screen_id} --apply         # ghi DB
 *
 * Output: in console JSON. Default KHÔNG ghi DB (preview mode).
 */
class EnrichContextPoc extends Command
{
    protected $signature = 'oohx:enrich-context-poc
                            {screen? : Screen ID}
                            {--lat= : Latitude (nếu không có screen)}
                            {--lon= : Longitude (nếu không có screen)}
                            {--name= : Site name for prompt context}
                            {--radius=500 : Bán kính query OSM (meters)}
                            {--apply : Ghi kết quả AI vào DB (default = preview only)}
                            {--save-raw= : Save raw response to file}';

    protected $description = 'POC: enrich screen context từ OSM + Claude Haiku';

    public function handle(PoiContextEnricher $enricher): int
    {
        $radius = (int) $this->option('radius');
        $screenId = $this->argument('screen');

        try {
            if ($screenId) {
                $screen = Screen::with('site')->find($screenId);
                if (! $screen) {
                    $this->error("Screen {$screenId} not found");
                    return self::FAILURE;
                }
                $this->info("📍 {$screen->name}");
                $this->line('   ' . trim(($screen->site?->address ?? '') . ', ' . ($screen->site?->city ?? ''), ', '));
                $this->line("   lat={$screen->site?->lat} lon={$screen->site?->lon} radius={$radius}m");
                $this->newLine();

                $this->info('Running enrichment pipeline (OSM → AI)...');
                $result = $enricher->enrichScreen($screen, $radius);
            } else {
                $lat = (float) $this->option('lat');
                $lon = (float) $this->option('lon');
                if (! $lat || ! $lon) {
                    $this->error('Cần screen ID hoặc --lat + --lon');
                    return self::FAILURE;
                }
                $name = $this->option('name') ?? 'Ad-hoc site';
                $this->info("📍 {$name}");
                $this->line("   lat={$lat} lon={$lon} radius={$radius}m");
                $this->newLine();

                $this->info('Running enrichment pipeline (OSM → AI)...');
                $result = $enricher->enrichByCoords($lat, $lon, $name, '', '', $radius);
            }
        } catch (\Throwable $e) {
            $this->error('Enrichment failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── Print POI summary ────────────────────────────────────────
        $features = $result['features'];
        $this->line('   → ' . $features['total_pois'] . ' POIs / ' . count($features['categories']) . ' categories');

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

        // ── Save raw if requested ────────────────────────────────────
        if ($savePath = $this->option('save-raw')) {
            file_put_contents($savePath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("   → raw saved to {$savePath}");
        }

        // ── AI output ────────────────────────────────────────────────
        $meta = $result['meta'];
        $this->newLine();
        $this->line(sprintf(
            '<fg=cyan>AI:</> %d in / %d out tokens · cost ≈ $%s · latency %dms',
            $meta['tokens_in'], $meta['tokens_out'], number_format($meta['cost_usd'], 4), $meta['latency_ms']
        ));

        if (! $meta['has_api_key']) {
            $this->error('ANTHROPIC_API_KEY chưa có trong .env. Bỏ qua AI inference.');
            return self::FAILURE;
        }

        $ai = $result['ai'];
        if (! $ai) {
            $this->warn('⚠ AI output không parse được JSON. Skip apply.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->line('<fg=cyan>══════════ AI INFERENCE OUTPUT ══════════</>');
        $this->line(json_encode($ai, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // ── Apply to DB ──────────────────────────────────────────────
        if ($this->option('apply') && isset($screen)) {
            $enricher->applyToScreen($screen, $result);
            $this->newLine();
            $this->info("✔ Applied to screen {$screen->id}");
        } elseif ($this->option('apply')) {
            $this->warn('--apply only valid with screen ID, not ad-hoc lat/lon');
        } else {
            $this->newLine();
            $this->comment('PREVIEW only. Dùng --apply để ghi DB.');
        }

        return self::SUCCESS;
    }
}
