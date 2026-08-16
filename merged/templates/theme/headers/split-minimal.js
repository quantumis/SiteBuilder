// templates/theme/headers/split-minimal.js
(function() {
    'use strict';

    var header = document.querySelector('.sb-header-split-minimal');
    var toggle = document.querySelector('.sb-header-split-minimal-toggle');

    if (!header || !toggle) return;

    // Toggle sidebar on mobile
    toggle.addEventListener('click', function() {
        var isOpen = header.classList.contains('is-open');
        
        if (isOpen) {
            header.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        } else {
            header.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }
    });

    // Submenus are always visible, no accordion needed

    // Close sidebar on outside click (mobile only)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!header.contains(e.target) && !toggle.contains(e.target)) {
                header.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }
    });

    // Close on resize
    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                header.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }, 250);
    });
})();
