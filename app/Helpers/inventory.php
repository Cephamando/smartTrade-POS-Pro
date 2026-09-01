<?php

function executeDeduction($pdo, $pId, $qty, $locId, $uId, $actionType)
{
    $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE product_id = ? AND location_id = ?");
    $stmt->execute([$pId, $locId]);
    $inv = $stmt->fetch();
    if ($inv) {
        $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $inv['id']]);
        $newQty = $inv['quantity'] - $qty;
    } else {
        $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)")->execute([$pId, $locId, -$qty]);
        $newQty = -$qty;
    }
    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$pId, $locId, $uId, -$qty, $newQty, $actionType]);
}

function fiscalizePaidSale(PDO $pdo, int $saleId): string
{
    if ($saleId <= 0) {
        return '';
    }
    try {
        $client = new ZraVsdcClient($pdo);
        $res = $client->fiscalizeSale($saleId);
        if (($res['resultCd'] ?? '') === '000') {
            return ' Fiscalized with ZRA.';
        }
        if (($res['resultCd'] ?? '') === 'skipped') {
            return '';
        }
        $msg = $res['resultMsg'] ?? 'VSDC unavailable';
        if (preg_match('/it\s*is\s*succeeded/i', $msg)) {
            $msg = 'Succeeded.';
        }
        return ' ZRA pending: ' . $msg;
    } catch (Throwable $e) {
        return ' ZRA pending: ' . $e->getMessage();
    }
}

function deductStock($pdo, $productId, $qty, $locId, $uId, $actionOverride = 'sale')
{
    $stmt = $pdo->prepare("SELECT ingredient_product_id, quantity FROM product_recipes WHERE parent_product_id = ?");
    $stmt->execute([$productId]);
    $recipe = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($recipe) > 0) {
        foreach ($recipe as $ing) {
            $deductQty = $ing['quantity'] * $qty;
            $act = ($actionOverride === 'sale') ? 'recipe_deduction' : $actionOverride;
            executeDeduction($pdo, $ing['ingredient_product_id'], $deductQty, $locId, $uId, $act);
        }
    } else {
        executeDeduction($pdo, $productId, $qty, $locId, $uId, $actionOverride);
    }
}
