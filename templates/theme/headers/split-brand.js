// templates/theme/headers/split-brand.js
(function () {
    'use strict';

    var header = document.querySelector('.sb-header-split-brand');
    var toggle = document.querySelector('.sb-header-split-brand-toggle');
    var nav = document.querySelector('.sb-header-split-brand-nav');

    if (!header) return;

    var dropdownItems = Array.prototype.slice.call(document.querySelectorAll('.sb-split-brand-item')).filter(function (li) {
        return li.querySelector('.sb-split-brand-dropdown');
    });
    var openItem = null;
    var enterTimer = null;
    var leaveTimer = null;

    function openDropdown(li) {
        if (openItem === li) return;
        clearTimeout(leaveTimer);
        closeDropdown();

        var trigger = li.querySelector('.sb-split-brand-trigger');
        li.classList.add('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        openItem = li;
    }

    function closeDropdown() {
        if (!openItem) return;
        var trigger = openItem.querySelector('.sb-split-brand-trigger');
        openItem.classList.remove('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        openItem = null;
    }

    dropdownItems.forEach(function (li) {
        var trigger = li.querySelector('.sb-split-brand-trigger');
        if (!trigger) return;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (window.innerWidth <= 900) {
                var isOpen = li.classList.contains('sb-dropdown-open');
                dropdownItems.forEach(function (other) {
                    if (other !== li) {
                        other.classList.remove('sb-dropdown-open');
                        var t = other.querySelector('.sb-split-brand-trigger');
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
            var trigger = openItem.querySelector('.sb-split-brand-trigger');
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
                    var t = item.querySelector('.sb-split-brand-trigger');
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
