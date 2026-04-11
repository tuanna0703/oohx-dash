<?php

namespace App\Filament\Publisher\Resources\ScreenResource\Pages;

use App\Filament\Publisher\Resources\ScreenResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewScreen extends ViewRecord
{
    protected static string $resource = ScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $screen = $this->record;
        $screen->load(['spec', 'inventory', 'inventory.network', 'site']);
        $inv  = $screen->inventory;
        $spec = $screen->spec;
        $site = $screen->site;

        return $infolist->schema([

            // ── Tổng quan ────────────────────────────────────────────────────
            Infolists\Components\Section::make('Tổng quan')->columns(4)->schema([
                Infolists\Components\TextEntry::make('external_id')
                    ->label('Screen ID')
                    ->copyable()
                    ->fontFamily('mono'),

                Infolists\Components\TextEntry::make('active_status')
                    ->label('Trạng thái')
                    ->badge()
                    ->getStateUsing(fn () => $screen->active ? 'Active' : 'Inactive')
                    ->color(fn ($state) => $state === 'Active' ? 'success' : 'danger'),

                Infolists\Components\TextEntry::make('status')
                    ->label('Device')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'unknown'))
                    ->color(fn ($state) => match ($state) {
                        'online'      => 'success',
                        'maintenance' => 'warning',
                        default       => 'danger',
                    }),

                Infolists\Components\TextEntry::make('inventory.venue_type')
                    ->label('Loại biển')
                    ->placeholder('—'),
            ]),

            // ── Vị trí ──────────────────────────────────────────────────────
            Infolists\Components\Section::make('Vị trí & Site')->columns(3)->schema([
                Infolists\Components\TextEntry::make('site.name')
                    ->label('Site')
                    ->placeholder('—'),

                Infolists\Components\TextEntry::make('network_name')
                    ->label('Network')
                    ->getStateUsing(fn () => $inv?->network?->name ?? $inv?->network_name ?? '—'),

                Infolists\Components\TextEntry::make('site_city')
                    ->label('Thành phố')
                    ->getStateUsing(fn () => $site?->city ?? '—'),

                Infolists\Components\TextEntry::make('site_address')
                    ->label('Địa chỉ')
                    ->getStateUsing(fn () => $site?->address ?? '—'),

                Infolists\Components\TextEntry::make('site.lat')
                    ->label('Tọa độ')
                    ->getStateUsing(fn () => $site?->lat
                        ? number_format((float) $site->lat, 7) . ', ' . number_format((float) $site->lon, 7)
                        : '—'),

                Infolists\Components\TextEntry::make('inventory.timezone')
                    ->label('Múi giờ')
                    ->placeholder('—'),

                Infolists\Components\ViewEntry::make('screen_map')
                    ->label('')
                    ->view('filament.components.screen-map')
                    ->columnSpanFull(),
            ]),

            // ── Kỹ thuật ────────────────────────────────────────────────────
            Infolists\Components\Section::make('Kỹ thuật màn hình')->columns(3)->schema([
                Infolists\Components\TextEntry::make('resolution')
                    ->label('Resolution')
                    ->getStateUsing(fn () => $spec
                        ? $spec->width_px . '×' . $spec->height_px . ' px'
                        : '—'),

                Infolists\Components\TextEntry::make('physical_size')
                    ->label('Kích thước vật lý')
                    ->getStateUsing(fn () => $spec?->width_cm
                        ? $spec->width_cm . ' × ' . $spec->height_cm . ' cm'
                        : '—'),

                Infolists\Components\TextEntry::make('content_types')
                    ->label('Loại content')
                    ->getStateUsing(fn () => implode(', ', array_filter([
                        $spec?->allow_image ? 'Image' : null,
                        $spec?->allow_video ? 'Video' : null,
                    ])) ?: '—'),
            ]),

            // ── Giá & Inventory ─────────────────────────────────────────────
            Infolists\Components\Section::make('Giá & Inventory')->columns(3)->schema([
                Infolists\Components\TextEntry::make('floor_cpm_display')
                    ->label('Floor CPM')
                    ->getStateUsing(fn () => $inv?->floor_cpm
                        ? number_format((float) $inv->floor_cpm, 0, '.', ',') . ' ' . ($inv->floor_cpm_currency ?? 'VND')
                        : '—'),

                Infolists\Components\TextEntry::make('weekly_impressions')
                    ->label('Lượt xem / tuần')
                    ->getStateUsing(fn () => $inv?->weekly_impressions
                        ? number_format((int) $inv->weekly_impressions)
                        : '—'),

                Infolists\Components\TextEntry::make('spot_length_display')
                    ->label('Thời lượng QC')
                    ->getStateUsing(fn () => ($inv?->spot_length ?? 15) . ' giây'),

                Infolists\Components\TextEntry::make('programmatic_display')
                    ->label('Programmatic')
                    ->badge()
                    ->getStateUsing(fn () => $inv?->programmatic_enabled ? 'Enabled' : 'Disabled')
                    ->color(fn ($state) => $state === 'Enabled' ? 'success' : 'gray'),
            ]),

            // ── Lịch hoạt động ──────────────────────────────────────────────
            Infolists\Components\Section::make('Lịch hoạt động')
                ->collapsed()
                ->schema([
                    Infolists\Components\ViewEntry::make('operating_hours_grid')
                        ->label('')
                        ->view('filament.publisher.components.operating-hours-view')
                        ->getStateUsing(fn () => $inv?->operating_hours ?? []),
                ]),

            // ── AdOps nâng cao ───────────────────────────────────────────────
            Infolists\Components\Section::make('AdOps nâng cao')
                ->description('Chi tiết SSP/programmatic — Phase 2')
                ->collapsed()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('uuid')
                        ->label('Ad request UUID')
                        ->copyable()
                        ->fontFamily('mono')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('unit_id')
                        ->label('Unit ID')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('player_type')
                        ->label('Player type')
                        ->formatStateUsing(fn ($state) => match ($state) {
                            'adtrue_android' => 'AdTRUE Android',
                            'adtrue_webview' => 'AdTRUE WebView',
                            'third_party'    => '3rd Party',
                            'vast_only'      => 'VAST Only',
                            default          => $state ?? '—',
                        }),

                    Infolists\Components\TextEntry::make('spec.facing_direction')
                        ->label('Hướng mặt biển')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('share_of_voice')
                        ->label('Max SOV')
                        ->getStateUsing(fn () => ($inv?->share_of_voice_max_pct ?? 100) . '%'),

                    Infolists\Components\TextEntry::make('frequency_cap_display')
                        ->label('Frequency cap')
                        ->getStateUsing(fn () => $inv?->frequency_cap
                            ? $inv->frequency_cap . 's'
                            : 'Unlimited'),

                    Infolists\Components\TextEntry::make('internal_notes')
                        ->label('Ghi chú nội bộ')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
