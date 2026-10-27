(function () {
    'use strict';

    var body = document.body;

    // Navigation mobile
    document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            body.classList.toggle('adm-nav-open');
        });
    });

    // Menu utilisateur : fermeture au clic extérieur et à Échap
    document.querySelectorAll('[data-user-menu]').forEach(function (menu) {
        document.addEventListener('click', function (event) {
            if (menu.open && !menu.contains(event.target)) {
                menu.open = false;
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary').focus();
            }
        });
    });

    // Tout cocher / décocher (case [data-check-all] et cases [data-check-item] du même formulaire)
    document.querySelectorAll('[data-check-all]').forEach(function (master) {
        master.addEventListener('change', function () {
            var scope = master.closest('form') || document;
            scope.querySelectorAll('input[data-check-item]:not(:disabled)').forEach(function (box) {
                box.checked = master.checked;
            });
        });
    });

    // Dialogue de confirmation (formulaires portant data-confirm)
    var dialog = document.getElementById('confirm-dialog');
    var pendingForm = null;

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm') || !dialog) {
            return;
        }
        if (form.dataset.confirmed === '1') {
            return;
        }
        event.preventDefault();
        pendingForm = form;
        dialog.querySelector('[data-confirm-title]').textContent = form.getAttribute('data-confirm-title') || 'Confirmer cette action';
        dialog.querySelector('[data-confirm-text]').textContent = form.getAttribute('data-confirm');
        dialog.showModal();
    });

    if (dialog) {
        dialog.addEventListener('click', function (event) {
            var action = event.target.closest('[data-confirm-action]');
            if (!action) {
                return;
            }
            var proceed = action.getAttribute('data-confirm-action') === 'yes';
            dialog.close();
            if (proceed && pendingForm) {
                pendingForm.dataset.confirmed = '1';
                pendingForm.submit();
            }
            pendingForm = null;
        });
    }

    // Selects dependants: <select data-depends-on="entity_id"> avec options data-parent="ID"
    document.querySelectorAll('select[data-depends-on]').forEach(function (child) {
        var parent = document.querySelector('[name="' + child.getAttribute('data-depends-on') + '"]');
        if (!parent) {
            return;
        }
        var refresh = function () {
            var parentValue = parent.value;
            Array.prototype.forEach.call(child.options, function (option) {
                var optionParent = option.getAttribute('data-parent');
                var visible = !option.value || !parentValue || !optionParent || optionParent === parentValue;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) {
                    child.value = '';
                }
            });
        };
        parent.addEventListener('change', refresh);
        refresh();
    });

    // Onglets d'un formulaire : sans JavaScript tous les panneaux restent visibles.
    document.querySelectorAll('[data-tabset]').forEach(function (set) {
        var tabs = Array.prototype.slice.call(set.querySelectorAll('[role="tab"]'));
        var panels = {};
        var storeKey = 'admin-tab:' + window.location.pathname;

        tabs.forEach(function (tab) {
            panels[tab.getAttribute('aria-controls')] = document.getElementById(tab.getAttribute('aria-controls'));
        });

        var show = function (tab, focus) {
            tabs.forEach(function (other) {
                var active = other === tab;
                other.classList.toggle('is-active', active);
                other.setAttribute('aria-selected', active ? 'true' : 'false');
                other.tabIndex = active ? 0 : -1;
                panels[other.getAttribute('aria-controls')].hidden = !active;
            });
            if (focus) { tab.focus(); }
            try { window.sessionStorage.setItem(storeKey, tab.getAttribute('aria-controls')); } catch (e) { /* stockage indisponible */ }
        };

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () { show(tab, false); });
            tab.addEventListener('keydown', function (event) {
                var next = null;
                if (event.key === 'ArrowRight') { next = tabs[(index + 1) % tabs.length]; }
                if (event.key === 'ArrowLeft') { next = tabs[(index - 1 + tabs.length) % tabs.length]; }
                if (next) { event.preventDefault(); show(next, true); }
            });
        });

        // Onglet affiché : celui qui contient la première erreur, sinon le dernier utilisé, sinon le premier.
        var initial = tabs[0];
        var failing = tabs.filter(function (tab) { return panels[tab.getAttribute('aria-controls')].querySelector('.has-error, p.error'); })[0];
        var remembered = null;
        try { remembered = window.sessionStorage.getItem(storeKey); } catch (e) { /* stockage indisponible */ }

        if (failing) {
            initial = failing;
        } else if (remembered) {
            initial = tabs.filter(function (tab) { return tab.getAttribute('aria-controls') === remembered; })[0] || tabs[0];
        }

        show(initial, false);
    });

    // Alertes de succes auto-masquees
    document.querySelectorAll('.alert-success[data-autohide]').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 450);
        }, 5000);
    });
})();
