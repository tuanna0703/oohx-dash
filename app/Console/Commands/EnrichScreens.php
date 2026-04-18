<?php

namespace App\Console\Commands;

use App\Models\Screen;
use App\Services\PoiContextEnricher;
use Illuminate\Console\Command;

/**
 * Batch enrichment — chạy AI POI inference cho nhiều screens 1 lượt.
 *
 * Default behavior:
 *   - Scan tất cả active screens có lat/lon
 *   - Skip screens đã có audience_profile (tránh ghi đè không cần)
 *   - Sleep 2s giữa các Anthropic calls (rate-limit safe)
 *   - Progress bar + summary cost
 *
 * Examples:
 *   php artisan oohx:enrich-screens --dry-run
 *   php artisan oohx:enrich-screens --limit=5
 *   php artisan oohx:enrich-screens --city="Hà Nội"
 *   php artisan oohx:enrich-screens --vn-category=mall --force
 *   php artisan oohx:enrich-screens --screen=01kpabn30scbvzp4s5774p6z05
 */
class EnrichScreens extends Command
{
    protected $signature = 'oohx:enrich-screens
                            {--screen= : Chạy cho 1 screen ID cụ thể}
                            {--city= : Filter theo city name (LIKE %...%)}
                            {--vn-category= : Filter theo venue category slug}
                            {--limit=0 : Giới hạn N screens (0 = không giới hạn)}
                            {--radius=500 : Bán kính query OSM (meters)}
                            {--sleep=2 : Delay giây giữa các Anthropic calls}
                            {--force : Ghi đè cả screen đã có audience_profile}
                            {--dry-run : Chỉ liệt kê screens, không gọi API}';

    protected $description = 'Batch enrich AI Context cho screens (POI + audience inference).';

    public function handle(PoiContextEnricher $enricher): int
    {
        $startedAt = microtime(true);

        // ── Build query ──────────────────────────────────────────────
        $query = Screen::query()
            ->where('active', true)
            ->whereHas('site', fn ($q) => $q->whereNotNull('lat')->whereNotNull('lon')
                ->where('lat', '!=', 0)->where('lon', '!=', 0))
            ->with(['site', 'inventory.vnCategory']);

        if ($id = $this->option('screen')) {
            $query->where('id', $id);
        }
        if ($city = $this->option('city')) {
            $query->whereHas('site', fn ($q) => $q->where('city', 'LIKE', "%{$city}%"));
        }
        if ($catSlug = $this->option('vn-category')) {
            $query->whereHas('inventory.vnCategory', fn ($q) => $q->where('slug', $catSlug));
        }
        if (! $this->option('force')) {
            $query->whereNull('audience_profile');
        }
        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $screens = $query->get();
        $total = $screens->count();

        if ($total === 0) {
            $this->warn('Không có screens match filter. Dùng --force để ghi đè screens đã enrich.');
            return self::SUCCESS;
        }

        // ── Pre-flight check ─────────────────────────────────────────
        $hasKey = (bool) config('services.anthropic.key');
        if (! $hasKey && ! $this->option('dry-run')) {
            $this->error('ANTHROPIC_API_KEY chưa có trong .env. Add rồi chạy lại.');
            return self::FAILURE;
        }

        $sleep = (int) $this->option('sleep');
        $estCost = $total * 0.005;
        $estTime = $total * 30 + $total * $sleep;

        $this->info("📊 Sẽ enrich {$total} screens");
        $this->line("   Radius: {$this->option('radius')}m");
        $this->line("   Sleep giữa calls: {$sleep}s");
        $this->line('   Estimate: ~' . gmdate('H:i:s', $estTime) . " · cost ≈ \${$estCost}");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->table(
                ['Screen', 'City', 'Category', 'Lat,Lon'],
                $screens->take(20)->map(fn ($s) => [
                    substr($s->name, 0, 40),
                    $s->site?->city,
                    $s->inventory?->vnCategory?->slug,
                    $s->site?->lat . ',' . $s->site?->lon,
                ])->toArray()
            );
            if ($total > 20) {
                $this->line("... và " . ($total - 20) . " screens khác.");
            }
            $this->newLine();
            $this->warn('DRY RUN — không có API call. Bỏ --dry-run để chạy thật.');
            return self::SUCCESS;
        }

        if (! $this->confirm("Tiếp tục enrich {$total} screens?", true)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        // ── Run ──────────────────────────────────────────────────────
        $stats = ['ok' => 0, 'ai_failed' => 0, 'osm_failed' => 0, 'cost' => 0.0];
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% · %message%');
        $bar->setMessage('starting');
        $bar->start();

        foreach ($screens as $screen) {
            $bar->setMessage(substr($screen->name, 0, 35));
            $bar->display();

            try {
                $result = $enricher->enrichScreen($screen, (int) $this->option('radius'));
                $stats['cost'] += $result['meta']['cost_usd'] ?? 0;

                if (empty($result['ai'])) {
                    $stats['ai_failed']++;
                    $bar->setMessage('AI parse failed: ' . substr($screen->name, 0, 30));
                } else {
                    $enricher->applyToScreen($screen, $result);
                    $stats['ok']++;
                    $bar->setMessage('✔ ' . substr($screen->name, 0, 35));
                }
            } catch (\Throwable $e) {
                $stats['osm_failed']++;
                $bar->setMessage('✗ ' . substr($e->getMessage(), 0, 50));
                // Log failure cho debug
                logger()->warning('enrich-screens failed', [
                    'screen_id' => $screen->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();

            // Sleep giữa calls để respect rate limit (skip cuối)
            if ($sleep > 0 && ! $screen->is($screens->last())) {
                sleep($sleep);
            }
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary ──────────────────────────────────────────────────
        $duration = (int) (microtime(true) - $startedAt);
        $this->table(['Metric', 'Value'], [
            ['Total processed',   $total],
            ['✔ Applied',         $stats['ok']],
            ['⚠ AI parse failed', $stats['ai_failed']],
            ['✗ OSM/API failed',  $stats['osm_failed']],
            ['Total cost',        '$' . number_format($stats['cost'], 4)],
            ['Total time',        gmdate('H:i:s', $duration)],
            ['Avg per screen',    $total > 0 ? round($duration / $total, 1) . 's' : 'N/A'],
        ]);

        if ($stats['osm_failed'] > 0) {
            $this->warn('Có lỗi — check storage/logs/laravel.log để debug.');
        }

        return self::SUCCESS;
    }
}
