<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-fire text-danger"></i> Meal Production</h3>
    <!-- <a href="index.php?page=pos" class="btn btn-outline-secondary"><i class="bi bi-cart4"></i> Go to POS</a> -->
</div>

<div class="alert alert-info border-info shadow-sm mb-4">
    <strong><i class="bi bi-info-circle-fill"></i> Meal production:</strong> Recording a batch deducts recipe ingredients from kitchen stock and sends finished portions to the selected POS workstation.
</div>

<div class="row g-3">
    <?php foreach($meals as $meal):
        $mealCtx = \App\Models\Product::movementContext((int)$meal['id']);
        $isRawMeal = \App\Helpers\Movement::isRawProduct($mealCtx);
    ?>
    <div class="col-md-4 col-lg-3 col-xl-2">
        <div class="card shadow-sm h-100 border-0 border-bottom border-4 <?= $isRawMeal ? 'border-secondary' : 'border-danger' ?>">
            <div class="card-body text-center d-flex flex-column p-3 <?= $isRawMeal ? 'opacity-50' : '' ?>">
                <h6 class="card-title fw-bold text-dark mb-1" style="height: 40px; overflow: hidden;"><?= htmlspecialchars($meal['name']) ?></h6>
                <div class="mb-3"><span class="badge bg-secondary" style="font-size: 0.65rem;"><?= htmlspecialchars($meal['cat_name']) ?></span></div>

                <?php if ($isRawMeal): ?>
                    <div class="small text-muted mt-auto"><i class="bi bi-info-circle text-info"></i> Raw food cannot be sent to POS. Use it in a recipe, then produce the finished dish.</div>
                <?php else: ?>
                <form method="POST" class="mt-auto js-confirm-form"
                    data-swal-title="Send batch to POS?"
                    data-swal-text="Recipe ingredients will be deducted from kitchen stock. Finished portions will be added at the selected workstation."
                    data-swal-confirm="Send"
                    data-swal-cancel="Cancel"
                    data-swal-icon="question"
                    data-swal-confirm-color="#198754">
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
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
