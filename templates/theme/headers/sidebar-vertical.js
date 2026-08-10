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

    // Dropdown functionality for submenus
    var dropdownItems = Array.prototype.slice.call(document.querySelectorAll('.sb-sidebar-item.menu-item-has-children'));
    
    dropdownItems.forEach(function(li) {
        var trigger = li.querySelector('.sb-sidebar-trigger');
        if (!trigger) return;

        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var isOpen = li.classList.contains('sb-submenu-open');
            
            // Close all other submenus
            dropdownItems.forEach(function(other) {
                if (other !== li) {
                    other.classList.remove('sb-submenu-open');
                    var t = other.querySelector('.sb-sidebar-trigger');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });
            
            // Toggle current submenu
            li.classList.toggle('sb-submenu-open', !isOpen);
            trigger.setAttribute('aria-expanded', String(!isOpen));
        });
    });

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
