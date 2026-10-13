<?php
/** @var string $bulkFormId */
/** @var string $bulkAction */
/** @var array<int,array<string,string>> $bulkButtons */
$bulkHidden = $bulkHidden ?? [];
?>
<form id="<?= htmlspecialchars($bulkFormId) ?>" method="POST" action="<?= htmlspecialchars($bulkAction) ?>" class="bulk-form d-none">
    <?php foreach ($bulkHidden as $key => $value): ?>
        <input type="hidden" name="<?= htmlspecialchars((string)$key) ?>" value="<?= htmlspecialchars((string)$value) ?>">
    <?php endforeach; ?>
</form>
<div class="bulk-bar d-none align-items-center gap-2 flex-wrap mb-2 px-2" data-bulk-form="<?= htmlspecialchars($bulkFormId) ?>">
    <span class="badge bulk-count">0 selected</span>
    <?php foreach ($bulkButtons as $button): ?>
        <button type="button"
            class="btn btn-sm <?= htmlspecialchars($button['class'] ?? 'btn-light') ?>"
            data-bulk-submit="<?= htmlspecialchars($button['value']) ?>"
            data-bulk-form="<?= htmlspecialchars($bulkFormId) ?>"
            data-swal-title="<?= htmlspecialchars($button['title']) ?>"
            data-swal-text="<?= htmlspecialchars($button['text'] ?? '') ?>"
            data-swal-confirm="<?= htmlspecialchars($button['confirm'] ?? 'Apply') ?>"
            data-swal-color="<?= htmlspecialchars($button['color'] ?? '#0d6efd') ?>"
            <?= !empty($button['auth']) ? 'data-bulk-auth="' . htmlspecialchars($button['auth']) . '"' : '' ?>><?= $button['label'] ?></button>
    <?php endforeach; ?>
</div>
