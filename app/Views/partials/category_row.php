<tr data-live-id="<?= (int)$c['id'] ?>">
    <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$c['id'] ?>" form="bulkCategories"></td>
    <td class="fw-bold text-dark">
        <?php if (!empty($c['parent_id'])): ?><i class="bi bi-arrow-return-right text-muted me-2"></i><?php endif; ?>
        <?= htmlspecialchars((string)$c['name']) ?>
    </td>
    <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst((string)($c['type'] ?? 'other'))) ?></span></td>
    <td>
        <?php if (!empty($c['parent_name'])): ?>
            <span class="badge bg-info text-dark"><i class="bi bi-folder-fill"></i> <?= htmlspecialchars((string)$c['parent_name']) ?></span>
        <?php else: ?>
            <em class="text-muted small">Master Category</em>
        <?php endif; ?>
    </td>
    <td class="text-end">
        <button type="button" class="btn btn-outline-primary btn-sm js-edit-category"
            data-id="<?= (int)$c['id'] ?>"
            data-name="<?= htmlspecialchars((string)$c['name'], ENT_QUOTES) ?>"
            data-type="<?= htmlspecialchars((string)($c['type'] ?? 'other'), ENT_QUOTES) ?>"
            data-parent="<?= htmlspecialchars((string)($c['parent_id'] ?? ''), ENT_QUOTES) ?>">
            <i class="bi bi-pencil"></i> Edit
        </button>
        <form method="POST" action="index.php?page=categories" class="d-inline ms-1 js-confirm-form"
            data-swal-title="Delete this category?"
            data-swal-text="<?= htmlspecialchars((string)$c['name'], ENT_QUOTES) ?> will be removed if it has no products or subcategories."
            data-swal-confirm="Delete"
            data-swal-cancel="Cancel"
            data-swal-icon="warning">
            <input type="hidden" name="delete_category" value="1">
            <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
        </form>
    </td>
</tr>
