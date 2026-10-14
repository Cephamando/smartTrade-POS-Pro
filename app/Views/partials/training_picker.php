<?php
if (!empty($_SESSION['training_mode'])) {
    return;
}
$trainingCatalog = (new \App\Services\Training())->catalog(strtolower((string)($_SESSION['role'] ?? '')));
if ($trainingCatalog === []) {
    return;
}
$trainingButtonClass = $trainingButtonClass ?? 'nav-chip btn';
?>
<button type="button" class="<?= htmlspecialchars($trainingButtonClass) ?>" data-bs-toggle="modal" data-bs-target="#trainingPicker" title="Practice on temporary data">
    <i class="bi bi-mortarboard"></i>
    Training
</button>
