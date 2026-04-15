<?php

namespace App\Filament\Concerns;

use App\Models\ScreenInventory;
use App\Models\ScreenSpec;

trait SavesScreenRelationships
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->resolveOwnerId($data);
        $data = $this->resolveExternalId($data);
        return $this->extractNestedData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->resolveOwnerId($data);
        $data = $this->resolveExternalId($data);
        return $this->extractNestedData($data);
    }

    /**
     * Resolve owner_id theo thứ tự ưu tiên:
     * 1. Form field (admin chọn owner)
     * 2. current_owner_id (publisher đang login)
     * 3. Site's owner_id (fallback)
     */
    private function resolveOwnerId(array $data): array
    {
        if (! empty($data['owner_id'])) {
            return $data;
        }

        $data['owner_id'] = auth()->user()?->current_owner_id
            ?? \App\Models\Site::find($data['site_id'] ?? null)?->owner_id;

        return $data;
    }

    private function resolveExternalId(array $data): array
    {
        if (empty($data['external_id']) && ! empty($data['name'])) {
            $base = strtoupper(\Illuminate\Support\Str::slug($data['name'], '-'));
            $ownerId = $data['owner_id'] ?? null;
            $extId = $base;
            $n = 1;
            $query = \App\Models\Screen::withoutGlobalScopes()->withTrashed()
                ->where('owner_id', $ownerId)
                ->where('external_id', $extId);
            if ($this->record ?? null) {
                $query->where('id', '!=', $this->record->id);
            }
            while ($query->exists()) {
                $n++;
                $extId = $base . '-' . str_pad($n, 2, '0', STR_PAD_LEFT);
                $query = \App\Models\Screen::withoutGlobalScopes()->withTrashed()
                    ->where('owner_id', $ownerId)
                    ->where('external_id', $extId);
                if ($this->record ?? null) {
                    $query->where('id', '!=', $this->record->id);
                }
            }
            $data['external_id'] = $extId;
        }

        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = \App\Models\Screen::generateUniqueSlug($data['name'], $this->record ?? null);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->saveSpec($this->record);
        $this->saveInventory($this->record);
    }

    protected function afterSave(): void
    {
        $this->saveSpec($this->record);
        $this->saveInventory($this->record);
    }

    private function extractNestedData(array $data): array
    {
        $this->specData      = $data['spec']      ?? [];
        $this->inventoryData = $data['inventory'] ?? [];

        // Site lat/lon: from virtual fields _site_lat/_site_lon or legacy site.lat/site.lon
        $this->siteData = [];
        if (isset($data['_site_lat']) || isset($data['_site_lon'])) {
            $this->siteData['lat'] = $data['_site_lat'] ?? null;
            $this->siteData['lon'] = $data['_site_lon'] ?? null;
        } elseif (isset($data['site'])) {
            $this->siteData = $data['site'];
        }

        unset(
            $data['spec'], $data['inventory'], $data['site'],
            $data['_site_lat'], $data['_site_lon'],
            $data['network_filter'],
            $data['resolution_preset'], $data['allowed_content'],
            $data['allow_different_durations'], $data['selective_listing'],
            $data['override_screen_count']
        );

        return $data;
    }

    private function saveSpec(\App\Models\Screen $screen): void
    {
        $s = $this->specData ?? [];

        ScreenSpec::updateOrCreate(
            ['screen_id' => $screen->id],
            [
                'width_px'          => $s['width_px']          ?? 1920,
                'height_px'         => $s['height_px']         ?? 1080,
                'resolution_preset' => $s['resolution_preset'] ?? null,
                'width_cm'         => $s['width_cm']         ?? null,
                'width_unit'       => $s['width_unit']       ?? 'cm',
                'height_cm'        => $s['height_cm']        ?? null,
                'height_unit'      => $s['height_unit']      ?? 'cm',
                'facing_direction' => $s['facing_direction'] ?? null,
                'photos'           => isset($s['photos']) ? array_values($s['photos']) : null,
                'photo_url'        => isset($s['photos']) ? (collect($s['photos'])->first() ?? null) : ($s['photo_url'] ?? null),
                'allow_image'      => $s['allow_image']      ?? true,
                'allow_video'      => $s['allow_video']      ?? true,
                'allow_html'       => $s['allow_html']       ?? false,
                'allow_zip'        => $s['allow_zip']        ?? false,
                'allow_vast'       =>  true,
            ]
        );
    }

    private function saveInventory(\App\Models\Screen $screen): void
    {
        $i = $this->inventoryData ?? [];
        // Luôn tạo/cập nhật inventory record kể cả khi dữ liệu rỗng (dùng default values)

        if (!empty($i['floor_cpm']) && ($i['floor_cpm_currency'] ?? 'VND') === 'VND') {
            $i['floor_cpm_usd'] = round((float)$i['floor_cpm'] / 25000, 4);
        }

        // Load existing inventory để preserve các field không có trong form
        // (ví dụ: frequency_cap khi user không có quyền pricing)
        $existing = $screen->inventory;

        // Auto-fill vn_category_id: form → existing → network's category
        $vnCatId = $i['vn_category_id']
            ?? $existing?->vn_category_id
            ?? $screen->site?->network?->vn_category_id;

        ScreenInventory::updateOrCreate(
            ['screen_id' => $screen->id],
            [
                'network_id'           => $i['network_id']           ?? null,
                'vn_category_id'       => $vnCatId,
                'venue_type'           => $i['venue_type']           ?? $existing?->venue_type,
                'spot_length'          => $i['spot_length']          ?? 15,
                'max_spot_length'      => $i['max_spot_length']      ?? 30,
                'min_spot_length'      => $i['min_spot_length']      ?? 5,
                'loop_length'          => $i['loop_length']          ?? null,
                'weekly_impressions'   => $i['weekly_impressions']   ?? null,
                'floor_cpm'            => array_key_exists('floor_cpm', $i)          ? $i['floor_cpm']          : $existing?->floor_cpm,
                'floor_cpm_currency'   => array_key_exists('floor_cpm_currency', $i) ? $i['floor_cpm_currency'] : ($existing?->floor_cpm_currency ?? 'VND'),
                'floor_cpm_usd'        => $i['floor_cpm_usd']        ?? $existing?->floor_cpm_usd,
                'operating_hours'      => $i['operating_hours']      ?? null,
                'timezone'             => $i['timezone']             ?? 'Asia/Ho_Chi_Minh',
                'programmatic_enabled' => $i['programmatic_enabled'] ?? false,
                // Pricing model — validate enum value
                'pricing_model'        => in_array($i['pricing_model'] ?? null, ['cpm', 'io', 'both'])
                                            ? $i['pricing_model']
                                            : ($existing?->pricing_model ?? 'io'),
                'io_rate'              => array_key_exists('io_rate', $i)
                                            ? $i['io_rate']
                                            : $existing?->io_rate,
                'io_rate_unit'         => array_key_exists('io_rate_unit', $i)
                                            ? $i['io_rate_unit']
                                            : ($existing?->io_rate_unit ?? 'month'),
                'io_kpi_spots_per_day' => array_key_exists('io_kpi_spots_per_day', $i)
                                            ? ($i['io_kpi_spots_per_day'] ?: null)
                                            : $existing?->io_kpi_spots_per_day,
                'pmp_only'             => $i['pmp_only']             ?? false,
                'ad_server_enabled'    => $i['ad_server_enabled']    ?? true,
                'deals_enabled'        => $i['deals_enabled']        ?? true,
                'share_of_voice_max_pct' => array_key_exists('share_of_voice_max_pct', $i)
                                            ? $i['share_of_voice_max_pct']
                                            : ($existing?->share_of_voice_max_pct ?? 100),
                'screen_count_override'  => array_key_exists('screen_count_override', $i)
                                            ? ((int)$i['screen_count_override'] > 0 ? (int)$i['screen_count_override'] : null)
                                            : $existing?->screen_count_override,
                // Pricing-gated fields: giữ giá trị DB nếu field không có trong form
                'frequency_cap'            => array_key_exists('frequency_cap', $i)
                                                ? (int) $i['frequency_cap']
                                                : ($existing?->frequency_cap ?? 0),
                'category_frequency_cap'   => array_key_exists('category_frequency_cap', $i)
                                                ? (int) $i['category_frequency_cap']
                                                : ($existing?->category_frequency_cap ?? 0),
                'strict_frequency_capping' => array_key_exists('strict_frequency_capping', $i)
                                                ? (bool) $i['strict_frequency_capping']
                                                : ($existing?->strict_frequency_capping ?? false),
            ]
        );

        // Cập nhật site lat/lon
        $siteData = $this->siteData ?? [];
        if (!empty($siteData['lat']) || !empty($siteData['lon'])) {
            $screen->site?->update([
                'lat' => $siteData['lat'] ?? null,
                'lon' => $siteData['lon'] ?? null,
            ]);
        }
    }
}
