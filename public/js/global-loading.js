if (!window.globalLoadingInitialized) {
    window.globalLoadingInitialized = true;

    // Objeto global loading
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

    // Dropdowns selecionar Linguagem
    document.addEventListener('click', function (e) {
        // User Dropdown
        var userBtn = e.target.closest('#userDropdownBtn');
        if (userBtn) {
            e.stopPropagation();
            var userDropdown = document.getElementById('userDropdown');
            if (userDropdown) {
                var isOpen = userDropdown.classList.toggle('open');
                userBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                
                // Fechar dropdown se tiver aberto
                var langDropdown = document.getElementById('langDropdown');
                var langBtn = document.getElementById('langDropdownBtn');
                if (langDropdown && langBtn) {
                    langDropdown.classList.remove('open');
                    langBtn.setAttribute('aria-expanded', 'false');
                }
            }
            return;
        }

        // Lang Dropdown
        var langBtn = e.target.closest('#langDropdownBtn');
        if (langBtn) {
            e.stopPropagation();
            var langDropdown = document.getElementById('langDropdown');
            if (langDropdown) {
                var isOpen = langDropdown.classList.toggle('open');
                langBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                
                // Fecha dropdown se tiver aberto
                var userDropdown = document.getElementById('userDropdown');
                var userBtnRef = document.getElementById('userDropdownBtn');
                if (userDropdown && userBtnRef) {
                    userDropdown.classList.remove('open');
                    userBtnRef.setAttribute('aria-expanded', 'false');
                }
            }
            return;
        }

        // Clicar fora fecha ambos os dropdowns
        var userDropdown = document.getElementById('userDropdown');
        var userBtnRef = document.getElementById('userDropdownBtn');
        if (userDropdown && userBtnRef && !userDropdown.contains(e.target) && !userBtnRef.contains(e.target)) {
            userDropdown.classList.remove('open');
            userBtnRef.setAttribute('aria-expanded', 'false');
        }

        var langDropdown = document.getElementById('langDropdown');
        var langBtnRef = document.getElementById('langDropdownBtn');
        if (langDropdown && langBtnRef && !langDropdown.contains(e.target) && !langBtnRef.contains(e.target)) {
            langDropdown.classList.remove('open');
            langBtnRef.setAttribute('aria-expanded', 'false');
        }
    });

    // Mobile nav toggle
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

    // oader no Turbo load, submit end, fetch response, or error para previnir struck
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