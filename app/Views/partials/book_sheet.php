<div id="bookSheet" class="bg-white p-4 rounded shadow-sm border">
    <div class="mb-4">
        <h2 class="fw-bold mb-1"><?= htmlspecialchars($opened['title']) ?></h2>
        <div class="text-muted"><?= htmlspecialchars($opened['note']) ?></div>
        <div class="fw-bold mt-2"><?= htmlspecialchars(date('d M Y', strtotime($start))) ?> – <?= htmlspecialchars(date('d M Y', strtotime($end))) ?></div>
    </div>
    <?php foreach ($opened['sections'] as $section): ?>
        <h5 class="fw-bold mt-4"><?= htmlspecialchars($section['heading']) ?></h5>
        <?php if ($section['rows'] === []): ?>
            <p class="text-muted">Nothing was recorded in this period.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <?php foreach ($section['columns'] as $col): ?>
                                <th class="<?= !empty($col['money']) || !empty($col['num']) ? 'text-end' : '' ?>"><?= htmlspecialchars($col['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($section['columns'] as $col): ?>
                                    <?php $value = $row[$col['key']] ?? ''; ?>
                                    <td class="<?= !empty($col['money']) || !empty($col['num']) ? 'text-end' : '' ?>">
                                        <?php if (!empty($col['money'])): ?>
                                            <?= number_format((float)$value, 2) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars((string)$value) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if ($section['totals'] !== []): ?>
                        <tfoot>
                            <tr class="fw-bold">
                                <?php $labeled = false; foreach ($section['columns'] as $col): ?>
                                    <td class="<?= !empty($col['money']) || !empty($col['num']) ? 'text-end' : '' ?>">
                                        <?php if (isset($section['totals'][$col['key']])): ?>
                                            <?= number_format((float)$section['totals'][$col['key']], 2) ?>
                                        <?php elseif (!$labeled): $labeled = true; ?>
                                            Total
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (($opened['title'] ?? '') === 'Stock and inventory register' && count($opened['sections'][0]['rows'] ?? []) >= 800): ?>
        <p class="text-muted small">This register shows the latest 800 movements in the period.</p>
    <?php endif; ?>
</div>
