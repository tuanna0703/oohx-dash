<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="runPreview">
            {{ $this->form }}
            <div class="mt-6 flex items-center gap-4">
                <x-filament::button
                    type="submit"
                    color="primary"
                    icon="heroicon-o-magnifying-glass"
                    wire:loading.attr="disabled"
                    wire:target="runPreview">
                    <span wire:loading.remove wire:target="runPreview">Preview trước khi Import</span>
                    <span wire:loading wire:target="runPreview" class="flex items-center gap-2">
                        <x-filament::loading-indicator class="h-4 w-4"/>
                        Đang phân tích file...
                    </span>
                </x-filament::button>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    File sẽ được kiểm tra và so sánh với database trước — chưa lưu gì ở bước này.
                </p>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
