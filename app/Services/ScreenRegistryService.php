<?php

namespace App\Services;

use App\Models\Owner;
use App\Models\Screen;
use App\Models\ScreenExternalId;
use App\Models\ScreenImpressionMultiplier;
use App\Models\ScreenInventory;
use App\Models\ScreenSpec;
use App\Models\Site;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ScreenRegistryService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Column map constants — tách riêng để dễ maintain
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Hivestack original format (TrueView_Update_New_Inventory.xlsx)
     * Sheet "2b. Screen List", data bắt đầu row index 4 (row 5 Excel)
     */
    private const COL_HIVESTACK = [
        'external_id'        => 0,
        'uuid'               => 1,
        'name'               => 2,
        'internal_notes'     => 3,
        'site_external_id'   => 4,
        'width_px'           => 5,
        'height_px'          => 6,
        'width_cm'           => 7,
        'height_cm'          => 8,
        'facing_direction'   => 9,
        'photo_url'          => 10,
        'lat'                => 11,
        'lon'                => 12,
        'network_name'       => 13,
        'venue_type'         => 14,
        'allow_image'        => 15,
        'allow_video'        => 16,
        'allow_html'         => 17,
        'allow_zip'          => 18,
        'spot_length'        => 19,
        'max_spot_length'    => 20,
        'min_spot_length'    => 21,
        'dwell_time'         => 22,
        'weekly_impressions' => 23,
        'floor_cpm'          => 24,
        'hours_col_start'    => 25,   // Mon Open, Mon Close, Tue Open ... Sun Close
        'header_row_offset'  => 4,    // skip rows 0-3, data từ index 4
    ];

    /**
     * AdTRUE SSP template format
     * Sheet "Danh Sach Man Hinh", data bắt đầu row index 4 (row 5 Excel)
     * Bỏ: UUID(1), facing_direction(9), photo_url(10), ZIP(18) so với Hivestack
     */
    private const COL_ADTRUE = [
        'external_id'        => 0,
        'name'               => 1,   // ← AdTRUE không có UUID riêng
        'internal_notes'     => 2,
        'network_name'       => 3,
        'site_external_id'   => 4,
        'lat'                => 5,
        'lon'                => 6,
        'width_px'           => 7,
        'height_px'          => 8,
        'width_cm'           => 9,
        'height_cm'          => 10,
        'venue_type'         => 11,
        'allow_video'        => 12,
        'allow_image'        => 13,
        'allow_html'         => 14,
        'spot_length'        => 15,
        'max_spot_length'    => 16,
        'min_spot_length'    => 17,
        'weekly_impressions' => 18,
        'floor_cpm'          => 19,
        'hours_col_start'    => 20,   // Mon Open, Mon Close, Tue Open ... Sun Close
        'header_row_offset'  => 4,    // skip rows 0-3, data từ index 4
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Single screen registration
    // ─────────────────────────────────────────────────────────────────────────

    public function registerScreen(array $data, string $ownerId): Screen
    {
        $site = Site::where('owner_id', $ownerId)
                    ->where('external_id', $data['site_external_id'])
                    ->firstOrFail();

        $screen = Screen::updateOrCreate(
            ['owner_id' => $ownerId, 'external_id' => $data['external_id']],
            [
                'site_id'          => $site->id,
                'owner_id'         => $ownerId,
                'uuid'             => $data['uuid'] ?? (string) Str::uuid(),
                'name'             => $data['name'],
                'description'      => $data['description'] ?? null,
                'internal_notes'   => $data['internal_notes'] ?? null,
                'site_external_id' => $data['site_external_id'],
                'player_type'      => $data['player_type'] ?? 'adtrue_android',
                'active'           => true,
            ]
        );

        if (! empty($data['spec'])) {
            $this->saveSpec($screen, $data['spec']);
        }

        if (! empty($data['inventory'])) {
            $this->saveInventory($screen, $data['inventory']);
        }

        if (! empty($data['multiplier_string'])) {
            $this->saveMultipliersFromString($screen, $data['multiplier_string']);
        } elseif (! empty($data['multipliers'])) {
            $this->saveMultipliers($screen, $data['multipliers']);
        }

        return $screen->load(['spec', 'inventory', 'site']);
    }

    public function saveSpec(Screen $screen, array $spec): ScreenSpec
    {
        return ScreenSpec::updateOrCreate(
            ['screen_id' => $screen->id],
            [
                'width_px'          => $spec['width_px'],
                'height_px'         => $spec['height_px'],
                'width_cm'          => $spec['width_cm'] ?? null,
                'height_cm'         => $spec['height_cm'] ?? null,
                'facing_direction'  => $spec['facing_direction'] ?? null,
                'photo_url'         => $spec['photo_url'] ?? null,
                'allow_image'       => $spec['allow_image'] ?? true,
                'allow_video'       => $spec['allow_video'] ?? true,
                'allow_html'        => $spec['allow_html'] ?? false,
                'allow_zip'         => $spec['allow_zip'] ?? false,
                'allow_vast'        => $spec['allow_vast'] ?? true,
                'allowed_languages' => $spec['allowed_languages'] ?? null,
            ]
        );
    }

    public function saveInventory(Screen $screen, array $inv): ScreenInventory
    {
        $networkId = $inv['network_id'] ?? null;
        if (! $networkId && ! empty($inv['network_name'])) {
            $network   = $screen->owner->networks()
                                ->where('name', $inv['network_name'])
                                ->first();
            $networkId = $network?->id;
        }

        $floorCpmUsd = null;
        if (! empty($inv['floor_cpm'])) {
            $currency    = $inv['floor_cpm_currency'] ?? 'VND';
            $floorCpmUsd = $currency === 'USD'
                ? (float) $inv['floor_cpm']
                : round($inv['floor_cpm'] / ($inv['exchange_rate'] ?? 25000), 4);
        }

        return ScreenInventory::updateOrCreate(
            ['screen_id' => $screen->id],
            [
                'network_id'             => $networkId,
                'network_name'           => $inv['network_name'] ?? null,
                'venue_type'             => $inv['venue_type'] ?? null,
                'spot_length'            => $inv['spot_length'] ?? 15,
                'max_spot_length'        => $inv['max_spot_length'] ?? 30,
                'min_spot_length'        => $inv['min_spot_length'] ?? 5,
                'loop_length'            => $inv['loop_length'] ?? null,
                'weekly_impressions'     => $inv['weekly_impressions'] ?? null,
                'floor_cpm'              => $inv['floor_cpm'] ?? null,
                'floor_cpm_currency'     => $inv['floor_cpm_currency'] ?? 'VND',
                'floor_cpm_usd'          => $floorCpmUsd,
                'operating_hours'        => $inv['operating_hours'] ?? null,
                'timezone'               => $inv['timezone'] ?? 'Asia/Ho_Chi_Minh',
                'programmatic_enabled'   => $inv['programmatic_enabled'] ?? false,
                'pmp_only'               => $inv['pmp_only'] ?? false,
                'share_of_voice_max_pct' => $inv['share_of_voice_max_pct'] ?? 100,
            ]
        );
    }

    public function saveMultipliersFromString(Screen $screen, string $hivestackStr): void
    {
        $rows = ScreenImpressionMultiplier::parseHivestackString($hivestackStr);
        $this->saveMultipliers($screen, $rows);
    }

    public function saveMultipliers(Screen $screen, array $rows): void
    {
        $screen->multipliers()->delete();

        $inserts = array_map(fn($r) => [
            'screen_id'   => $screen->id,
            'day_of_week' => $r['day_of_week'],
            'hour_of_day' => $r['hour_of_day'],
            'multiplier'  => $r['multiplier'] ?? 1.0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $rows);

        collect($inserts)->chunk(168)->each(fn($chunk) =>
            ScreenImpressionMultiplier::insert($chunk->toArray())
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Heartbeat
    // ─────────────────────────────────────────────────────────────────────────

    public function heartbeat(string $screenUuid): ?Screen
    {
        $screen = Screen::withoutGlobalScope('owner_scope')
                        ->where('uuid', $screenUuid)
                        ->first();

        if (! $screen) return null;

        $screen->update([
            'last_heartbeat_at' => now(),
            'status'            => 'online',
        ]);

        return $screen;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk import — auto-detect format
    // ─────────────────────────────────────────────────────────────────────────

    public function importHivestackXlsx(string $filePath, string $ownerId): array
    {
        $results     = ['sites' => 0, 'screens' => 0, 'errors' => [], 'format' => ''];
        $spreadsheet = IOFactory::load($filePath);

        // Auto-detect format bằng cách kiểm tra tên sheet
        $sheetNames = array_map(fn($s) => $s->getTitle(), $spreadsheet->getAllSheets());
        $isAdtrue   = in_array('Danh Sach Man Hinh', $sheetNames)
                   || in_array('Danh Sach Site', $sheetNames);

        $results['format'] = $isAdtrue ? 'adtrue' : 'hivestack';

        if ($isAdtrue) {
            $this->importSitesAdtrue($spreadsheet, $ownerId, $results);
            $this->importScreensAdtrue($spreadsheet, $ownerId, $results);
        } else {
            $this->importSites($spreadsheet, $ownerId, $results);
            $this->importScreens($spreadsheet, $ownerId, $results);
            $this->importMultipliersSheet($spreadsheet, $results);
        }

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hivestack format (original)
    // ─────────────────────────────────────────────────────────────────────────

    private function importSites($spreadsheet, string $ownerId, array &$r): void
    {
        $sheet = $spreadsheet->getSheetByName('1b. Site List');
        if (! $sheet) return;

        $rows = $sheet->toArray();
        foreach (array_slice($rows, 3) as $row) {
            if (empty($row[0])) continue;
            try {
                Site::updateOrCreate(
                    ['owner_id' => $ownerId, 'external_id' => $row[0]],
                    ['name' => $row[2], 'description' => $row[3] ?? null,
                     'lat'  => $row[4] ?? null, 'lon' => $row[5] ?? null]
                );
                $r['sites']++;
            } catch (\Throwable $e) {
                $r['errors'][] = "Site {$row[0]}: {$e->getMessage()}";
            }
        }
    }

    private function importScreens($spreadsheet, string $ownerId, array &$r): void
    {
        $sheet = $spreadsheet->getSheetByName('2b. Screen List');
        if (! $sheet) return;

        $c    = self::COL_HIVESTACK;
        $rows = $sheet->toArray();

        foreach (array_slice($rows, $c['header_row_offset']) as $row) {
            if (empty($row[$c['external_id']])) continue;
            try {
                $site = Site::withoutGlobalScope('owner_scope')
                            ->where('owner_id', $ownerId)
                            ->where('external_id', $row[$c['site_external_id']])
                            ->first();

                $screen = Screen::updateOrCreate(
                    ['owner_id' => $ownerId, 'external_id' => $row[$c['external_id']]],
                    [
                        'site_id'          => $site?->id,
                        'owner_id'         => $ownerId,
                        'uuid'             => $row[$c['uuid']] ?? (string) Str::uuid(),
                        'name'             => $row[$c['name']],
                        'internal_notes'   => $row[$c['internal_notes']] ?? null,
                        'site_external_id' => $row[$c['site_external_id']] ?? null,
                        'active'           => true,
                    ]
                );

                $this->saveSpec($screen, [
                    'width_px'         => (int) ($row[$c['width_px']] ?? 1920),
                    'height_px'        => (int) ($row[$c['height_px']] ?? 1080),
                    'width_cm'         => $this->numericOrNull($row[$c['width_cm']] ?? null),
                    'height_cm'        => $this->numericOrNull($row[$c['height_cm']] ?? null),
                    'facing_direction'  => $row[$c['facing_direction']] ?? null,
                    'photo_url'        => $row[$c['photo_url']] ?? null,
                    'allow_image'      => $this->parseBool($row[$c['allow_image']] ?? true),
                    'allow_video'      => $this->parseBool($row[$c['allow_video']] ?? true),
                    'allow_html'       => $this->parseBool($row[$c['allow_html']] ?? false),
                    'allow_zip'        => $this->parseBool($row[$c['allow_zip']] ?? false),
                ]);

                $this->saveInventory($screen, [
                    'network_name'       => $row[$c['network_name']] ?? null,
                    'venue_type'         => $row[$c['venue_type']] ?? null,
                    'spot_length'        => (int) ($row[$c['spot_length']] ?? 15),
                    'max_spot_length'    => (int) ($row[$c['max_spot_length']] ?? 30),
                    'min_spot_length'    => (int) ($row[$c['min_spot_length']] ?? 5),
                    'loop_length'        => $this->numericOrNull($row[$c['dwell_time']] ?? null, 'int'),
                    'weekly_impressions' => $this->numericOrNull($row[$c['weekly_impressions']] ?? null, 'int'),
                    'floor_cpm'          => $this->numericOrNull($row[$c['floor_cpm']] ?? null),
                    'floor_cpm_currency' => 'VND',
                    'operating_hours'    => $this->parseOperatingHoursFromRow($row, $c['hours_col_start']),
                ]);

                $r['screens']++;
            } catch (\Throwable $e) {
                $r['errors'][] = "Screen {$row[$c['external_id']]}: {$e->getMessage()}";
            }
        }
    }

    private function importMultipliersSheet($spreadsheet, array &$r): void
    {
        // Sheet name bị truncate trong PhpSpreadsheet
        $sheet = $spreadsheet->getSheetByName('Impression Multipliers (Impress')
              ?? $spreadsheet->getSheetByName('Impression Multipliers');
        if (! $sheet) return;

        $rows = $sheet->toArray();
        foreach (array_slice($rows, 1) as $row) {
            $extId = $row[0] ?? null;
            if (empty($extId)) continue;

            $screen = Screen::withoutGlobalScope('owner_scope')
                            ->where('external_id', $extId)
                            ->first();
            if (! $screen) continue;

            // Col 1 là chuỗi comma-separated, cols 2-169 là giá trị tách sẵn
            $multiplierStr = $row[1] ?? null;
            if (! empty($multiplierStr) && str_contains((string) $multiplierStr, ',')) {
                // Dùng chuỗi col 1
                $this->saveMultipliersFromString($screen, (string) $multiplierStr);
            } else {
                // Dùng 168 cols tách sẵn (col 2 → col 169)
                $values = array_slice($row, 2, 168);
                $this->saveMultipliersFromString(
                    $screen,
                    implode(',', array_map(fn($v) => is_numeric($v) ? $v : 1, $values))
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AdTRUE SSP template format
    // ─────────────────────────────────────────────────────────────────────────

    private function importSitesAdtrue($spreadsheet, string $ownerId, array &$r): void
    {
        $sheet = $spreadsheet->getSheetByName('Danh Sach Site');
        if (! $sheet) return;

        $rows = $sheet->toArray();
        // Row 0: title, Row 1: headers, Row 2: descriptions → data từ row 3
        foreach (array_slice($rows, 3) as $row) {
            if (empty($row[0])) continue;
            // Skip note/summary rows
            if (str_starts_with((string) ($row[0] ?? ''), 'LUU Y')
             || str_starts_with((string) ($row[0] ?? ''), 'TONG')) continue;
            try {
                Site::updateOrCreate(
                    ['owner_id' => $ownerId, 'external_id' => $row[0]],
                    [
                        'name'        => $row[1] ?? $row[0],
                        'description' => $row[2] ?? null,
                        'lat'         => $this->numericOrNull($row[3] ?? null),
                        'lon'         => $this->numericOrNull($row[4] ?? null),
                    ]
                );
                $r['sites']++;
            } catch (\Throwable $e) {
                $r['errors'][] = "Site {$row[0]}: {$e->getMessage()}";
            }
        }
    }

    private function importScreensAdtrue($spreadsheet, string $ownerId, array &$r): void
    {
        $sheet = $spreadsheet->getSheetByName('Danh Sach Man Hinh');
        if (! $sheet) return;

        $c    = self::COL_ADTRUE;
        $rows = $sheet->toArray();

        // Row 0: title, Row 1: section bars, Row 2: headers, Row 3: descriptions
        // Row 4: note banner → data từ row 5 (index 5)
        foreach (array_slice($rows, 5) as $row) {
            if (empty($row[$c['external_id']])) continue;
            // Skip note/summary rows
            if (str_starts_with((string) ($row[0] ?? ''), 'LUU Y')
             || str_starts_with((string) ($row[0] ?? ''), 'TONG')) continue;

            try {
                $site = Site::withoutGlobalScope('owner_scope')
                            ->where('owner_id', $ownerId)
                            ->where('external_id', $row[$c['site_external_id']])
                            ->first();

                $screen = Screen::updateOrCreate(
                    ['owner_id' => $ownerId, 'external_id' => $row[$c['external_id']]],
                    [
                        'site_id'          => $site?->id,
                        'owner_id'         => $ownerId,
                        // AdTRUE không có UUID riêng → tự sinh
                        'uuid'             => (string) Str::uuid(),
                        'name'             => $row[$c['name']],
                        'internal_notes'   => $row[$c['internal_notes']] ?? null,
                        'site_external_id' => $row[$c['site_external_id']] ?? null,
                        'active'           => true,
                    ]
                );

                $this->saveSpec($screen, [
                    'width_px'    => (int) ($row[$c['width_px']] ?? 1920),
                    'height_px'   => (int) ($row[$c['height_px']] ?? 1080),
                    'width_cm'    => $this->numericOrNull($row[$c['width_cm']] ?? null),
                    'height_cm'   => $this->numericOrNull($row[$c['height_cm']] ?? null),
                    'allow_image' => $this->parseBool($row[$c['allow_image']] ?? true),
                    'allow_video' => $this->parseBool($row[$c['allow_video']] ?? true),
                    'allow_html'  => $this->parseBool($row[$c['allow_html']] ?? false),
                    'allow_zip'   => false,  // không có trong AdTRUE template
                ]);

                $this->saveInventory($screen, [
                    'network_name'       => $row[$c['network_name']] ?? null,
                    'venue_type'         => $row[$c['venue_type']] ?? null,
                    'spot_length'        => (int) ($row[$c['spot_length']] ?? 15),
                    'max_spot_length'    => (int) ($row[$c['max_spot_length']] ?? 30),
                    'min_spot_length'    => (int) ($row[$c['min_spot_length']] ?? 5),
                    'weekly_impressions' => $this->numericOrNull($row[$c['weekly_impressions']] ?? null, 'int'),
                    'floor_cpm'          => $this->numericOrNull($row[$c['floor_cpm']] ?? null),
                    'floor_cpm_currency' => 'VND',
                    'operating_hours'    => $this->parseOperatingHoursFromRow($row, $c['hours_col_start']),
                ]);

                $r['screens']++;
            } catch (\Throwable $e) {
                $r['errors'][] = "Screen {$row[$c['external_id']]}: {$e->getMessage()}";
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parse operating hours từ 14 cột liên tiếp bắt đầu từ $colStart
     * Thứ tự: Mon_Open, Mon_Close, Tue_Open, Tue_Close, ... Sun_Open, Sun_Close
     */
    private function parseOperatingHoursFromRow(array $row, int $colStart): array
    {
        $days  = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $hours = [];

        foreach ($days as $i => $day) {
            $open  = $row[$colStart + ($i * 2)]     ?? null;
            $close = $row[$colStart + ($i * 2) + 1] ?? null;

            if (empty($open) || strtolower(trim((string) $open)) === 'closed') {
                $hours[$day] = 'closed';
            } else {
                $hours[$day] = [
                    'open'  => substr(trim((string) $open), 0, 5),
                    'close' => substr(trim((string) $close), 0, 5),
                ];
            }
        }

        return $hours;
    }

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        return filter_var(trim((string) $value), FILTER_VALIDATE_BOOLEAN);
    }

    private function numericOrNull(mixed $value, string $cast = 'float'): float|int|null
    {
        if ($value === null || $value === '') return null;
        if (! is_numeric($value)) return null;
        return $cast === 'int' ? (int) $value : (float) $value;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ─────────────────────────────────────────────────────────────────────
    // Preview / Dry-run (không ghi DB)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Dry-run import: parse file, so sánh với DB, trả về preview data
     * KHÔNG ghi gì vào database.
     */
    public function previewImport(string $filePath, string $ownerId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheetNames  = array_map(fn($s) => $s->getTitle(), $spreadsheet->getAllSheets());
        $isAdtrue    = in_array('Danh Sach Man Hinh', $sheetNames)
                    || in_array('Danh Sach Site', $sheetNames);

        $preview = [
            'format'  => $isAdtrue ? 'adtrue' : 'hivestack',
            'sites'   => [],   // ['action'=>new|update|skip|error, 'data'=>[], 'error'=>'']
            'screens' => [],
            'summary' => [
                'sites_new'      => 0, 'sites_update'    => 0, 'sites_error'    => 0,
                'screens_new'    => 0, 'screens_update'  => 0, 'screens_error'  => 0,
            ],
        ];

        if ($isAdtrue) {
            $this->previewSitesAdtrue($spreadsheet, $ownerId, $preview);
            $this->previewScreensAdtrue($spreadsheet, $ownerId, $preview);
        } else {
            $this->previewSitesHivestack($spreadsheet, $ownerId, $preview);
            $this->previewScreensHivestack($spreadsheet, $ownerId, $preview);
        }

        // Tính summary
        foreach ($preview['sites'] as $s) {
            match ($s['action']) {
                'new'    => $preview['summary']['sites_new']++,
                'update' => $preview['summary']['sites_update']++,
                'error'  => $preview['summary']['sites_error']++,
                default  => null,
            };
        }
        foreach ($preview['screens'] as $s) {
            match ($s['action']) {
                'new'    => $preview['summary']['screens_new']++,
                'update' => $preview['summary']['screens_update']++,
                'error'  => $preview['summary']['screens_error']++,
                default  => null,
            };
        }

        return $preview;
    }

    private function previewSitesAdtrue($spreadsheet, string $ownerId, array &$preview): void
    {
        $sheet = $spreadsheet->getSheetByName('Danh Sach Site');
        if (! $sheet) return;

        foreach (array_slice($sheet->toArray(), 3) as $idx => $row) {
            $extId = trim((string) ($row[0] ?? ''));
            if (empty($extId)
             || str_starts_with($extId, 'LUU Y')
             || str_starts_with($extId, 'TONG')) continue;

            $parsed = [
                'external_id' => $extId,
                'name'        => $row[1] ?? $extId,
                'description' => $row[2] ?? null,
                'lat'         => $this->numericOrNull($row[3] ?? null),
                'lon'         => $this->numericOrNull($row[4] ?? null),
            ];

            $preview['sites'][] = $this->classifySite($parsed, $ownerId, $idx + 4);
        }
    }

    private function previewSitesHivestack($spreadsheet, string $ownerId, array &$preview): void
    {
        $sheet = $spreadsheet->getSheetByName('1b. Site List');
        if (! $sheet) return;

        foreach (array_slice($sheet->toArray(), 3) as $idx => $row) {
            $extId = trim((string) ($row[0] ?? ''));
            if (empty($extId)) continue;

            $parsed = [
                'external_id' => $extId,
                'name'        => $row[2] ?? $extId,
                'description' => $row[3] ?? null,
                'lat'         => $this->numericOrNull($row[4] ?? null),
                'lon'         => $this->numericOrNull($row[5] ?? null),
            ];

            $preview['sites'][] = $this->classifySite($parsed, $ownerId, $idx + 4);
        }
    }

    private function classifySite(array $parsed, string $ownerId, int $rowNum): array
    {
        $errors = $this->validateSite($parsed, $rowNum);
        if ($errors) {
            return ['action' => 'error', 'row' => $rowNum, 'data' => $parsed,
                    'error' => implode('; ', $errors), 'existing' => null];
        }

        $existing = Site::withoutGlobalScopes()
            ->where('owner_id', $ownerId)
            ->where('external_id', $parsed['external_id'])
            ->first(['id', 'name', 'lat', 'lon']);

        $action = $existing ? 'update' : 'new';
        $changes = [];
        if ($existing) {
            if ($existing->name !== $parsed['name'])       $changes[] = "name: \"{$existing->name}\" → \"{$parsed['name']}\"";
            if ((float)$existing->lat !== (float)($parsed['lat'] ?? 0)) $changes[] = "lat: {$existing->lat} → {$parsed['lat']}";
            if ((float)$existing->lon !== (float)($parsed['lon'] ?? 0)) $changes[] = "lon: {$existing->lon} → {$parsed['lon']}";
        }

        return [
            'action'   => $action,
            'row'      => $rowNum,
            'data'     => $parsed,
            'changes'  => $changes,
            'existing' => $existing ? ['name' => $existing->name] : null,
            'error'    => null,
        ];
    }

    private function previewScreensAdtrue($spreadsheet, string $ownerId, array &$preview): void
    {
        $sheet = $spreadsheet->getSheetByName('Danh Sach Man Hinh');
        if (! $sheet) return;

        $c = self::COL_ADTRUE;
        foreach (array_slice($sheet->toArray(), 5) as $idx => $row) {
            $extId = trim((string) ($row[$c['external_id']] ?? ''));
            if (empty($extId)
             || str_starts_with($extId, 'LUU Y')
             || str_starts_with($extId, 'TONG')) continue;

            $parsed = [
                'external_id'      => $extId,
                'name'             => $row[$c['name']] ?? null,
                'network_name'     => $row[$c['network_name']] ?? null,
                'site_external_id' => $row[$c['site_external_id']] ?? null,
                'lat'              => $this->numericOrNull($row[$c['lat']] ?? null),
                'lon'              => $this->numericOrNull($row[$c['lon']] ?? null),
                'width_px'         => (int) ($row[$c['width_px']] ?? 0),
                'height_px'        => (int) ($row[$c['height_px']] ?? 0),
                'venue_type'       => $row[$c['venue_type']] ?? null,
                'allow_video'      => $this->parseBool($row[$c['allow_video']] ?? true),
                'allow_image'      => $this->parseBool($row[$c['allow_image']] ?? true),
                'allow_html'       => $this->parseBool($row[$c['allow_html']] ?? false),
                'spot_length'      => (int) ($row[$c['spot_length']] ?? 15),
                'max_spot_length'  => (int) ($row[$c['max_spot_length']] ?? 30),
                'min_spot_length'  => (int) ($row[$c['min_spot_length']] ?? 5),
                'floor_cpm'        => $this->numericOrNull($row[$c['floor_cpm']] ?? null),
                'operating_hours'  => $this->parseOperatingHoursFromRow($row, $c['hours_col_start']),
            ];

            $preview['screens'][] = $this->classifyScreen($parsed, $ownerId, $idx + 6, $preview['sites']);
        }
    }

    private function previewScreensHivestack($spreadsheet, string $ownerId, array &$preview): void
    {
        $sheet = $spreadsheet->getSheetByName('2b. Screen List');
        if (! $sheet) return;

        $c = self::COL_HIVESTACK;
        foreach (array_slice($sheet->toArray(), $c['header_row_offset']) as $idx => $row) {
            $extId = trim((string) ($row[$c['external_id']] ?? ''));
            if (empty($extId)) continue;

            $parsed = [
                'external_id'      => $extId,
                'name'             => $row[$c['name']] ?? null,
                'network_name'     => $row[$c['network_name']] ?? null,
                'site_external_id' => $row[$c['site_external_id']] ?? null,
                'lat'              => $this->numericOrNull($row[$c['lat']] ?? null),
                'lon'              => $this->numericOrNull($row[$c['lon']] ?? null),
                'width_px'         => (int) ($row[$c['width_px']] ?? 0),
                'height_px'        => (int) ($row[$c['height_px']] ?? 0),
                'venue_type'       => $row[$c['venue_type']] ?? null,
                'allow_video'      => $this->parseBool($row[$c['allow_video']] ?? true),
                'allow_image'      => $this->parseBool($row[$c['allow_image']] ?? true),
                'allow_html'       => $this->parseBool($row[$c['allow_html']] ?? false),
                'spot_length'      => (int) ($row[$c['spot_length']] ?? 15),
                'max_spot_length'  => (int) ($row[$c['max_spot_length']] ?? 30),
                'min_spot_length'  => (int) ($row[$c['min_spot_length']] ?? 5),
                'floor_cpm'        => $this->numericOrNull($row[$c['floor_cpm']] ?? null),
                'operating_hours'  => $this->parseOperatingHoursFromRow($row, $c['hours_col_start']),
            ];

            $preview['screens'][] = $this->classifyScreen($parsed, $ownerId, $idx + 5, $preview['sites']);
        }
    }

    private function classifyScreen(array $parsed, string $ownerId, int $rowNum, array $previewSites): array
    {
        $errors = $this->validateScreen($parsed, $rowNum);

        // Kiểm tra site_external_id có tồn tại không (trong DB hoặc đang được import)
        $siteInFile = collect($previewSites)
            ->where('action', '!=', 'error')
            ->pluck('data.external_id')
            ->contains($parsed['site_external_id']);

        $siteInDb = Site::withoutGlobalScopes()
            ->where('owner_id', $ownerId)
            ->where('external_id', $parsed['site_external_id'])
            ->exists();

        if (! $siteInFile && ! $siteInDb) {
            $errors[] = "Site ID \"{$parsed['site_external_id']}\" không tồn tại trong DB và không có trong file";
        }

        if ($errors) {
            return ['action' => 'error', 'row' => $rowNum, 'data' => $parsed,
                    'error' => implode('; ', $errors), 'existing' => null, 'changes' => []];
        }

        $existing = Screen::withoutGlobalScopes()
            ->where('owner_id', $ownerId)
            ->where('external_id', $parsed['external_id'])
            ->with(['spec', 'inventory'])
            ->first();

        $changes = [];
        if ($existing) {
            if ($existing->name !== $parsed['name'])
                $changes[] = "name: \"{$existing->name}\" → \"{$parsed['name']}\"";
            if ($existing->spec && (int)$existing->spec->width_px !== $parsed['width_px'])
                $changes[] = "resolution: {$existing->spec->width_px}×{$existing->spec->height_px} → {$parsed['width_px']}×{$parsed['height_px']}";
            if ($existing->inventory && (float)$existing->inventory->floor_cpm !== (float)($parsed['floor_cpm'] ?? 0))
                $changes[] = "floor_cpm: " . number_format((float)$existing->inventory->floor_cpm) . " → " . number_format((float)($parsed['floor_cpm'] ?? 0));
            if ($existing->inventory && $existing->inventory->venue_type !== $parsed['venue_type'])
                $changes[] = "venue_type: \"{$existing->inventory->venue_type}\" → \"{$parsed['venue_type']}\"";
        }

        return [
            'action'   => $existing ? 'update' : 'new',
            'row'      => $rowNum,
            'data'     => $parsed,
            'changes'  => $changes,
            'existing' => $existing ? ['name' => $existing->name, 'id' => $existing->id] : null,
            'error'    => null,
        ];
    }

    private function validateSite(array $data, int $row): array
    {
        $errors = [];
        if (empty($data['external_id']))    $errors[] = "Row {$row}: Site ID trống";
        if (empty($data['name']))           $errors[] = "Row {$row}: Site Name trống";
        if ($data['lat'] && ($data['lat'] < -90 || $data['lat'] > 90))
            $errors[] = "Row {$row}: Latitude không hợp lệ ({$data['lat']})";
        if ($data['lon'] && ($data['lon'] < -180 || $data['lon'] > 180))
            $errors[] = "Row {$row}: Longitude không hợp lệ ({$data['lon']})";
        return $errors;
    }

    private function validateScreen(array $data, int $row): array
    {
        $errors = [];
        if (empty($data['external_id']))        $errors[] = "Row {$row}: Screen ID trống";
        if (empty($data['name']))               $errors[] = "Row {$row}: Screen Name trống";
        if (empty($data['site_external_id']))   $errors[] = "Row {$row}: Site ID trống";
        if (($data['width_px'] ?? 0) <= 0)     $errors[] = "Row {$row}: Width px không hợp lệ";
        if (($data['height_px'] ?? 0) <= 0)    $errors[] = "Row {$row}: Height px không hợp lệ";
        if (empty($data['venue_type']))         $errors[] = "Row {$row}: Venue Type trống";
        if (($data['floor_cpm'] ?? 0) <= 0)    $errors[] = "Row {$row}: Floor CPM phải > 0";
        if (($data['spot_length'] ?? 0) <= 0)  $errors[] = "Row {$row}: Spot duration phải > 0";
        return $errors;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Impression logging
    // ─────────────────────────────────────────────────────────────────────────

    public function logImpression(array $data): \App\Models\ImpressionLog
    {
        $screen = Screen::withoutGlobalScope('owner_scope')
                        ->where('uuid', $data['screen_uuid'])
                        ->with(['inventory'])
                        ->firstOrFail();

        $multiplier   = $screen->getCurrentMultiplier();
        $spotSec      = $screen->inventory?->spot_length ?? 15;
        $impCount     = ($data['duration_sec'] / $spotSec) * $multiplier;

        $cpm          = $data['cpm_charged'] ?? $screen->inventory?->floor_cpm_usd ?? 0;
        $revenueGross = ($impCount / 1000) * $cpm;
        $revenueShare = $screen->owner?->revenue_share_pct ?? 70;
        $revenueOwner = $revenueGross * ($revenueShare / 100);

        return \App\Models\ImpressionLog::create([
            'id'                 => (string) Str::ulid(),
            'screen_id'          => $screen->id,
            'owner_id'           => $screen->owner_id,
            'campaign_id'        => $data['campaign_id'] ?? null,
            'creative_id'        => $data['creative_id'] ?? null,
            'played_at'          => $data['played_at'] ?? now(),
            'duration_sec'       => $data['duration_sec'],
            'multiplier_applied' => $multiplier,
            'imp_count'          => round($impCount, 2),
            'deal_type'          => $data['deal_type'] ?? 'direct',
            'cpm_charged'        => $cpm,
            'revenue_gross'      => round($revenueGross, 4),
            'revenue_owner'      => round($revenueOwner, 4),
            'proof_url'          => $data['proof_url'] ?? null,
            'source'             => $data['source'] ?? 'adtrue_player',
        ]);
    }
}
