<?php

namespace App\Controllers;

use App\Models\Sale;

final class ZraController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        $zra = new \ZraVsdcClient($this->pdo());

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
                $actionResult['resultMsg'] = $zra->friendlyMessage($actionResult);
                $actionResult = $actionResult + $explained;
                $_SESSION['zra_last_result'] = $actionResult;
                if (($actionResult['resultCd'] ?? '') === '000' || ($explained['layer'] ?? '') === 'ok') {
                    $this->flash('success', $actionResult['resultMsg'] ?? 'VSDC action completed.');
                } elseif (($explained['layer'] ?? '') === 'client') {
                    $this->flash('error', $actionResult['resultMsg'] ?? 'Unable to complete this VSDC request.');
                } elseif (($actionResult['resultCd'] ?? '') === 'skipped') {
                    $this->flash('info', $actionResult['resultMsg'] ?? 'No action was required.');
                } else {
                    $this->flash('error', $actionResult['resultMsg'] ?? 'VSDC action failed.');
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('zra');
        }

        $this->render([
            'zra' => $zra,
            'zraConfig' => $zra->getConfig(),
            'zraSettings' => $zra->getSettings(),
            'zraPing' => $zra->ping(),
            'zraSummary' => $zra->statusSummary(),
            'zraLastResult' => $_SESSION['zra_last_result'] ?? null,
            'pendingSales' => Sale::pendingFiscal(),
            'recentSynced' => Sale::recentSynced(),
        ]);
    }
}
