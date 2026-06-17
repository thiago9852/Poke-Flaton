(function () {
    var dropdown = document.getElementById('userDropdown');
    var btn = document.getElementById('userDropdownBtn');
    if (! dropdown || ! btn) 
    return;


    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = dropdown.classList.toggle('open');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function () {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
    });

    dropdown.querySelector('.nav-dropdown-menu').addEventListener('click', function (e) {
        e.stopPropagation();
    });
})();

(function () {
    var navToggle = document.getElementById('navToggle');
    var appNav = document.getElementById('appNav');
    if (!navToggle || !appNav) return;

    navToggle.addEventListener('click', function (e) {
        e.stopPropagation();
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
    });

    document.addEventListener('click', function (event) {
        if (!appNav.contains(event.target) && !navToggle.contains(event.target)) {
            appNav.classList.remove('open');
            var icon = navToggle.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-xmark');
                icon.classList.add('fa-bars');
            }
        }
    });
})();

// Global loading
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