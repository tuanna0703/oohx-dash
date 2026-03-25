<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

/**
 * php artisan sites:geocode [--owner=] [--all] [--dry-run]
 *
 * Cập nhật province_id, commune_id, address, city, region
 * cho các sites có lat/lon từ OpenStreetMap Nominatim.
 *
 * Rate limit: 1 request/giây theo yêu cầu Nominatim.
 */
class GeocodeSitesCommand extends Command
{
    protected $signature = 'sites:geocode
                            {--owner= : Chỉ geocode cho owner_id cụ thể}
                            {--all    : Geocode lại tất cả (kể cả đã có province_id)}
                            {--dry-run: Hiển thị kết quả mà không lưu}';

    protected $description = 'Reverse geocode lat/lon để cập nhật tỉnh/thành, phường/xã cho sites';

    public function handle(GeocodingService $geocoding): int
    {
        $query = Site::whereNotNull('lat')->whereNotNull('lon');

        if (! $this->option('all')) {
            $query->whereNull('province_id');
        }

        if ($owner = $this->option('owner')) {
            $query->where('owner_id', $owner);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('Không có site nào cần geocode.');
            return self::SUCCESS;
        }

        $dryRun  = $this->option('dry-run');
        $updated = 0;
        $failed  = 0;

        $this->info("Geocoding {$total} site(s)" . ($dryRun ? ' [dry-run]' : '') . '...');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(50, function ($sites) use ($geocoding, $dryRun, &$updated, &$failed, $bar) {
            foreach ($sites as $site) {
                $result = $geocoding->resolveLocation((float) $site->lat, (float) $site->lon);

                if (empty($result)) {
                    $failed++;
                    $bar->advance();
                    sleep(1); // rate limit
                    continue;
                }

                if ($dryRun) {
                    $this->newLine();
                    $this->line("  [{$site->external_id}] {$site->name}");
                    $this->line('    → ' . json_encode($result, JSON_UNESCAPED_UNICODE));
                } else {
                    $site->update($result);
                    $updated++;
                }

                $bar->advance();
                sleep(1); // Nominatim rate limit: 1 req/sec
            }
        });

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run hoàn tất. {$total} site(s) sẽ được cập nhật.");
        } else {
            $this->info("Hoàn tất: {$updated} updated, {$failed} failed.");
        }

        return self::SUCCESS;
    }
}
