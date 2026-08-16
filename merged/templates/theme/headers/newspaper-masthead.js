// templates/theme/headers/newspaper-masthead.js
(function () {
    'use strict';

    var header = document.querySelector('.sb-header-newspaper-masthead');
    var toggle = document.querySelector('.sb-header-newspaper-toggle');
    var menu = document.querySelector('.sb-header-newspaper-menu');

    if (!header) return;

    // Server-rendered date is baked in at cache time and can go stale on
    // cached pages. Overwrite with the visitor's actual local date.
    var dateEl = document.querySelector('.sb-newspaper-date[data-sb-live-date]');
    if (dateEl) {
        var locale = dateEl.getAttribute('data-locale') || undefined;
        try {
            var formatted = new Date().toLocaleDateString(locale, {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
            dateEl.textContent = formatted;
        } catch (e) {
            // Keep server-rendered fallback on unsupported locales.
        }
    }

    var dropdownItems = Array.prototype.slice.call(document.querySelectorAll('.sb-newspaper-item')).filter(function (li) {
        return li.querySelector('.sb-newspaper-dropdown');
    });
    var openItem = null;
    var enterTimer = null;
    var leaveTimer = null;

    function openDropdown(li) {
        if (openItem === li) return;
        clearTimeout(leaveTimer);
        closeDropdown();

        var trigger = li.querySelector('.sb-newspaper-trigger');
        li.classList.add('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        openItem = li;
    }

    function closeDropdown() {
        if (!openItem) return;
        var trigger = openItem.querySelector('.sb-newspaper-trigger');
        openItem.classList.remove('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        openItem = null;
    }

    dropdownItems.forEach(function (li) {
        var trigger = li.querySelector('.sb-newspaper-trigger');
        if (!trigger) return;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (window.innerWidth <= 900) {
                var isOpen = li.classList.contains('sb-dropdown-open');
                dropdownItems.forEach(function (other) {
                    if (other !== li) {
                        other.classList.remove('sb-dropdown-open');
                        var t = other.querySelector('.sb-newspaper-trigger');
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
            var trigger = openItem.querySelector('.sb-newspaper-trigger');
            closeDropdown();
            if (trigger) trigger.focus();
        }
    });

    document.addEventListener('click', function (e) {
        if (openItem && !openItem.contains(e.target)) closeDropdown();
    });

    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            var isOpen = menu.classList.contains('is-open');

            if (isOpen) {
                menu.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                dropdownItems.forEach(function (item) {
                    item.classList.remove('sb-dropdown-open');
                    var t = item.querySelector('.sb-newspaper-trigger');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            } else {
                menu.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    }

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > 900 && menu && menu.classList.contains('is-open')) {
                menu.classList.remove('is-open');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        }, 200);
    });
})();