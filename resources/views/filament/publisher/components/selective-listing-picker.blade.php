{{--
    Selective listing picker — Hivestack card style
    Sets: inventory.ad_server_enabled, inventory.deals_enabled,
          inventory.programmatic_enabled, inventory.pmp_only
--}}
<div
    x-data="{
        items: [
            { key: 'ad_server',     label: 'Ad server',     field: 'data.inventory.ad_server_enabled',  active: true  },
            { key: 'deals',         label: 'Deals',         field: 'data.inventory.deals_enabled',       active: true  },
            { key: 'open_exchange', label: 'Open exchange', field: 'data.inventory.programmatic_enabled', active: false },
        ],
        init() {
            const inv = $wire.data?.inventory ?? {};
            this.items.find(i => i.key === 'ad_server').active     = inv.ad_server_enabled     !== false && inv.ad_server_enabled     !== 0;
            this.items.find(i => i.key === 'deals').active         = inv.deals_enabled         !== false && inv.deals_enabled         !== 0;
            this.items.find(i => i.key === 'open_exchange').active = !!inv.programmatic_enabled;
        },
        toggle(item) {
            item.active = !item.active;
            $wire.set(item.field, item.active);
        }
    }"
    x-init="init()"
    class="mt-2"
>
    <div class="grid grid-cols-3 gap-4">
        <template x-for="item in items" :key="item.key">
            <div
                @click="toggle(item)"
                :class="item.active
                    ? 'border-primary-400 bg-primary-50 ring-1 ring-primary-400'
                    : 'border-gray-200 bg-white hover:border-gray-300'"
                class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 p-6 transition-all duration-150 select-none"
            >
                <template x-if="item.key === 'ad_server'">
                    <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
                    </svg>
                </template>
                <template x-if="item.key === 'deals'">
                    <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                    </svg>
                </template>
                <template x-if="item.key === 'open_exchange'">
                    <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                </template>
                <span class="text-sm font-medium text-gray-700" x-text="item.label"></span>
            </div>
        </template>
    </div>
</div>
