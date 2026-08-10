/* split-rail command-bar interactivity — vanilla JS, IIFE-scoped so it survives
   being concatenated with other variant scripts into one theme.js.
   Loaded in the footer, so the DOM is ready (no DOMContentLoaded needed). */
(function () {
    var header = document.querySelector('.sb-header-split-rail');
    if (!header) return;

    var trigger = header.querySelector('.sb-header-split-rail-trigger');
    var overlay = header.querySelector('.sb-header-split-rail-overlay');
    if (!trigger || !overlay) return;

    var panelItems = overlay.querySelectorAll('.sb-header-split-rail-overlay-nav > ul > li');

    function openMenu() {
        overlay.hidden = false;
        /* Force a reflow so the transition runs from the hidden state */
        void overlay.offsetWidth;
        overlay.classList.add('sb-sr-open');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('sb-sr-menu-lock');
        /* Staggered cascade: reveal each top-level item in turn */
        for (var i = 0; i < panelItems.length; i++) {
            (function (el, idx) {
                setTimeout(function () { el.classList.add('sb-sr-in'); }, 120 + idx * 55);
            })(panelItems[i], i);
        }
    }

    function closeMenu() {
        overlay.classList.remove('sb-sr-open');
        trigger.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('sb-sr-menu-lock');
        for (var i = 0; i < panelItems.length; i++) {
            panelItems[i].classList.remove('sb-sr-in');
        }
        /* Hide after the panel slide-out finishes */
        setTimeout(function () {
            if (!overlay.classList.contains('sb-sr-open')) overlay.hidden = true;
        }, 340);
    }

    trigger.addEventListener('click', function () {
        if (overlay.classList.contains('sb-sr-open')) { closeMenu(); } else { openMenu(); }
    });

    /* Close on backdrop / X (any element flagged with data-sr-close) */
    var closers = overlay.querySelectorAll('[data-sr-close]');
    for (var c = 0; c < closers.length; c++) {
        closers[c].addEventListener('click', closeMenu);
    }

    /* Close on Escape */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('sb-sr-open')) closeMenu();
    });

    /* Accordion: parent rows toggle their sub-menu instead of navigating */
    var parents = overlay.querySelectorAll('.menu-item-has-children > a');
    for (var p = 0; p < parents.length; p++) {
        parents[p].addEventListener('click', function (e) {
            var li = this.parentNode;
            var sub = li.querySelector(':scope > .sub-menu');
            if (!sub) return;
            e.preventDefault();
            var expanded = li.classList.toggle('sb-sr-expanded');
            sub.classList.toggle('sb-sr-sub-open', expanded);
        });
    }

    /* Subtle shadow on scroll */
    var onScroll = function () {
        if (window.scrollY > 4) { header.classList.add('sb-sr-scrolled'); }
        else { header.classList.remove('sb-sr-scrolled'); }
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
})();
