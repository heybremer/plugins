(function () {
    'use strict';

    var MQ_DESKTOP = window.matchMedia('(min-width: 992px)');
    var MD_Z = '2147483646';

    function mdIsActive() {
        return document.body && (
            document.body.classList.contains('womosi-menu-design') ||
            window.WomosiMenuDesignActive === 1
        );
    }

    function mdGetNavBottom() {
        var nav = document.getElementById('mainNavigation');
        if (!nav) {
            return 0;
        }
        return Math.max(0, Math.round(nav.getBoundingClientRect().bottom));
    }

    function mdManageBackdrop(open) {
        document.querySelectorAll('.modal-backdrop, .backdrop').forEach(function (el) {
            if (open) {
                el.classList.add('md-backdrop-behind-menu');
                el.style.zIndex = '2147483640';
            } else if (el.classList.contains('md-backdrop-behind-menu')) {
                el.classList.remove('md-backdrop-behind-menu');
                el.style.zIndex = '';
            }
        });
    }

    function mdShowPortalMenu(menu) {
        var top = mdGetNavBottom();
        var vw = window.innerWidth || document.documentElement.clientWidth || 0;
        var styles = {
            'display':          'block',
            'visibility':       'visible',
            'opacity':          '1',
            'position':         'fixed',
            'top':              top + 'px',
            'left':             '0',
            'right':            '0',
            'width':            vw + 'px',
            'min-width':        '100vw',
            'max-width':        '100vw',
            'margin':           '0',
            'padding':          '0',
            'float':            'none',
            'transform':        'none',
            'z-index':          MD_Z,
            'max-height':       'calc(100vh - ' + (top + 16) + 'px)',
            'overflow-x':       'visible',
            'overflow-y':       'auto',
            'pointer-events':   'auto',
            'box-sizing':       'border-box',
            'background':       '#fff',
            'box-shadow':       '0 8px 32px rgba(15,23,42,0.14)',
            'border-top':       '1px solid rgba(0,0,0,0.07)'
        };
        var cssText = Object.keys(styles).map(function (k) {
            return k + ':' + styles[k] + '!important';
        }).join(';');
        menu.style.cssText = cssText;
        menu.classList.add('show', 'md-dropdown-portal-visible');
        mdManageBackdrop(true);
    }

    function mdHidePortalMenu(menu) {
        menu.style.cssText = 'display:none!important';
        menu.classList.remove('show', 'md-dropdown-portal-visible');
        if (!document.querySelector('.md-dropdown-portal-visible')) {
            mdManageBackdrop(false);
        }
    }

    function mdItemIsOpen(item, placeholder) {
        return item.classList.contains('show')
            || item.classList.contains('hover')
            || item.classList.contains('md-open')
            || (placeholder && placeholder.classList.contains('show'));
    }

    /**
     * Smarty may cache the JS-modified DOM (placeholder divs, data-md-* attrs).
     * On fresh page load the real dropdown-menu elements are missing from body while
     * md-menu-placeholder elements remain in the nav items. Remove those orphan
     * placeholders so mdSetupPortalItem can create fresh portals.
     */
    function mdCleanCachedPortals(nav) {
        // Remove data-md-portal-setup so items can be processed again
        nav.querySelectorAll('.nav-item[data-md-portal-setup]').forEach(function (item) {
            item.removeAttribute('data-md-portal-setup');
        });
        // Remove orphan placeholders (cached placeholder without a matching body portal)
        nav.querySelectorAll('[data-md-placeholder]').forEach(function (ph) {
            if (ph.parentElement) {
                ph.parentElement.removeChild(ph);
            }
        });
        // Remove dangling portals in body that have no linked item
        document.querySelectorAll('body > .md-dropdown-portal').forEach(function (portal) {
            if (!portal._mdItem || !portal._mdItem.isConnected) {
                document.body.removeChild(portal);
            }
        });
    }

    /**
     * NOVA: showDropdown setzt .show auf .dropdown-toggle.next() — Placeholder bleibt im DOM,
     * echtes Panel liegt in document.body (kein overflow:hidden von .nav-scrollbar).
     */
    function mdSetupPortalItem(item) {
        if (!MQ_DESKTOP.matches || item.getAttribute('data-md-portal-setup') === '1') {
            return;
        }
        var toggle = item.querySelector(':scope > .dropdown-toggle, :scope > .nav-link.dropdown-toggle');
        var menu = item.querySelector(':scope > .dropdown-menu:not([data-md-placeholder])');
        if (!toggle || !menu || menu.getAttribute('data-md-real-menu') === '1') {
            return;
        }

        item.setAttribute('data-md-portal-setup', '1');

        var ph = document.createElement('div');
        ph.className = menu.className + ' md-menu-placeholder';
        ph.setAttribute('data-md-placeholder', '1');
        ph.setAttribute('aria-hidden', 'true');
        if (menu.id) {
            ph.id = menu.id;
            menu.removeAttribute('id');
        }
        ph.style.cssText = 'position:absolute!important;left:0!important;top:100%!important;width:1px!important;height:1px!important;overflow:hidden!important;opacity:0!important;pointer-events:none!important;padding:0!important;margin:0!important;border:0!important;visibility:hidden!important;';

        item.replaceChild(ph, menu);
        menu.setAttribute('data-md-real-menu', '1');
        menu.classList.add('md-dropdown-portal');
        document.body.appendChild(menu);

        item._mdRealMenu = menu;
        item._mdPh = ph;
        menu._mdItem = item;

        var sync = function () {
            if (!MQ_DESKTOP.matches) {
                mdHidePortalMenu(menu);
                document.body.classList.remove('md-nav-dropdown-open');
                return;
            }
            if (mdItemIsOpen(item, ph)) {
                document.body.classList.add('md-nav-dropdown-open');
                mdShowPortalMenu(menu);
            } else {
                mdHidePortalMenu(menu);
                if (!document.querySelector('.md-dropdown-portal-visible')) {
                    document.body.classList.remove('md-nav-dropdown-open');
                    mdManageBackdrop(false);
                }
            }
        };

        var obs = new MutationObserver(function () {
            window.requestAnimationFrame(sync);
        });
        obs.observe(item, { attributes: true, attributeFilter: ['class'] });
        obs.observe(ph, { attributes: true, attributeFilter: ['class'] });
        sync();
    }

    function mdSetupAllPortals(nav) {
        if (!MQ_DESKTOP.matches) {
            return;
        }
        mdCleanCachedPortals(nav);
        nav.querySelectorAll(
            '.nav-scrollbar-inner.navbar-nav > .nav-item.dropdown, ' +
            '.navbar-nav.nav-scrollbar-inner > .nav-item.dropdown, ' +
            '.navbar-nav > .nav-item.dropdown'
        ).forEach(mdSetupPortalItem);
    }

    function mdHasChildren(item) {
        return !!item.querySelector(':scope > .dropdown-menu, :scope > .md-menu-placeholder, :scope > [data-md-placeholder]');
    }

    function mdMarkItems(nav) {
        nav.querySelectorAll('.nav-item.dropdown, .nav-item').forEach(function (item) {
            if (mdHasChildren(item)) {
                item.classList.add('md-has-children');
            }
        });
    }

    function mdMegaPanels(nav) {
        if (document.body.getAttribute('data-md-mega') !== '1') {
            return;
        }
        document.querySelectorAll('.md-dropdown-portal .dropdown-menu, .md-dropdown-portal').forEach(function (menu) {
            if (menu.classList.contains('md-menu-placeholder')) {
                return;
            }
            if (menu.querySelectorAll('.dropdown-item').length >= 6) {
                menu.classList.add('md-mega-panel');
            }
        });
    }

    function mdBindMobile(nav) {
        if (document.body.getAttribute('data-md-mobile') !== '1') {
            return;
        }
        nav.querySelectorAll('.nav-item.md-has-children').forEach(function (item) {
            var link = item.querySelector(':scope > .nav-link, :scope > a.nav-link');
            if (!link || link.getAttribute('data-md-bound') === '1') {
                return;
            }
            link.setAttribute('data-md-bound', '1');
            link.addEventListener('click', function (ev) {
                if (MQ_DESKTOP.matches) {
                    return;
                }
                var href = link.getAttribute('href') || '';
                var isToggle = href === '' || href === '#' || link.classList.contains('dropdown-toggle');
                if (!isToggle && !item.querySelector('.dropdown-menu, .md-menu-placeholder')) {
                    return;
                }
                ev.preventDefault();
                ev.stopPropagation();
                var open = item.classList.contains('md-open');
                item.parentElement.querySelectorAll(':scope > .nav-item.md-open').forEach(function (sib) {
                    if (sib !== item) {
                        sib.classList.remove('md-open', 'show');
                    }
                });
                if (open) {
                    item.classList.remove('md-open', 'show');
                    link.setAttribute('aria-expanded', 'false');
                } else {
                    item.classList.add('md-open', 'show');
                    link.setAttribute('aria-expanded', 'true');
                }
            });
        });
    }

    function mdWatchNav(nav) {
        if (typeof MutationObserver === 'undefined') {
            return;
        }
        var obs = new MutationObserver(function () {
            window.requestAnimationFrame(function () {
                document.querySelectorAll('.md-dropdown-portal').forEach(function (menu) {
                    var item = menu._mdItem;
                    if (item && mdItemIsOpen(item, item._mdPh)) {
                        mdShowPortalMenu(menu);
                    } else {
                        mdHidePortalMenu(menu);
                    }
                });
            });
        });
        obs.observe(nav, { subtree: true, attributes: true, attributeFilter: ['class'] });
    }

    function mdSetupPortals(nav) {
        if (!MQ_DESKTOP.matches) {
            return;
        }
        mdSetupAllPortals(nav);
        mdMegaPanels(nav);
    }

    function mdInit() {
        if (!mdIsActive()) {
            return;
        }
        var nav = document.getElementById('mainNavigation');
        if (!nav) {
            return;
        }

        if (nav.getAttribute('data-md-ready') !== '1') {
            nav.setAttribute('data-md-ready', '1');
            nav.classList.add('md-enhanced-nav');
            mdMarkItems(nav);
            mdBindMobile(nav);
            mdWatchNav(nav);

            window.addEventListener('scroll', function () {
                document.querySelectorAll('.md-dropdown-portal-visible').forEach(mdShowPortalMenu);
            }, { passive: true });
            window.addEventListener('resize', function () {
                mdSetupPortals(nav);
                document.querySelectorAll('.md-dropdown-portal-visible').forEach(mdShowPortalMenu);
            }, { passive: true });
        }

        mdSetupPortals(nav);
    }

    MQ_DESKTOP.addEventListener('change', function () {
        if (MQ_DESKTOP.matches) {
            mdInit();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mdInit);
    } else {
        mdInit();
    }
    window.addEventListener('load', mdInit);
    window.setTimeout(mdInit, 300);
    window.setTimeout(mdInit, 800);
    window.setTimeout(mdInit, 2000);
})();
