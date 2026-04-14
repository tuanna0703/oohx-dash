<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillSlugs extends Command
{
    protected $signature = 'oohx:backfill-slugs';
    protected $description = 'Backfill missing slugs for screens and sites';

    public function handle(): int
    {
        $this->backfillTable('screens');
        $this->backfillTable('sites');

        return self::SUCCESS;
    }

    private function backfillTable(string $table): void
    {
        $count = DB::table($table)->whereNull('slug')->count();
        $this->info("Backfilling {$count} {$table} with NULL slug...");

        $bar = $this->output->createProgressBar($count);

        DB::table($table)->whereNull('slug')->orderBy('id')->each(function ($row) use ($table, $bar) {
            $base = Str::slug($row->name, '-', 'vi') ?: $table . '-' . Str::random(6);
            $base = Str::limit($base, 120, '');
            $slug = $base;
            $n = 1;

            while (DB::table($table)->where('slug', $slug)->where('id', '!=', $row->id)->exists()) {
                $n++;
                $slug = $base . '-' . $n;
            }

            DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done: {$table}");
    }
}
