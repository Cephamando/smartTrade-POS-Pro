</div> 

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // GLOBAL SWEETALERT HANDLER
    // Checks if the PHP session set a flash message and displays it.
    <?php if (isset($_SESSION['swal_type'])): ?>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= $_SESSION['swal_type'] === "success" ? "Success" : "Notice" ?>',
            text: '<?= addslashes($_SESSION['swal_msg']) ?>',
            timer: 3000,
            showConfirmButton: false
        });
    });
    <?php 
    // Clear message after showing
    unset($_SESSION['swal_type']); 
    unset($_SESSION['swal_msg']); 
    ?>
    <?php endif; ?>
</script>

</body>
</html>