    </div> <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (isset($_SESSION['swal_msg'])): ?>
            Swal.fire({
                icon: '<?= $_SESSION['swal_type'] ?>',
                title: '<?= $_SESSION['swal_msg'] ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['swal_msg'], $_SESSION['swal_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
