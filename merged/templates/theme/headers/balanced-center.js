// templates/theme/headers/balanced-center.js
(function () {
    'use strict';

    var header = document.querySelector('.sb-header-balanced-center');
    var toggle = document.querySelector('.sb-header-balanced-center-toggle');
    var nav = document.querySelector('.sb-header-balanced-center-nav');

    if (!header || !toggle || !nav) return;

    var dropdownItems = Array.prototype.slice.call(nav.querySelectorAll('.sb-balanced-item')).filter(function (li) {
        return li.querySelector(':scope > .sb-balanced-dropdown');
    });
    var openItem = null;
    var enterTimer = null;
    var leaveTimer = null;

    // ---- scroll effect ----
    window.addEventListener('scroll', function () {
        header.classList.toggle('scrolled', window.pageYOffset > 50);
    }, { passive: true });

    // ---- desktop dropdown ----
    function openDropdown(li) {
        if (openItem === li) return;
        clearTimeout(leaveTimer);
        closeDropdown();

        var trigger = li.querySelector(':scope > .sb-balanced-pill > .sb-balanced-trigger');
        li.classList.add('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        openItem = li;
    }

    function closeDropdown() {
        if (!openItem) return;
        var trigger = openItem.querySelector(':scope > .sb-balanced-pill > .sb-balanced-trigger');
        openItem.classList.remove('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        openItem = null;
    }

    dropdownItems.forEach(function (li) {
        var trigger = li.querySelector(':scope > .sb-balanced-pill > .sb-balanced-trigger');
        var link = li.querySelector(':scope > .sb-balanced-pill > .sb-balanced-link');
        if (!trigger || !link) return;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Mobile behavior
            if (window.innerWidth <= 768) {
                var isOpen = li.classList.contains('sb-dropdown-open');
                
                // Close all other dropdowns
                dropdownItems.forEach(function (other) {
                    if (other !== li) {
                        other.classList.remove('sb-dropdown-open');
                        var t = other.querySelector('.sb-balanced-trigger');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    }
                });
                
                // Toggle current dropdown
                li.classList.toggle('sb-dropdown-open', !isOpen);
                trigger.setAttribute('aria-expanded', String(!isOpen));
            } else {
                // Desktop behavior
                if (openItem === li) {
                    closeDropdown();
                    trigger.focus();
                } else {
                    openDropdown(li);
                }
            }
        });

        li.addEventListener('mouseenter', function () {
            if (window.matchMedia('(hover: hover)').matches && window.innerWidth > 768) {
                clearTimeout(leaveTimer);
                clearTimeout(enterTimer);
                enterTimer = setTimeout(function () { openDropdown(li); }, 100);
            }
        });
        li.addEventListener('mouseleave', function () {
            if (window.matchMedia('(hover: hover)').matches && window.innerWidth > 768) {
                clearTimeout(enterTimer);
                leaveTimer = setTimeout(function () { closeDropdown(); }, 220);
            }
        });

        trigger.addEventListener('focus', function () {
            if (window.innerWidth > 768) openDropdown(li);
        });
        link.addEventListener('focus', function () {
            if (window.innerWidth > 768) openDropdown(li);
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openItem) {
            var trigger = openItem.querySelector(':scope > .sb-balanced-pill > .sb-balanced-trigger');
            closeDropdown();
            if (trigger) trigger.focus();
        }
    });

    document.addEventListener('click', function (e) {
        if (openItem && !openItem.contains(e.target)) closeDropdown();
    });
    document.addEventListener('focusin', function (e) {
        if (openItem && !openItem.contains(e.target) && !header.contains(e.target)) closeDropdown();
    });

    // ---- mobile burger + accordion ----
    toggle.addEventListener('click', function () {
        var isOpen = nav.classList.contains('is-open');

        if (isOpen) {
            nav.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            dropdownItems.forEach(function (item) {
                item.classList.remove('sb-dropdown-open');
                var t = item.querySelector('.sb-balanced-trigger');
                if (t) t.setAttribute('aria-expanded', 'false');
            });
        } else {
            nav.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }
    });

    // mobile: tapping the caret toggles that item's accordion panel
    // (этот код теперь не нужен, так как обработка уже есть выше)

    // close mobile menu on resize back to desktop
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > 768) {
                if (nav.classList.contains('is-open')) {
                    nav.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
                dropdownItems.forEach(function (item) {
                    item.classList.remove('sb-dropdown-open');
                    var t = item.querySelector('.sb-balanced-trigger');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
                closeDropdown();
            }
        }, 200);
    });
})();