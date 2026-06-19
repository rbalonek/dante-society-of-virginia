/**
 * Dante Society Navigation Toggle
 */
(function() {
    var toggle = document.getElementById('nav-toggle');
    var nav = document.getElementById('main-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            nav.classList.toggle('open');
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
            if (!toggle.contains(e.target) && !nav.contains(e.target)) {
                nav.classList.remove('open');
            }
        });
    }

    // Add admin bar class if present
    var adminBar = document.getElementById('wpadminbar');
    if (adminBar) {
        document.body.classList.add('admin-bar');
    }
})();
