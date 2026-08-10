// templates/theme/headers/floating-circle.js
(function () {
    'use strict';

    var header = document.querySelector('.sb-header-floating-circle');
    var backdrop = document.querySelector('.sb-mega-backdrop');
    var shell = document.querySelector('.sb-mega-shell');
    var megaContent = shell ? shell.querySelector('.sb-mega-grid') : null;
    var toggle = document.querySelector('.sb-header-floating-circle-toggle');
    var mobileMenu = document.querySelector('.sb-header-floating-circle-mobile-menu');
    var mobileList = mobileMenu ? mobileMenu.querySelector('.sb-menu') : null;

    if (!header || !backdrop || !shell || !megaContent || !toggle || !mobileMenu || !mobileList) return;

    var triggerLis = Array.prototype.slice.call(header.querySelectorAll('.sb-header-floating-circle-nav .sb-menu > li')).filter(function (li) {
        return li.querySelector(':scope > .sb-mega-grid-data');
    });
    var openLi = null;
    var enterTimer = null;
    var leaveTimer = null;

    // ---- header height -> CSS var, so the mega panel always sits right
    // below the capsule regardless of how tall it currently is
    function syncHeaderHeight() {
        var h = header.getBoundingClientRect().height;
        document.documentElement.style.setProperty('--sb-header-h', h + 'px');
    }
    if (window.ResizeObserver) {
        new ResizeObserver(syncHeaderHeight).observe(header);
    } else {
        window.addEventListener('resize', syncHeaderHeight);
    }
    syncHeaderHeight();

    // ---- desktop mega menu ----
    function openMega(li) {
        if (openLi === li) return;
        clearTimeout(leaveTimer);
        closeMega(true);

        var data = li.querySelector(':scope > .sb-mega-grid-data');
        var trigger = li.querySelector(':scope > .sb-item-group > .sb-trigger');
        if (!data || !trigger) return;

        megaContent.innerHTML = data.innerHTML;
        li.classList.add('sb-mega-active');
        trigger.setAttribute('aria-expanded', 'true');
        shell.classList.add('is-open');
        backdrop.classList.add('is-open');
        header.classList.add('sb-mega-open');
        openLi = li;
    }

    function closeMega(keepConnector) {
        if (!openLi) return;
        var trigger = openLi.querySelector(':scope > .sb-item-group > .sb-trigger');
        openLi.classList.remove('sb-mega-active');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        shell.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        if (!keepConnector) header.classList.remove('sb-mega-open');
        openLi = null;
    }

    triggerLis.forEach(function (li) {
        var trigger = li.querySelector(':scope > .sb-item-group > .sb-trigger');
        var parentLink = li.querySelector(':scope > .sb-item-group > .sb-parent-link');
        if (!trigger || !parentLink) return;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            if (openLi === li) {
                closeMega();
                trigger.focus();
            } else {
                openMega(li);
            }
        });

        li.addEventListener('mouseenter', function () {
            if (window.matchMedia('(hover: hover)').matches) {
                clearTimeout(leaveTimer);
                clearTimeout(enterTimer);
                enterTimer = setTimeout(function () { openMega(li); }, 100);
            }
        });
        li.addEventListener('mouseleave', function () {
            if (window.matchMedia('(hover: hover)').matches) {
                clearTimeout(enterTimer);
                leaveTimer = setTimeout(function () { closeMega(); }, 250);
            }
        });

        trigger.addEventListener('focus', function () { openMega(li); });
        parentLink.addEventListener('focus', function () { openMega(li); });
    });

    shell.addEventListener('mouseenter', function () { clearTimeout(leaveTimer); });
    shell.addEventListener('mouseleave', function () {
        leaveTimer = setTimeout(function () { closeMega(); }, 250);
    });

    backdrop.addEventListener('click', function () { closeMega(); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openLi) {
            var trigger = openLi.querySelector(':scope > .sb-item-group > .sb-trigger');
            closeMega();
            if (trigger) trigger.focus();
        }
    });

    document.addEventListener('click', function (e) {
        if (openLi && !header.contains(e.target) && !shell.contains(e.target)) closeMega();
    });
    document.addEventListener('focusin', function (e) {
        if (openLi && !header.contains(e.target) && !shell.contains(e.target)) closeMega();
    });

    // ---- scroll effect ----
    window.addEventListener('scroll', function () {
        header.classList.toggle('scrolled', window.pageYOffset > 100);
    }, { passive: true });

    // ---- mobile accordion, built from the same source markup so desktop
    // and mobile can never drift out of sync with each other ----
    function buildMobileMenu() {
        mobileList.innerHTML = '';
        var sourceLis = header.querySelectorAll('.sb-header-floating-circle-nav .sb-menu > li');
        var processedItems = {};

        sourceLis.forEach(function (li) {
            // Получаем уникальный идентификатор пункта меню (по href)
            var plainLink = li.querySelector(':scope > a');
            var group = li.querySelector(':scope > .sb-item-group');
            var itemKey = null;
            
            if (plainLink) {
                itemKey = plainLink.getAttribute('href');
            } else if (group) {
                var parentLink = group.querySelector('.sb-parent-link');
                if (parentLink) {
                    itemKey = parentLink.getAttribute('href');
                }
            }
            
            // Пропускаем, если уже обработали этот пункт
            if (itemKey && processedItems[itemKey]) {
                return;
            }
            if (itemKey) {
                processedItems[itemKey] = true;
            }
            
            var clone = document.createElement('li');
            var data = li.querySelector(':scope > .sb-mega-grid-data');

            if (plainLink) {
                clone.appendChild(plainLink.cloneNode(true));
                mobileList.appendChild(clone);
                return;
            }
            if (!group || !data) return;

            var newGroup = document.createElement('span');
            newGroup.className = 'sb-item-group';

            var newLink = group.querySelector('.sb-parent-link').cloneNode(true);
            var sourceTrigger = group.querySelector('.sb-trigger');
            var sourceCaret = group.querySelector('.sb-trigger-caret');

            var newBtn = document.createElement('button');
            newBtn.type = 'button';
            newBtn.className = 'sb-trigger';
            newBtn.setAttribute('aria-expanded', 'false');
            if (sourceTrigger) {
                newBtn.setAttribute('aria-label', sourceTrigger.getAttribute('aria-label') || '');
            }
            if (sourceCaret) {
                newBtn.innerHTML = sourceCaret.outerHTML;
            }

            newGroup.appendChild(newLink);
            newGroup.appendChild(newBtn);
            clone.appendChild(newGroup);

            var sub = document.createElement('div');
            sub.className = 'sb-mobile-submenu';
            var subInner = document.createElement('div');
            subInner.className = 'sb-mobile-submenu-inner';
            var grid = document.createElement('div');
            grid.className = 'sb-mega-grid';
            grid.innerHTML = data.innerHTML;
            subInner.appendChild(grid);
            sub.appendChild(subInner);
            clone.appendChild(sub);

            newBtn.addEventListener('click', function () {
                var isOpen = clone.classList.contains('sb-mega-active');
                mobileList.querySelectorAll('li.sb-mega-active').forEach(function (openItem) {
                    openItem.classList.remove('sb-mega-active');
                    var b = openItem.querySelector('.sb-trigger');
                    if (b) b.setAttribute('aria-expanded', 'false');
                });
                if (!isOpen) {
                    clone.classList.add('sb-mega-active');
                    newBtn.setAttribute('aria-expanded', 'true');
                }
            });

            mobileList.appendChild(clone);
        });
    }
    buildMobileMenu();

    toggle.addEventListener('click', function () {
        var isOpen = mobileMenu.classList.contains('is-open');
        mobileMenu.classList.toggle('is-open', !isOpen);
        toggle.setAttribute('aria-expanded', String(!isOpen));
        if (isOpen) {
            mobileList.querySelectorAll('li.sb-mega-active').forEach(function (li) {
                li.classList.remove('sb-mega-active');
            });
        }
    });
})();