/* split-accent: isolated mobile menu toggle */
(function () {
    'use strict';

    var header = document.querySelector('.sb-header-split-accent');
    if (!header) return;

    var burger = header.querySelector('.sb-header-sa-burger');
    var nav = header.querySelector('#sb-sa-nav');
    if (!burger || !nav) return;

    function closeMenu() {
        nav.classList.remove('sb-sa-open');
        burger.setAttribute('aria-expanded', 'false');
    }

    burger.addEventListener('click', function () {
        var isOpen = nav.classList.toggle('sb-sa-open');
        burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
        if (!header.contains(event.target) && nav.classList.contains('sb-sa-open')) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && nav.classList.contains('sb-sa-open')) {
            closeMenu();
            burger.focus();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 800 && nav.classList.contains('sb-sa-open')) {
            closeMenu();
        }
    });
}());
