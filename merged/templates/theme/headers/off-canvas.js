/* off-canvas: panel toggle + accordion for nested menus */
var ocBurger = document.querySelector('.sb-header-oc-burger');
var ocPanel  = document.getElementById('sb-oc-panel');
var ocBackdrop = document.querySelector('.sb-header-oc-backdrop');
var ocClose  = document.querySelector('.sb-header-oc-panel-close');

function ocOpen()  {
    if (!ocPanel) return;
    ocPanel.removeAttribute('hidden');
    ocBackdrop.removeAttribute('hidden');
    // Delay class add so transition fires
    requestAnimationFrame(function () {
        ocPanel.classList.add('sb-oc-open');
        ocBackdrop.classList.add('sb-oc-open');
    });
    ocBurger.setAttribute('aria-expanded', 'true');
    ocPanel.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function ocClosePanel() {
    if (!ocPanel) return;
    ocPanel.classList.remove('sb-oc-open');
    ocBackdrop.classList.remove('sb-oc-open');
    setTimeout(function () {
        ocPanel.setAttribute('hidden', '');
        ocBackdrop.setAttribute('hidden', '');
    }, 250);
    ocBurger.setAttribute('aria-expanded', 'false');
    ocPanel.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
if (ocBurger) ocBurger.addEventListener('click', ocOpen);
if (ocClose) ocClose.addEventListener('click', ocClosePanel);
if (ocBackdrop) ocBackdrop.addEventListener('click', ocClosePanel);
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && ocPanel && !ocPanel.hasAttribute('hidden')) ocClosePanel();
});

/* Accordion for sub-menus in the off-canvas panel — click parent to expand */
var ocParents = document.querySelectorAll('.sb-header-oc-nav .menu-item-has-children > a');
ocParents.forEach(function (a) {
    a.addEventListener('click', function (e) {
        // Only toggle if the link doesn't actually go somewhere useful
        // (kept simple: always toggle, since submenu items provide targets)
        e.preventDefault();
        var li = a.parentElement;
        li.classList.toggle('sb-oc-open');
    });
});
