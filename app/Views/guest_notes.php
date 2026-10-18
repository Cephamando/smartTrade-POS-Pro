<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1"><i class="bi bi-chat-left-heart"></i> Guest notes</h3>
        <p class="text-muted mb-0">Private notes from guests. Unhappy ones stay at the top until someone marks them handled.</p>
    </div>
    <?php if (!empty($openCount)): ?>
        <span class="badge bg-danger"><?= (int)$openCount ?> open</span>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>When</th>
                    <th>Store</th>
                    <th>Mood</th>
                    <th>Note</th>
                    <th>Phone</th>
                    <th>From</th>
                    <th class="text-end">Handled</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($notes)): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">No notes yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($notes as $note): ?>
                <?php
                    $place = trim((string)($note['zone_name'] ?? '') . ' · ' . (string)($note['table_name'] ?? ''), ' ·');
                    $from = !empty($note['sale_id']) ? 'Order #' . (int)$note['sale_id'] : 'Table visit';
                    if ($place !== '') {
                        $from .= ' · ' . $place;
                    }
                ?>
                <tr class="<?= ($note['mood'] ?? '') === 'off' && empty($note['handled_at']) ? 'table-warning' : '' ?>">
                    <td class="text-nowrap small"><?= !empty($note['created_at']) ? date('d M Y H:i', strtotime((string)$note['created_at'])) : '' ?></td>
                    <td><?= htmlspecialchars((string)($note['location_name'] ?? '')) ?></td>
                    <td>
                        <?php if (($note['mood'] ?? '') === 'off'): ?>
                            <span class="badge bg-warning text-dark">Something was off</span>
                        <?php else: ?>
                            <span class="badge bg-success">It was lovely</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= htmlspecialchars((string)($note['note'] ?? '')) ?></td>
                    <td class="small text-nowrap"><?= htmlspecialchars((string)($note['contact_phone'] ?? '')) ?></td>
                    <td class="small"><?= htmlspecialchars($from) ?></td>
                    <td class="text-end">
                        <?php if (!empty($note['handled_at'])): ?>
                            <span class="small text-muted"><?= date('d M H:i', strtotime((string)$note['handled_at'])) ?></span>
                        <?php elseif (($note['mood'] ?? '') === 'off'): ?>
                            <form method="POST" action="index.php?page=guest_notes" class="d-inline">
                                <button class="btn btn-sm btn-outline-dark" type="submit" name="handle_note" value="<?= (int)$note['id'] ?>">Mark handled</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
