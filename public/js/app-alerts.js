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
        if (timer === undefined) {
            timer = isBlocking ? undefined : 3200;
        }
        return Swal.fire({
            icon: icon,
            title: opts.title || TITLES[icon] || 'Notice',
            text: opts.text || '',
            confirmButtonText: opts.confirmText || 'OK',
            confirmButtonColor: isBlocking ? '#dc3545' : '#0d6efd',
            showConfirmButton: true,
            timer: timer || undefined,
            timerProgressBar: !!timer && !isBlocking
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
            cancelButtonColor: '#6c757d'
        }).then(function (result) {
            return result.isConfirmed;
        });
    };

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList || !form.classList.contains('js-confirm-form')) {
            return;
        }
        if (form.dataset.swalConfirmed === '1') {
            return;
        }
        e.preventDefault();
        AppAlert.confirm({
            title: form.dataset.swalTitle || 'Please confirm',
            text: form.dataset.swalText || '',
            confirmText: form.dataset.swalConfirm || 'Confirm',
            cancelText: form.dataset.swalCancel || 'Cancel',
            icon: form.dataset.swalIcon || 'question',
            confirmColor: form.dataset.swalConfirmColor || '#dc3545'
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            form.dataset.swalConfirmed = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    window.AppAlert = AppAlert;
})(window);
