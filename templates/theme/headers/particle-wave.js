// templates/theme/headers/particle-wave.js
(function () {
    'use strict';

    var header = document.querySelector('.sb-header-particle-wave');
    var canvas = document.querySelector('.sb-header-particle-canvas');
    var toggle = document.querySelector('.sb-header-particle-toggle');
    var nav = document.querySelector('.sb-header-particle-nav');

    if (!header || !canvas) return;

    var ctx = canvas.getContext('2d');
    var particles = [];
    var mouse = { x: null, y: null, radius: 100 };
    var particleCount = 80;
    
    // Get particle count based on screen size
    function getParticleCount() {
        return window.innerWidth <= 768 ? 60 : 80;
    }
    
    // Get connection distance based on screen size
    function getConnectionDistance() {
        return window.innerWidth <= 768 ? 110 : 150;
    }

    // Get computed color from CSS variables
    function getParticleColor() {
        var style = getComputedStyle(document.documentElement);
        var color = style.getPropertyValue('--sb-color-link').trim() || '#2563eb';
        return color;
    }

    // Set canvas size
    function resizeCanvas() {
        canvas.width = header.offsetWidth;
        canvas.height = header.offsetHeight;
        particleCount = getParticleCount();
        initParticles();
    }

    // Particle class with Poisson Disk Sampling
    function Particle(existingParticles) {
        var minDistance = 25; // Minimum distance between particles
        var maxAttempts = 30;
        var attempt = 0;
        var valid = false;
        
        while (!valid && attempt < maxAttempts) {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            
            valid = true;
            // Check distance to all existing particles
            for (var i = 0; i < existingParticles.length; i++) {
                var dx = this.x - existingParticles[i].x;
                var dy = this.y - existingParticles[i].y;
                var distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < minDistance) {
                    valid = false;
                    break;
                }
            }
            attempt++;
        }
        
        // Adaptive particle size
        var maxSize = window.innerWidth <= 768 ? 3.2 : 4;
        var minSize = window.innerWidth <= 768 ? 1.2 : 1;
        this.size = Math.random() * (maxSize - minSize) + minSize;
        
        this.baseX = this.x;
        this.baseY = this.y;
        this.density = Math.random() * 30 + 10;
        
        // Adaptive speed
        var speed = window.innerWidth <= 768 ? 0.15 : 0.45;
        this.speedX = (Math.random() - 0.5) * speed;
        this.speedY = (Math.random() - 0.5) * speed;
    }

    Particle.prototype.update = function () {
        // Mouse interaction
        var dx = mouse.x - this.x;
        var dy = mouse.y - this.y;
        var distance = Math.sqrt(dx * dx + dy * dy);
        var forceDirectionX = dx / distance;
        var forceDirectionY = dy / distance;
        var maxDistance = mouse.radius;
        var force = (maxDistance - distance) / maxDistance;
        var directionX = forceDirectionX * force * this.density;
        var directionY = forceDirectionY * force * this.density;

        if (distance < mouse.radius) {
            this.x -= directionX;
            this.y -= directionY;
        } else {
            if (this.x !== this.baseX) {
                var dx = this.x - this.baseX;
                this.x -= dx / 10;
            }
            if (this.y !== this.baseY) {
                var dy = this.y - this.baseY;
                this.y -= dy / 10;
            }
        }

        // Natural movement with bounce instead of wrap
        this.baseX += this.speedX;
        this.baseY += this.speedY;

        // Bounce off edges instead of wrapping
        if (this.baseX <= 0 || this.baseX >= canvas.width) {
            this.speedX *= -1;
            this.baseX = Math.max(0, Math.min(canvas.width, this.baseX));
        }
        if (this.baseY <= 0 || this.baseY >= canvas.height) {
            this.speedY *= -1;
            this.baseY = Math.max(0, Math.min(canvas.height, this.baseY));
        }
    };

    Particle.prototype.draw = function () {
        ctx.fillStyle = getParticleColor();
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.closePath();
        ctx.fill();
    };

    // Initialize particles with Poisson Disk Sampling
    function initParticles() {
        particles = [];
        var count = getParticleCount();
        for (var i = 0; i < count; i++) {
            particles.push(new Particle(particles));
        }
    }

    // Connect particles with lines - only to nearest neighbors
    function connectParticles() {
        var color = getParticleColor();
        var connectionDistance = getConnectionDistance();
        var lineWidth = window.innerWidth <= 768 ? 0.8 : 1;
        var maxNeighbors = 3; // Connect only to 3 nearest neighbors
        
        for (var i = 0; i < particles.length; i++) {
            // Find nearest neighbors
            var neighbors = [];
            
            for (var j = 0; j < particles.length; j++) {
                if (i === j) continue;
                
                var dx = particles[i].x - particles[j].x;
                var dy = particles[i].y - particles[j].y;
                var distance = Math.sqrt(dx * dx + dy * dy);
                
                if (distance < connectionDistance) {
                    neighbors.push({
                        particle: particles[j],
                        distance: distance,
                        index: j
                    });
                }
            }
            
            // Sort by distance and take only closest neighbors
            neighbors.sort(function(a, b) { return a.distance - b.distance; });
            neighbors = neighbors.slice(0, maxNeighbors);
            
            // Draw lines only to nearest neighbors and avoid duplicates
            for (var k = 0; k < neighbors.length; k++) {
                if (neighbors[k].index > i) { // Draw each line only once
                    var distance = neighbors[k].distance;
                    var opacity = 1 - distance / connectionDistance;
                    ctx.strokeStyle = color + Math.floor(opacity * 255).toString(16).padStart(2, '0');
                    ctx.lineWidth = lineWidth;
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(neighbors[k].particle.x, neighbors[k].particle.y);
                    ctx.stroke();
                }
            }
        }
    }

    // Animation loop
    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        for (var i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();
        }

        connectParticles();
        requestAnimationFrame(animate);
    }

    // Mouse move handler
    header.addEventListener('mousemove', function (e) {
        var rect = header.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
    });

    header.addEventListener('mouseleave', function () {
        mouse.x = null;
        mouse.y = null;
    });

    // Scroll effect
    window.addEventListener('scroll', function () {
        header.classList.toggle('scrolled', window.pageYOffset > 50);
    }, { passive: true });

    // Dropdown functionality
    var dropdownItems = Array.prototype.slice.call(document.querySelectorAll('.sb-particle-item')).filter(function (li) {
        return li.querySelector('.sb-particle-dropdown');
    });
    var openItem = null;
    var enterTimer = null;
    var leaveTimer = null;

    function openDropdown(li) {
        if (openItem === li) return;
        clearTimeout(leaveTimer);
        closeDropdown();

        var trigger = li.querySelector('.sb-particle-trigger');
        li.classList.add('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'true');
        openItem = li;
    }

    function closeDropdown() {
        if (!openItem) return;
        var trigger = openItem.querySelector('.sb-particle-trigger');
        openItem.classList.remove('sb-dropdown-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        openItem = null;
    }

    dropdownItems.forEach(function (li) {
        var trigger = li.querySelector('.sb-particle-trigger');
        var link = li.querySelector('.sb-particle-pill-link');
        if (!trigger) return;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (window.innerWidth <= 968) {
                // Mobile
                var isOpen = li.classList.contains('sb-dropdown-open');
                dropdownItems.forEach(function (other) {
                    if (other !== li) {
                        other.classList.remove('sb-dropdown-open');
                        var t = other.querySelector('.sb-particle-trigger');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    }
                });
                li.classList.toggle('sb-dropdown-open', !isOpen);
                trigger.setAttribute('aria-expanded', String(!isOpen));
            } else {
                // Desktop
                if (openItem === li) {
                    closeDropdown();
                    trigger.focus();
                } else {
                    openDropdown(li);
                }
            }
        });

        if (window.innerWidth > 968) {
            li.addEventListener('mouseenter', function () {
                clearTimeout(leaveTimer);
                clearTimeout(enterTimer);
                enterTimer = setTimeout(function () { openDropdown(li); }, 100);
            });

            li.addEventListener('mouseleave', function () {
                clearTimeout(enterTimer);
                leaveTimer = setTimeout(function () { closeDropdown(); }, 250);
            });
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openItem) {
            var trigger = openItem.querySelector('.sb-particle-trigger');
            closeDropdown();
            if (trigger) trigger.focus();
        }
    });

    document.addEventListener('click', function (e) {
        if (openItem && !openItem.contains(e.target)) closeDropdown();
    });

    // Mobile menu toggle
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.contains('is-open');

            if (isOpen) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                dropdownItems.forEach(function (item) {
                    item.classList.remove('sb-dropdown-open');
                    var t = item.querySelector('.sb-particle-trigger');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            } else {
                nav.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // Resize handler
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            resizeCanvas();
            if (window.innerWidth > 968 && nav && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        }, 250);
    });

    // Initialize
    resizeCanvas();
    animate();
})();
