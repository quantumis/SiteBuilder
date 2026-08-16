// templates/theme/headers/liquid-underline.js
(function () {
    'use strict';

    var header = document.querySelector('.sb-header-liquid-underline');
    var toggle = document.querySelector('.sb-header-liquid-toggle');
    var nav = document.querySelector('.sb-header-liquid-nav');
    var menu = document.querySelector('.sb-header-liquid-menu');
    var indicator = document.querySelector('.sb-liquid-indicator');

    if (!header) return;

    // ---- liquid underline tracking ----
    if (indicator && menu && window.innerWidth > 900) {
        var topLevelLinks = Array.prototype.slice.call(
            menu.querySelectorAll(':scope > .sb-liquid-item > .sb-liquid-link, :scope > .sb-liquid-item > .sb-liquid-pill > .sb-liquid-pill-link')
        );

        function moveIndicatorTo(el) {
            var navRect = nav.getBoundingClientRect();
            var elRect = el.getBoundingClientRect();
            indicator.style.left = (elRect.left - navRect.left) + 'px';
            indicator.style.width = elRect.width + 'px';
            indicator.style.top = (elRect.bottom - navRect.top - 4) + 'px';
            nav.classList.add('sb-liquid-active');
        }

        var currentEl = menu.querySelector('.current-menu-item > .sb-liquid-link, .current-menu-item > .sb-liquid-pill > .sb-liquid-pill-link');

        topLevelLinks.forEach(function (link) {
            link.addEventListener('mouseenter', function () {
                moveIndicatorTo(link);
            });
        });

        menu.addEventListener('mouseleave', function () {
            if (currentEl) {
                moveIndicatorTo(currentEl);
            } else {
                nav.classList.remove('sb-liquid-active');
            }
        });

        if (currentEl) {
            // Delay to ensure layout is settled.
            setTimeout(function () { moveIndicatorTo(currentEl); }, 50);
        }

        window.addEventListener('resize', function () {
            var target = menu.querySelector(':hover') ? null : currentEl;
            if (target) moveIndicatorTo(target);
        });
    }

    // ---- dropdown logic ----
    var dropdownItems = Array.prototype.slice.call(document.querySelectorAll('.sb-liquid-item')).filter(function (li) {
        return li.querySelector('.sb-liquid-dropdown');
    });
    var openItem = null;
    var enterTimer = null;
    var leaveTimer = null;

    function openDropdown(li) {
        if (openItem === li) return;
        clearTimeout(leaveTimer);
        closeDropdown();

        var trigger = li.querySelector('.sb-liquid-trigger');
        li.classList.add('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        openItem = li;
    }

    function closeDropdown() {
        if (!openItem) return;
        var trigger = openItem.querySelector('.sb-liquid-trigger');
        openItem.classList.remove('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        openItem = null;
    }

    dropdownItems.forEach(function (li) {
        var trigger = li.querySelector('.sb-liquid-trigger');
        if (!trigger) return;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (window.innerWidth <= 900) {
                var isOpen = li.classList.contains('sb-dropdown-open');
                dropdownItems.forEach(function (other) {
                    if (other !== li) {
                        other.classList.remove('sb-dropdown-open');
                        var t = other.querySelector('.sb-liquid-trigger');
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
            var trigger = openItem.querySelector('.sb-liquid-trigger');
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
                    var t = item.querySelector('.sb-liquid-trigger');
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
