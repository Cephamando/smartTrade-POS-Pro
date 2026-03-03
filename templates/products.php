<style>
    @media print {
        /* Hide everything globally */
        body * {
            visibility: hidden;
        }
        /* Make only the catalog section visible */
        #printable-catalog, #printable-catalog * {
            visibility: visible;
        }
        /* Stretch the printable area to the top-left of the paper */
        #printable-catalog {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        /* Hide web-only elements like buttons and DataTables controls */
        .no-print, .dataTables_filter, .dataTables_length, .dataTables_info, .dataTables_paginate {
            display: none !important;
        }
        /* Clean up borders and shadows for paper */
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        /* Prevent table rows from awkwardly splitting across pages */
        tr { 
            page-break-inside: avoid; 
        }
    }
</style>

<div id="printable-catalog">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>📦 Product Catalog</h3>
        <div class="no-print">
            <button class="btn btn-warning fw-bold me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload"></i> Import CSV</button>
            <button onclick="exportToExcel()" class="btn btn-success fw-bold me-2 shadow-sm">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </button>
            <button onclick="printCatalog()" class="btn btn-dark fw-bold me-2 shadow-sm">
                <i class="bi bi-printer"></i> Print Catalog
            </button>
            <button class="btn btn-outline-primary fw-bold me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#catModal">
                <i class="bi bi-folder-plus"></i> New Category
            </button>
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#prodModal">
                <i class="bi bi-plus-circle"></i> New Product
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="catalogTable">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>SKU</th>
                            <th>Unit</th>
                            <th class="text-end">Cost (ZMW)</th>
                            <th class="text-end">Price (ZMW)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($p['name']) ?></td>
                                    <td>
                                        <?php if (!empty($p['category_name'])): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($p['category_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['sku'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($p['sku']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($p['unit']) ?></small></td>
                                    <td class="text-end text-muted"><?= number_format((float)$p['cost_price'], 2) ?></td>
                                    <td class="text-end fw-bold text-success"><?= number_format((float)$p['price'], 2) ?></td>
                                    <td>
                                        <?php if (!empty($p['is_active'])): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=products">
                <div class="modal-body">
                    <input type="hidden" name="add_category" value="1">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">CATEGORY NAME</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-primary w-100 fw-bold py-2 shadow-sm">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="prodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-primary border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=products">
                <div class="modal-body">
                    <input type="hidden" name="save_product" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">PRODUCT NAME</label>
                        <input type="text" name="name" class="form-control fw-bold" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">CATEGORY</label>
                            <select name="category_id" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">UNIT (e.g., kg, bottle)</label>
                            <input type="text" name="unit" class="form-control" value="unit">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">COST PRICE (ZMW)</label>
                            <input type="number" step="0.01" name="cost_price" class="form-control text-danger" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">SELLING PRICE (ZMW)</label>
                            <input type="number" step="0.01" name="price" class="form-control text-success fw-bold" placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">SKU (Optional)</label>
                        <input type="text" name="sku" class="form-control" placeholder="e.g. DRINK-001">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-primary w-100 fw-bold py-2 shadow-sm">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-warning border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-cloud-upload"></i> Import Products (CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=products" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="import_csv" value="1">
                    
                    <div class="alert alert-info small">
                        <strong>Expected CSV Columns (in exact order):</strong><br>
                        1. Name<br>
                        2. Category<br>
                        3. SKU<br>
                        4. Unit (e.g., unit, kg, bottle)<br>
                        5. Cost Price<br>
                        6. Selling Price<br><br>
                        <em>Note: The system will automatically create missing categories and update existing products to avoid duplicates!</em>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">SELECT CSV FILE</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-warning w-100 fw-bold py-2 shadow-sm">Upload & Process Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        if (!$.fn.DataTable.isDataTable('#catalogTable')) {
            $('#catalogTable').DataTable({
                "pageLength": 25,
                "lengthMenu": [[25, 50, 100, 250, 500, -1], [25, 50, 100, 250, 500, "All"]],
                "language": {
                    "search": "",
                    "searchPlaceholder": "Quick filter products...",
                    "emptyTable": "No products found. Add your first product or use the Import CSV button."
                },
                "stateSave": true 
            });
            
            $('.dataTables_filter input').addClass('form-control d-inline-block w-auto ms-2 border-primary shadow-sm');
            $('.dataTables_length select').addClass('form-select d-inline-block w-auto border-primary shadow-sm');
        }
    });

    // Smart Print Function for DataTables
    function printCatalog() {
        var table = $('#catalogTable').DataTable();
        var currentPageLength = table.page.len(); 
        
        table.page.len(-1).draw(); 
        
        setTimeout(function() {
            window.print();
            table.page.len(currentPageLength).draw(); 
        }, 300);
    }

    // Smart Export to Excel (CSV) function
    function exportToExcel() {
        let table = $('#catalogTable').DataTable();
        
        // 1. Grab Headers
        let headers = [];
        $('#catalogTable thead th').each(function() {
            headers.push('"' + $(this).text().trim() + '"');
        });
        
        let csv = [headers.join(',')];
        
        // 2. Loop through ONLY the currently filtered/searched rows
        table.rows({ search: 'applied' }).every(function() {
            let rowData = [];
            let node = this.node(); 
            
            $(node).find('td').each(function() {
                // Strip out badges and HTML, keep raw text, escape quotes
                let text = $(this).text().trim().replace(/"/g, '""'); 
                rowData.push('"' + text + '"');
            });
            csv.push(rowData.join(','));
        });
        
        // 3. Create the CSV File Blob
        let csvContent = csv.join('\n');
        let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        let url = URL.createObjectURL(blob);
        
        // 4. Force Download
        let link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "Product_Catalog_" + new Date().toISOString().slice(0,10) + ".csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
