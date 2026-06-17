if (!window.globalLoadingInitialized) {
    window.globalLoadingInitialized = true;

    // Global loading object
    window.Loader = {
        show: function () {
            var loader = document.getElementById('global-loader');
            if (loader) {
                loader.classList.add('active');
            }
        },
        hide: function () {
            var loader = document.getElementById('global-loader');
            if (loader) {
                loader.classList.remove('active');
            }
        }
    };

    // User dropdown click handler with delegation
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#userDropdownBtn');
        if (btn) {
            e.stopPropagation();
            var dropdown = document.getElementById('userDropdown');
            if (dropdown) {
                var isOpen = dropdown.classList.toggle('open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
            return;
        }

        var dropdown = document.getElementById('userDropdown');
        var dropdownBtn = document.getElementById('userDropdownBtn');
        if (dropdown && dropdownBtn && !dropdown.contains(e.target) && !dropdownBtn.contains(e.target)) {
            dropdown.classList.remove('open');
            dropdownBtn.setAttribute('aria-expanded', 'false');
        }
    });

    // Mobile nav toggle handler with delegation
    document.addEventListener('click', function (e) {
        var navToggle = e.target.closest('#navToggle');
        if (navToggle) {
            e.stopPropagation();
            var appNav = document.getElementById('appNav');
            if (appNav) {
                var isOpen = appNav.classList.toggle('open');
                var icon = navToggle.querySelector('i');
                if (icon) {
                    if (isOpen) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-xmark');
                    } else {
                        icon.classList.remove('fa-xmark');
                        icon.classList.add('fa-bars');
                    }
                }
            }
            return;
        }

        var appNav = document.getElementById('appNav');
        var navToggleBtn = document.getElementById('navToggle');
        if (appNav && navToggleBtn && !appNav.contains(e.target) && !navToggleBtn.contains(e.target)) {
            appNav.classList.remove('open');
            var icon = navToggleBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-bars');
            }
        }
    });

    // auto-trigger loader em paginas
    document.addEventListener('click', function (e) {
        var target = e.target.closest('a');
        if (! target) 
            return;

        var href = target.getAttribute('href');
        var targetAttr = target.getAttribute('target');
        if (targetAttr && targetAttr !== '_self') 
            return;

        if (! href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) 
            return;

        try {
            var url = new URL(target.href, window.location.href);
            if (url.origin !== window.location.origin) 
                return;
        } catch (err) {
            return;
        }

        if (target.hasAttribute('download') || target.classList.contains('no-loader')) 
            return;

        window.Loader.show();
    });

    // Auto-trigger loader em form que não for AJAX
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.getAttribute('data-ajax') === 'true' || form.classList.contains('ajax-form')) {
            return;
        }
        window.Loader.show();
    });

    // Hide loader on Turbo load, submit end, fetch response, or error to prevent getting stuck
    document.addEventListener('turbo:load', function () {
        window.Loader.hide();
    });

    document.addEventListener('turbo:submit-end', function () {
        window.Loader.hide();
    });

    document.addEventListener('turbo:before-fetch-response', function () {
        window.Loader.hide();
    });

    document.addEventListener('turbo:fetch-error', function () {
        window.Loader.hide();
    });
}