<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Refund</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light p-4">
    <div class="card shadow mx-auto" style="max-width: 600px;">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Process Refund - Sale #<?= $saleId ?></h5>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
            
            <p>Select quantities to refund back to stock.</p>
            <form method="POST">
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
