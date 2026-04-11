{{--
    Operating Hours — Simple Time Range per Day
    Usage: Forms\Components\View::make('filament.publisher.components.operating-hours-grid')
--}}

<div
    x-data="operatingHoursSimple($wire)"
    x-init="$nextTick(() => boot())"
    class="space-y-2"
>
    {{-- Preset buttons --}}
    <div class="flex flex-wrap gap-2 mb-3">
        <button type="button" @click="preset247()"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
            :class="is247 ? 'bg-primary-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
            24/7
        </button>
        <button type="button" @click="presetBusiness()"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
            Giờ HC (8:00–22:00)
        </button>
        <button type="button" @click="presetWeekdays()"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
            Chỉ ngày thường
        </button>
        <button type="button" @click="presetClear()"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
            Xoá tất cả
        </button>
    </div>

    {{-- Day rows --}}
    <template x-for="(day, idx) in days" :key="day.key">
        <div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
            {{-- Toggle --}}
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" class="sr-only peer"
                    :checked="day.active"
                    @change="toggleDay(idx)">
                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary-500"></div>
            </label>

            {{-- Day label --}}
            <div class="w-20 shrink-0 text-sm font-semibold"
                :class="day.active ? 'text-gray-800' : 'text-gray-400'">
                <span x-text="day.label"></span>
            </div>

            {{-- Time range --}}
            <template x-if="day.active">
                <div class="flex items-center gap-2">
                    <select
                        class="rounded-lg border-gray-300 text-sm py-1.5 px-2 focus:ring-primary-500 focus:border-primary-500"
                        :value="day.open"
                        @change="setOpen(idx, $event.target.value)">
                        <template x-for="t in timeOptions" :key="'o'+idx+t">
                            <option :value="t" :selected="t === day.open" x-text="t"></option>
                        </template>
                    </select>
                    <span class="text-gray-400 text-sm">đến</span>
                    <select
                        class="rounded-lg border-gray-300 text-sm py-1.5 px-2 focus:ring-primary-500 focus:border-primary-500"
                        :value="day.close"
                        @change="setClose(idx, $event.target.value)">
                        <template x-for="t in timeOptions" :key="'c'+idx+t">
                            <option :value="t" :selected="t === day.close" x-text="t"></option>
                        </template>
                    </select>
                </div>
            </template>

            <template x-if="!day.active">
                <span class="text-sm text-gray-400 italic">Đóng cửa</span>
            </template>
        </div>
    </template>

    {{-- Summary --}}
    <div class="pt-2 text-xs text-gray-400" x-text="summary()"></div>
</div>

<script>
function operatingHoursSimple(wire) {
    return {
        wire,
        is247: false,
        days: [
            { key: 'mon', label: 'Thứ 2',    active: true, open: '08:00', close: '22:00' },
            { key: 'tue', label: 'Thứ 3',    active: true, open: '08:00', close: '22:00' },
            { key: 'wed', label: 'Thứ 4',    active: true, open: '08:00', close: '22:00' },
            { key: 'thu', label: 'Thứ 5',    active: true, open: '08:00', close: '22:00' },
            { key: 'fri', label: 'Thứ 6',    active: true, open: '08:00', close: '22:00' },
            { key: 'sat', label: 'Thứ 7',    active: true, open: '08:00', close: '22:00' },
            { key: 'sun', label: 'Chủ nhật', active: true, open: '08:00', close: '22:00' },
        ],
        timeOptions: [],

        boot() {
            // Build time options: 00:00 → 23:30 (30min steps)
            this.timeOptions = [];
            for (let h = 0; h < 24; h++) {
                this.timeOptions.push(String(h).padStart(2,'0') + ':00');
                this.timeOptions.push(String(h).padStart(2,'0') + ':30');
            }
            this.timeOptions.push('24:00');

            // Load existing data
            try {
                const data = this.wire.get('data.inventory.operating_hours');
                if (data && typeof data === 'object' && Object.keys(data).length > 0) {
                    this.loadFromData(data);
                }
            } catch(e) {
                console.warn('[OperatingHours] boot error:', e);
            }

            this.check247();
        },

        loadFromData(data) {
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch(e) { return; }
            }
            if (!data || typeof data !== 'object') return;

            this.days.forEach((day) => {
                const v = data[day.key];
                if (v === 'closed' || v === null || v === undefined) {
                    day.active = false;
                    day.open   = '08:00';
                    day.close  = '22:00';
                } else if (v && typeof v === 'object' && !Array.isArray(v) && v.open && v.close) {
                    // Range format: {open: '08:00', close: '22:00'}
                    day.active = true;
                    day.open   = v.open;
                    day.close  = v.close;
                } else if (Array.isArray(v)) {
                    // Hour array format: [8, 9, 10, ..., 21]
                    if (v.length === 0) {
                        day.active = false;
                    } else {
                        day.active = true;
                        const sorted = [...v].sort((a,b) => a - b);
                        day.open  = String(sorted[0]).padStart(2,'0') + ':00';
                        const last = sorted[sorted.length - 1];
                        day.close = String(last + 1).padStart(2,'0') + ':00';
                    }
                } else {
                    day.active = true;
                    day.open   = '00:00';
                    day.close  = '24:00';
                }
            });
        },

        toggleDay(idx) {
            this.days[idx].active = !this.days[idx].active;
            this.save();
        },

        setOpen(idx, val) {
            this.days[idx].open = val;
            // Auto-fix: if open >= close, push close forward
            if (val >= this.days[idx].close) {
                const h = parseInt(val.split(':')[0]);
                this.days[idx].close = h < 23 ? String(h + 1).padStart(2,'0') + ':00' : '24:00';
            }
            this.save();
        },

        setClose(idx, val) {
            this.days[idx].close = val;
            if (val <= this.days[idx].open) {
                const h = parseInt(val.split(':')[0]);
                this.days[idx].open = h > 0 ? String(h - 1).padStart(2,'0') + ':00' : '00:00';
            }
            this.save();
        },

        // ── Presets ──────────────────────────────────────────────────────────

        preset247() {
            this.days.forEach(d => { d.active = true; d.open = '00:00'; d.close = '24:00'; });
            this.save();
        },

        presetBusiness() {
            this.days.forEach(d => { d.active = true; d.open = '08:00'; d.close = '22:00'; });
            this.save();
        },

        presetWeekdays() {
            this.days.forEach((d, i) => {
                d.active = i < 5; // Mon-Fri active
                d.open   = '08:00';
                d.close  = '22:00';
            });
            this.save();
        },

        presetClear() {
            this.days.forEach(d => { d.active = false; });
            this.save();
        },

        // ── Persist ──────────────────────────────────────────────────────────

        save() {
            const result = {};
            this.days.forEach(d => {
                result[d.key] = d.active
                    ? { open: d.open, close: d.close }
                    : 'closed';
            });
            this.wire.set('data.inventory.operating_hours', result);
            this.check247();
        },

        check247() {
            this.is247 = this.days.every(d => d.active && d.open === '00:00' && d.close === '24:00');
        },

        summary() {
            const active = this.days.filter(d => d.active);
            if (active.length === 0) return 'Tất cả ngày đóng cửa';
            if (this.is247) return 'Hoạt động 24/7';

            const allSame = active.every(d => d.open === active[0].open && d.close === active[0].close);
            if (active.length === 7 && allSame) {
                return 'Hàng ngày ' + active[0].open + ' – ' + active[0].close;
            }
            if (active.length === 5 && allSame && !this.days[5].active && !this.days[6].active) {
                return 'T2–T6 ' + active[0].open + ' – ' + active[0].close;
            }
            return active.length + '/7 ngày hoạt động';
        },
    };
}
</script>
