<?php
/**
 * Developer card listing open shifts. The page swaps this card in place after
 * an End shift action, so it must render from $openShifts alone.
 *
 * @var list<array<string, mixed>> $openShifts
 */
$openShiftPlaces = [];
foreach ($openShifts as $shift) {
    $openShiftPlaces[(int)$shift['location_id']] = (string)$shift['location_name'];
}
?>
<div class="card shadow-sm border-0 mb-4" id="openShiftsCard">
    <div class="card-header bg-white py-3">
        <div class="fw-bold"><i class="bi bi-clock-history text-primary"></i> Open shifts</div>
        <div class="small text-muted">Ending a shift does not sign the person out and does not count the drawer. The report keeps the cash that should be in the till, with no difference recorded.</div>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Person</th>
                    <th>Workstation</th>
                    <th>Started</th>
                    <th>Expected in till</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$openShifts): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No open shifts.</td></tr>
                <?php endif; ?>
                <?php foreach ($openShifts as $shift): ?>
                <tr>
                    <td class="ps-4">
                        <span class="fw-bold"><?= htmlspecialchars((string)$shift['who']) ?></span>
                        <span class="d-block small text-muted">@<?= htmlspecialchars((string)$shift['username']) ?> · <?= htmlspecialchars(str_replace('_', ' ', (string)$shift['role'])) ?></span>
                    </td>
                    <td><?= htmlspecialchars((string)$shift['location_name']) ?></td>
                    <td class="text-nowrap"><?= htmlspecialchars((string)$shift['started_label']) ?></td>
                    <td class="text-nowrap">ZMW <?= number_format((float)$shift['expected'], 2) ?></td>
                    <td class="text-end pe-4">
                        <form method="POST" class="d-inline js-confirm-form js-end-shift-form"
                            data-swal-title="End <?= htmlspecialchars((string)$shift['who']) ?>'s shift?"
                            data-swal-text="The drawer is not counted, so no cash difference is recorded. They stay signed in."
                            data-swal-confirm="End shift"
                            data-swal-cancel="Cancel"
                            data-swal-icon="warning"
                            data-swal-confirm-color="#dc3545">
                            <input type="hidden" name="end_open_shift" value="1">
                            <input type="hidden" name="shift_id" value="<?= (int)$shift['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">End shift</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($openShifts): ?>
    <div class="card-footer bg-white d-flex flex-wrap gap-2 align-items-center">
        <form method="POST" class="d-flex flex-wrap gap-2 align-items-center js-confirm-form js-end-shift-form"
            data-swal-title="End the shifts at this workstation?"
            data-swal-text="Every open shift at the workstation you chose is closed. Drawers are not counted, and staff stay signed in."
            data-swal-confirm="End shifts"
            data-swal-cancel="Cancel"
            data-swal-icon="warning"
            data-swal-confirm-color="#dc3545">
            <input type="hidden" name="end_open_shifts_location" value="1">
            <select name="shift_location_id" class="form-select form-select-sm no-search" style="width:auto" required>
                <option value="">Choose a workstation</option>
                <?php foreach ($openShiftPlaces as $placeId => $placeName): ?>
                <option value="<?= (int)$placeId ?>"><?= htmlspecialchars($placeName) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-dark" type="submit">End shifts at this workstation</button>
        </form>
        <form method="POST" class="ms-auto js-confirm-form js-end-shift-form"
            data-swal-title="End every open shift?"
            data-swal-text="Every open shift is closed. Drawers are not counted, and staff stay signed in."
            data-swal-confirm="End every shift"
            data-swal-cancel="Cancel"
            data-swal-icon="warning"
            data-swal-confirm-color="#dc3545">
            <input type="hidden" name="end_open_shifts_all" value="1">
            <button class="btn btn-sm btn-danger" type="submit">End every open shift</button>
        </form>
    </div>
    <?php endif; ?>
</div>
