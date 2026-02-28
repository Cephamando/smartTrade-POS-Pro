<div class="d-flex justify-content-between align-items-center mb-4 mt-4">
    <h4 class="fw-bold m-0"><i class="bi bi-pie-chart text-danger me-2"></i> Recipe Consumption Report</h4>
    <a href="index.php?page=reports" class="btn btn-outline-secondary fw-bold"><i class="bi bi-arrow-left"></i> Back to Reports</a>
</div>

<div class="card shadow-sm border-0 mb-4 border-top border-danger border-4">
    <div class="card-body bg-light p-4">
        <form method="GET" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="report_consumption">
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">START DATE</label>
                <input type="date" name="start_date" class="form-control fw-bold" value="<?= htmlspecialchars($startDate) ?>" required>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted">END DATE</label>
                <input type="date" name="end_date" class="form-control fw-bold" value="<?= htmlspecialchars($endDate) ?>" required>
            </div>
            
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">LOCATION</label>
                <select name="location_id" class="form-select fw-bold">
                    <option value="0">All Locations</option>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>" <?= ($locId == $loc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-danger w-100 fw-bold shadow-sm py-2">
                    <i class="bi bi-funnel-fill"></i> GENERATE
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table id="consumptionTable" class="table table-hover table-striped align-middle border">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3 ps-3">Ingredient / Product</th>
                        <th class="py-3">Location</th>
                        <th class="py-3 text-center">Total Consumed via Recipes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($consumption as $row): ?>
                    <tr>
                        <td class="py-3 ps-3 fw-bold text-dark"><?= htmlspecialchars($row['ingredient_name']) ?></td>
                        <td class="py-3"><span class="badge bg-secondary"><?= htmlspecialchars($row['location_name']) ?></span></td>
                        <td class="py-3 text-center fs-5 fw-bold text-danger">
                            <?= number_format($row['total_consumed'], 2) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
    $(document).ready(function() {
        $('#consumptionTable').DataTable({
            "pageLength": 25,
            "order": [[ 2, "desc" ]], // Order by consumption highest to lowest
            dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row mt-3"<"col-md-6"i><"col-md-6"p>>',
            buttons: [
                { extend: 'excel', className: 'btn btn-sm btn-success fw-bold', text: '<i class="bi bi-file-earmark-excel"></i> Excel' },
                { extend: 'pdf', className: 'btn btn-sm btn-danger fw-bold', text: '<i class="bi bi-file-earmark-pdf"></i> PDF' },
                { extend: 'print', className: 'btn btn-sm btn-dark fw-bold', text: '<i class="bi bi-printer"></i> Print' }
            ],
            "language": {
                "search": "",
                "searchPlaceholder": "Filter ingredients...",
                "emptyTable": "No recipes were consumed during this date range."
            }
        });
        
        // Clean up Bootstrap styling for Datatables inputs
        $('.dataTables_filter input').addClass('form-control shadow-sm border-secondary');
    });
</script>

<?php include 'footer.php'; ?>
