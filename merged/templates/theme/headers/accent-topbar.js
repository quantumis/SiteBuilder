/* accent-topbar: burger toggle + outside-click close (mobile) */
var atHeader = document.querySelector('.sb-header-at');
var atBurger = document.querySelector('.sb-header-at-burger');
var atNav    = document.getElementById('sb-at-nav');
if (atBurger && atNav) {
    atBurger.addEventListener('click', function () {
        var open = atNav.classList.toggle('sb-at-open');
        atBurger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (atHeader && !atHeader.contains(e.target) && atNav.classList.contains('sb-at-open')) {
            atNav.classList.remove('sb-at-open');
            atBurger.setAttribute('aria-expanded', 'false');
        }
    });
}
