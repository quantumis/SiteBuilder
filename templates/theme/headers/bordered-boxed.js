/* bordered-boxed: burger toggle + outside-click close (mobile) */
var bbHeader = document.querySelector('.sb-header-bb');
var bbBurger = document.querySelector('.sb-header-bb-burger');
var bbNav    = document.getElementById('sb-bb-nav');
if (bbBurger && bbNav) {
    bbBurger.addEventListener('click', function () {
        var open = bbNav.classList.toggle('sb-bb-open');
        bbBurger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (bbHeader && !bbHeader.contains(e.target) && bbNav.classList.contains('sb-bb-open')) {
            bbNav.classList.remove('sb-bb-open');
            bbBurger.setAttribute('aria-expanded', 'false');
        }
    });
}
