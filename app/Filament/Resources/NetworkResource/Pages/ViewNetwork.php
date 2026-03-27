<?php

namespace App\Filament\Resources\NetworkResource\Pages;

use App\Filament\Resources\NetworkResource;
use App\Models\Screen;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewNetwork extends ViewRecord
{
    protected static string $resource = NetworkResource::class;

    public array $screensMapData = [];

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
            ->with(['site.province'])
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

        return $infolist->schema([

            Section::make('Network Info')->columns(3)->schema([

                TextEntry::make('owner.name')
                    ->label('Media Owner')->placeholder('—'),

                TextEntry::make('name')
                    ->label('Network Name'),

                TextEntry::make('status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        default  => 'gray',
                    }),

                TextEntry::make('description')
                    ->label('Description')->placeholder('—')->columnSpanFull(),

                TextEntry::make('default_floor_cpm')
                    ->label('Default Floor CPM')
                    ->getStateUsing(fn() => $network->default_floor_cpm
                        ? number_format((float) $network->default_floor_cpm, 2)
                          . ' ' . ($network->default_floor_cpm_currency ?? 'VND')
                        : '—'),

                TextEntry::make('created_at')
                    ->label('Created on')->dateTime('M j, Y, g:i A'),

                TextEntry::make('updated_at')
                    ->label('Last modified on')->dateTime('M j, Y, g:i A'),

            ]),

        ]);
    }
}
