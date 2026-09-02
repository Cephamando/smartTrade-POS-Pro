    </div> 
    
    <footer class="footer mt-auto py-3 bg-white border-top">
        <div class="container text-center">
            <span class="text-muted small">
                &copy; <?= date('Y') ?> <strong>Odelia Enterprise</strong>. All rights reserved.<br>
                <i class="bi bi-geo-alt-fill"></i> Mazabuka, Zambia
            </span>
        </div>
    </footer>

    <script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (window.bootstrap && bootstrap.Tooltip) {
                new bootstrap.Tooltip(el);
            }
        });
        <?php if (isset($_SESSION['swal_msg'])): ?>
            AppAlert.notify({
                icon: <?= json_encode($_SESSION['swal_type'] ?? 'info') ?>,
                text: <?= json_encode((string)$_SESSION['swal_msg']) ?>
            });
            <?php unset($_SESSION['swal_msg'], $_SESSION['swal_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
