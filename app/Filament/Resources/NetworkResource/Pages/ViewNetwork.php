<?php

namespace App\Filament\Resources\NetworkResource\Pages;

use App\Filament\Resources\NetworkResource;
use App\Models\Screen;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewNetwork extends ViewRecord
{
    protected static string $resource = NetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $network = $this->record;
        $network->load(['owner']);

        $screens = Screen::whereHas('inventory', fn($q) => $q->where('network_id', $network->id))
            ->with(['site', 'inventory', 'spec'])
            ->get();

        return $infolist->schema([

            Infolists\Components\Section::make('Network Info')->columns(3)->schema([

                Infolists\Components\TextEntry::make('owner.name')
                    ->label('Media Owner')->placeholder('—'),

                Infolists\Components\TextEntry::make('name')
                    ->label('Network Name'),

                Infolists\Components\TextEntry::make('status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        default  => 'gray',
                    }),

                Infolists\Components\TextEntry::make('description')
                    ->label('Description')->placeholder('—')->columnSpanFull(),

                Infolists\Components\TextEntry::make('default_floor_cpm')
                    ->label('Default Floor CPM')
                    ->getStateUsing(fn() => $network->default_floor_cpm
                        ? number_format((float) $network->default_floor_cpm, 2)
                          . ' ' . ($network->default_floor_cpm_currency ?? 'VND')
                        : '—'),

                Infolists\Components\TextEntry::make('screens_count')
                    ->label('Total Screens')
                    ->getStateUsing(fn() => $screens->count() . ' màn hình'),

            ]),

            Infolists\Components\Section::make('Screens in this network')
                ->description('Danh sách màn hình thuộc network này')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('screens_list')
                        ->label('')
                        ->getStateUsing(fn() => $screens->map(fn($s) => [
                            'id'          => $s->id,
                            'name'        => $s->name,
                            'external_id' => $s->external_id,
                            'site_name'   => $s->site?->name ?? '—',
                            'status'      => $s->active ? 'Active' : 'Inactive',
                            'floor_cpm'   => $s->inventory?->floor_cpm
                                ? number_format((float) $s->inventory->floor_cpm, 2)
                                  . ' ' . ($s->inventory->floor_cpm_currency ?? 'VND')
                                : '—',
                        ])->toArray())
                        ->columns(4)
                        ->schema([
                            Infolists\Components\TextEntry::make('external_id')
                                ->label('Screen ID')->fontFamily('mono'),
                            Infolists\Components\TextEntry::make('name')
                                ->label('Name'),
                            Infolists\Components\TextEntry::make('site_name')
                                ->label('Site'),
                            Infolists\Components\TextEntry::make('floor_cpm')
                                ->label('Floor CPM'),
                            Infolists\Components\TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->color(fn($state) => $state === 'Active' ? 'success' : 'danger'),
                        ]),
                ]),

        ]);
    }
}
