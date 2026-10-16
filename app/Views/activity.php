<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1"><i class="bi bi-person-lines-fill"></i> Activity log</h3>
        <p class="text-muted mb-0">Sign-ins, sign-outs, workstation changes, and sales. Stock movements stay on <a href="index.php?page=audit">stock history</a>.</p>
    </div>
</div>
<form method="GET" action="index.php" class="card shadow-sm border-0 mb-3">
    <input type="hidden" name="page" value="activity">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small fw-bold text-muted">FROM</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>"></div>
        <div class="col-md-2"><label class="form-label small fw-bold text-muted">TO</label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-bold text-muted">STAFF</label>
            <select name="user_id" class="form-select">
                <option value="0">Everyone</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= (int)$userId === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small fw-bold text-muted">ACTION</label>
            <select name="action_name" class="form-select no-search">
                <option value="">All</option>
                <?php foreach ($actions as $name): ?>
                <option value="<?= htmlspecialchars((string)$name) ?>" <?= $action === (string)$name ? 'selected' : '' ?>><?= htmlspecialchars((string)$name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small fw-bold text-muted">SEARCH</label><input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Name, place, sale"></div>
        <div class="col-md-1"><button class="btn btn-dark w-100">Show</button></div>
    </div>
</form>
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark"><tr><th>When</th><th>Staff</th><th>Action</th><th>Result</th><th>Location</th><th>Detail</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?><tr><td colspan="7" class="text-center text-muted py-5">No activity in this period.</td></tr><?php endif; ?>
            <?php foreach ($logs as $row): ?>
                <tr>
                    <td class="small text-nowrap"><?= htmlspecialchars((string)$row['created_at']) ?></td>
                    <td><?= htmlspecialchars((string)($row['username'] ?: '—')) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars((string)$row['action']) ?></td>
                    <td><span class="badge <?= ($row['outcome'] ?? '') === 'ok' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars((string)$row['outcome']) ?></span></td>
                    <td class="small"><?= htmlspecialchars((string)($row['location_name'] ?: '—')) ?></td>
                    <td class="small"><?= htmlspecialchars(trim((string)($row['subject'] ?? '') . ' ' . (string)($row['detail'] ?? ''))) ?></td>
                    <td class="small text-muted"><?= htmlspecialchars((string)($row['ip'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
