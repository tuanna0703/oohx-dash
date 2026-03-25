<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="import">
            {{ $this->form }}
            <div class="mt-4 flex gap-3">
                <x-filament::button type="submit" color="primary" icon="heroicon-o-arrow-up-tray">
                    Import Now
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    @if($results)
        <x-filament::section heading="Import Results">
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="rounded-lg bg-success-50 p-4 text-center">
                    <div class="text-3xl font-bold text-success-700">{{ $results['sites'] }}</div>
                    <div class="text-sm text-success-600 mt-1">Sites imported</div>
                </div>
                <div class="rounded-lg bg-primary-50 p-4 text-center">
                    <div class="text-3xl font-bold text-primary-700">{{ $results['screens'] }}</div>
                    <div class="text-sm text-primary-600 mt-1">Screens imported</div>
                </div>
                <div class="rounded-lg bg-danger-50 p-4 text-center">
                    <div class="text-3xl font-bold text-danger-700">{{ count($results['errors'] ?? []) }}</div>
                    <div class="text-sm text-danger-600 mt-1">Errors</div>
                </div>
            </div>

            @if(!empty($results['errors']))
                <div class="mt-4">
                    <h4 class="font-medium text-sm text-gray-700 mb-2">Errors:</h4>
                    <ul class="text-sm text-danger-700 space-y-1 max-h-48 overflow-y-auto">
                        @foreach($results['errors'] as $error)
                            <li class="flex items-start gap-1">
                                <x-heroicon-s-x-circle class="w-4 h-4 mt-0.5 flex-shrink-0"/>
                                {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
