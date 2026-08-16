// templates/theme/headers/split-flap-board.js
(function () {
    'use strict';

    var header = document.querySelector('.sb-header-split-flap-board');
    var toggle = document.querySelector('.sb-header-flap-toggle');
    var nav = document.querySelector('.sb-header-flap-nav');

    if (!header) return;

    var FLIP_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#$%&*'.split('');
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var isDesktop = window.innerWidth > 900;

    function makeTile(targetChar) {
        var tile = document.createElement('span');
        tile.className = 'sb-flap-tile' + (targetChar === ' ' ? ' sb-flap-tile-space' : '');

        var current = document.createElement('span');
        current.className = 'sb-flap-tile-char sb-flap-tile-char-current';
        current.textContent = targetChar === ' ' ? '' : targetChar;

        var incoming = document.createElement('span');
        incoming.className = 'sb-flap-tile-char sb-flap-tile-char-incoming';
        incoming.textContent = targetChar === ' ' ? '' : targetChar;

        tile.appendChild(current);
        tile.appendChild(incoming);
        tile.dataset.target = targetChar;
        return tile;
    }

    function buildWord(el) {
        var text = el.getAttribute('data-flap-text') || '';
        el.innerHTML = '';
        var tiles = [];
        text.split('').forEach(function (ch) {
            var tile = makeTile(ch);
            el.appendChild(tile);
            tiles.push(tile);
        });
        el.__flapTiles = tiles;
        return tiles;
    }

    function randomChar() {
        return FLIP_CHARS[Math.floor(Math.random() * FLIP_CHARS.length)];
    }

    // Plays a short shuffle before landing on the real character.
    function shuffleTile(tile, delay) {
        if (reduceMotion) return;
        var target = tile.dataset.target;
        if (target === ' ') return;

        var current = tile.querySelector('.sb-flap-tile-char-current');
        var incoming = tile.querySelector('.sb-flap-tile-char-incoming');
        var steps = 2 + Math.floor(Math.random() * 2);
        var i = 0;

        setTimeout(function runStep() {
            if (i < steps) {
                incoming.textContent = randomChar();
                tile.classList.add('is-flipping');
                setTimeout(function () {
                    current.textContent = incoming.textContent;
                    tile.classList.remove('is-flipping');
                    i++;
                    runStep();
                }, 160);
            } else {
                incoming.textContent = target;
                tile.classList.add('is-flipping');
                setTimeout(function () {
                    current.textContent = target;
                    tile.classList.remove('is-flipping');
                }, 160);
            }
        }, delay);
    }

    function playWord(el, staggerMs) {
        var tiles = el.__flapTiles || buildWord(el);
        tiles.forEach(function (tile, idx) {
            shuffleTile(tile, idx * (staggerMs || 22));
        });
    }

    // Build all flap words up front (static, no animation) so content
    // is present immediately even if JS animation is skipped.
    var allWords = Array.prototype.slice.call(document.querySelectorAll('.sb-flap-word'));
    allWords.forEach(function (el) {
        buildWord(el);
    });

    // Play the intro flip sequence once on load (desktop only, perf).
    if (isDesktop && !reduceMotion) {
        window.setTimeout(function () {
            allWords.forEach(function (el, wordIdx) {
                playWord(el, 20);
            });
        }, 150);

        // Re-shuffle a menu item's word on hover for a "live board" feel.
        var navLinks = document.querySelectorAll('.sb-flap-link, .sb-flap-pill-link');
        navLinks.forEach(function (link) {
            var word = link.querySelector('.sb-flap-word');
            if (!word) return;
            link.addEventListener('mouseenter', function () {
                playWord(word, 18);
            });
        });
    }

    // ---- dropdown logic ----
    var dropdownItems = Array.prototype.slice.call(document.querySelectorAll('.sb-flap-item')).filter(function (li) {
        return li.querySelector('.sb-flap-dropdown');
    });
    var openItem = null;
    var enterTimer = null;
    var leaveTimer = null;

    function openDropdown(li) {
        if (openItem === li) return;
        clearTimeout(leaveTimer);
        closeDropdown();

        var trigger = li.querySelector('.sb-flap-trigger');
        li.classList.add('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        openItem = li;
    }

    function closeDropdown() {
        if (!openItem) return;
        var trigger = openItem.querySelector('.sb-flap-trigger');
        openItem.classList.remove('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        openItem = null;
    }

    dropdownItems.forEach(function (li) {
        var trigger = li.querySelector('.sb-flap-trigger');
        if (!trigger) return;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (window.innerWidth <= 900) {
                var isOpen = li.classList.contains('sb-dropdown-open');
                dropdownItems.forEach(function (other) {
                    if (other !== li) {
                        other.classList.remove('sb-dropdown-open');
                        var t = other.querySelector('.sb-flap-trigger');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    }
                });
                li.classList.toggle('sb-dropdown-open', !isOpen);
                trigger.setAttribute('aria-expanded', String(!isOpen));
            } else {
                if (openItem === li) {
                    closeDropdown();
                    trigger.focus();
                } else {
                    openDropdown(li);
                }
            }
        });

        if (window.innerWidth > 900) {
            li.addEventListener('mouseenter', function () {
                clearTimeout(leaveTimer);
                clearTimeout(enterTimer);
                enterTimer = setTimeout(function () { openDropdown(li); }, 100);
            });

            li.addEventListener('mouseleave', function () {
                clearTimeout(enterTimer);
                leaveTimer = setTimeout(function () { closeDropdown(); }, 220);
            });
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openItem) {
            var trigger = openItem.querySelector('.sb-flap-trigger');
            closeDropdown();
            if (trigger) trigger.focus();
        }
    });

    document.addEventListener('click', function (e) {
        if (openItem && !openItem.contains(e.target)) closeDropdown();
    });

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.contains('is-open');

            if (isOpen) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                dropdownItems.forEach(function (item) {
                    item.classList.remove('sb-dropdown-open');
                    var t = item.querySelector('.sb-flap-trigger');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            } else {
                nav.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    }

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > 900 && nav && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        }, 200);
    });
})();
