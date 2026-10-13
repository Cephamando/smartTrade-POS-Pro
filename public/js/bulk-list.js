(function () {
    'use strict';

    function boxes(formId, onlyChecked) {
        return Array.prototype.filter.call(
            document.querySelectorAll('input.bulk-one[form="' + formId + '"]'),
            function (box) {
                var row = box.closest('tr');
                if (row && row.style.display === 'none') {
                    return false;
                }
                return !onlyChecked || box.checked;
            }
        );
    }

    function refresh(formId) {
        if (!formId) {
            return;
        }
        var all = boxes(formId, false);
        var picked = all.filter(function (box) { return box.checked; });
        var bar = document.querySelector('.bulk-bar[data-bulk-form="' + formId + '"]');
        if (bar) {
            var count = bar.querySelector('.bulk-count');
            if (count) {
                count.textContent = picked.length + ' selected';
            }
            bar.classList.toggle('d-none', picked.length === 0);
            bar.classList.toggle('d-flex', picked.length > 0);
        }
        var master = document.querySelector('.bulk-all[data-bulk-form="' + formId + '"]');
        if (master) {
            master.checked = all.length > 0 && picked.length === all.length;
            master.indeterminate = picked.length > 0 && picked.length < all.length;
        }
    }

    document.addEventListener('change', function (event) {
        var master = event.target.closest('.bulk-all');
        if (master) {
            boxes(master.getAttribute('data-bulk-form'), false).forEach(function (box) {
                box.checked = master.checked;
            });
            refresh(master.getAttribute('data-bulk-form'));
            return;
        }
        if (event.target.classList && event.target.classList.contains('bulk-one')) {
            refresh(event.target.getAttribute('form'));
        }
    });

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-bulk-submit]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var formId = btn.getAttribute('data-bulk-form');
        var form = document.getElementById(formId);
        var picked = boxes(formId, true);
        if (!form || picked.length === 0) {
            if (window.AppAlert) {
                AppAlert.notify({ icon: 'warning', text: 'Select at least one row first.' });
            }
            return;
        }
        var go = function () {
            var action = form.querySelector('input[name="bulk_action"]');
            if (!action) {
                action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'bulk_action';
                form.appendChild(action);
            }
            action.value = btn.getAttribute('data-bulk-submit');
            form.submit();
        };
        var askDev = btn.getAttribute('data-bulk-auth') === 'dev';
        var afterAuth = function () {
            if (!window.AppAlert) {
                go();
                return;
            }
            AppAlert.confirm({
                title: btn.getAttribute('data-swal-title') || 'Apply to ' + picked.length + ' selected?',
                text: btn.getAttribute('data-swal-text') || '',
                icon: 'warning',
                confirmText: btn.getAttribute('data-swal-confirm') || 'Apply',
                confirmColor: btn.getAttribute('data-swal-color') || '#0d6efd'
            }).then(function (ok) {
                if (ok) {
                    go();
                }
            });
        };
        if (askDev && window.Swal) {
            Swal.fire({
                title: 'Developer authorisation',
                html: '<p class="text-muted small">This bulk delete needs a developer sign-off, once for the whole selection.</p>' +
                    '<input id="swal-dev-user" class="swal2-input" placeholder="Developer username">' +
                    '<input id="swal-dev-pass" type="password" class="swal2-input" placeholder="Developer password">',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Continue',
                confirmButtonColor: '#dc3545',
                preConfirm: function () {
                    var user = document.getElementById('swal-dev-user').value.trim();
                    var pass = document.getElementById('swal-dev-pass').value;
                    if (!user || !pass) {
                        Swal.showValidationMessage('Enter the developer username and password.');
                        return false;
                    }
                    return { user: user, pass: pass };
                }
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }
                var userField = form.querySelector('input[name="dev_username"]');
                var passField = form.querySelector('input[name="dev_password"]');
                if (userField) {
                    userField.value = result.value.user;
                }
                if (passField) {
                    passField.value = result.value.pass;
                }
                afterAuth();
            });
            return;
        }
        afterAuth();
    });
})();
