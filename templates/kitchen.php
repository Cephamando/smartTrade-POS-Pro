<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-fire text-danger"></i> Meal Production</h3>
    <a href="index.php?page=pos" class="btn btn-outline-secondary"><i class="bi bi-cart4"></i> Go to POS</a>
</div>

<div class="alert alert-info border-info shadow-sm mb-4">
    <strong><i class="bi bi-info-circle-fill"></i> Batch Production:</strong> Record prepared batches here. Select the POS Workstation below to instantly make the food available for cashiers to sell.
</div>

<div class="row g-3">
    <?php foreach($meals as $meal): ?>
    <div class="col-md-4 col-lg-3 col-xl-2">
        <div class="card shadow-sm h-100 border-0 border-bottom border-4 border-danger">
            <div class="card-body text-center d-flex flex-column p-3">
                <h6 class="card-title fw-bold text-dark mb-1" style="height: 40px; overflow: hidden;"><?= htmlspecialchars($meal['name']) ?></h6>
                <div class="mb-3"><span class="badge bg-secondary" style="font-size: 0.65rem;"><?= htmlspecialchars($meal['cat_name']) ?></span></div>

                <form method="POST" onsubmit="return confirm('Send this batch to the POS?');" class="mt-auto">
                    <input type="hidden" name="produce_item" value="1">
                    <input type="hidden" name="product_id" value="<?= $meal['id'] ?>">
                    
                    <select name="target_location_id" class="form-select form-select-sm mb-2 fw-bold text-primary border-primary" required>
                        <option value="">Select POS...</option>
                        <?php foreach($sellableLocs as $sl): ?>
                            <option value="<?= $sl['id'] ?>">To: <?= htmlspecialchars($sl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text bg-white fw-bold">Qty</span>
                        <input type="number" step="0.01" name="quantity" class="form-control text-center fw-bold text-danger" placeholder="0" required min="0.01">
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold btn-sm"><i class="bi bi-send-check"></i> PRODUCE & SEND</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
    <?php if(isset($_SESSION['swal_msg'])): ?>
    Swal.fire({ icon: '<?= $_SESSION['swal_type'] ?>', title: '<?= $_SESSION['swal_msg'] ?>', timer: 1500, showConfirmButton: false });
    <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>
