/* underline-tabs: burger toggle + outside-click close (mobile) */
var utHeader = document.querySelector('.sb-header-ut');
var utBurger = document.querySelector('.sb-header-ut-burger');
var utNav    = document.getElementById('sb-ut-nav');
if (utBurger && utNav) {
    utBurger.addEventListener('click', function () {
        var open = utNav.classList.toggle('sb-ut-open');
        utBurger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (utHeader && !utHeader.contains(e.target) && utNav.classList.contains('sb-ut-open')) {
            utNav.classList.remove('sb-ut-open');
            utBurger.setAttribute('aria-expanded', 'false');
        }
    });
}
