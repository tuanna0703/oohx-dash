<x-filament-panels::page>
    <form wire:submit="forecast" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end gap-3">
            <x-filament::button
                type="button"
                color="gray"
                tag="a"
                :href="\App\Filament\Resources\OohxCampaignEstimateResource::getUrl('index')">
                Cancel
            </x-filament::button>

            <x-filament::button type="submit" icon="heroicon-o-rocket-launch">
                Forecast campaign
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
