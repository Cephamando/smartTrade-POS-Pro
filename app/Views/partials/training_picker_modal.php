<?php
if (!empty($_SESSION['training_mode'])) {
    return;
}
$trainingCatalog = (new \App\Services\Training())->catalog(strtolower((string)($_SESSION['role'] ?? '')));
if ($trainingCatalog === []) {
    return;
}
?>
<div class="modal fade" id="trainingPicker" tabindex="-1" aria-labelledby="trainingPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="trainingPickerTitle">Choose a training</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Your role sees the trainings for that job. Managers and administrators can also open the floor trainings. Practice records stay in temporary data and live sales are not changed. The first start copies the database and can take a moment.</p>
                <?php foreach ($trainingCatalog as $key => $course): ?>
                <form method="post" action="index.php?page=training" class="border rounded p-3 mb-3">
                    <input type="hidden" name="course" value="<?= htmlspecialchars($key) ?>">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= htmlspecialchars($course['title']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($course['audience']) ?></div>
                            <p class="mb-2 mt-2"><?= htmlspecialchars($course['summary']) ?></p>
                            <ol class="small mb-0 ps-3">
                                <?php foreach ($course['steps'] as $title): ?>
                                <li><?= htmlspecialchars($title) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                        <button type="submit" name="start_training" value="1" class="btn btn-warning fw-bold flex-shrink-0">Start</button>
                    </div>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
