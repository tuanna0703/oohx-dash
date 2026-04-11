<?php

namespace App\Filament\Shared\Pages;

use App\Filament\Shared\Widgets\SiteDetailStats;
use App\Models\Screen;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/**
 * Shared base for admin and publisher ViewSite pages.
 * Subclasses only need to declare $resource and optionally override screenViewUrl().
 */
abstract class BaseViewSite extends ViewRecord
{
    public array $screensMapData = [];
    public int   $totalScreens   = 0;

    public function getTitle(): string
    {
        return $this->record?->name ?? 'Overview';
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
            'view_url'    => $this->screenViewUrl($s),
        ])->values()->all();
    }

    /**
     * Hook: override in publisher subclass to point at publisher ScreenResource routes.
     */
    protected function screenViewUrl(Screen $screen): string
    {
        return \App\Filament\Resources\ScreenResource::getUrl('view', ['record' => $screen->id]);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [SiteDetailStats::class];
    }

    protected function getHeaderWidgetsData(): array
    {
        return ['record' => $this->record];
    }
}
