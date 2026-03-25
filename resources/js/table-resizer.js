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

        ths.forEach((th, i) => {
            th.style.width = widths[i] + 'px';
            addHandle(th);
        });

        table.style.tableLayout = 'fixed';
    }

    function addHandle(th) {
        const pos = getComputedStyle(th).position;
        if (pos === 'static') th.style.position = 'relative';

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

            handle.style.background = 'rgba(99,102,241,0.55)';
            document.body.style.cursor    = 'col-resize';
            document.body.style.userSelect = 'none';

            const onMove = e => {
                const newW = Math.max(MIN_WIDTH, w0 + e.clientX - x0);
                th.style.width = newW + 'px';
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

    // ── Init all tables on the page ──────────────────────────────────────────

    function initAll() {
        document.querySelectorAll('.fi-ta-content table').forEach(table => {
            if (table.tHead && table.tHead.rows.length) {
                initResizer(table);
            }
        });
    }

    // ── Hooks ────────────────────────────────────────────────────────────────

    // First load
    document.addEventListener('DOMContentLoaded', () => setTimeout(initAll, 150));

    // After Livewire navigation (SPA-style panel nav)
    document.addEventListener('livewire:navigated', () => setTimeout(initAll, 150));

    // After each Livewire commit (pagination / search / sort / filter)
    document.addEventListener('livewire:init', () => {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => setTimeout(initAll, 80));
        });
    });

})();
