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

    // Fenetres modales de formulaire : [data-modal-open="id"] ouvre, [data-modal-close] ferme, [data-modal-autoopen] s'ouvre au chargement (erreurs).
    document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var modal = document.getElementById(trigger.getAttribute('data-modal-open'));
            if (modal && typeof modal.showModal === 'function') { modal.showModal(); }
        });
    });
    document.querySelectorAll('dialog.modal').forEach(function (modal) {
        modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
        modal.addEventListener('mousedown', function (event) {
            if (event.target === modal) { modal.close(); }
        });
        if (modal.hasAttribute('data-modal-autoopen') && typeof modal.showModal === 'function') { modal.showModal(); }
    });

    // Champ logo : choix du fichier, apercu immediat, controle du format et de la taille avant l'envoi.
    document.querySelectorAll('[data-logo-field]').forEach(function (field) {
        var input = field.querySelector('[data-logo-input]');
        var pick = field.querySelector('[data-logo-pick]');
        var preview = field.querySelector('[data-logo-preview]');
        var placeholder = field.querySelector('[data-logo-placeholder]');
        var label = field.querySelector('[data-logo-name]');
        var error = field.querySelector('[data-logo-error]');
        var remove = field.querySelector('[data-logo-remove]');
        var maxBytes = (parseFloat(field.getAttribute('data-max-mb')) || 3) * 1024 * 1024;
        var allowed = ['image/png', 'image/jpeg', 'image/webp'];
        var objectUrl = null;

        if (!input || !pick) { return; }

        var fail = function (message) {
            input.value = '';
            error.textContent = message;
            error.hidden = false;
        };

        pick.addEventListener('click', function () { input.click(); });

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            error.hidden = true;
            if (!file) { return; }
            if (allowed.indexOf(file.type) === -1) { return fail('Format non accepté : choisissez un fichier jpg, png ou webp.'); }
            if (file.size > maxBytes) { return fail('Fichier trop lourd (' + (file.size / 1048576).toFixed(1).replace('.', ',') + ' Mo) : ' + (maxBytes / 1048576) + ' Mo maximum.'); }

            if (objectUrl) { URL.revokeObjectURL(objectUrl); }
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            preview.hidden = false;
            if (placeholder) { placeholder.hidden = true; }
            if (label) { label.textContent = file.name; }
            if (remove) { remove.checked = false; }
        });

        if (remove) {
            remove.addEventListener('change', function () {
                if (remove.checked) {
                    input.value = '';
                    error.hidden = true;
                }
            });
        }
    });

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
