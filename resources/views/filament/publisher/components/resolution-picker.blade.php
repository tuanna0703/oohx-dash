{{--
    Resolution picker — Hivestack card style
    Sets: spec.width_px, spec.height_px, spec.resolution_preset via Alpine → Livewire
--}}
<div
    x-data="{
        selected: '1920x1080',
        presets: [
            { key: '1080x1920', w: 1080, h: 1920, label: '1080×1920', ratio: 9/16 },
            { key: '1920x1080', w: 1920, h: 1080, label: '1920×1080', ratio: 16/9 },
            { key: 'custom',    w: null, h: null,  label: 'Custom',    ratio: 1    },
        ],
        init() {
            const preset = $wire.data?.spec?.resolution_preset;
            if (preset) {
                this.selected = preset;
            } else {
                const w = $wire.data?.spec?.width_px;
                const h = $wire.data?.spec?.height_px;
                if (w && h) {
                    const match = this.presets.find(p => p.w === parseInt(w) && p.h === parseInt(h));
                    this.selected = match ? match.key : 'custom';
                }
            }
        },
        pick(preset) {
            this.selected = preset.key;
            $wire.set('data.spec.resolution_preset', preset.key);
            if (preset.w) {
                $wire.set('data.spec.width_px',  preset.w);
                $wire.set('data.spec.height_px', preset.h);
            }
        }
    }"
    x-init="init()"
    class="flex items-center justify-center gap-6 py-4"
>
    <template x-for="p in presets" :key="p.key">
        <div
            @click="pick(p)"
            :class="selected === p.key
                ? 'border-primary-500 bg-primary-500 text-white shadow-lg scale-105'
                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
            class="relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 p-4 transition-all duration-150 select-none"
            style="width:120px"
        >
            <div
                :style="p.ratio > 1
                    ? 'width:80px;height:' + Math.round(80/p.ratio) + 'px'
                    : 'height:80px;width:' + Math.round(80*p.ratio) + 'px'"
                :class="selected === p.key ? 'bg-white/30' : 'bg-gray-200'"
                class="mb-3 rounded flex items-center justify-center text-xs font-bold"
                x-text="p.label"
            ></div>

            <div x-show="selected === p.key"
                 class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-green-500 text-white shadow">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
        </div>
    </template>
</div>
