<?php
$editPayload = [
    'id' => (int)$p['id'],
    'name' => (string)$p['name'],
    'sku' => (string)($p['sku'] ?? ''),
    'category_id' => (int)($p['category_id'] ?? 0),
    'unit' => (string)($p['unit'] ?: 'unit'),
    'type' => (string)($p['type'] ?? 'item'),
    'tax_class' => (string)($p['tax_class'] ?? 'A'),
    'unspsc_code' => (string)($p['unspsc_code'] ?? ''),
    'unspsc_name' => (string)($p['unspsc_name'] ?? ''),
    'cost_price' => $p['cost_price'],
    'price' => $p['price'],
    'is_active' => (int)($p['is_active'] ?? 1),
    'is_sellable' => array_key_exists('is_sellable', $p) ? (int)$p['is_sellable'] : 1,
    'image_path' => \App\Helpers\Media::url((string)($p['image_path'] ?? '')),
];
?>
<tr data-live-id="<?= (int)$p['id'] ?>">
    <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$p['id'] ?>" form="bulkProducts"></td>
    <td class="fw-bold">
        <?= htmlspecialchars((string)$p['name']) ?>
        <?php if (isset($p['is_sellable']) && (int)$p['is_sellable'] === 0): ?>
            <span class="badge bg-secondary ms-1" style="font-size: 0.6rem;">RAW</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if (!empty($p['category_name'])): ?>
            <span class="badge bg-secondary"><?= htmlspecialchars((string)$p['category_name']) ?></span>
        <?php else: ?>
            <span class="text-muted">-</span>
        <?php endif; ?>
    </td>
    <td><small class="text-muted"><?= htmlspecialchars((string)($p['sku'] ?? '-')) ?></small></td>
    <td><?= htmlspecialchars((string)($p['unit'] ?: 'unit')) ?></td>
    <td><?= htmlspecialchars((string)($p['type'] ?? 'item')) ?></td>
    <?php if (!empty($isEnt)): ?>
    <td><span class="badge bg-info text-dark">Class <?= htmlspecialchars((string)($p['tax_class'] ?? 'A')) ?></span></td>
    <?php endif; ?>
    <td>
        <button type="button" class="btn btn-link btn-sm p-0 text-start unspsc-pick no-print"
            data-product-id="<?= (int)$p['id'] ?>"
            data-product-name="<?= htmlspecialchars((string)$p['name'], ENT_QUOTES) ?>">
            <?php if (!empty($p['unspsc_code'])): ?>
                <span class="fw-bold"><?= htmlspecialchars((string)$p['unspsc_code']) ?></span>
                <?php if (!empty($p['unspsc_name'])): ?>
                    <div class="small text-muted"><?= htmlspecialchars((string)$p['unspsc_name']) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <span class="text-muted">Assign code</span>
            <?php endif; ?>
        </button>
    </td>
    <td class="text-end text-muted"><?= number_format((float)$p['cost_price'], 2) ?></td>
    <td class="text-end fw-bold text-success"><?= number_format((float)$p['price'], 2) ?></td>
    <td>
        <?php if (!empty($p['is_active'])): ?>
            <span class="badge bg-success">Active</span>
        <?php else: ?>
            <span class="badge bg-danger">Disabled</span>
        <?php endif; ?>
    </td>
    <td class="text-end no-print">
        <button type="button" class="btn btn-sm btn-outline-primary js-edit-product"
            data-product="<?= htmlspecialchars(json_encode($editPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
            title="Edit product">
            <i class="bi bi-pencil"></i>
        </button>
    </td>
</tr>
