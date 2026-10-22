<?php

function executeDeduction($pdo, $pId, $qty, $locId, $uId, $actionType)
{
    $started = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $started = true;
    }
    try {
        $stmt = $pdo->prepare('SELECT id, quantity FROM inventory WHERE product_id = ? AND location_id = ? FOR UPDATE');
        $stmt->execute([$pId, $locId]);
        $inv = $stmt->fetch();
        $qty = (float)$qty;
        if ($inv) {
            $current = max(0.0, (float)$inv['quantity']);
            $newQty = max(0.0, $current - $qty);
            $pdo->prepare('UPDATE inventory SET quantity = ? WHERE id = ?')->execute([$newQty, $inv['id']]);
        } else {
            $current = 0.0;
            $newQty = max(0.0, -$qty);
            $pdo->prepare('INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)')
                ->execute([$pId, $locId, $newQty]);
        }
        $actualChange = $newQty - $current;
        $logChange = $actualChange;
        if ($actionType === 'recipe_deduction' && abs($actualChange) < abs($qty) && $qty != 0.0) {
            $logChange = -$qty;
        }
        $pdo->prepare('INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$pId, $locId, $uId, $logChange, $newQty, $actionType]);
        if ($started) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function inventoryQtyAt($pdo, int $productId, int $locationId): float
{
    $stmt = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
    $stmt->execute([$productId, $locationId]);
    $qty = $stmt->fetchColumn();
    return $qty !== false ? max(0.0, (float)$qty) : 0.0;
}

function recipeLines($pdo, int $productId): array
{
    $stmt = $pdo->prepare('SELECT ingredient_product_id, quantity FROM product_recipes WHERE parent_product_id = ?');
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function resolveStockLocation($pdo, int $productId, int $preferredLocId): int
{
    if ($preferredLocId > 0 && inventoryQtyAt($pdo, $productId, $preferredLocId) > 0) {
        return $preferredLocId;
    }
    $stmt = $pdo->prepare(
        "SELECT i.location_id
         FROM inventory i
         JOIN locations l ON l.id = i.location_id
         WHERE i.product_id = ? AND i.quantity > 0
         ORDER BY CASE l.type
            WHEN 'kitchen' THEN 1
            WHEN 'warehouse' THEN 2
            WHEN 'bar' THEN 3
            WHEN 'store' THEN 4
            ELSE 5
         END ASC, i.quantity DESC, l.id ASC
         LIMIT 1"
    );
    $stmt->execute([$productId]);
    $found = $stmt->fetchColumn();
    if ($found) {
        return (int)$found;
    }
    return $preferredLocId > 0 ? $preferredLocId : 0;
}

function consumeProduct($pdo, int $productId, float $qty, int $saleLocId, int $userId, string $action, array $stack = []): void
{
    if ($qty == 0.0 || $productId <= 0) {
        return;
    }
    if (isset($stack[$productId])) {
        $src = resolveStockLocation($pdo, $productId, $saleLocId);
        if ($src > 0) {
            executeDeduction($pdo, $productId, $qty, $src, $userId, $action);
        }
        return;
    }
    $stack[$productId] = true;
    $recipe = recipeLines($pdo, $productId);
    $restoring = $qty < 0;
    $absQty = abs($qty);

    if ($restoring) {
        if ($recipe) {
            foreach ($recipe as $ing) {
                consumeProduct(
                    $pdo,
                    (int)$ing['ingredient_product_id'],
                    -((float)$ing['quantity'] * $absQty),
                    $saleLocId,
                    $userId,
                    $action,
                    $stack
                );
            }
            return;
        }
        $src = resolveStockLocation($pdo, $productId, $saleLocId);
        if ($src > 0) {
            executeDeduction($pdo, $productId, $qty, $src, $userId, $action);
        }
        return;
    }

    if ($recipe) {
        $fromFg = 0.0;
        if (in_array($action, ['sale', 'online_sale'], true) && $saleLocId > 0) {
            $fg = inventoryQtyAt($pdo, $productId, $saleLocId);
            if ($fg > 0) {
                $fromFg = min($fg, $absQty);
                executeDeduction($pdo, $productId, $fromFg, $saleLocId, $userId, $action);
            }
        }
        $remain = $absQty - $fromFg;
        if ($remain > 0.00001) {
            $ingAction = in_array($action, ['sale', 'online_sale'], true) ? 'recipe_deduction' : $action;
            foreach ($recipe as $ing) {
                consumeProduct(
                    $pdo,
                    (int)$ing['ingredient_product_id'],
                    (float)$ing['quantity'] * $remain,
                    $saleLocId,
                    $userId,
                    $ingAction,
                    $stack
                );
            }
        }
        return;
    }

    $src = resolveStockLocation($pdo, $productId, $saleLocId);
    if ($src > 0) {
        executeDeduction($pdo, $productId, $absQty, $src, $userId, $action);
    }
}

function deductStock($pdo, $productId, $qty, $locId, $uId, $actionOverride = 'sale')
{
    consumeProduct($pdo, (int)$productId, (float)$qty, (int)$locId, (int)$uId, (string)$actionOverride);
}

function stampTrainingReceipt(PDO $pdo, int $saleId): void
{
    if ($saleId <= 0) {
        return;
    }
    $sign = 'TRN' . strtoupper(substr(hash('sha256', 'training-sale-' . $saleId), 0, 13));
    $internal = strtoupper(substr(hash('sha256', 'training-internal-' . $saleId), 0, 16));
    $qr = 'https://sandboxportal.zra.org.zm/common/link/ebm/receipt/indexEbmReceiptData?Data=0000000000001' . $sign;
    try {
        $pdo->prepare(
            "UPDATE sales SET
                zra_rcpt_no = ?, zra_intrl_data = ?, zra_rcpt_sign = ?,
                zra_sdc_id = ?, zra_mrc_no = ?, zra_vsdc_rcpt_pbct_date = ?,
                zra_qr_code_url = ?, zra_status = 'synced', zra_cis_invc_no = ?,
                zra_last_error = NULL
             WHERE id = ?"
        )->execute([
            $saleId,
            $internal,
            $sign,
            'SDC-TRAIN01',
            'MRC-TRAIN01',
            date('YmdHis'),
            $qr,
            'TRAIN-' . $saleId,
            $saleId,
        ]);
    } catch (Throwable $e) {
        return;
    }
}

function fiscalizePaidSale(PDO $pdo, int $saleId, array $opts = []): string
{
    if ($saleId <= 0) {
        return '';
    }
    if (!empty($_SESSION['training_mode'])) {
        stampTrainingReceipt($pdo, $saleId);
        return ' Sample ZRA receipt ready.';
    }
    try {
        $edge = new \App\Services\EdgeSync($pdo);
        $vsdcMode = strtolower(trim((string)(getenv('ZRA_VSDC_MODE') ?: ($_ENV['ZRA_VSDC_MODE'] ?? 'local')))) ?: 'local';
        if ($edge->isEdge() && $vsdcMode === 'remote') {
            $remote = $edge->pushAndFiscalizeSale($saleId, $opts);
            if (!empty($remote['ok']) && !empty($remote['fiscalized'])) {
                return ' Posted to ZRA.';
            }
            return ' Saved locally — ZRA & Online sync queued.';
        }
        $client = new ZraVsdcClient($pdo);
        $res = $client->fiscalizeSale($saleId, $opts);
        if (($res['resultCd'] ?? '') === '000') {
            return ' Posted to ZRA.';
        }
        if (($res['resultCd'] ?? '') === 'skipped') {
            return '';
        }
        $msg = trim((string)($res['resultMsg'] ?? 'VSDC unavailable'));
        if (preg_match('/it\s*is\s*succeeded/i', $msg)) {
            $msg = 'Succeeded.';
        }
        return ' ZRA posting is pending' . ($msg !== '' ? ': ' . $msg : '.');
    } catch (Throwable $e) {
        return ' ZRA posting is pending.';
    }
}
