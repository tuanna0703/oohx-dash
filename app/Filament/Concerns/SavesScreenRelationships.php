<?php

namespace App\Filament\Concerns;

use App\Models\ScreenInventory;
use App\Models\ScreenSpec;

trait SavesScreenRelationships
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['owner_id'])) {
            $data['owner_id'] = auth()->user()->current_owner_id;
        }
        return $this->extractNestedData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractNestedData($data);
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
        $this->siteData      = $data['site']      ?? [];

        unset(
            $data['spec'], $data['inventory'], $data['site'],
            $data['resolution_preset'], $data['allowed_content'],
            $data['allow_different_durations'], $data['selective_listing'],
            // frequency_cap, strict_frequency_capping, screen_count_override đã chuyển
            // sang inventory.* nên không còn ở root data — bỏ khỏi danh sách unset thủ công
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
                'photo_url'        => $s['photo_url']        ?? null,
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

        ScreenInventory::updateOrCreate(
            ['screen_id' => $screen->id],
            [
                'network_id'           => $i['network_id']           ?? null,
                'vn_category_id'       => $i['vn_category_id']       ?? $existing?->vn_category_id,
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
