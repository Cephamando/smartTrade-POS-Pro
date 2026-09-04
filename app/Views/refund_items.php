<?php
$saleId = (int)($saleId ?? 0);
$sale = $sale ?? null;
$items = $items ?? [];
$error = $error ?? '';
$refundReasons = $refundReasons ?? \ZraVsdcClient::refundReasonCodes();
$selectedReason = $selectedReason ?? '07';
?>
<div class="card shadow mx-auto" style="max-width: 720px;">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">Refund sale #<?= $saleId ?></h5>
    </div>
    <div class="card-body">
        <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$sale): ?>
            <p class="text-muted mb-0">Sale not found.</p>
        <?php elseif ($items === []): ?>
            <p class="text-muted">Nothing left to refund on this ticket.</p>
            <a href="index.php?page=reports" class="btn btn-secondary">Back to reports</a>
        <?php else: ?>
            <p>Choose quantities to return. This restores stock and posts a <strong>ZRA credit note</strong> against the original Smart Invoice. The original signed receipt is not rewritten.</p>
            <form method="POST" action="index.php?page=refund_items&sale_id=<?= $saleId ?>" class="js-confirm-form" id="refundItemsForm"
                data-swal-title="Process this refund?"
                data-swal-text="Stock will be restored and a ZRA credit note will be sent for the returned quantities."
                data-swal-confirm="Refund"
                data-swal-cancel="Cancel"
                data-swal-icon="warning">
                <input type="hidden" name="process_refund" value="1">
                <input type="hidden" name="sale_id" value="<?= $saleId ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark" for="rfd_rsn_cd">ZRA refund reason</label>
                    <select name="rfd_rsn_cd" id="rfd_rsn_cd" class="form-select form-select-lg bg-white text-dark border" required>
                        <?php foreach ($refundReasons as $code => $label): ?>
                        <option value="<?= htmlspecialchars((string)$code) ?>" <?= (string)$code === (string)$selectedReason ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">This is the reason ZRA prints on the credit note.</div>
                </div>
                <table class="table">
                    <thead><tr><th>Item</th><th>Remaining</th><th>Price</th><th>Refund qty</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$i['name']) ?></td>
                            <td><?= htmlspecialchars(\App\Helpers\Movement::formatQty((float)$i['remaining'])) ?></td>
                            <td><?= number_format((float)$i['price'], 2) ?></td>
                            <td>
                                <input type="number" name="refund_qty[<?= (int)$i['id'] ?>]" class="form-control form-control-sm"
                                    min="0" max="<?= htmlspecialchars((string)$i['remaining']) ?>" step="0.01" value="0">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button class="btn btn-danger w-100">Confirm refund</button>
            </form>
            <script>
            (function () {
                const form = document.getElementById('refundItemsForm');
                const sel = document.getElementById('rfd_rsn_cd');
                if (!form || !sel) return;
                function syncReason() {
                    const label = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : 'Other';
                    form.dataset.swalText = 'Stock will be restored and a ZRA credit note will be sent for: ' + label + '.';
                }
                sel.addEventListener('change', syncReason);
                syncReason();
            })();
            </script>
        <?php endif; ?>
    </div>
    <div class="card-footer">
        <a href="index.php?page=reports" class="btn btn-secondary w-100">Cancel</a>
    </div>
</div>
