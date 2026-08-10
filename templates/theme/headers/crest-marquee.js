/* crest-marquee "Command Deck" interactivity — vanilla JS, IIFE-scoped so it
   survives being concatenated with other variant scripts into one theme.js.
   Loaded in the footer, so the DOM is ready (no DOMContentLoaded needed). */
(function () {
    var header = document.querySelector('.sb-header-crest-marquee');
    if (!header) return;

    var trigger = header.querySelector('.sb-header-crest-marquee-trigger');
    var deck = header.querySelector('.sb-header-crest-marquee-deck');
    if (!trigger || !deck) return;

    var rows = deck.querySelectorAll('.sb-header-crest-marquee-deck-nav > ul > li');

    function openDeck() {
        deck.hidden = false;
        void deck.offsetWidth;
        deck.classList.add('sb-cm-open');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('sb-cm-menu-lock');
        /* Radar sweep: rotate each row into place in turn */
        for (var i = 0; i < rows.length; i++) {
            (function (el, idx) {
                setTimeout(function () { el.classList.add('sb-cm-in'); }, 140 + idx * 65);
            })(rows[i], i);
        }
    }
    function closeDeck() {
        deck.classList.remove('sb-cm-open');
        trigger.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('sb-cm-menu-lock');
        for (var i = 0; i < rows.length; i++) { rows[i].classList.remove('sb-cm-in'); }
        setTimeout(function () {
            if (!deck.classList.contains('sb-cm-open')) deck.hidden = true;
        }, 420);
    }

    trigger.addEventListener('click', function () {
        if (deck.classList.contains('sb-cm-open')) { closeDeck(); } else { openDeck(); }
    });

    var closers = deck.querySelectorAll('[data-cm-close]');
    for (var c = 0; c < closers.length; c++) {
        closers[c].addEventListener('click', closeDeck);
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && deck.classList.contains('sb-cm-open')) closeDeck();
    });

    /* Accordion: parent rows toggle their sub-menu instead of navigating */
    var parents = deck.querySelectorAll('.menu-item-has-children > a');
    for (var p = 0; p < parents.length; p++) {
        parents[p].addEventListener('click', function (e) {
            var li = this.parentNode;
            var sub = li.querySelector(':scope > .sub-menu');
            if (!sub) return;
            e.preventDefault();
            var expanded = li.classList.toggle('sb-cm-expanded');
            sub.classList.toggle('sb-cm-sub-open', expanded);
        });
    }

    var onScroll = function () {
        if (window.scrollY > 4) { header.classList.add('sb-cm-scrolled'); }
        else { header.classList.remove('sb-cm-scrolled'); }
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
})();
