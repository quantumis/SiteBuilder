/* pill-nav: burger toggle + outside-click close (mobile) */
var pillHeader = document.querySelector('.sb-header-pill');
var pillBurger = document.querySelector('.sb-header-pill-burger');
var pillNav    = document.getElementById('sb-pill-nav');
if (pillBurger && pillNav) {
    pillBurger.addEventListener('click', function () {
        var open = pillNav.classList.toggle('sb-pill-open');
        pillBurger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (pillHeader && !pillHeader.contains(e.target) && pillNav.classList.contains('sb-pill-open')) {
            pillNav.classList.remove('sb-pill-open');
            pillBurger.setAttribute('aria-expanded', 'false');
        }
    });
}
