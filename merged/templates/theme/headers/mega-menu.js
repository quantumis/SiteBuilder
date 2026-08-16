/* mega-menu: burger toggle + outside-click close (mobile) */
var megaHeader = document.querySelector('.sb-header-mega');
var megaBurger = document.querySelector('.sb-header-mega-burger');
var megaNav    = document.getElementById('sb-mega-nav');
if (megaBurger && megaNav) {
    megaBurger.addEventListener('click', function () {
        var open = megaNav.classList.toggle('sb-mega-open');
        megaBurger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (megaHeader && !megaHeader.contains(e.target) && megaNav.classList.contains('sb-mega-open')) {
            megaNav.classList.remove('sb-mega-open');
            megaBurger.setAttribute('aria-expanded', 'false');
        }
    });
}
