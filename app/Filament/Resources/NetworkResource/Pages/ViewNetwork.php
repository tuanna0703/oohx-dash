<?php

namespace App\Filament\Resources\NetworkResource\Pages;

use App\Filament\Resources\NetworkResource;
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
        $network->loadMissing(['owner']);

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

            ]),

            Infolists\Components\Section::make('Màn hình trong network')
                ->description('Lọc và xem màn hình theo danh sách hoặc bản đồ')
                ->schema([
                    Infolists\Components\ViewEntry::make('screens_panel')
                        ->label('')
                        ->view('filament.components.network-screens-panel')
                        ->columnSpanFull(),
                ]),

        ]);
    }
}
