<?php

namespace App\Console\Commands;

use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

/**
 * Fetch 3321 phường/xã/thị trấn từ API 34tinhthanh.com.
 * (Dữ liệu sau cải cách hành chính 01/07/2025)
 *
 * Yêu cầu: đã seed 34 tỉnh trước (VietnamAdminDivisions2025Seeder)
 *
 * Cú pháp:
 *   php artisan vietnam:fetch                  # fetch communes
 *   php artisan vietnam:fetch --only=provinces # chỉ fetch/sync tỉnh từ API
 *   php artisan vietnam:fetch --only=communes  # chỉ fetch phường/xã
 *   php artisan vietnam:fetch --fresh          # xóa communes cũ, fetch lại
 */
class FetchVietnamAdminDivisions extends Command
{
    protected $signature = 'vietnam:fetch
        {--only=communes  : all | provinces | communes}
        {--fresh          : Xóa communes cũ trước khi fetch}
        {--delay=200      : Milliseconds chờ giữa mỗi tỉnh}';

    protected $description = 'Fetch 3321 phường/xã/thị trấn 2025 từ 34tinhthanh.com';

    private const BASE  = 'https://34tinhthanh.com/api';
    private const RETRY = 3;

    // ─────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $only  = $this->option('only');
        $fresh = $this->option('fresh');
        $delay = (int) $this->option('delay');

        $this->info('🌐 Nguồn: https://34tinhthanh.com (dữ liệu 34 tỉnh sau 01/07/2025)');
        $this->newLine();

        if ($fresh) {
            $this->warn('⚠  --fresh: xóa toàn bộ phường/xã cũ...');
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            VietnamCommune::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        if (in_array($only, ['all', 'provinces'])) {
            $this->syncProvinces();
        }

        if (in_array($only, ['all', 'communes'])) {
            $this->fetchCommunes($delay);
        }

        $this->newLine();
        $this->info('✅ Hoàn thành!');
        return 0;
    }

    // ── Sync provinces từ API (optional, để verify) ───────────────────────────

    private function syncProvinces(): void
    {
        $this->info('🔄 Sync tỉnh/thành từ API...');

        $res = $this->apiGet(self::BASE . '/provinces');
        if (! $res) return;

        $list = $res->json();
        $ok   = 0;

        foreach ($list as $p) {
            $code = str_pad((string) $p['province_code'], 2, '0', STR_PAD_LEFT);
            $name = preg_replace('/^(Thành phố|Tỉnh)\s+/u', '', $p['name']);

            // Chỉ update name/full_name nếu province đã tồn tại (không ghi đè region_id)
            VietnamProvince::where('code', $code)->update([
                'name'      => $name,
                'full_name' => $p['name'],
            ]);
            $ok++;
        }

        $this->info("  → Synced {$ok} tỉnh/thành.");
    }

    // ── Fetch communes ────────────────────────────────────────────────────────

    private function fetchCommunes(int $delay): void
    {
        $provinces = VietnamProvince::orderBy('code')->get();

        if ($provinces->isEmpty()) {
            $this->error('Chưa có dữ liệu tỉnh/thành!');
            $this->error('Chạy: php artisan db:seed --class=VietnamAdminDivisions2025Seeder');
            return;
        }

        $this->info('📦 Fetching 3321 phường/xã/thị trấn (' . $provinces->count() . ' tỉnh)...');
        $this->line("  Delay {$delay}ms/tỉnh. Ước tính: ~" . ceil($provinces->count() * ($delay + 1500) / 60000) . " phút.");
        $this->newLine();

        $totalOk      = 0;
        $totalSkipped = 0;

        foreach ($provinces as $province) {
            $code = str_pad((string) $province->code, 2, '0', STR_PAD_LEFT);

            $this->line("  [{$code}] {$province->name}...", null, 'v');
            $this->output->write("  [{$code}] {$province->name} ");

            $res = $this->apiGet(self::BASE . '/wards?province_code=' . $code);

            if (! $res) {
                $this->line('<fg=red>✗ lỗi kết nối</>');
                $totalSkipped++;
                continue;
            }

            $wards   = $res->json();
            $records = [];

            foreach ($wards as $w) {
                $wardCode = str_pad((string) ($w['ward_code'] ?? ''), 5, '0', STR_PAD_LEFT);
                $fullName = trim($w['ward_name'] ?? '');
                if (! $wardCode || ! $fullName) continue;

                // Xác định type từ tên hoặc place_type
                $type = $this->resolveType($fullName, $w['place_type'] ?? '');

                // Tên ngắn: bỏ prefix
                $name = preg_replace('/^(Phường|Xã|Thị trấn|Thị xã)\s+/u', '', $fullName);

                $records[] = [
                    'code'        => $wardCode,
                    'name'        => $name,
                    'full_name'   => $fullName,
                    'type'        => $type,
                    'province_id' => $province->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }

            $count = count($records);

            if ($count > 0) {
                foreach (array_chunk($records, 200) as $chunk) {
                    VietnamCommune::upsert(
                        $chunk,
                        ['code'],
                        ['name', 'full_name', 'type', 'province_id', 'updated_at']
                    );
                }
                $this->line("<fg=green>✓ {$count} phường/xã</>");
                $totalOk += $count;
            } else {
                $this->line('<fg=yellow>0 (không có dữ liệu)</>');
            }

            if ($delay > 0) usleep($delay * 1000);
        }

        $this->newLine();
        $this->info("  → Tổng cộng {$totalOk} phường/xã/thị trấn đã lưu.");
        if ($totalSkipped > 0) {
            $this->warn("  → {$totalSkipped} tỉnh bị lỗi kết nối (chạy lại lệnh để thử tiếp).");
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function apiGet(string $url)
    {
        for ($i = 0; $i < self::RETRY; $i++) {
            try {
                $res = Http::timeout(15)->get($url);
                if ($res->ok()) return $res;
                if ($res->status() === 429) {
                    $this->warn("\n  Rate limit — chờ 10 giây...");
                    sleep(10);
                }
            } catch (\Throwable $e) {
                if ($i < self::RETRY - 1) usleep(2000000); // 2s
            }
        }
        return null;
    }

    private function resolveType(string $fullName, string $placeType): string
    {
        $lower = mb_strtolower($fullName);
        if (str_starts_with($lower, 'phường'))    return 'phuong';
        if (str_starts_with($lower, 'thị trấn'))  return 'thi_tran';
        if (str_starts_with($lower, 'xã'))         return 'xa';

        // Fallback: city center → phường, others → xã
        $pt = mb_strtolower($placeType ?? '');
        if (str_contains($pt, 'thành phố') || str_contains($pt, 'trung ương')) return 'phuong';

        return 'xa';
    }
}
