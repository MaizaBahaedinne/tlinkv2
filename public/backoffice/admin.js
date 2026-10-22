(function () {
    'use strict';

    var body = document.body;

    // Navigation mobile
    document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            body.classList.toggle('adm-nav-open');
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

    // Alertes de succes auto-masquees
    document.querySelectorAll('.alert-success[data-autohide]').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .4s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 450);
        }, 5000);
    });
})();
