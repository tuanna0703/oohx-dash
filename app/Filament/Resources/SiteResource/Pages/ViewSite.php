<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Models\Screen;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    public array $screensMapData = [];
    public int   $totalScreens   = 0;

    public function getTitle(): string
    {
        return 'Overview';
    }

    public function getView(): string
    {
        return 'filament.resources.site-resource.pages.view-site';
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $site = $this->record;
        $site->loadMissing(['owner', 'province', 'commune']);

        $this->totalScreens   = Screen::where('site_id', $site->id)->count();
        $this->screensMapData = $this->buildScreensMapData();
    }

    protected function buildScreensMapData(): array
    {
        $site    = $this->record;
        $screens = Screen::where('site_id', $site->id)->get();

        return $screens->map(fn($s) => [
            'id'          => $s->id,
            'external_id' => $s->external_id ?? '—',
            'name'        => $s->name ?? '—',
            'active'      => (bool) $s->active,
            'view_url'    => \App\Filament\Resources\ScreenResource::getUrl('view', ['record' => $s->id]),
        ])->values()->all();
    }

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
