<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixIoPricing extends Command
{
    protected $signature = 'oohx:fix-io-pricing {--dry-run : Show count without modifying data}';
    protected $description = 'Move floor_cpm → io_rate for inventory rows imported with pricing_model=io but values placed in CPM column';

    public function handle(): int
    {
        $base = DB::table('screen_inventory')
            ->where('pricing_model', 'io')
            ->whereNull('io_rate')
            ->where('floor_cpm', '>', 0);

        $count = (clone $base)->count();
        $this->info("Found {$count} rows with pricing_model=io, io_rate=NULL, floor_cpm>0");

        if ($count === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $samples = (clone $base)->limit(5)->get(['screen_id', 'floor_cpm', 'floor_cpm_currency', 'io_rate_unit']);
            $this->table(['screen_id', 'floor_cpm', 'currency', 'io_rate_unit'], $samples->map(fn ($r) => (array) $r)->toArray());
            $this->warn('DRY RUN — no changes written. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($base) {
            (clone $base)->update([
                'io_rate'   => DB::raw('floor_cpm'),
                'floor_cpm' => null,
            ]);
        });

        $this->info("Migrated {$count} rows: floor_cpm → io_rate, floor_cpm set NULL");
        return self::SUCCESS;
    }
}
