<?php

namespace App\Filament\Resources\NetworkResource\Pages;

use App\Filament\Resources\NetworkResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
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
