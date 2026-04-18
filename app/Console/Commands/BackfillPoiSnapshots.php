<?php

namespace App\Console\Commands;

use App\Models\PoiSnapshot;
use App\Models\Screen;
use App\Services\PoiContextEnricher;
use Illuminate\Console\Command;

/**
 * Backfill poi_snapshots — re-fetch OSM POI cho các screens đã enrich (có audience_profile)
 * nhưng chưa có snapshot trong DB.
 *
 * Use case:
 *   - Sau khi migrate cache → DB, các screens cũ đã enrich nhưng cache đã bị flush
 *   - Hoặc khi POI snapshot expire → muốn refresh proactively
 *
 * Examples:
 *   php artisan oohx:backfill-poi-snapshots --dry-run
 *   php artisan oohx:backfill-poi-snapshots
 *   php artisan oohx:backfill-poi-snapshots --limit=10
 *   php artisan oohx:backfill-poi-snapshots --refresh-expired
 */
class BackfillPoiSnapshots extends Command
{
    protected $signature = 'oohx:backfill-poi-snapshots
                            {--limit=0 : Giới hạn N screens (0 = không giới hạn)}
                            {--radius=500 : Bán kính OSM query}
                            {--sleep=2 : Delay giây giữa các Overpass calls}
                            {--refresh-expired : Refresh cả snapshot đã expire}
                            {--dry-run : Chỉ liệt kê screens, không fetch}';

    protected $description = 'Backfill OSM POI snapshots cho screens có audience_profile mà thiếu data.';

    public function handle(PoiContextEnricher $enricher): int
    {
        $radius = (int) $this->option('radius');
        $sleep  = (int) $this->option('sleep');
        $limit  = (int) $this->option('limit');

        // Screens đã enrich (có audience_profile) + có lat/lon
        $screens = Screen::query()
            ->whereNotNull('audience_profile')
            ->whereHas('site', fn ($q) => $q->whereNotNull('lat')->whereNotNull('lon')
                ->where('lat', '!=', 0)->where('lon', '!=', 0))
            ->with('site')
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get();

        // Filter: chỉ giữ screens chưa có fresh snapshot
        $needsBackfill = $screens->filter(function ($s) use ($radius) {
            $snap = PoiSnapshot::freshFor((float) $s->site->lat, (float) $s->site->lon, $radius, 'osm')->first();
            if (! $snap) return true;
            return $this->option('refresh-expired') && $snap->expires_at && $snap->expires_at->isPast();
        });

        $total = $needsBackfill->count();

        if ($total === 0) {
            $this->info('Tất cả screens đã enrich đều có snapshot fresh. Không có gì cần backfill.');
            return self::SUCCESS;
        }

        $this->info("📦 Sẽ backfill {$total} screens (radius {$radius}m)");
        $this->line('   Estimate: ~' . gmdate('H:i:s', $total * ($sleep + 3)));

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Name', 'Lat/Lon'], $needsBackfill->map(fn ($s) => [
                $s->id, $s->name, "{$s->site->lat}, {$s->site->lon}"
            ])->all());
            return self::SUCCESS;
        }

        if (! $this->confirm("Tiếp tục backfill {$total} screens?", true)) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% · %message%');
        $bar->setMessage('starting…');
        $bar->start();

        $ok = 0;
        $fail = 0;

        foreach ($needsBackfill as $screen) {
            $bar->setMessage(mb_strimwidth($screen->name, 0, 50, '…'));
            try {
                $enricher->fetchPoisOnly(
                    (float) $screen->site->lat,
                    (float) $screen->site->lon,
                    $radius,
                );
                $ok++;
                $bar->setMessage('✔ ' . mb_strimwidth($screen->name, 0, 50, '…'));
            } catch (\Throwable $e) {
                $fail++;
                $bar->setMessage('✗ ' . mb_strimwidth($screen->name, 0, 50, '…'));
                $this->newLine();
                $this->warn("  {$screen->id}: {$e->getMessage()}");
            }
            $bar->advance();
            if ($sleep > 0) sleep($sleep);
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Value'], [
            ['Total processed', $total],
            ['✔ Backfilled',     $ok],
            ['✗ Failed',         $fail],
        ]);

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
