<?php
$editPayload = [
    'id' => (int)$m['id'],
    'name' => (string)$m['name'],
    'category_id' => (int)($m['category_id'] ?? 0),
    'price' => $m['price'],
    'cost_price' => $m['cost_price'],
    'unspsc_code' => (string)($m['unspsc_code'] ?? ''),
    'unspsc_name' => (string)($m['unspsc_name'] ?? ''),
];
?>
<tr class="menu-row" data-live-id="<?= (int)$m['id'] ?>" data-name="<?= htmlspecialchars(strtolower((string)$m['name'])) ?>" data-category="<?= htmlspecialchars((string)($m['cat_name'] ?? '')) ?>">
    <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$m['id'] ?>" form="bulkMenu"></td>
    <td class="ps-3 fw-bold">
        <?= htmlspecialchars((string)$m['name']) ?>
        <?php if (!empty($hasRecipe)): ?><span class="badge bg-info text-dark ms-2" style="font-size:0.6rem;"><i class="bi bi-diagram-3"></i> RECIPE</span><?php endif; ?>
        <?php if (!empty($m['unspsc_code'])): ?>
            <div class="small text-muted font-monospace"><?= htmlspecialchars((string)$m['unspsc_code']) ?><?= !empty($m['unspsc_name']) ? ' · ' . htmlspecialchars((string)$m['unspsc_name']) : '' ?></div>
        <?php endif; ?>
    </td>
    <td><span class="badge bg-secondary"><?= htmlspecialchars((string)($m['cat_name'] ?? '')) ?></span></td>
    <td class="text-end text-success fw-bold">ZMW <?= number_format((float)$m['price'], 2) ?></td>
    <td class="text-end pe-3">
        <button type="button" class="btn btn-sm btn-outline-info fw-bold me-1 js-open-recipe"
            data-id="<?= (int)$m['id'] ?>"
            data-name="<?= htmlspecialchars((string)$m['name'], ENT_QUOTES) ?>"
            data-code="<?= htmlspecialchars((string)($m['unspsc_code'] ?? ''), ENT_QUOTES) ?>"
            data-unspsc-name="<?= htmlspecialchars((string)($m['unspsc_name'] ?? ''), ENT_QUOTES) ?>"
            title="Manage Recipe">
            <i class="bi bi-cup-straw"></i> Recipe
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary js-edit-menu"
            data-item="<?= htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-pencil"></i>
        </button>
        <form method="POST" class="d-inline js-confirm-form"
            data-swal-title="Remove from menu?"
            data-swal-text="This item will no longer appear on the POS."
            data-swal-confirm="Remove"
            data-swal-cancel="Cancel"
            data-swal-icon="warning">
            <input type="hidden" name="delete_item" value="1">
            <input type="hidden" name="item_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </td>
</tr>
