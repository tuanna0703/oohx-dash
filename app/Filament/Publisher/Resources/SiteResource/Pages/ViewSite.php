<?php

namespace App\Filament\Publisher\Resources\SiteResource\Pages;

use App\Filament\Publisher\Resources\SiteResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $site = $this->record;
        $site->load(['province', 'commune', 'screens']);

        return $infolist->schema([

            Infolists\Components\Section::make('Site Identity')->columns(2)->schema([

                Infolists\Components\TextEntry::make('external_id')
                    ->label('Site ID')->copyable()->fontFamily('mono'),

                Infolists\Components\TextEntry::make('name')
                    ->label('Name'),

                Infolists\Components\TextEntry::make('description')
                    ->label('Description')->placeholder('—')->columnSpanFull(),

                Infolists\Components\TextEntry::make('status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'closed' => 'danger',
                        default  => 'gray',
                    }),

                Infolists\Components\TextEntry::make('screens_count')
                    ->label('Screens')
                    ->getStateUsing(fn() => $site->screens->count() . ' màn hình'),

            ]),

            Infolists\Components\Section::make('Location')->columns(2)->schema([

                Infolists\Components\TextEntry::make('province.full_name')
                    ->label('Tỉnh / Thành phố')->placeholder('—'),

                Infolists\Components\TextEntry::make('commune.full_name')
                    ->label('Phường / Xã / Thị trấn')->placeholder('—'),

                Infolists\Components\TextEntry::make('address')
                    ->label('Địa chỉ chi tiết')->placeholder('—')->columnSpanFull(),

                Infolists\Components\TextEntry::make('city')
                    ->label('City')->placeholder('—'),

                Infolists\Components\TextEntry::make('region')
                    ->label('Region')->placeholder('—'),

                Infolists\Components\TextEntry::make('country')
                    ->label('Country')->placeholder('—'),

                Infolists\Components\TextEntry::make('lat')
                    ->label('Latitude')
                    ->getStateUsing(fn() => $site->lat ? number_format((float) $site->lat, 7) : '—'),

                Infolists\Components\TextEntry::make('lon')
                    ->label('Longitude')
                    ->getStateUsing(fn() => $site->lon ? number_format((float) $site->lon, 7) : '—'),

                Infolists\Components\ViewEntry::make('site_map')
                    ->label('')
                    ->view('filament.components.site-map')
                    ->columnSpanFull(),

            ]),

        ]);
    }
}
