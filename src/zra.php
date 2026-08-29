<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userRole = strtolower($_SESSION['role'] ?? '');
if (!in_array($userRole, ['admin', 'manager', 'dev'], true)) {
    header('Location: index.php?page=dashboard');
    exit;
}

require_once __DIR__ . '/ZraVsdcClient.php';

$zra = new ZraVsdcClient($pdo);
$actionResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['zra_action'] ?? '';
    try {
        $actionResult = match ($action) {
            'initialize' => $zra->initialize(!empty($_POST['force'])),
            'test_connection' => $zra->testConnection(),
            'sync_codes' => $zra->syncStandardCodes(),
            'sync_classes' => $zra->syncItemClasses(),
            'sync_items' => $zra->syncUnsyncedProducts(100),
            'retry_sales' => $zra->retryFailedSales(25),
            'fiscalize_one' => $zra->fiscalizeSale((int)($_POST['sale_id'] ?? 0)),
            default => ['resultCd' => '904', 'resultMsg' => 'Unknown action'],
        };
        $explained = $zra->explainResult($actionResult);
        $actionResult = $actionResult + $explained;
        $_SESSION['zra_last_result'] = $actionResult;
        if (($actionResult['resultCd'] ?? '') === '000' || ($explained['layer'] ?? '') === 'ok') {
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = $actionResult['resultMsg'] ?? 'VSDC action completed.';
        } elseif (($explained['layer'] ?? '') === 'client') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = ($actionResult['resultMsg'] ?? 'Client error') . ' (code ' . ($actionResult['resultCd'] ?? '?') . ')';
        } elseif (($actionResult['resultCd'] ?? '') === 'skipped') {
            $_SESSION['swal_type'] = 'info';
            $_SESSION['swal_msg'] = $actionResult['resultMsg'] ?? 'Skipped.';
        } else {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = $actionResult['resultMsg'] ?? 'VSDC action failed.';
        }
    } catch (Throwable $e) {
        $actionResult = ['resultCd' => '999', 'resultMsg' => $e->getMessage()];
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }
    header('Location: index.php?page=zra');
    exit;
}

$zraConfig = $zra->getConfig();
$zraSettings = $zra->getSettings();
$zraPing = $zra->ping();
$zraSummary = $zra->statusSummary();
$zraLastResult = $_SESSION['zra_last_result'] ?? null;

$pendingSales = $pdo->query(
    "SELECT id, created_at, customer_name, final_total, payment_method, COALESCE(zra_status, 'pending') AS zra_status, zra_last_error
     FROM sales
     WHERE payment_status = 'paid'
       AND (zra_rcpt_sign IS NULL OR zra_rcpt_sign = '')
     ORDER BY id DESC
     LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

$recentSynced = $pdo->query(
    "SELECT id, created_at, customer_name, final_total, zra_rcpt_no, zra_sdc_id, zra_qr_code_url
     FROM sales
     WHERE zra_rcpt_sign IS NOT NULL AND zra_rcpt_sign <> ''
     ORDER BY id DESC
     LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);
