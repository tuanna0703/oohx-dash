<?php

namespace App\Filament\Resources\NetworkResource\Pages;

use App\Filament\Resources\NetworkResource;
use App\Models\Screen;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewNetwork extends ViewRecord
{
    protected static string $resource = NetworkResource::class;

    public array $screensMapData = [];

    public function getTitle(): string
    {
        return 'Overview';
    }

    public function getView(): string
    {
        return 'filament.resources.network-resource.pages.view-network';
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->screensMapData = $this->buildScreensMapData();
    }

    protected function buildScreensMapData(): array
    {
        $network = $this->record;
        $screens = Screen::whereHas('inventory', fn($q) => $q->where('network_id', $network->id))
            ->with(['site.province', 'site.commune'])
            ->get();

        return $screens->map(fn($s) => [
            'id'            => $s->id,
            'external_id'   => $s->external_id ?? '—',
            'name'          => $s->name ?? '—',
            'site_id'       => (string) ($s->site_id ?? ''),
            'site_name'     => $s->site?->name ?? '—',
            'site_lat'      => $s->site?->lat  ? (float) $s->site->lat  : null,
            'site_lon'      => $s->site?->lon  ? (float) $s->site->lon  : null,
            'province_id'   => $s->site?->province_id ? (string) $s->site->province_id : '',
            'province_name' => $s->site?->province?->name ?? '',
            'commune_id'    => $s->site?->commune_id ? (string) $s->site->commune_id : '',
            'commune_name'  => $s->site?->commune?->full_name ?? '',
            'active'        => (bool) $s->active,
            'view_url'      => \App\Filament\Resources\ScreenResource::getUrl('view', ['record' => $s->id]),
        ])->values()->all();
    }

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $network = $this->record;
        $network->loadMissing(['owner']);

        $totalScreens = Screen::whereHas('inventory', fn($q) => $q->where('network_id', $network->id))->count();
        $totalSites   = Screen::whereHas('inventory', fn($q) => $q->where('network_id', $network->id))
            ->distinct('site_id')->count('site_id');

        return $infolist->schema([

            Tabs::make('info_tabs')
                ->activeTab(1)
                ->tabs([

                    Tabs\Tab::make('Network Info')
                        ->icon('heroicon-o-signal')
                        ->schema([
                            Section::make()->columns(3)->schema([

                                TextEntry::make('name')
                                    ->label('Network Name'),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'active' => 'success',
                                        'paused' => 'warning',
                                        default  => 'gray',
                                    }),

                                TextEntry::make('default_floor_cpm')
                                    ->label('Default Floor CPM')
                                    ->getStateUsing(fn() => $network->default_floor_cpm
                                        ? number_format((float) $network->default_floor_cpm, 2)
                                          . ' ' . ($network->default_floor_cpm_currency ?? 'VND')
                                        : '—'),

                                TextEntry::make('total_screens')
                                    ->label('Tổng số Screens')
                                    ->getStateUsing(fn() => $totalScreens),

                                TextEntry::make('total_sites')
                                    ->label('Tổng số Sites')
                                    ->getStateUsing(fn() => $totalSites),

                                TextEntry::make('updated_at')
                                    ->label('Last modified on')
                                    ->dateTime('M j, Y, g:i A'),

                            ]),
                        ]),

                    Tabs\Tab::make('Media Owner')
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Section::make()->columns(3)->schema([

                                ImageEntry::make('owner.logo_url')
                                    ->label('Logo')
                                    ->height(64)
                                    ->columnSpanFull()
                                    ->hidden(fn() => ! $network->owner?->logo_url),

                                TextEntry::make('owner.name')
                                    ->label('Tên'),

                                TextEntry::make('owner.status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn($state) => match($state) {
                                        'active'    => 'success',
                                        'suspended' => 'danger',
                                        default     => 'warning',
                                    }),

                                TextEntry::make('owner.founded')
                                    ->label('Founded')
                                    ->placeholder('—'),

                                TextEntry::make('owner.website')
                                    ->label('Website')
                                    ->placeholder('—')
                                    ->url(fn($state) => $state ?: null)
                                    ->openUrlInNewTab(),

                                TextEntry::make('owner.email')
                                    ->label('Email')
                                    ->placeholder('—'),

                                TextEntry::make('owner.phone')
                                    ->label('Phone')
                                    ->placeholder('—'),

                                TextEntry::make('owner.created_at')
                                    ->label('Ngày tham gia')
                                    ->dateTime('M j, Y'),

                                TextEntry::make('owner.tagline')
                                    ->label('Tagline')
                                    ->placeholder('—')
                                    ->columnSpanFull(),

                            ]),
                        ]),

                ]),

        ]);
    }
}
