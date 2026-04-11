{{--
    Operating Hours — Visual Time Range Bar per Day
    Usage: Forms\Components\View::make('filament.publisher.components.operating-hours-grid')
--}}

<div
    x-data="operatingHoursBar($wire)"
    x-init="$nextTick(() => boot())"
    class="space-y-1"
>
    {{-- Preset buttons --}}
    <div class="flex flex-wrap gap-2 mb-4">
        <button type="button" @click="preset247()"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
            :class="is247 ? 'bg-primary-500 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
            24/7
        </button>
        <button type="button" @click="presetBusiness()"
            class="rounded-lg px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
            Giờ HC (8–22h)
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

    {{-- Time ruler --}}
    <div class="flex items-end gap-0 mb-1" style="padding-left:108px;padding-right:4px">
        <div class="flex-1 relative h-4">
            <span class="absolute text-[10px] text-gray-400 font-mono" style="left:0">0</span>
            <span class="absolute text-[10px] text-gray-400 font-mono" style="left:25%;transform:translateX(-50%)">6</span>
            <span class="absolute text-[10px] text-gray-400 font-mono" style="left:50%;transform:translateX(-50%)">12</span>
            <span class="absolute text-[10px] text-gray-400 font-mono" style="left:75%;transform:translateX(-50%)">18</span>
            <span class="absolute text-[10px] text-gray-400 font-mono right-0">24</span>
        </div>
    </div>

    {{-- Day rows --}}
    <template x-for="(day, idx) in days" :key="day.key">
        <div class="flex items-center gap-2 py-1">
            {{-- Toggle --}}
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="checkbox" class="sr-only peer"
                    :checked="day.active"
                    @change="toggleDay(idx)">
                <div class="w-8 h-[18px] bg-gray-200 rounded-full peer peer-checked:bg-primary-500 after:content-[''] after:absolute after:top-[1px] after:left-[1px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-[14px]"></div>
            </label>

            {{-- Day label --}}
            <div class="w-16 shrink-0 text-xs font-semibold"
                :class="day.active ? 'text-gray-700' : 'text-gray-400'">
                <span x-text="day.label"></span>
            </div>

            {{-- Range bar or closed --}}
            <template x-if="day.active">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    {{-- Bar track --}}
                    <div class="flex-1 relative h-7 bg-gray-100 rounded-md overflow-hidden cursor-pointer select-none"
                         @mousedown="barMouseDown($event, idx)"
                         @touchstart.passive="barTouchStart($event, idx)"
                         :data-idx="idx">
                        {{-- Active segment --}}
                        <div class="absolute top-0 bottom-0 bg-primary-500/20 border-y-2 border-primary-500 transition-all duration-75"
                            :style="'left:' + (day.openH/24*100) + '%;right:' + ((24-day.closeH)/24*100) + '%'">
                        </div>
                        {{-- Open handle --}}
                        <div class="absolute top-0 bottom-0 w-2 bg-primary-600 rounded-l cursor-ew-resize z-10 hover:bg-primary-700"
                            :style="'left:' + (day.openH/24*100) + '%'"
                            @mousedown.stop="handleDown($event, idx, 'open')"
                            @touchstart.stop.passive="handleTouchDown($event, idx, 'open')">
                        </div>
                        {{-- Close handle --}}
                        <div class="absolute top-0 bottom-0 w-2 bg-primary-600 rounded-r cursor-ew-resize z-10 hover:bg-primary-700"
                            :style="'right:' + ((24-day.closeH)/24*100) + '%'"
                            @mousedown.stop="handleDown($event, idx, 'close')"
                            @touchstart.stop.passive="handleTouchDown($event, idx, 'close')">
                        </div>
                    </div>

                    {{-- Time label --}}
                    <div class="shrink-0 text-xs font-mono text-gray-600 w-[90px] text-right"
                        x-text="fmt(day.openH) + ' – ' + fmt(day.closeH)">
                    </div>
                </div>
            </template>

            <template x-if="!day.active">
                <div class="flex-1 flex items-center gap-2">
                    <div class="flex-1 h-7 bg-gray-50 rounded-md"></div>
                    <span class="shrink-0 text-xs text-gray-400 italic w-[90px] text-right">Đóng cửa</span>
                </div>
            </template>
        </div>
    </template>

    {{-- Summary --}}
    <div class="pt-2 text-xs text-gray-400" x-text="summary()"></div>
</div>

<style>
    [x-data] .drag-active { user-select: none; -webkit-user-select: none; }
</style>

<script>
function operatingHoursBar(wire) {
    return {
        wire,
        is247: false,
        dragging: null, // {idx, type: 'open'|'close'|'move', startX, startOpen, startClose}
        days: [
            { key: 'mon', label: 'Thứ 2',    active: true, openH: 8, closeH: 22 },
            { key: 'tue', label: 'Thứ 3',    active: true, openH: 8, closeH: 22 },
            { key: 'wed', label: 'Thứ 4',    active: true, openH: 8, closeH: 22 },
            { key: 'thu', label: 'Thứ 5',    active: true, openH: 8, closeH: 22 },
            { key: 'fri', label: 'Thứ 6',    active: true, openH: 8, closeH: 22 },
            { key: 'sat', label: 'Thứ 7',    active: true, openH: 8, closeH: 22 },
            { key: 'sun', label: 'CN',       active: true, openH: 8, closeH: 22 },
        ],

        boot() {
            try {
                const data = this.wire.get('data.inventory.operating_hours');
                if (data && typeof data === 'object' && Object.keys(data).length > 0) {
                    this.loadFromData(data);
                }
            } catch(e) {
                console.warn('[OH] boot error:', e);
            }
            this.check247();

            // Global mouse/touch up
            document.addEventListener('mousemove', (e) => this.onDragMove(e));
            document.addEventListener('mouseup',   ()  => this.onDragEnd());
            document.addEventListener('touchmove',  (e) => this.onDragMove(e.touches[0]), {passive: false});
            document.addEventListener('touchend',   ()  => this.onDragEnd());
        },

        loadFromData(data) {
            if (typeof data === 'string') { try { data = JSON.parse(data); } catch(e) { return; } }
            if (!data || typeof data !== 'object') return;

            this.days.forEach(day => {
                const v = data[day.key];
                if (v === 'closed' || v === null || v === undefined) {
                    day.active = false;
                } else if (v && typeof v === 'object' && !Array.isArray(v) && v.open && v.close) {
                    day.active = true;
                    day.openH  = parseInt(v.open.split(':')[0]);
                    day.closeH = v.close === '24:00' ? 24 : parseInt(v.close.split(':')[0]);
                } else if (Array.isArray(v)) {
                    if (v.length === 0) { day.active = false; return; }
                    const s = [...v].sort((a,b)=>a-b);
                    day.active = true;
                    day.openH  = s[0];
                    day.closeH = s[s.length-1] + 1;
                } else {
                    day.active = true; day.openH = 0; day.closeH = 24;
                }
            });
        },

        // ── Interactions ─────────────────────────────────────────────────────

        toggleDay(idx) {
            this.days[idx].active = !this.days[idx].active;
            this.save();
        },

        handleDown(e, idx, type) {
            e.preventDefault();
            const bar = e.target.closest('[data-idx]');
            this.dragging = { idx, type, bar, startX: e.clientX, startOpen: this.days[idx].openH, startClose: this.days[idx].closeH };
            document.body.classList.add('drag-active');
        },

        handleTouchDown(e, idx, type) {
            const bar = e.target.closest('[data-idx]');
            this.dragging = { idx, type, bar, startX: e.touches[0].clientX, startOpen: this.days[idx].openH, startClose: this.days[idx].closeH };
            document.body.classList.add('drag-active');
        },

        barMouseDown(e, idx) {
            // Click on bar (not handle) → move range center
            if (e.target.dataset.idx === undefined && !e.target.closest('[data-idx]')) return;
            const bar = e.target.closest('[data-idx]') || e.target;
            const rect = bar.getBoundingClientRect();
            const pct  = (e.clientX - rect.left) / rect.width;
            const hour = Math.round(pct * 24);
            const d    = this.days[idx];
            const span = d.closeH - d.openH;

            // Move the range so center is at click point
            let newOpen  = Math.max(0, hour - Math.floor(span / 2));
            let newClose = newOpen + span;
            if (newClose > 24) { newClose = 24; newOpen = newClose - span; }

            d.openH  = newOpen;
            d.closeH = newClose;

            this.dragging = { idx, type: 'move', bar, startX: e.clientX, startOpen: newOpen, startClose: newClose };
            document.body.classList.add('drag-active');
            this.save();
        },

        barTouchStart(e, idx) {
            this.barMouseDown({ clientX: e.touches[0].clientX, target: e.target, closest: e.target.closest.bind(e.target), preventDefault: ()=>{} }, idx);
        },

        onDragMove(e) {
            if (!this.dragging) return;
            const { idx, type, bar, startX, startOpen, startClose } = this.dragging;
            const rect  = bar.getBoundingClientRect();
            const delta = ((e.clientX - startX) / rect.width) * 24;
            const d     = this.days[idx];

            if (type === 'open') {
                d.openH = Math.max(0, Math.min(d.closeH - 1, Math.round(startOpen + delta)));
            } else if (type === 'close') {
                d.closeH = Math.min(24, Math.max(d.openH + 1, Math.round(startClose + delta)));
            } else if (type === 'move') {
                const span = startClose - startOpen;
                let newOpen = Math.round(startOpen + delta);
                newOpen = Math.max(0, Math.min(24 - span, newOpen));
                d.openH  = newOpen;
                d.closeH = newOpen + span;
            }
        },

        onDragEnd() {
            if (this.dragging) {
                this.dragging = null;
                document.body.classList.remove('drag-active');
                this.save();
            }
        },

        // ── Presets ──────────────────────────────────────────────────────────

        preset247() {
            this.days.forEach(d => { d.active = true; d.openH = 0; d.closeH = 24; });
            this.save();
        },
        presetBusiness() {
            this.days.forEach(d => { d.active = true; d.openH = 8; d.closeH = 22; });
            this.save();
        },
        presetWeekdays() {
            this.days.forEach((d, i) => { d.active = i < 5; d.openH = 8; d.closeH = 22; });
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
                    ? { open: this.fmt(d.openH), close: this.fmt(d.closeH) }
                    : 'closed';
            });
            this.wire.set('data.inventory.operating_hours', result);
            this.check247();
        },

        check247() {
            this.is247 = this.days.every(d => d.active && d.openH === 0 && d.closeH === 24);
        },

        fmt(h) {
            return String(h).padStart(2, '0') + ':00';
        },

        summary() {
            const active = this.days.filter(d => d.active);
            if (active.length === 0) return 'Tất cả ngày đóng cửa';
            if (this.is247) return 'Hoạt động 24/7';
            const allSame = active.every(d => d.openH === active[0].openH && d.closeH === active[0].closeH);
            if (active.length === 7 && allSame) return 'Hàng ngày ' + this.fmt(active[0].openH) + ' – ' + this.fmt(active[0].closeH);
            if (active.length === 5 && allSame && !this.days[5].active && !this.days[6].active) return 'T2–T6 ' + this.fmt(active[0].openH) + ' – ' + this.fmt(active[0].closeH);
            return active.length + '/7 ngày hoạt động';
        },
    };
}
</script>
