<?php

namespace App\Controllers;

use App\Models\Sale;

final class ZraController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        $zra = new \ZraVsdcClient($this->pdo());

        if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_REQUEST['zra_action']) || isset($_REQUEST['action'])) {
            
            // 1. Grab the action from GET, POST, or JSON body
            $action = $_REQUEST['zra_action'] ?? $_REQUEST['action'] ?? '';
            
            $json = [];
            if (empty($action)) {
                $json = json_decode(file_get_contents('php://input'), true) ?: [];
                $action = $json['zra_action'] ?? $json['action'] ?? '';
            }

            // 2. Fallback for form buttons submitted without a specific value
            if (empty($action)) {
                $possible = ['sync_items', 'register_products', 'register_items', 'test_connection', 'initialize', 'sync_codes', 'sync_classes', 'retry_sales'];
                foreach ($possible as $p) {
                    if (isset($_REQUEST[$p]) || isset($json[$p])) {
                        $action = $p;
                        break;
                    }
                }
            }

            // If we are strictly rendering the page (not an action), break out.
            if (empty($action) && $_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                goto render_page;
            }

            try {
                if ($action === 'save_device') {
                    if ($this->role() !== 'dev') {
                        throw new \RuntimeException('Only a developer can change the ZRA device.');
                    }
                    $tpin = preg_replace('/\D+/', '', (string)($_POST['zra_tpin'] ?? '')) ?? '';
                    if ($tpin !== '' && strlen($tpin) !== 10) {
                        throw new \RuntimeException('TPIN must be 10 digits.');
                    }
                    $bhf = preg_replace('/\D+/', '', (string)($_POST['zra_bhf_id'] ?? '')) ?? '';
                    if ($bhf !== '') {
                        $bhf = str_pad(substr($bhf, -3), 3, '0', STR_PAD_LEFT);
                    }
                    $serial = trim((string)($_POST['zra_dvc_srl_no'] ?? ''));
                    $url = trim((string)($_POST['zra_vsdc_url'] ?? ''));
                    $portal = trim((string)($_POST['zra_portal_url'] ?? ''));
                    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
                        throw new \RuntimeException('The VSDC address must be a full http address.');
                    }
                    if ($portal !== '' && filter_var($portal, FILTER_VALIDATE_URL) === false) {
                        throw new \RuntimeException('The receipt portal must be a full https address.');
                    }
                    \App\Models\Setting::save([
                        'zra_tpin' => $tpin,
                        'zra_bhf_id' => $bhf,
                        'zra_dvc_srl_no' => $serial,
                        'zra_vsdc_url' => $url,
                        'zra_portal_url' => $portal,
                        'zra_non_vat' => isset($_POST['zra_non_vat']) ? '1' : '0',
                    ]);
                    \App\Services\Activity::log($this->pdo(), 'zra_device', 'ok', [
                        'subject' => 'ZRA device settings saved',
                        'detail' => 'TPIN ' . ($tpin !== '' ? $tpin : 'from server') . ' · branch ' . ($bhf !== '' ? $bhf : 'from server'),
                    ]);
                    $this->flash('success', 'ZRA device settings saved. Initialize only if this device has never been registered.');
                    $this->redirect('zra');
                }
                $actionResult = match ($action) {
                    'initialize' => $zra->initialize(!empty($_REQUEST['force']) || !empty($json['force'])),
                    'test_connection' => $zra->testConnection(),
                    'sync_codes' => $zra->syncStandardCodes(),
                    'sync_classes' => $zra->syncItemClasses(),
                    'sync_items', 'register_products', 'register_items' => $zra->syncUnsyncedProducts(150),
                    'retry_sales' => $zra->retryFailedSales(25),
                    'fiscalize_one' => $zra->fiscalizeSale((int)($_REQUEST['sale_id'] ?? $json['sale_id'] ?? 0)),
                    default => ['resultCd' => '904', 'resultMsg' => 'Unknown action: [' . htmlspecialchars($action ?: 'EMPTY') . ']. Keys sent: ' . implode(', ', array_keys(array_merge($_REQUEST, $json)))],
                };
                
                $explained = $zra->explainResult($actionResult);
                $actionResult['resultMsg'] = $zra->friendlyMessage($actionResult);
                $actionResult = $actionResult + $explained;

                // Return JSON for AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($json) || !empty($_GET['action']) || !empty($_GET['zra_action'])) {
                    header('Content-Type: application/json');
                    echo json_encode($actionResult);
                    exit;
                }

                // Flash message for standard POST
                $_SESSION['zra_last_result'] = $actionResult;
                if (($actionResult['resultCd'] ?? '') === '000' || ($explained['layer'] ?? '') === 'ok') {
                    $this->flash('success', $actionResult['resultMsg'] ?? 'VSDC action completed.');
                } elseif (($actionResult['resultCd'] ?? '') === 'skipped' || ($explained['layer'] ?? '') === 'skipped') {
                    $this->flash('info', $actionResult['resultMsg'] ?? 'No action was required.');
                } elseif (($explained['layer'] ?? '') === 'client') {
                    $this->flash('error', $actionResult['resultMsg'] ?? 'Unable to complete this VSDC request.');
                } else {
                    $this->flash('error', $actionResult['resultMsg'] ?? 'VSDC action failed.');
                }
            } catch (\Throwable $e) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($json) || !empty($_GET['action']) || !empty($_GET['zra_action'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['resultCd' => '500', 'resultMsg' => $e->getMessage()]);
                    exit;
                }
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('zra');
        }

        render_page:
        $last = $_SESSION['zra_last_result'] ?? null;
        unset($_SESSION['zra_last_result']);
        $this->render([
            'zra' => $zra,
            'zraConfig' => $zra->getConfig(),
            'zraSettings' => $zra->getSettings(),
            'zraPing' => $zra->ping(),
            'zraSummary' => $zra->statusSummary(),
            'zraLastResult' => $last,
            'pendingSales' => Sale::pendingFiscal(),
            'recentSynced' => Sale::recentSynced(),
        ]);
    }
}