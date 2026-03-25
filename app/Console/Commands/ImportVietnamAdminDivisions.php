<?php

namespace App\Console\Commands;

use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import dữ liệu địa giới hành chính Việt Nam từ file Excel/CSV.
 *
 * Cú pháp:
 *   php artisan vietnam:import --type=provinces --file=storage/app/provinces.xlsx
 *   php artisan vietnam:import --type=communes  --file=storage/app/communes.xlsx
 *
 * ── Định dạng Excel tỉnh/thành (provinces) ────────────────────────────────
 *   Cột A: code      (VD: 01)
 *   Cột B: name      (VD: Hà Nội)
 *   Cột C: full_name (VD: Thành phố Hà Nội)
 *   Cột D: type      (tinh | thanh_pho)
 *   Cột E: region    (VD: Đồng bằng sông Hồng)
 *
 * ── Định dạng Excel phường/xã (communes) ──────────────────────────────────
 *   Cột A: code          (VD: 00001)
 *   Cột B: name          (VD: Phúc Xá)
 *   Cột C: full_name     (VD: Phường Phúc Xá)
 *   Cột D: type          (xa | phuong | thi_tran)
 *   Cột E: province_code (VD: 01)
 *
 * Dòng đầu tiên là header, sẽ bị bỏ qua.
 */
class ImportVietnamAdminDivisions extends Command
{
    protected $signature = 'vietnam:import
        {--type=provinces : provinces | communes}
        {--file=          : Đường dẫn tới file Excel/CSV}
        {--fresh          : Xóa dữ liệu cũ trước khi import}';

    protected $description = 'Import dữ liệu địa giới hành chính Việt Nam từ Excel';

    public function handle(): int
    {
        $type = $this->option('type');
        $file = $this->option('file');

        if (! in_array($type, ['provinces', 'communes'])) {
            $this->error('--type phải là "provinces" hoặc "communes"');
            return 1;
        }

        if (! $file || ! file_exists($file)) {
            $this->error("File không tồn tại: {$file}");
            $this->line('  Ví dụ: php artisan vietnam:import --type=provinces --file=storage/app/provinces.xlsx');
            return 1;
        }

        if ($this->option('fresh')) {
            if ($type === 'communes') {
                $this->warn('Đang xóa toàn bộ phường/xã cũ...');
                VietnamCommune::truncate();
            } else {
                $this->warn('Đang xóa toàn bộ tỉnh/thành cũ (kéo theo phường/xã)...');
                VietnamCommune::truncate();
                VietnamProvince::truncate();
            }
        }

        $this->info("Đang đọc file: {$file}");

        try {
            $spreadsheet = IOFactory::load($file);
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            $this->error('Không đọc được file: ' . $e->getMessage());
            return 1;
        }

        // Bỏ hàng header
        array_shift($rows);

        return match ($type) {
            'provinces' => $this->importProvinces($rows),
            'communes'  => $this->importCommunes($rows),
        };
    }

    // ── Provinces ─────────────────────────────────────────────────────────────

    private function importProvinces(array $rows): int
    {
        $bar     = $this->output->createProgressBar(count($rows));
        $ok      = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            [$code, $name, $full_name, $type, $region] = array_pad(array_values($row), 5, null);

            $code      = trim((string) ($code ?? ''));
            $name      = trim((string) ($name ?? ''));
            $full_name = trim((string) ($full_name ?? ''));
            $type      = trim((string) ($type ?? 'tinh'));
            $region    = trim((string) ($region ?? ''));

            if (! $code || ! $name) { $skipped++; $bar->advance(); continue; }

            if (! in_array($type, ['tinh', 'thanh_pho'])) $type = 'tinh';

            VietnamProvince::updateOrCreate(
                ['code' => $code],
                compact('name', 'full_name', 'type', 'region')
            );

            $ok++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ Import xong: {$ok} tỉnh/thành (bỏ qua: {$skipped})");
        return 0;
    }

    // ── Communes ──────────────────────────────────────────────────────────────

    private function importCommunes(array $rows): int
    {
        // Cache province code → id
        $provinceMap = VietnamProvince::pluck('id', 'code')->toArray();

        if (empty($provinceMap)) {
            $this->error('Chưa có dữ liệu tỉnh/thành. Chạy import provinces trước.');
            return 1;
        }

        $bar     = $this->output->createProgressBar(count($rows));
        $ok      = 0;
        $skipped = 0;
        $chunk   = [];

        foreach ($rows as $i => $row) {
            [$code, $name, $full_name, $type, $province_code] = array_pad(array_values($row), 5, null);

            $code          = trim((string) ($code ?? ''));
            $name          = trim((string) ($name ?? ''));
            $full_name     = trim((string) ($full_name ?? ''));
            $type          = trim((string) ($type ?? 'xa'));
            $province_code = trim((string) ($province_code ?? ''));

            if (! $code || ! $name || ! isset($provinceMap[$province_code])) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if (! in_array($type, ['xa', 'phuong', 'thi_tran'])) $type = 'xa';

            $chunk[] = [
                'code'        => $code,
                'name'        => $name,
                'full_name'   => $full_name,
                'type'        => $type,
                'province_id' => $provinceMap[$province_code],
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
            $ok++;

            // Upsert theo chunk 500
            if (count($chunk) >= 500) {
                VietnamCommune::upsert($chunk, ['code'], ['name', 'full_name', 'type', 'province_id', 'updated_at']);
                $chunk = [];
            }

            $bar->advance();
        }

        if (! empty($chunk)) {
            VietnamCommune::upsert($chunk, ['code'], ['name', 'full_name', 'type', 'province_id', 'updated_at']);
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ Import xong: {$ok} phường/xã (bỏ qua: {$skipped})");
        return 0;
    }
}
