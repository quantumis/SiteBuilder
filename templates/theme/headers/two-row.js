/* two-row: burger toggle (mobile only) */
var trBurger = document.querySelector('.sb-header-tr-burger');
var trBottom = document.querySelector('.sb-header-tr-bottom');
if (trBurger && trBottom) {
    trBurger.addEventListener('click', function () {
        var open = trBottom.classList.toggle('sb-tr-open');
        trBurger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}
