/**
 * Shared SweetAlert2 notifications and form confirmations.
 * Forms: add class js-confirm-form plus data-swal-* attributes.
 */
(function (window) {
    'use strict';

    var TITLES = {
        success: 'Success',
        error: 'Unable to complete',
        warning: 'Please review',
        info: 'Notice',
        question: 'Please confirm'
    };

    function AppAlert() {}

    AppAlert.notify = function (opts) {
        opts = opts || {};
        var icon = opts.icon || 'info';
        var isBlocking = icon === 'error' || icon === 'warning';
        var timer = opts.timer;
        var url = opts.url || '';
        var html = opts.html || '';
        if (timer === undefined) {
            timer = (isBlocking || url || html) ? undefined : 3200;
        }
        var fire = {
            icon: icon,
            title: opts.title || TITLES[icon] || 'Notice',
            confirmButtonText: opts.confirmText || (url ? 'Continue' : 'OK'),
            confirmButtonColor: isBlocking ? '#dc3545' : '#0d6efd',
            cancelButtonText: opts.cancelText || 'Cancel',
            showConfirmButton: true,
            showCancelButton: !!(url || opts.cancelText),
            reverseButtons: true,
            zIndex: 20000,
            timer: timer || undefined,
            timerProgressBar: !!timer && !isBlocking && !url && !html
        };
        if (html) {
            fire.html = html;
            fire.didOpen = function (popup) {
                popup.querySelectorAll('a[href]').forEach(function (a) {
                    a.addEventListener('click', function (e) {
                        var href = a.getAttribute('href');
                        if (!href || href.charAt(0) === '#') {
                            return;
                        }
                        e.preventDefault();
                        window.location.href = href;
                    });
                });
            };
        } else {
            fire.text = opts.text || '';
        }
        return Swal.fire(fire).then(function (result) {
            if (url && result.isConfirmed) {
                window.location.href = url;
            }
        });
    };

    AppAlert.confirm = function (opts) {
        opts = opts || {};
        return Swal.fire({
            icon: opts.icon || 'question',
            title: opts.title || 'Please confirm',
            text: opts.text || '',
            showCancelButton: true,
            reverseButtons: true,
            focusCancel: true,
            confirmButtonText: opts.confirmText || 'Confirm',
            cancelButtonText: opts.cancelText || 'Cancel',
            confirmButtonColor: opts.confirmColor || '#0d6efd',
            cancelButtonColor: '#6c757d',
            zIndex: 20000
        }).then(function (result) {
            return result.isConfirmed;
        });
    };

    function confirmOpts(el) {
        return {
            title: el.dataset.swalTitle || 'Please confirm',
            text: el.dataset.swalText || '',
            confirmText: el.dataset.swalConfirm || 'Confirm',
            cancelText: el.dataset.swalCancel || 'Cancel',
            icon: el.dataset.swalIcon || 'question',
            confirmColor: el.dataset.swalConfirmColor || '#dc3545'
        };
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList || !form.classList.contains('js-confirm-form')) {
            return;
        }
        if (form.dataset.swalConfirmed === '1') {
            return;
        }
        var submitter = e.submitter || null;
        e.preventDefault();
        AppAlert.confirm(confirmOpts(form)).then(function (ok) {
            if (!ok) {
                return;
            }
            form.dataset.swalConfirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                if (submitter) {
                    form.requestSubmit(submitter);
                } else {
                    form.requestSubmit();
                }
            } else {
                form.submit();
            }
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                delete form.dataset.swalConfirmed;
            }
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.js-confirm-action');
        if (!btn || btn.dataset.swalConfirmed === '1') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        AppAlert.confirm(confirmOpts(btn)).then(function (ok) {
            if (!ok) {
                return;
            }
            btn.dataset.swalConfirmed = '1';
            if (typeof btn.click === 'function') {
                btn.click();
            }
        });
    }, true);

    window.AppAlert = AppAlert;
})(window);
