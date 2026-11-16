/**
 * Smart POS Keyboard Default Behavior:
 * - Hidden by default on mobile phones (< 992px wide).
 * - Enabled by default only on tablets / large screens (>= 992px).
 */
document.addEventListener('DOMContentLoaded', function () {
    const isMobilePhone = window.innerWidth < 992 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const keyboardEl = document.getElementById('posKeyboard');
    const toggleBtn = document.getElementById('posKeyboardToggle');

    if (isMobilePhone) {
        // Force hide keyboard by default on phones
        if (keyboardEl) {
            keyboardEl.classList.add('d-none');
        }
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-pressed', 'false');
            toggleBtn.classList.remove('btn-warning', 'text-dark');
            toggleBtn.classList.add('btn-outline-light');
        }
    } else {
        // Auto-enable on tablets / large screens
        if (keyboardEl) {
            keyboardEl.classList.remove('d-none');
        }
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-pressed', 'true');
            toggleBtn.classList.add('btn-warning', 'text-dark');
            toggleBtn.classList.remove('btn-outline-light');
        }
    }
});
