{{-- Smart Filter JS — raw JS, include inside existing <script> block --}}
(function(){
    var sft = document.getElementById('sft');
    if (!sft) return;
    var target = sft.dataset.target || '/map';

    // ── Chip toggle ──
    sft.querySelectorAll('.sft-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            // Region: radio behavior (only 1 active)
            if (chip.dataset.group === 'region') {
                var wasOn = chip.classList.contains('on');
                sft.querySelectorAll('.sft-chip[data-group="region"]').forEach(function(c){ c.classList.remove('on'); });
                if (!wasOn) chip.classList.add('on');
            } else {
                chip.classList.toggle('on');
            }
        });
    });

    // ── Show more / collapse ──
    sft.querySelectorAll('.sft-chips-wrap[data-max]').forEach(function(wrap) {
        var max = parseInt(wrap.dataset.max) || 6;
        var chips = wrap.querySelectorAll('.sft-chip');
        if (chips.length <= max) return;

        var hiddenCount = 0;
        chips.forEach(function(chip, i) {
            if (i >= max && !chip.classList.contains('on')) {
                chip.classList.add('sft-hidden');
                hiddenCount++;
            }
        });
        if (hiddenCount === 0) return;

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'sft-more';
        toggle.textContent = '+' + hiddenCount + ' khác';
        toggle.addEventListener('click', function() {
            var hidden = wrap.querySelectorAll('.sft-hidden');
            if (hidden.length > 0) {
                hidden.forEach(function(el) { el.classList.remove('sft-hidden'); });
                toggle.textContent = 'Thu gọn';
            } else {
                chips.forEach(function(chip, i) {
                    if (i >= max && !chip.classList.contains('on')) chip.classList.add('sft-hidden');
                });
                toggle.textContent = '+' + hiddenCount + ' khác';
            }
        });
        wrap.appendChild(toggle);
    });

    // ── Build URL + apply ──
    function buildFilterUrl() {
        var params = new URLSearchParams();
        var q = document.getElementById('sft-q');
        if (q && q.value.trim()) params.append('q', q.value.trim());

        sft.querySelectorAll('.sft-chip.on').forEach(function(chip) {
            var group = chip.dataset.group;
            var val = chip.dataset.val;
            params.append(group + '[]', val);
        });

        var str = params.toString().replace(/%5B/gi, '[').replace(/%5D/gi, ']');
        return target + (str ? '?' + str : '');
    }

    var applyBtn = document.getElementById('sft-apply');
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            window.location.href = buildFilterUrl();
        });
    }

    // Enter key on search
    var qInput = document.getElementById('sft-q');
    if (qInput) {
        qInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); window.location.href = buildFilterUrl(); }
        });
    }

    // ── Advanced filter toggle ──
    var advToggle = document.getElementById('sft-adv-toggle');
    var advBody = document.getElementById('sft-adv-body');
    if (advToggle && advBody) {
        advToggle.addEventListener('click', function() {
            var isOpen = advBody.style.display !== 'none';
            advBody.style.display = isOpen ? 'none' : '';
            advToggle.classList.toggle('open', !isOpen);
        });
        // Mark open if already visible
        if (advBody.style.display !== 'none') advToggle.classList.add('open');
    }
})();
