{{-- Mega Search JS — raw JS, include inside existing <script> block
     Handles: dropdown toggle, item selection, URL building, tag removal, chip click
--}}
(function(){
    var openDrop = null;
    var backdrop = document.getElementById('ms-backdrop');
    var msBox = document.getElementById('ms-box');

    // ── Position dropdown below search box (fixed positioning) ──
    function positionDrop(drop) {
        if (!msBox || !drop) return;
        var rect = msBox.getBoundingClientRect();
        var dropW = Math.min(720, window.innerWidth * 0.96);
        var left = rect.left + (rect.width / 2) - (dropW / 2);
        // Keep within viewport
        if (left < 8) left = 8;
        if (left + dropW > window.innerWidth - 8) left = window.innerWidth - 8 - dropW;
        drop.style.top = (rect.bottom + 8) + 'px';
        drop.style.left = left + 'px';
        drop.style.width = dropW + 'px';
    }

    // ── Dropdown open/close ──
    function closeDrop() {
        if (openDrop) { openDrop.classList.remove('open'); openDrop = null; }
        if (backdrop) backdrop.classList.remove('open');
        document.querySelectorAll('.ms-pill').forEach(function(b){ b.classList.remove('on'); });
    }

    function bindDrop(btnId, dropId) {
        var btn = document.getElementById(btnId);
        var drop = document.getElementById(dropId);
        if (!btn || !drop) return;
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (openDrop === drop) { closeDrop(); return; }
            closeDrop();
            positionDrop(drop);
            drop.classList.add('open');
            openDrop = drop;
            btn.classList.add('on');
            if (backdrop) backdrop.classList.add('open');
        });
    }

    bindDrop('ms-btn-city', 'ms-drop-city');
    bindDrop('ms-btn-network', 'ms-drop-network');
    bindDrop('ms-btn-type', 'ms-drop-type');

    // Reposition on scroll/resize
    window.addEventListener('scroll', function(){ if (openDrop) positionDrop(openDrop); }, {passive:true});
    window.addEventListener('resize', function(){ if (openDrop) positionDrop(openDrop); });

    if (backdrop) backdrop.addEventListener('click', closeDrop);
    document.querySelectorAll('.ms-drop-close').forEach(function(b){ b.addEventListener('click', closeDrop); });

    // ── Item selection (location) ──
    document.querySelectorAll('.ms-loc-item').forEach(function(item) {
        item.addEventListener('click', function(){ item.classList.toggle('on'); });
    });

    // ── Item selection (grid: network, type) ──
    document.querySelectorAll('.ms-grid-item').forEach(function(item) {
        item.addEventListener('click', function(){ item.classList.toggle('on'); });
    });

    // ── Clear inside dropdowns ──
    ['city','network','type'].forEach(function(key) {
        var clearBtn = document.getElementById('ms-clear-' + key);
        if (!clearBtn) return;
        clearBtn.addEventListener('click', function() {
            var drop = document.getElementById('ms-drop-' + key);
            if (!drop) return;
            drop.querySelectorAll('.on').forEach(function(el){ el.classList.remove('on'); });
        });
    });

    // ── Build URL from current state ──
    // Detect target page: homepage → /explore, others → stay on current path
    var msTarget = (window.location.pathname === '/' || window.location.pathname === '') ? '/explore' : window.location.pathname;

    function buildUrl() {
        var params = new URLSearchParams();
        var q = document.getElementById('ms-q');
        if (q && q.value.trim()) params.append('q', q.value.trim());

        // Cities from location dropdown
        document.querySelectorAll('#ms-drop-city .ms-loc-item.on').forEach(function(el) {
            params.append('city[]', el.dataset.code);
        });
        // Networks
        document.querySelectorAll('#ms-drop-network .ms-grid-item.on').forEach(function(el) {
            params.append('network[]', el.dataset.code);
        });
        // Types
        document.querySelectorAll('#ms-drop-type .ms-grid-item.on').forEach(function(el) {
            params.append('venue_type[]', el.dataset.code);
        });

        // Preserve sort if on /explore
        var sortSel = document.getElementById('sort-sel');
        if (sortSel && sortSel.value) params.append('sort', sortSel.value);

        // Preserve tab context (e.g. owner detail inventory tab)
        var curTab = new URLSearchParams(window.location.search).get('tab');
        if (curTab) params.append('tab', curTab);

        var str = params.toString().replace(/%5B/gi, '[').replace(/%5D/gi, ']');
        window.location.href = msTarget + (str ? '?' + str : '');
    }

    // ── Apply buttons ──
    ['city','network','type'].forEach(function(key) {
        var applyBtn = document.getElementById('ms-apply-' + key);
        if (applyBtn) applyBtn.addEventListener('click', function(){ closeDrop(); buildUrl(); });
    });

    // ── Search button + Enter ──
    var searchBtn = document.getElementById('ms-search-btn');
    if (searchBtn) searchBtn.addEventListener('click', buildUrl);
    var qInput = document.getElementById('ms-q');
    if (qInput) qInput.addEventListener('keydown', function(e){
        if (e.key === 'Enter') { e.preventDefault(); buildUrl(); }
    });

    // ── Tag removal ──
    document.querySelectorAll('.ms-tag-x').forEach(function(x) {
        x.addEventListener('click', function(e) {
            e.stopPropagation();
            var tag = x.closest('.ms-tag');
            var type = tag.dataset.type;
            var val = tag.dataset.val;
            var url = new URL(window.location.href);
            var all = url.searchParams.getAll(type + '[]');
            url.searchParams.delete(type + '[]');
            all.filter(function(v){ return v !== val; }).forEach(function(v){ url.searchParams.append(type + '[]', v); });
            window.location.href = url.toString().replace(/%5B/gi, '[').replace(/%5D/gi, ']');
        });
    });

    // ── Clear all tags ──
    var clearAll = document.getElementById('ms-clear-all');
    if (clearAll) clearAll.addEventListener('click', function(){
        var tab = new URLSearchParams(window.location.search).get('tab');
        window.location.href = msTarget + (tab ? '?tab=' + tab : '');
    });

    // ── Search chips (hero variant) ──
    document.querySelectorAll('.ms-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            var qInput = document.getElementById('ms-q');
            if (qInput) { qInput.value = chip.dataset.q; }
            buildUrl();
        });
    });
})();
