(function () {
    'use strict';

    const MIN_WIDTH = 60;
    const HANDLE_ATTR = 'data-col-resizer';

    // ── Core ─────────────────────────────────────────────────────────────────

    function initResizer(table) {
        if (table.querySelector('[' + HANDLE_ATTR + ']')) return; // already done

        const ths = [...table.querySelectorAll('thead tr:first-child th')];
        if (!ths.length) return;

        // Snapshot current widths before switching to fixed layout
        const widths = ths.map(th => th.getBoundingClientRect().width);
        if (widths.every(w => w === 0)) return; // table not visible yet

        ths.forEach((th, i) => {
            th.style.width = widths[i] + 'px';
            addHandle(th);
        });

        table.style.tableLayout = 'fixed';
    }

    function addHandle(th) {
        if (getComputedStyle(th).position === 'static') {
            th.style.position = 'relative';
        }

        const handle = document.createElement('span');
        handle.setAttribute(HANDLE_ATTR, '1');
        handle.setAttribute('aria-hidden', 'true');
        handle.style.cssText = [
            'position:absolute', 'right:0', 'top:0', 'bottom:0',
            'width:5px', 'cursor:col-resize', 'z-index:20',
            'background:transparent', 'transition:background 0.15s',
        ].join(';');

        handle.addEventListener('mouseenter', () => {
            handle.style.background = 'rgba(99,102,241,0.35)';
        });
        handle.addEventListener('mouseleave', () => {
            handle.style.background = 'transparent';
        });

        handle.addEventListener('mousedown', e => {
            e.preventDefault();
            e.stopPropagation();

            const x0 = e.clientX;
            const w0 = parseFloat(th.style.width) || th.getBoundingClientRect().width;

            handle.style.background    = 'rgba(99,102,241,0.55)';
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            const onMove = e => {
                th.style.width = Math.max(MIN_WIDTH, w0 + e.clientX - x0) + 'px';
            };

            const onUp = () => {
                handle.style.background    = 'transparent';
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });

        th.appendChild(handle);
    }

    // ── Find Filament tables (broad selector for all v3 structures) ───────────

    function findTables() {
        // Try known Filament class names first
        let tables = [
            ...document.querySelectorAll('.fi-ta-content table'),
            ...document.querySelectorAll('.fi-ta-ctn table'),
            ...document.querySelectorAll('table.fi-ta-table'),
        ];

        // Deduplicate
        tables = [...new Set(tables)];

        // Fallback: any table inside a Livewire component that has thead
        if (!tables.length) {
            tables = [...document.querySelectorAll('[wire\\:id] table')];
        }

        return tables.filter(t => t.tHead && t.tHead.rows.length);
    }

    function initAll() {
        findTables().forEach(initResizer);
    }

    // ── Hooks ────────────────────────────────────────────────────────────────

    // Initial load — wait for Livewire to hydrate
    document.addEventListener('DOMContentLoaded', () => setTimeout(initAll, 300));

    // After Filament SPA navigation
    document.addEventListener('livewire:navigated', () => setTimeout(initAll, 300));

    // After each Livewire commit (pagination / search / sort / filter)
    document.addEventListener('livewire:init', () => {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => setTimeout(initAll, 100));
        });
    });

    // Fallback: if livewire:init already fired before this script loaded
    if (typeof Livewire !== 'undefined') {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => setTimeout(initAll, 100));
        });
    }

})();
