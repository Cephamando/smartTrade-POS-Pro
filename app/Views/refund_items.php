<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Refund</title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
</head>
<body class="bg-light p-4">
    <div class="card shadow mx-auto" style="max-width: 600px;">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Process Refund - Sale #<?= $saleId ?></h5>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
            <script>
                AppAlert.notify({ icon: 'error', text: <?= json_encode($error) ?>, confirmText: 'OK' });
            </script>
            <?php endif; ?>
            
            <p>Select quantities to return to stock.</p>
            <form method="POST" class="js-confirm-form"
                data-swal-title="Process this refund?"
                data-swal-text="Selected quantities will be returned to stock."
                data-swal-confirm="Refund"
                data-swal-cancel="Cancel"
                data-swal-icon="warning">
                <input type="hidden" name="process_refund" value="1">
                <table class="table">
                    <thead><tr><th>Item</th><th>Sold</th><th>Price</th><th>Refund Qty</th></tr></thead>
                    <tbody>
                        <?php foreach($items as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['name']) ?></td>
                            <td><?= $i['quantity'] ?></td>
                            <td><?= number_format($i['price'], 2) ?></td>
                            <td>
                                <input type="number" name="refund_qty[<?= $i['id'] ?>]" class="form-control form-control-sm" min="0" max="<?= $i['quantity'] ?>" value="0">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button class="btn btn-danger w-100">CONFIRM REFUND</button>
            </form>
        </div>
        <div class="card-footer">
            <a href="index.php?page=reports" class="btn btn-secondary w-100">Cancel</a>
        </div>
    </div>
</body>
</html>
