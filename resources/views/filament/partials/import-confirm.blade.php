<x-filament-panels::page>
<x-filament::section>
@php
    $errCount = count($results['errors'] ?? []);
    $ok       = $errCount === 0;
@endphp

    <div class="py-6 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full
                    {{ $ok ? 'bg-success-100' : 'bg-warning-100' }}">
            @if($ok)
                <x-heroicon-o-check-circle class="h-10 w-10 text-success-600"/>
            @else
                <x-heroicon-o-exclamation-triangle class="h-10 w-10 text-warning-600"/>
            @endif
        </div>
        <h3 class="text-xl font-bold text-gray-800 dark:text-white">
            {{ $ok ? 'Import thành công!' : 'Import hoàn tất' }}
        </h3>
        <p class="mt-1 text-sm text-gray-500">
            {{ $ok ? 'Tất cả dữ liệu đã được lưu vào database.' : 'Một số dòng bị bỏ qua do lỗi validation.' }}
        </p>
    </div>

    <div class="mb-6 grid grid-cols-3 gap-4">
        <div class="rounded-xl border border-success-200 bg-success-50 p-5 text-center">
            <div class="text-3xl font-bold text-success-700 tabular-nums">{{ $results['sites'] }}</div>
            <div class="mt-1 text-sm font-medium text-success-600">Sites đã lưu</div>
        </div>
        <div class="rounded-xl border border-primary-200 bg-primary-50 p-5 text-center">
            <div class="text-3xl font-bold text-primary-700 tabular-nums">{{ $results['screens'] }}</div>
            <div class="mt-1 text-sm font-medium text-primary-600">Screens đã lưu</div>
        </div>
        <div class="rounded-xl border p-5 text-center
                    {{ $errCount > 0 ? 'border-danger-200 bg-danger-50' : 'border-gray-200 bg-gray-50' }}">
            <div class="text-3xl font-bold tabular-nums
                        {{ $errCount > 0 ? 'text-danger-600' : 'text-gray-400' }}">
                {{ $errCount }}
            </div>
            <div class="mt-1 text-sm font-medium {{ $errCount > 0 ? 'text-danger-500' : 'text-gray-400' }}">
                Lỗi bị bỏ qua
            </div>
        </div>
    </div>

    @if($errCount > 0)
    <div class="mb-6 rounded-lg border border-danger-200 bg-danger-50 p-4">
        <h4 class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-danger-700">
            <x-heroicon-s-x-circle class="h-4 w-4"/> Chi tiết lỗi ({{ $errCount }} dòng)
        </h4>
        <ul class="max-h-48 space-y-1.5 overflow-y-auto text-sm text-danger-700">
            @foreach($results['errors'] as $err)
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 shrink-0 text-danger-400">•</span>{{ $err }}
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="flex justify-center pt-2">
        <x-filament::button
            wire:click="importAnother"
            icon="heroicon-o-arrow-up-tray"
            color="primary">
            Import file khác
        </x-filament::button>
    </div>
</x-filament::section>
</x-filament-panels::page>
