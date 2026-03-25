<?php

namespace App\Filament\Resources\ScreenResource\Pages;

use App\Filament\Resources\ScreenResource;
use App\Models\ImpressionLog;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewScreen extends ViewRecord
{
    protected static string $resource = ScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public static function buildViewData(\App\Models\Screen $screen): array
    {
        $screen->load(['spec', 'inventory', 'inventory.network', 'site', 'multipliers', 'owner']);

        $heatmap = ImpressionLog::where('screen_id', $screen->id)
            ->where('played_at', '>=', now()->subDays(7)->startOfDay())
            ->select(
                DB::raw('DAYOFWEEK(played_at) as dow'),
                DB::raw('HOUR(played_at) as hour'),
                DB::raw('SUM(imp_count) as total_imp')
            )
            ->groupBy('dow', 'hour')
            ->get()
            ->keyBy(fn($r) => "{$r->dow}_{$r->hour}");

        $impressionChart = ImpressionLog::where('screen_id', $screen->id)
            ->where('played_at', '>=', now()->subDays(7)->startOfDay())
            ->select(
                DB::raw('DATE_FORMAT(played_at, "%Y-%m-%d %H:00:00") as hour_slot'),
                DB::raw('AVG(imp_count) as avg_imp')
            )
            ->groupBy('hour_slot')
            ->orderBy('hour_slot')
            ->get();

        $operatingHours = $screen->inventory?->operating_hours ?? [];
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $hoursGrid = [];
        foreach ($days as $day) {
            $val = $operatingHours[$day] ?? null;
            if ($val === 'closed' || empty($val)) {
                $hoursGrid[$day] = array_fill(0, 24, false);
            } elseif (is_array($val) && array_is_list($val)) {
                $active = array_map('intval', $val);
                $hoursGrid[$day] = array_map(fn($h) => in_array($h, $active), range(0, 23));
            } else {
                $open  = (int) explode(':', $val['open']  ?? '00:00')[0];
                $close = (int) explode(':', $val['close'] ?? '24:00')[0];
                $hoursGrid[$day] = array_map(fn($h) => $h >= $open && $h < $close, range(0, 23));
            }
        }

        $lastAdRequest = ImpressionLog::where('screen_id', $screen->id)->latest('played_at')->value('played_at');
        $lastPlayedOn  = ImpressionLog::where('screen_id', $screen->id)->where('source', 'adtrue_player')->latest('played_at')->value('played_at');

        return compact('heatmap', 'impressionChart', 'hoursGrid', 'lastAdRequest', 'lastPlayedOn');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $screen = $this->record;
        $screen->load(['spec', 'inventory', 'inventory.network', 'site', 'multipliers']);
        $inv  = $screen->inventory;
        $spec = $screen->spec;
        $site = $screen->site;

        return $infolist->schema([

            // ── Summary ───────────────────────────────────────────────────────
            Infolists\Components\Section::make('Summary')->columns(4)->schema([

                Infolists\Components\TextEntry::make('external_id')
                    ->label('Screen ID')->copyable()->fontFamily('mono'),

                Infolists\Components\TextEntry::make('unit_id')
                    ->label('Unit ID')->placeholder('—'),

                Infolists\Components\TextEntry::make('uuid')
                    ->label('Ad request UUID')->copyable()->fontFamily('mono')
                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),

                Infolists\Components\TextEntry::make('uuid2')
                    ->label('OpenRTB UUID')
                    ->getStateUsing(fn() => $screen->uuid)
                    ->copyable()->fontFamily('mono')
                    ->size(Infolists\Components\TextEntry\TextEntrySize::Small),

                Infolists\Components\TextEntry::make('internal_notes')
                    ->label('Internal notes')->placeholder('—'),

                Infolists\Components\TextEntry::make('active_status')
                    ->label('Status')->badge()
                    ->getStateUsing(fn() => $screen->active ? 'Active' : 'Inactive')
                    ->color(fn($state) => $state === 'Active' ? 'success' : 'danger'),

                Infolists\Components\TextEntry::make('status')
                    ->label('Device status')->badge()
                    ->formatStateUsing(fn($state) => ucfirst($state ?? 'unknown'))
                    ->color(fn($state) => match($state) {
                        'online'      => 'success',
                        'maintenance' => 'warning',
                        default       => 'danger',
                    }),

                Infolists\Components\TextEntry::make('player_type')
                    ->label('Player type')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'adtrue_android' => 'AdTRUE Android',
                        'adtrue_webview' => 'AdTRUE WebView',
                        'third_party'    => '3rd Party Player',
                        'vast_only'      => 'VAST Only',
                        default          => $state ?? '—',
                    })->placeholder('—'),

                Infolists\Components\IconEntry::make('has_impression_data')
                    ->label('Has impression data?')
                    ->getStateUsing(fn() => $screen->multipliers->isNotEmpty())
                    ->boolean(),
            ]),

            // ── Site & Location ───────────────────────────────────────────────
            Infolists\Components\Section::make('Site & location')->columns(3)->schema([

                Infolists\Components\TextEntry::make('site.name')
                    ->label('Site')->placeholder('—'),

                Infolists\Components\TextEntry::make('network_name')
                    ->label('Network')
                    ->getStateUsing(fn() => $inv?->network?->name ?? $inv?->network_name ?? '—'),

                Infolists\Components\TextEntry::make('inventory.venue_type')
                    ->label('Venue type')->placeholder('—'),

                Infolists\Components\TextEntry::make('site_address')
                    ->label('Address')
                    ->getStateUsing(fn() => $site?->address ?? '—'),

                Infolists\Components\TextEntry::make('site_city')
                    ->label('City')
                    ->getStateUsing(fn() => $site?->city ?? '—'),

                Infolists\Components\TextEntry::make('site_region')
                    ->label('Region / Province')
                    ->getStateUsing(fn() => $site?->region ?? '—'),

                Infolists\Components\TextEntry::make('site_country')
                    ->label('Country')
                    ->getStateUsing(fn() => $site?->country ?? '—'),

                Infolists\Components\TextEntry::make('geographic_context')
                    ->label('Geographic context')
                    ->getStateUsing(fn() => implode(' > ', array_filter([
                        $site?->country,
                        $site?->region,
                        $site?->city,
                    ])) ?: '—'),

                Infolists\Components\TextEntry::make('inventory.timezone')
                    ->label('Timezone')->placeholder('—'),

                Infolists\Components\TextEntry::make('site.lat')
                    ->label('Latitude')
                    ->getStateUsing(fn() => $site?->lat ? number_format((float) $site->lat, 7) : '—'),

                Infolists\Components\TextEntry::make('site.lon')
                    ->label('Longitude')
                    ->getStateUsing(fn() => $site?->lon ? number_format((float) $site->lon, 7) : '—'),

                Infolists\Components\ViewEntry::make('screen_map')
                    ->label('')
                    ->view('filament.components.screen-map')
                    ->columnSpanFull(),
            ]),

            // ── Screen specs ──────────────────────────────────────────────────
            Infolists\Components\Section::make('Screen specs')->columns(3)->schema([

                Infolists\Components\TextEntry::make('resolution')
                    ->label('Resolution (pixels)')
                    ->getStateUsing(fn() => $spec
                        ? $spec->width_px . '×' . $spec->height_px . ' px'
                          . match($spec->resolution_preset) {
                              '1080x1920' => ' — Portrait',
                              '1920x1080' => ' — Landscape',
                              'custom'    => ' — Custom',
                              default     => '',
                          }
                        : '—'),

                Infolists\Components\TextEntry::make('physical_size')
                    ->label('Physical size')
                    ->getStateUsing(fn() => $spec?->width_cm
                        ? $spec->width_cm . ' ' . ($spec->width_unit ?? 'cm')
                          . ' × ' . $spec->height_cm . ' ' . ($spec->height_unit ?? 'cm')
                        : '—'),

                Infolists\Components\TextEntry::make('spec.facing_direction')
                    ->label('Facing direction')->placeholder('—'),

                Infolists\Components\IconEntry::make('spec.allow_image')->label('Images')->boolean(),
                Infolists\Components\IconEntry::make('spec.allow_video')->label('Videos')->boolean(),
                Infolists\Components\IconEntry::make('spec.allow_html')->label('HTML')->boolean(),
                Infolists\Components\IconEntry::make('spec.allow_zip')->label('ZIP')->boolean(),
                Infolists\Components\IconEntry::make('spec.allow_vast')->label('VAST')->boolean(),
            ]),

            // ── Inventory & Commercial ────────────────────────────────────────
            Infolists\Components\Section::make('Inventory & commercial')->columns(3)->schema([

                Infolists\Components\TextEntry::make('floor_cpm_display')
                    ->label('Floor CPM')
                    ->getStateUsing(fn() => $inv?->floor_cpm
                        ? number_format((float) $inv->floor_cpm, 2)
                          . ' ' . ($inv->floor_cpm_currency ?? 'VND')
                          . ($inv->floor_cpm_usd ? ' ($' . number_format((float)$inv->floor_cpm_usd, 4) . ' USD)' : '')
                        : '—'),

                Infolists\Components\TextEntry::make('share_of_voice')
                    ->label('Max share of voice')
                    ->getStateUsing(fn() => ($inv?->share_of_voice_max_pct ?? 100) . '%'),

                Infolists\Components\TextEntry::make('screen_count')
                    ->label('Screen count override')
                    ->getStateUsing(fn() => $inv?->screen_count_override && $inv->screen_count_override > 1
                        ? $inv->screen_count_override . ' screens' : '—'),

                Infolists\Components\TextEntry::make('weekly_impressions')
                    ->label('Weekly impressions')
                    ->getStateUsing(fn() => $inv?->weekly_impressions
                        ? number_format((int) $inv->weekly_impressions) : '—'),

                Infolists\Components\TextEntry::make('selective_listing_display')
                    ->label('Selective listing')
                    ->getStateUsing(function () use ($inv) {
                        $items = [];
                        if ($inv?->ad_server_enabled !== false) $items[] = 'Ad server';
                        if ($inv?->deals_enabled !== false) {
                            $items[] = $inv?->pmp_only ? 'Deals (PMP only)' : 'Deals';
                        }
                        if ($inv?->programmatic_enabled) $items[] = 'Open exchange';
                        return $items ? implode(', ', $items) : '—';
                    }),
            ]),

            // ── Playback ──────────────────────────────────────────────────────
            Infolists\Components\Section::make('Playback')->columns(3)->schema([

                Infolists\Components\TextEntry::make('spot_length_display')
                    ->label('Default ad duration')
                    ->getStateUsing(fn() =>
                        ($inv?->spot_length ?? 15) . ' secs'
                        . ' (min: ' . ($inv?->min_spot_length ?? 3)
                        . ', max: ' . ($inv?->max_spot_length ?? 180) . ')'
                    ),

                Infolists\Components\TextEntry::make('loop_length_display')
                    ->label('Loop length')
                    ->getStateUsing(fn() => $inv?->loop_length ? $inv->loop_length . ' seconds' : '—'),
            ]),

            // ── Frequency cap ─────────────────────────────────────────────────
            Infolists\Components\Section::make('Frequency cap')->columns(3)->schema([

                Infolists\Components\TextEntry::make('frequency_cap_display')
                    ->label('Advertiser frequency cap')
                    ->getStateUsing(fn() => $inv?->frequency_cap
                        ? $inv->frequency_cap . ' seconds' : 'Unlimited'),

                Infolists\Components\TextEntry::make('category_frequency_cap_display')
                    ->label('Category frequency cap')
                    ->getStateUsing(fn() => $inv?->category_frequency_cap
                        ? $inv->category_frequency_cap . ' seconds' : 'Unlimited'),

                Infolists\Components\TextEntry::make('strict_frequency_capping_display')
                    ->label('Strict frequency capping')->badge()
                    ->getStateUsing(fn() => $inv?->strict_frequency_capping ? 'Yes' : 'No')
                    ->color(fn($state) => $state === 'Yes' ? 'warning' : 'gray'),
            ]),

            // ── Operating Hours ───────────────────────────────────────────────
            Infolists\Components\Section::make('Operating hours')->schema([
                Infolists\Components\ViewEntry::make('operating_hours_grid')
                    ->label('')
                    ->view('filament.publisher.components.operating-hours-view')
                    ->getStateUsing(fn() => $inv?->operating_hours ?? []),
            ]),

        ]);
    }
}
