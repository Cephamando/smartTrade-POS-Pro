<?php
/** @var array<string,mixed> $event */
$id = (int)($event['id'] ?? 0);
$dtLocal = static fn($v) => $v ? date('Y-m-d\TH:i', strtotime((string)$v)) : '';
?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <h6 class="fw-bold"><?= $id ? 'Event file' : 'New event' ?></h6>
        <form method="POST" class="row g-2">
            <input type="hidden" name="event_id" value="<?= $id ?>">
            <div class="col-12"><label class="form-label small fw-bold text-muted">NAME</label><input name="title" class="form-control" value="<?= htmlspecialchars((string)$event['title']) ?>" required></div>
            <div class="col-md-6"><label class="form-label small fw-bold text-muted">STARTS</label><input type="datetime-local" name="starts_at" class="form-control" value="<?= htmlspecialchars($dtLocal($event['starts_at'] ?? '')) ?>" required></div>
            <div class="col-md-6"><label class="form-label small fw-bold text-muted">ENDS</label><input type="datetime-local" name="ends_at" class="form-control" value="<?= htmlspecialchars($dtLocal($event['ends_at'] ?? '')) ?>" required></div>
            <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">STORE</label>
                <select name="location_id" class="form-select">
                    <option value="0">No store</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>" <?= (int)($event['location_id'] ?? 0) === (int)$loc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label small fw-bold text-muted">VENUE NOTE</label><input name="venue_note" class="form-control" value="<?= htmlspecialchars((string)($event['venue_note'] ?? '')) ?>"></div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">VISIBILITY</label>
                <select name="visibility" class="form-select no-search">
                    <option value="private" <?= ($event['visibility'] ?? '') === 'private' ? 'selected' : '' ?>>Private invite</option>
                    <option value="published" <?= ($event['visibility'] ?? '') === 'published' ? 'selected' : '' ?>>On the shop</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">STATUS</label>
                <select name="status" class="form-select no-search">
                    <?php foreach (['draft','open','closed','cancelled'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($event['status'] ?? 'draft') === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small fw-bold text-muted">CAPACITY</label><input type="number" min="0" name="capacity" class="form-control" value="<?= (int)($event['capacity'] ?? 0) ?>"></div>
            <div class="col-md-2"><label class="form-label small fw-bold text-muted">DEPOSIT</label><input type="number" step="0.01" min="0" name="deposit_amount" class="form-control" value="<?= htmlspecialchars((string)($event['deposit_amount'] ?? '0')) ?>"></div>
            <div class="col-12"><label class="form-label small fw-bold text-muted">SHORT LINE</label><input name="summary" class="form-control" maxlength="500" value="<?= htmlspecialchars((string)($event['summary'] ?? '')) ?>"></div>
            <div class="col-12"><label class="form-label small fw-bold text-muted">STORY</label><textarea name="story" class="form-control" rows="5" placeholder="The page guests read on the shop."><?= htmlspecialchars((string)($event['story'] ?? '')) ?></textarea></div>
            <div class="col-12">
                <label class="form-label small fw-bold text-muted">COVER FROM THE IMAGE LIBRARY</label>
                <select name="cover_path" class="form-select">
                    <option value="">No cover</option>
                    <?php foreach ($library as $image): ?>
                    <option value="<?= htmlspecialchars($image['path']) ?>" <?= ($event['cover_path'] ?? '') === $image['path'] ? 'selected' : '' ?>><?= htmlspecialchars($image['path']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><button class="btn btn-dark fw-bold" name="save_event" value="1">Save event</button></div>
        </form>
    </div>
</div>
