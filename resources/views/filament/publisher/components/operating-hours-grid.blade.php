{{--
    Operating Hours Grid Component
    Interactive 7 days × 24 hours grid — AdTRUE brand pink like Hivestack
    Usage: Forms\Components\View::make('filament.publisher.components.operating-hours-grid')
--}}

@php
    $days  = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $hours = [];
    for ($h = 0; $h < 24; $h++) {
        $label = $h === 0 ? '12 AM' : ($h < 12 ? $h.' AM' : ($h === 12 ? '12 PM' : ($h-12).' PM'));
        $hours[] = ['h' => $h, 'label' => $label];
    }
@endphp

<div
    x-data="operatingHoursGrid($wire)"
    x-init="$nextTick(() => bootGrid())"
    class="select-none"
>
    {{-- Column headers --}}
    <div class="mb-1 flex items-center">
        <div class="w-12 shrink-0"></div>
        <div class="grid flex-1 gap-px" style="grid-template-columns: repeat(24, 1fr)">
            @foreach($hours as $h)
            <div class="text-center">
                <span class="text-xs font-semibold text-primary-600 leading-none block">
                    {{ explode(' ', $h['label'])[0] }}
                </span>
                <span class="text-xs text-gray-400 leading-none">
                    {{ explode(' ', $h['label'])[1] ?? '' }}
                </span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Grid rows --}}
    <div class="space-y-1">
        @foreach($days as $di => $day)
        <div class="flex items-center gap-1">
            {{-- Day label --}}
            <div class="w-12 shrink-0 text-xs font-semibold text-gray-600">{{ $day }}</div>

            {{-- Hour cells --}}
            <div class="grid flex-1 gap-px" style="grid-template-columns: repeat(24, 1fr)"
                 @mousedown="startDrag($event)"
                 @mouseover="onHover($event, {{ $di }})"
                 @mouseup="endDrag()"
                 @mouseleave="endDrag()">
                @foreach($hours as $h)
                <div
                    :class="cells[{{ $di }}][{{ $h['h'] }}]
                        ? 'bg-primary-500 hover:bg-primary-400'
                        : 'bg-gray-200 hover:bg-gray-300'"
                    class="h-8 rounded-sm cursor-pointer transition-colors duration-75"
                    @click.prevent="toggle({{ $di }}, {{ $h['h'] }})"
                    data-day="{{ $di }}"
                    data-hour="{{ $h['h'] }}"
                    title="{{ $day }} {{ $h['label'] }}"
                ></div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

    {{-- Quick actions --}}
    <div class="mt-3 flex flex-wrap gap-2">
        <button type="button" @click="selectAll()"
            class="rounded px-3 py-1 text-xs font-medium bg-primary-100 text-primary-700 hover:bg-primary-200 transition-colors">
            Select all
        </button>
        <button type="button" @click="clearAll()"
            class="rounded px-3 py-1 text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
            Clear all
        </button>
        <button type="button" @click="setWeekdays()"
            class="rounded px-3 py-1 text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
            Weekdays only
        </button>
        <button type="button" @click="setBusiness()"
            class="rounded px-3 py-1 text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
            Business hours (8–22)
        </button>
        <span class="ml-auto text-xs text-gray-400 self-center" x-text="summary()"></span>
    </div>
</div>

<script>
function operatingHoursGrid(wire) {
    return {
        wire,
        cells: Array.from({length: 7}, () => Array(24).fill(true)),
        dragging: false,
        dragValue: true,

        bootGrid() {
            try {
                const initial = this.wire.get('data.inventory.operating_hours');
                if (initial && typeof initial === 'object' && Object.keys(initial).length > 0) {
                    this.loadFromData(initial);
                }
            } catch(e) {
                console.warn('[OperatingHours] bootGrid error:', e);
            }
        },

        loadFromData(data) {
            try {
                if (typeof data === 'string') data = JSON.parse(data);
                if (!data || typeof data !== 'object') return;

                const dayKeys = ['mon','tue','wed','thu','fri','sat','sun'];
                const newCells = Array.from({length: 7}, () => Array(24).fill(true));

                dayKeys.forEach((day, di) => {
                    const v = data[day];
                    if (v === 'closed') {
                        newCells[di] = Array(24).fill(false);
                    } else if (Array.isArray(v)) {
                        // New format: array of active hour indices [8, 9, 12, 14, ...]
                        newCells[di] = Array.from({length: 24}, (_, h) => v.includes(h));
                    } else if (v && v.open && v.close) {
                        // Legacy format: {open: '08:00', close: '18:00'} — single contiguous range
                        const open  = parseInt(v.open.split(':')[0]);
                        const close = parseInt(v.close.split(':')[0]);
                        newCells[di] = Array.from({length: 24}, (_, h) => h >= open && h < close);
                    }
                    // else: keep all-true (24/7 for that day)
                });

                this.cells = newCells;
            } catch(e) {
                console.warn('[OperatingHours] load error:', e);
            }
        },

        // ── User interactions ─────────────────────────────────────────────────

        toggle(day, hour) {
            const updated = this.cells.map(r => [...r]);
            updated[day][hour] = !updated[day][hour];
            this.cells = updated;
            this.save();
        },

        startDrag(e) {
            const day  = e.target.dataset.day;
            const hour = e.target.dataset.hour;
            if (day !== undefined && hour !== undefined) {
                this.dragging  = true;
                this.dragValue = !this.cells[parseInt(day)][parseInt(hour)];
            }
        },

        onHover(e, day) {
            if (!this.dragging) return;
            const hour = e.target.dataset.hour;
            if (hour !== undefined) {
                const updated = this.cells.map(r => [...r]);
                updated[day][parseInt(hour)] = this.dragValue;
                this.cells = updated;
            }
        },

        endDrag() {
            if (this.dragging) {
                this.dragging = false;
                this.save();
            }
        },

        selectAll() {
            this.cells = Array.from({length: 7}, () => Array(24).fill(true));
            this.save();
        },

        clearAll() {
            this.cells = Array.from({length: 7}, () => Array(24).fill(false));
            this.save();
        },

        setWeekdays() {
            this.cells = Array.from({length: 7}, (_, di) => Array(24).fill(di < 5));
            this.save();
        },

        setBusiness() {
            this.cells = Array.from({length: 7}, () =>
                Array.from({length: 24}, (_, h) => h >= 8 && h < 22)
            );
            this.save();
        },

        // ── Persist to Livewire form state ────────────────────────────────────

        save() {
            const dayKeys = ['mon','tue','wed','thu','fri','sat','sun'];
            const result  = {};

            dayKeys.forEach((day, di) => {
                const active = this.cells[di].map((v, h) => v ? h : -1).filter(h => h >= 0);
                result[day] = active.length === 0 ? 'closed' : active;
            });

            this.wire.set('data.inventory.operating_hours', result);
        },

        summary() {
            const total = this.cells.flat().filter(Boolean).length;
            return total === 168 ? 'All hours active' :
                   total === 0   ? 'No hours selected' :
                   total + '/168 hours active';
        },
    };
}
</script>
