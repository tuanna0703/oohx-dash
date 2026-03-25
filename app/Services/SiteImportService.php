<?php

namespace App\Services;

use App\Models\Network;
use App\Models\Screen;
use App\Models\Site;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Import Sites + Screens từ file Excel Hivestack format.
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │ Sheet "1b. Site List"                                                    │
 * │  Row 1 : Title                                                           │
 * │  Row 2-3: Empty                                                          │
 * │  Row 4 : Headers                                                         │
 * │  Row 5+: Data  → array_slice(rows, 4)                                   │
 * │                                                                          │
 * │  Col A (0) : Media Owner Site ID → hivestack_site_id (link key only)    │
 * │  Col B (1) : Site ID            → skip (empty)                          │
 * │  Col C (2) : Site Name          → name  &  external_id (generated slug) │
 * │  Col D (3) : Internal Notes     → description                           │
 * │  Col E (4) : Latitude           → lat                                   │
 * │  Col F (5) : Longitude          → lon                                   │
 * ├─────────────────────────────────────────────────────────────────────────┤
 * │ Sheet "2b. Screen List"                                                  │
 * │  Row 1 : Title                                                           │
 * │  Row 2 : Empty                                                           │
 * │  Row 3 : Group headers (merged)                                          │
 * │  Row 4 : Empty                                                           │
 * │  Row 5 : Column headers                                                  │
 * │  Row 6+: Data  → array_slice(rows, 5)                                   │
 * │                                                                          │
 * │  Col  1 (0) : Media Owner Screen ID → external_id                       │
 * │  Col  2 (1) : Screen UUID           → uuid                              │
 * │  Col  3 (2) : Screen Name           → name                              │
 * │  Col  4 (3) : Internal Notes        → internal_notes                    │
 * │  Col  5 (4) : Media Owner Site ID   → hivestack_site_id (chain lookup) │
 * │  Col  6 (5) : Width px              → spec.width_px                     │
 * │  Col  7 (6) : Height px             → spec.height_px                    │
 * │  Col  8 (7) : Width cm              → spec.width_cm                     │
 * │  Col  9 (8) : Height cm             → spec.height_cm                    │
 * │  Col 10 (9) : Facing direction      → spec.facing_direction             │
 * │  Col 11(10) : Photo URL             → spec.photo_url                    │
 * │  Col 12(11) : Latitude              → lat                               │
 * │  Col 13(12) : Longitude             → lon                               │
 * │  Col 14(13) : Network               → inventory.network_name            │
 * │  Col 15(14) : Venue Type            → inventory.venue_type              │
 * │  Col 16(15) : Images Allowed        → spec.allow_image                  │
 * │  Col 17(16) : Video Allowed         → spec.allow_video                  │
 * │  Col 18(17) : HTML Allowed          → spec.allow_html                   │
 * │  Col 19(18) : ZIP Allowed           → spec.allow_zip                    │
 * │  Col 20(19) : Spot Duration         → inventory.spot_length             │
 * │  Col 21(20) : Max Spot Duration     → inventory.max_spot_length         │
 * │  Col 22(21) : Min Spot Duration     → inventory.min_spot_length         │
 * │  Col 23(22) : Dwell Time            → inventory.loop_length             │
 * │  Col 24(23) : Weekly Impressions    → inventory.weekly_impressions      │
 * │  Col 25(24) : Floor CPM (VND)       → inventory.floor_cpm              │
 * │  Col 26-39 (25-38): Hours Mon-Sun   → inventory.operating_hours         │
 * └─────────────────────────────────────────────────────────────────────────┘
 */
class SiteImportService
{
    // ── Site List offsets ────────────────────────────────────────────────────
    const SITE_DATA_OFFSET = 4; // skip rows 0-3, data từ index 4

    const SITE_COL_EXTERNAL_ID = 0;
    const SITE_COL_NAME        = 2;
    const SITE_COL_NOTES       = 3;
    const SITE_COL_LAT         = 4;
    const SITE_COL_LON         = 5;

    // ── Screen List offsets ──────────────────────────────────────────────────
    const SCREEN_DATA_OFFSET = 5; // skip rows 0-4, data từ index 5

    const SCR_COL_EXTERNAL_ID  = 0;
    const SCR_COL_UUID         = 1;
    const SCR_COL_NAME         = 2;
    const SCR_COL_NOTES        = 3;
    const SCR_COL_SITE_ID      = 4;
    const SCR_COL_WIDTH_PX     = 5;
    const SCR_COL_HEIGHT_PX    = 6;
    const SCR_COL_WIDTH_CM     = 7;
    const SCR_COL_HEIGHT_CM    = 8;
    const SCR_COL_FACING       = 9;
    const SCR_COL_PHOTO        = 10;
    const SCR_COL_LAT          = 11;
    const SCR_COL_LON          = 12;
    const SCR_COL_NETWORK      = 13;
    const SCR_COL_VENUE_TYPE   = 14;
    const SCR_COL_ALLOW_IMAGE  = 15;
    const SCR_COL_ALLOW_VIDEO  = 16;
    const SCR_COL_ALLOW_HTML   = 17;
    const SCR_COL_ALLOW_ZIP    = 18;
    const SCR_COL_SPOT_LEN     = 19;
    const SCR_COL_MAX_SPOT     = 20;
    const SCR_COL_MIN_SPOT     = 21;
    const SCR_COL_DWELL        = 22;
    const SCR_COL_IMPRESSIONS  = 23;
    const SCR_COL_FLOOR_CPM    = 24;
    const SCR_COL_HOURS_START  = 25; // 14 cols: Mon Open/Close … Sun Open/Close

    // ─────────────────────────────────────────────────────────────────────────
    // Dependencies
    // ─────────────────────────────────────────────────────────────────────────

    public function __construct(private ScreenRegistryService $screenRegistry) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Preview (dry-run)
    // ─────────────────────────────────────────────────────────────────────────

    public function readPreview(string $filePath, string $ownerId): array
    {
        $spreadsheet = $this->loadSpreadsheet($filePath);
        $hasScreens  = $spreadsheet->getSheetByName('2b. Screen List') !== null;

        $siteRows   = $this->parseSiteRows($spreadsheet);
        $screenRows = $hasScreens ? $this->parseScreenRows($spreadsheet) : [];

        // ── Build mapping: chain ID → generated external_id ─────────────────
        // Media Owner Site ID (chain_xxx) chỉ là link key nội bộ.
        // Site ID thật = generateSiteId(Site Name) — bỏ dấu + thay space bằng _
        $chainToExtId = [];
        foreach ($siteRows as $r) {
            $chain = trim((string) ($r[self::SITE_COL_EXTERNAL_ID] ?? ''));
            $nm    = trim((string) ($r[self::SITE_COL_NAME] ?? ''));
            if ($chain !== '' && $nm !== '') {
                $chainToExtId[$chain] = $this->generateSiteId($nm);
            }
        }

        // ── Pre-scan: tìm external_id trùng nhau TRONG FILE ─────────────────
        // Dup dựa trên generated ID (từ tên), không phải chain ID
        $siteIdFreq = array_count_values(array_filter(
            array_map(fn($r) => $this->generateSiteId(trim((string) ($r[self::SITE_COL_NAME] ?? ''))), $siteRows),
            fn($v) => $v !== ''
        ));
        $fileDupSiteSet = array_flip(array_keys(array_filter($siteIdFreq, fn($c) => $c > 1)));

        $screenIdFreq = array_count_values(array_filter(
            array_map(fn($r) => trim((string) ($r[self::SCR_COL_EXTERNAL_ID] ?? '')), $screenRows),
            fn($v) => $v !== ''
        ));
        $fileDupScreenSet = array_flip(array_keys(array_filter($screenIdFreq, fn($c) => $c > 1)));

        // ── Preview Sites ───────────────────────────────────────────────────
        $sites       = [];
        $siteSummary = ['new' => 0, 'update' => 0, 'error' => 0, 'skip' => 0, 'file_dup' => 0];

        foreach ($siteRows as $idx => $row) {
            $rowNum  = self::SITE_DATA_OFFSET + 1 + $idx;
            $chainId = trim((string) ($row[self::SITE_COL_EXTERNAL_ID] ?? '')); // Media Owner Site ID → link key
            $name    = trim((string) ($row[self::SITE_COL_NAME] ?? ''));

            // Site ID = generated từ name (bỏ dấu + thay space bằng _)
            $externalId = $name !== '' ? $this->generateSiteId($name) : '';

            if ($chainId === '' && $name === '') {
                $siteSummary['skip']++;
                continue;
            }

            $errors = [];
            if ($name === '')    $errors[] = 'Site Name trống (không thể tạo Site ID)';
            if ($chainId === '') $errors[] = 'Media Owner Site ID trống';

            $lat = $this->numericOrNull($row[self::SITE_COL_LAT] ?? null);
            $lon = $this->numericOrNull($row[self::SITE_COL_LON] ?? null);

            if ($lat !== null && ($lat < -90 || $lat > 90))   $errors[] = "Latitude không hợp lệ ({$lat})";
            if ($lon !== null && ($lon < -180 || $lon > 180)) $errors[] = "Longitude không hợp lệ ({$lon})";

            $fileDup = isset($fileDupSiteSet[$externalId]);
            if ($fileDup) $siteSummary['file_dup']++;

            if ($errors) {
                $siteSummary['error']++;
                $sites[] = [
                    'row' => $rowNum, 'action' => 'error',
                    'external_id' => $externalId, 'hivestack_id' => $chainId, 'name' => $name,
                    'lat' => $lat, 'lon' => $lon, 'duplicate_in_file' => $fileDup,
                    'error' => implode('; ', $errors), 'changes' => [], 'existing' => null,
                ];
                continue;
            }

            // Tra DB bằng generated external_id
            $existing = Site::withoutGlobalScopes()
                ->where('owner_id', $ownerId)->where('external_id', $externalId)
                ->first(['id', 'name', 'lat', 'lon']);

            $action  = $existing ? 'update' : 'new';
            $changes = [];
            if ($existing) {
                if ($existing->name !== $name) $changes[] = "name: \"{$existing->name}\" → \"{$name}\"";
                if (round((float)($existing->lat ?? 0), 5) !== round((float)($lat ?? 0), 5)) $changes[] = "lat: {$existing->lat} → {$lat}";
                if (round((float)($existing->lon ?? 0), 5) !== round((float)($lon ?? 0), 5)) $changes[] = "lon: {$existing->lon} → {$lon}";
            }

            $siteSummary[$action]++;
            $sites[] = [
                'row'               => $rowNum,
                'action'            => $action,
                'external_id'       => $externalId,  // generated slug
                'hivestack_id'      => $chainId,      // chain_xxx (tham chiếu nội bộ)
                'name'              => $name,
                'lat'               => $lat,
                'lon'               => $lon,
                'duplicate_in_file' => $fileDup,
                'error'             => null,
                'changes'           => $changes,
                'existing'          => $existing ? ['name' => $existing->name] : null,
            ];
        }

        // ── Preview Screens ─────────────────────────────────────────────────
        $screens       = [];
        $screenSummary = ['new' => 0, 'update' => 0, 'error' => 0, 'skip' => 0, 'file_dup' => 0];

        // Map: chainId (Media Owner Site ID) → generated external_id
        // Dùng từ sites đã xử lý ở trên (action != error), fallback DB lookup bên dưới
        $importingChainMap = collect($sites)
            ->where('action', '!=', 'error')
            ->pluck('external_id', 'hivestack_id')
            ->toArray();

        // Cache kiểm tra network tồn tại (preview — không tạo mới)
        // key = normalized name, value = 'existing' | 'new'
        $previewNetworkCache = [];

        foreach ($screenRows as $idx => $row) {
            $rowNum      = self::SCREEN_DATA_OFFSET + 1 + $idx;
            $externalId  = trim((string) ($row[self::SCR_COL_EXTERNAL_ID] ?? ''));
            $name        = trim((string) ($row[self::SCR_COL_NAME] ?? ''));
            $siteChainId = trim((string) ($row[self::SCR_COL_SITE_ID] ?? '')); // chain ID (link key)

            if ($externalId === '') {
                $screenSummary['skip']++;
                continue;
            }

            $errors = [];
            if ($name === '') $errors[] = 'Screen Name trống';
            if ($siteChainId === '') $errors[] = 'Media Owner Site ID trống';

            $widthPx  = (int) ($row[self::SCR_COL_WIDTH_PX]  ?? 0);
            $heightPx = (int) ($row[self::SCR_COL_HEIGHT_PX] ?? 0);
            if ($widthPx <= 0)  $errors[] = 'Width px không hợp lệ';
            if ($heightPx <= 0) $errors[] = 'Height px không hợp lệ';

            // Resolve chain ID → real site external_id
            // 1. Tìm trong file đang import (importingChainMap)
            // 2. Fallback: tìm DB theo hivestack_site_id (site đã import phiên trước)
            $resolvedSiteExtId = null;
            if ($siteChainId !== '') {
                if (isset($importingChainMap[$siteChainId])) {
                    $resolvedSiteExtId = $importingChainMap[$siteChainId];
                } else {
                    $resolvedSiteExtId = Site::withoutGlobalScopes()
                        ->where('owner_id', $ownerId)
                        ->where('hivestack_site_id', $siteChainId)
                        ->value('external_id');
                }

                if ($resolvedSiteExtId === null) {
                    $errors[] = "Site chain ID \"{$siteChainId}\" không tìm thấy site tương ứng";
                }
            }

            $floorCpm  = $this->numericOrNull($row[self::SCR_COL_FLOOR_CPM] ?? null);
            $venueType = trim((string) ($row[self::SCR_COL_VENUE_TYPE] ?? ''));

            // ── Network: normalize tên → kiểm tra tồn tại (preview — không tạo) ──
            $rawNetwork        = trim((string) ($row[self::SCR_COL_NETWORK] ?? ''));
            $normalizedNetwork = $rawNetwork !== '' ? $this->generateSiteId($rawNetwork) : '';
            $networkAction     = ''; // 'existing' | 'new' | ''
            if ($normalizedNetwork !== '') {
                if (! isset($previewNetworkCache[$normalizedNetwork])) {
                    $exists = Network::withoutGlobalScopes()
                        ->where('owner_id', $ownerId)
                        ->where('name', $normalizedNetwork)
                        ->exists();
                    $previewNetworkCache[$normalizedNetwork] = $exists ? 'existing' : 'new';
                }
                $networkAction = $previewNetworkCache[$normalizedNetwork];
            }

            $fileDup = isset($fileDupScreenSet[$externalId]);
            if ($fileDup) $screenSummary['file_dup']++;

            if ($errors) {
                $screenSummary['error']++;
                $screens[] = [
                    'row' => $rowNum, 'action' => 'error',
                    'external_id' => $externalId, 'name' => $name,
                    'site_chain_id'     => $siteChainId,
                    'site_external_id'  => $resolvedSiteExtId,
                    'network_name'      => $normalizedNetwork,
                    'network_action'    => $networkAction,
                    'width_px' => $widthPx, 'height_px' => $heightPx,
                    'venue_type' => $venueType, 'floor_cpm' => $floorCpm,
                    'duplicate_in_file' => $fileDup,
                    'error' => implode('; ', $errors), 'changes' => [], 'existing' => null,
                ];
                continue;
            }

            $existing = Screen::withoutGlobalScopes()
                ->where('owner_id', $ownerId)->where('external_id', $externalId)
                ->with(['spec', 'inventory'])->first();

            $action  = $existing ? 'update' : 'new';
            $changes = [];
            if ($existing) {
                if ($existing->name !== $name) $changes[] = "name: \"{$existing->name}\" → \"{$name}\"";
                if ($existing->spec && (int)$existing->spec->width_px !== $widthPx)
                    $changes[] = "resolution: {$existing->spec->width_px}×{$existing->spec->height_px} → {$widthPx}×{$heightPx}";
                if ($existing->inventory && (float)$existing->inventory->floor_cpm !== (float)($floorCpm ?? 0))
                    $changes[] = 'floor_cpm: ' . number_format((float)$existing->inventory->floor_cpm) . ' → ' . number_format((float)($floorCpm ?? 0));
                if ($existing->inventory && $existing->inventory->venue_type !== $venueType)
                    $changes[] = "venue_type: \"{$existing->inventory->venue_type}\" → \"{$venueType}\"";
                if ($existing->inventory && $existing->inventory->network_name !== $normalizedNetwork)
                    $changes[] = "network: \"{$existing->inventory->network_name}\" → \"{$normalizedNetwork}\"";
            }

            $screenSummary[$action]++;
            $screens[] = [
                'row'               => $rowNum,
                'action'            => $action,
                'external_id'       => $externalId,
                'name'              => $name,
                'site_chain_id'     => $siteChainId,       // chain ID từ file (tham chiếu nội bộ)
                'site_external_id'  => $resolvedSiteExtId, // resolved từ chain → real external_id
                'network_name'      => $normalizedNetwork, // tên đã normalize (sẽ lưu vào DB)
                'network_action'    => $networkAction,      // 'existing' | 'new' | ''
                'width_px'          => $widthPx,
                'height_px'         => $heightPx,
                'venue_type'        => $venueType,
                'floor_cpm'         => $floorCpm,
                'duplicate_in_file' => $fileDup,
                'error'             => null,
                'changes'           => $changes,
                'existing'          => $existing ? ['name' => $existing->name] : null,
            ];
        }

        return [
            'format'   => $hasScreens ? 'hivestack' : 'site_list',
            'filename' => basename($filePath),
            'summary'  => [
                'sites_new'        => $siteSummary['new'],
                'sites_update'     => $siteSummary['update'],
                'sites_error'      => $siteSummary['error'],
                'sites_skip'       => $siteSummary['skip'],
                'sites_file_dup'   => $siteSummary['file_dup'],
                'screens_new'      => $screenSummary['new'],
                'screens_update'   => $screenSummary['update'],
                'screens_error'    => $screenSummary['error'],
                'screens_skip'     => $screenSummary['skip'],
                'screens_file_dup' => $screenSummary['file_dup'],
            ],
            'sites'   => $sites,
            'screens' => $screens,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Import (ghi DB)
    // ─────────────────────────────────────────────────────────────────────────

    public function import(string $filePath, string $ownerId): array
    {
        $spreadsheet = $this->loadSpreadsheet($filePath);
        $hasScreens  = $spreadsheet->getSheetByName('2b. Screen List') !== null;

        $results = [
            'format'          => $hasScreens ? 'hivestack' : 'site_list',
            'sites_created'   => 0,
            'sites_updated'   => 0,
            'screens_created' => 0,
            'screens_updated' => 0,
            'skipped'         => 0,
            'errors'          => [],
        ];

        // ── Import Sites ────────────────────────────────────────────────────
        // Map chainId → external_id để screens dùng khi resolve site
        $importedChainMap = [];

        foreach ($this->parseSiteRows($spreadsheet) as $idx => $row) {
            $rowNum     = self::SITE_DATA_OFFSET + 1 + $idx;
            $chainId    = trim((string) ($row[self::SITE_COL_EXTERNAL_ID] ?? '')); // Media Owner Site ID (link key)
            $name       = trim((string) ($row[self::SITE_COL_NAME] ?? ''));

            // external_id = generated slug từ Site Name (bỏ dấu + thay space bằng _)
            $externalId = $name !== '' ? $this->generateSiteId($name) : '';

            if ($chainId === '' && $name === '') {
                $results['skipped']++;
                continue;
            }
            if ($name === '' || $externalId === '') {
                $results['errors'][] = "Row {$rowNum}: Site Name trống (không thể tạo Site ID)";
                continue;
            }

            try {
                $site = Site::updateOrCreate(
                    ['owner_id' => $ownerId, 'external_id' => $externalId],
                    [
                        'name'             => $name,
                        'hivestack_site_id' => $chainId ?: null, // lưu chain ID để tra cứu
                        'description'      => trim((string) ($row[self::SITE_COL_NOTES] ?? '')) ?: null,
                        'lat'              => $this->numericOrNull($row[self::SITE_COL_LAT] ?? null),
                        'lon'              => $this->numericOrNull($row[self::SITE_COL_LON] ?? null),
                        'status'           => 'active',
                    ]
                );
                $site->wasRecentlyCreated ? $results['sites_created']++ : $results['sites_updated']++;

                // Ghi nhớ chain → external_id để dùng trong screens loop
                if ($chainId !== '') {
                    $importedChainMap[$chainId] = $externalId;
                }
            } catch (\Throwable $e) {
                $results['errors'][] = "Row {$rowNum} Site \"{$externalId}\": {$e->getMessage()}";
            }
        }

        // ── Import Screens ──────────────────────────────────────────────────
        if ($hasScreens) {
            // Cache network đã resolve trong phiên này (tránh N+1 query)
            // key = normalized name, value = network_id
            $networkCache = [];

            foreach ($this->parseScreenRows($spreadsheet) as $idx => $row) {
                $rowNum     = self::SCREEN_DATA_OFFSET + 1 + $idx;
                $externalId = trim((string) ($row[self::SCR_COL_EXTERNAL_ID] ?? ''));

                if ($externalId === '') {
                    $results['skipped']++;
                    continue;
                }

                $name        = trim((string) ($row[self::SCR_COL_NAME] ?? ''));
                $siteChainId = trim((string) ($row[self::SCR_COL_SITE_ID] ?? '')); // chain ID (link key)

                if ($name === '') {
                    $results['errors'][] = "Row {$rowNum} Screen \"{$externalId}\": Screen Name trống";
                    continue;
                }

                try {
                    // Resolve chain ID → Site model
                    // 1. Tìm trong $importedChainMap (sites vừa import phiên này)
                    // 2. Fallback: tìm DB theo hivestack_site_id (site đã import phiên trước)
                    $site = null;
                    if ($siteChainId !== '') {
                        if (isset($importedChainMap[$siteChainId])) {
                            $site = Site::withoutGlobalScopes()
                                ->where('owner_id', $ownerId)
                                ->where('external_id', $importedChainMap[$siteChainId])
                                ->first();
                        } else {
                            $site = Site::withoutGlobalScopes()
                                ->where('owner_id', $ownerId)
                                ->where('hivestack_site_id', $siteChainId)
                                ->first();
                        }
                    }

                    // ── Resolve / tạo Network ────────────────────────────────
                    $rawNetwork        = trim((string) ($row[self::SCR_COL_NETWORK] ?? ''));
                    $normalizedNetwork = $rawNetwork !== '' ? $this->generateSiteId($rawNetwork) : '';
                    $networkId         = null;

                    if ($normalizedNetwork !== '') {
                        if (! isset($networkCache[$normalizedNetwork])) {
                            $network = Network::withoutGlobalScopes()
                                ->where('owner_id', $ownerId)
                                ->where('name', $normalizedNetwork)
                                ->first();

                            if (! $network) {
                                $network = Network::create([
                                    'owner_id' => $ownerId,
                                    'name'     => $normalizedNetwork,
                                    'status'   => 'active',
                                ]);
                            }

                            $networkCache[$normalizedNetwork] = $network->id;
                        }
                        $networkId = $networkCache[$normalizedNetwork];
                    }

                    $screen = Screen::updateOrCreate(
                        ['owner_id' => $ownerId, 'external_id' => $externalId],
                        [
                            'site_id'          => $site?->id,
                            'owner_id'         => $ownerId,
                            'uuid'             => $row[self::SCR_COL_UUID] ?? (string) Str::uuid(),
                            'name'             => $name,
                            'internal_notes'   => $row[self::SCR_COL_NOTES] ?? null,
                            'site_external_id' => $site?->external_id ?? null,
                            'active'           => true,
                        ]
                    );

                    $this->screenRegistry->saveSpec($screen, [
                        'width_px'         => (int) ($row[self::SCR_COL_WIDTH_PX]  ?? 1920),
                        'height_px'        => (int) ($row[self::SCR_COL_HEIGHT_PX] ?? 1080),
                        'width_cm'         => $this->numericOrNull($row[self::SCR_COL_WIDTH_CM]  ?? null),
                        'height_cm'        => $this->numericOrNull($row[self::SCR_COL_HEIGHT_CM] ?? null),
                        'facing_direction'  => $row[self::SCR_COL_FACING] ?? null,
                        'photo_url'        => $row[self::SCR_COL_PHOTO]  ?? null,
                        'allow_image'      => $this->parseBool($row[self::SCR_COL_ALLOW_IMAGE] ?? true),
                        'allow_video'      => $this->parseBool($row[self::SCR_COL_ALLOW_VIDEO] ?? true),
                        'allow_html'       => $this->parseBool($row[self::SCR_COL_ALLOW_HTML]  ?? false),
                        'allow_zip'        => $this->parseBool($row[self::SCR_COL_ALLOW_ZIP]   ?? false),
                    ]);

                    $this->screenRegistry->saveInventory($screen, [
                        'network_id'         => $networkId,            // ID đã resolve (null nếu không có network)
                        'network_name'       => $normalizedNetwork ?: null, // tên đã normalize
                        'venue_type'         => $row[self::SCR_COL_VENUE_TYPE]  ?? null,
                        'spot_length'        => (int) ($row[self::SCR_COL_SPOT_LEN]   ?? 15),
                        'max_spot_length'    => (int) ($row[self::SCR_COL_MAX_SPOT]   ?? 30),
                        'min_spot_length'    => (int) ($row[self::SCR_COL_MIN_SPOT]   ?? 5),
                        'loop_length'        => $this->numericOrNull($row[self::SCR_COL_DWELL] ?? null, 'int'),
                        'weekly_impressions' => $this->numericOrNull($row[self::SCR_COL_IMPRESSIONS] ?? null, 'int'),
                        'floor_cpm'          => $this->numericOrNull($row[self::SCR_COL_FLOOR_CPM] ?? null),
                        'floor_cpm_currency' => 'VND',
                        'operating_hours'    => $this->parseOperatingHours($row),
                    ]);

                    $screen->wasRecentlyCreated ? $results['screens_created']++ : $results['screens_updated']++;
                } catch (\Throwable $e) {
                    $results['errors'][] = "Row {$rowNum} Screen \"{$externalId}\": {$e->getMessage()}";
                }
            }
        }

        // ── Kiểm tra trùng lặp trong DB sau khi import ──────────────────────
        $results['duplicates'] = $this->checkDbDuplicates($ownerId);

        return $results;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Duplicate check
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Tìm các bản ghi có thể bị trùng lặp trong DB sau khi import.
     *
     * Sites  : cùng tên (case-insensitive) trong cùng owner
     * Screens: cùng tên + cùng site_id trong cùng owner
     */
    private function checkDbDuplicates(string $ownerId): array
    {
        // ── Sites: tìm name xuất hiện > 1 lần ───────────────────────────────
        $dupSiteNames = \DB::table('sites')
            ->where('owner_id', $ownerId)
            ->whereNull('deleted_at')
            ->selectRaw('LOWER(name) as name_lower')
            ->groupBy('name_lower')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name_lower');

        $dupSites = [];
        if ($dupSiteNames->isNotEmpty()) {
            $rows = Site::withoutGlobalScopes()
                ->where('owner_id', $ownerId)
                ->whereIn(\DB::raw('LOWER(name)'), $dupSiteNames->toArray())
                ->get(['id', 'external_id', 'name']);

            // Group by lowercase name
            $groups = [];
            foreach ($rows as $s) {
                $key = mb_strtolower($s->name);
                $groups[$key][] = ['id' => $s->external_id, 'name' => $s->name];
            }
            foreach ($groups as $nameKey => $members) {
                if (count($members) > 1) {
                    $dupSites[] = [
                        'name'    => $members[0]['name'],
                        'count'   => count($members),
                        'records' => $members,
                    ];
                }
            }
        }

        // ── Screens: cùng name + cùng site ──────────────────────────────────
        $dupScreenGroups = \DB::table('screens')
            ->where('owner_id', $ownerId)
            ->whereNull('deleted_at')
            ->selectRaw('LOWER(name) as name_lower, site_id, COUNT(*) as cnt')
            ->groupBy('name_lower', 'site_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $dupScreens = [];
        foreach ($dupScreenGroups as $group) {
            $rows = Screen::withoutGlobalScopes()
                ->where('owner_id', $ownerId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($group->name_lower)])
                ->where('site_id', $group->site_id)
                ->with('site:id,name')
                ->get(['id', 'external_id', 'name', 'site_id']);

            $dupScreens[] = [
                'name'      => $rows->first()?->name ?? $group->name_lower,
                'site_name' => $rows->first()?->site?->name ?? '(no site)',
                'count'     => $rows->count(),
                'records'   => $rows->map(fn($s) => ['id' => $s->external_id, 'name' => $s->name])->toArray(),
            ];
        }

        return [
            'sites'   => $dupSites,
            'screens' => $dupScreens,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Site ID generation (giữ lại cho tính năng khác)
    // ─────────────────────────────────────────────────────────────────────────

    public function generateSiteId(string $name): string
    {
        $accents = [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'ă'=>'a','ắ'=>'a','ặ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a',
            'ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
            'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
            'ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o',
            'ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ũ'=>'u','ụ'=>'u','ủ'=>'u',
            'ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
            'ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
            'đ'=>'d','ñ'=>'n','ç'=>'c',
            'À'=>'A','Á'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','Å'=>'A',
            'Ă'=>'A','Ắ'=>'A','Ặ'=>'A','Ằ'=>'A','Ẳ'=>'A','Ẵ'=>'A',
            'Ầ'=>'A','Ấ'=>'A','Ậ'=>'A','Ẩ'=>'A','Ẫ'=>'A',
            'È'=>'E','É'=>'E','Ê'=>'E','Ë'=>'E',
            'Ề'=>'E','Ế'=>'E','Ệ'=>'E','Ể'=>'E','Ễ'=>'E',
            'Ì'=>'I','Í'=>'I','Î'=>'I','Ï'=>'I','Ị'=>'I','Ỉ'=>'I','Ĩ'=>'I',
            'Ò'=>'O','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','Ø'=>'O',
            'Ồ'=>'O','Ố'=>'O','Ộ'=>'O','Ổ'=>'O','Ỗ'=>'O',
            'Ơ'=>'O','Ờ'=>'O','Ớ'=>'O','Ợ'=>'O','Ở'=>'O','Ỡ'=>'O',
            'Ù'=>'U','Ú'=>'U','Û'=>'U','Ü'=>'U','Ũ'=>'U','Ụ'=>'U','Ủ'=>'U',
            'Ư'=>'U','Ừ'=>'U','Ứ'=>'U','Ử'=>'U','Ữ'=>'U',
            'Ý'=>'Y','Ỳ'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Ỵ'=>'Y',
            'Đ'=>'D','Ñ'=>'N','Ç'=>'C',
        ];

        $result = strtr($name, $accents);
        $result = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $result);
        $result = preg_replace('/\s+/', '_', trim($result));
        return trim($result, '_');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load file Excel tiết kiệm RAM:
     *  - setReadDataOnly(true)  → bỏ qua formula engine (FunctionArray), tiết kiệm ~70% RAM
     *  - setReadEmptyCells(false) → bỏ qua ô trống
     *  - ini_set memory_limit   → phòng hờ server limit thấp (128MB)
     */
    private function loadSpreadsheet(string $filePath): Spreadsheet
    {
        // Tăng memory limit nếu server đang đặt thấp
        if ($this->parseMemoryLimit((string) ini_get('memory_limit')) < 256 * 1024 * 1024) {
            ini_set('memory_limit', '256M');
        }

        // Tăng time limit — PhpSpreadsheet + toArray() trên file lớn có thể mất vài giây
        set_time_limit(120);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);    // Bỏ formula engine → tiết kiệm RAM & thời gian
        $reader->setReadEmptyCells(false); // Bỏ ô trống

        return $reader->load($filePath);
    }

    /** Chuyển chuỗi memory_limit (vd: "128M", "1G") sang bytes */
    private function parseMemoryLimit(string $val): int
    {
        $val  = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $num  = (int) $val;
        return match ($last) {
            'g'     => $num * 1024 * 1024 * 1024,
            'm'     => $num * 1024 * 1024,
            'k'     => $num * 1024,
            default => $num,
        };
    }

    /** Parse sheet "1b. Site List" → raw data rows */
    private function parseSiteRows(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('1b. Site List');
        if (! $sheet) return [];
        return array_slice($sheet->toArray(null, true, true, false), self::SITE_DATA_OFFSET);
    }

    /** Parse sheet "2b. Screen List" → raw data rows */
    private function parseScreenRows(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('2b. Screen List');
        if (! $sheet) return [];
        return array_slice($sheet->toArray(null, true, true, false), self::SCREEN_DATA_OFFSET);
    }

    /** Parse 14 operating-hours columns (Mon–Sun, Open/Close) */
    private function parseOperatingHours(array $row): array
    {
        $days  = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $hours = [];

        foreach ($days as $i => $day) {
            $open  = $row[self::SCR_COL_HOURS_START + ($i * 2)]     ?? null;
            $close = $row[self::SCR_COL_HOURS_START + ($i * 2) + 1] ?? null;

            if (empty($open) || strtolower(trim((string) $open)) === 'closed') {
                $hours[$day] = 'closed';
            } else {
                $hours[$day] = [
                    'open'  => substr(trim((string) $open),  0, 5),
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
}
