/* beacon-bar "Live Wire" interactivity — vanilla JS, IIFE-scoped so it survives
   being concatenated with other variant scripts into one theme.js.
   Loaded in the footer, so the DOM is ready (no DOMContentLoaded needed). */
(function () {
    var header = document.querySelector('.sb-header-beacon-bar');
    if (!header) return;

    var trigger = header.querySelector('.sb-header-beacon-bar-trigger');
    var signal = header.querySelector('.sb-header-beacon-bar-signal');
    var wireLive = header.querySelector('.sb-header-beacon-bar-wire-live');

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---- Live oscilloscope wave ----
       Build a smooth waveform across the 0..600 viewBox and animate a travelling
       phase so the line "breathes". A hover spike parameter bumps amplitude near
       a target x for a broadcast-pulse feel. */
    if (wireLive && !reduceMotion) {
        var W = 600, H = 40, mid = 20, points = 60;
        var phase = 0;
        var spikeX = null, spikeAmp = 0;

        function buildPath() {
            var d = '';
            for (var i = 0; i <= points; i++) {
                var x = (W / points) * i;
                var base = Math.sin((i / points) * Math.PI * 6 + phase) * 3;
                var ripple = Math.sin((i / points) * Math.PI * 14 - phase * 1.7) * 1.4;
                var y = mid + base + ripple;
                if (spikeX !== null) {
                    var dist = Math.abs(x - spikeX);
                    if (dist < 60) {
                        var k = (1 - dist / 60);
                        y -= spikeAmp * k * k * 12;
                    }
                }
                d += (i === 0 ? 'M' : 'L') + x.toFixed(1) + ',' + y.toFixed(1) + ' ';
            }
            wireLive.setAttribute('d', d.trim());
        }

        function tick() {
            phase += 0.06;
            if (spikeAmp > 0) { spikeAmp *= 0.92; if (spikeAmp < 0.02) { spikeAmp = 0; spikeX = null; } }
            buildPath();
            requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);

        /* Hover on inline menu / signal items spikes the wave */
        header.addEventListener('mouseover', function (e) {
            var a = e.target.closest('a');
            if (!a) return;
            var rect = header.getBoundingClientRect();
            var wireRect = header.querySelector('.sb-header-beacon-bar-wire').getBoundingClientRect();
            var cx = e.clientX - wireRect.left;
            spikeX = Math.max(0, Math.min(W, (cx / wireRect.width) * W));
            spikeAmp = 1;
        });
    }

    /* ---- Signal panel open / close ---- */
    if (trigger && signal) {
        var rows = signal.querySelectorAll('.sb-header-beacon-bar-signal-nav > ul > li');

        var openSignal = function () {
            signal.hidden = false;
            void signal.offsetWidth;
            signal.classList.add('sb-bb-open');
            trigger.setAttribute('aria-expanded', 'true');
            document.body.classList.add('sb-bb-menu-lock');
            for (var i = 0; i < rows.length; i++) {
                (function (el, idx) {
                    setTimeout(function () { el.classList.add('sb-bb-in'); }, 120 + idx * 55);
                })(rows[i], i);
            }
        };
        var closeSignal = function () {
            signal.classList.remove('sb-bb-open');
            trigger.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('sb-bb-menu-lock');
            for (var i = 0; i < rows.length; i++) { rows[i].classList.remove('sb-bb-in'); }
            setTimeout(function () {
                if (!signal.classList.contains('sb-bb-open')) signal.hidden = true;
            }, 380);
        };

        trigger.addEventListener('click', function () {
            if (signal.classList.contains('sb-bb-open')) { closeSignal(); } else { openSignal(); }
        });

        var closers = signal.querySelectorAll('[data-bb-close]');
        for (var c = 0; c < closers.length; c++) {
            closers[c].addEventListener('click', closeSignal);
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && signal.classList.contains('sb-bb-open')) closeSignal();
        });

        var parents = signal.querySelectorAll('.menu-item-has-children > a');
        for (var p = 0; p < parents.length; p++) {
            parents[p].addEventListener('click', function (e) {
                var li = this.parentNode;
                var sub = li.querySelector(':scope > .sub-menu');
                if (!sub) return;
                e.preventDefault();
                var expanded = li.classList.toggle('sb-bb-expanded');
                sub.classList.toggle('sb-bb-sub-open', expanded);
            });
        }
    }

    /* ---- Scroll shadow ---- */
    var onScroll = function () {
        if (window.scrollY > 4) { header.classList.add('sb-bb-scrolled'); }
        else { header.classList.remove('sb-bb-scrolled'); }
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
})();
