/* sticky-slim: sticky shadow on scroll + burger toggle */
var slimHeader = document.querySelector('.sb-header-slim');
if (slimHeader) {
    var onScroll = function () {
        if (window.scrollY > 4) slimHeader.classList.add('sb-slim-scrolled');
        else slimHeader.classList.remove('sb-slim-scrolled');
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}
var slimBurger = document.querySelector('.sb-header-slim-burger');
var slimNav    = document.getElementById('sb-slim-nav');
if (slimBurger && slimNav) {
    slimBurger.addEventListener('click', function () {
        var open = slimNav.classList.toggle('sb-slim-open');
        slimBurger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (!slimHeader.contains(e.target) && slimNav.classList.contains('sb-slim-open')) {
            slimNav.classList.remove('sb-slim-open');
            slimBurger.setAttribute('aria-expanded', 'false');
        }
    });
}
