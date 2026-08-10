/* edge-frame: isolated mobile toggle */
(function () {
    'use strict';
    var header = document.querySelector('.sb-header-edge-frame');
    if (!header) return;
    var burger = header.querySelector('.sb-header-ef-burger');
    var nav = header.querySelector('#sb-ef-nav');
    if (!burger || !nav) return;

    function closeMenu() {
        nav.classList.remove('sb-ef-open');
        burger.setAttribute('aria-expanded', 'false');
    }

    burger.addEventListener('click', function () {
        var open = nav.classList.toggle('sb-ef-open');
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (!header.contains(e.target)) closeMenu();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeMenu(); burger.focus(); }
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 800) closeMenu();
    });
}());
