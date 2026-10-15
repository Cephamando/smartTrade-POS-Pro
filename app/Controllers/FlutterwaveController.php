<?php

namespace App\Controllers;

use App\Services\Flutterwave;

/**
 * index.php?page=flutterwave&action=…
 *   init      POST  amount, phone, email, name      → {tx_ref, link}   (staff)
 *   momo      POST  amount, phone, network, name    → {tx_ref, status, redirect} (staff)
 *   status    GET   tx_ref                          → {status, amount, …}
 *   callback  GET   tx_ref (+status, transaction_id from Flutterwave) → HTML that notifies the POS popup opener
 *   test      GET                                   → {ok, message} (managers)
 */
final class FlutterwaveController extends Controller
{
    public function index(): void
    {
        $action = (string)($_GET['action'] ?? 'callback');
        $flw = new Flutterwave($this->pdo());

        if ($action === 'callback') {
            $this->callback($flw);
            return;
        }

        if (!$this->userId()) {
            $this->jsonExit(['status' => 'error', 'msg' => 'Sign in first.'], 401);
        }

        try {
            switch ($action) {
                case 'init':
                    $this->requirePost();
                    $res = $flw->createPaymentLink([
                        'amount' => (float)($_POST['amount'] ?? 0),
                        'phone' => trim((string)($_POST['phone'] ?? '')),
                        'email' => trim((string)($_POST['email'] ?? '')),
                        'name' => trim((string)($_POST['name'] ?? '')),
                        'redirect_url' => $this->callbackUrl(),
                        'location_id' => $this->locationId(),
                        'user_id' => $this->userId(),
                        'description' => 'POS sale at ' . ($_SESSION['location_name'] ?? 'store'),
                    ]);
                    $this->jsonExit(['status' => 'ok'] + $res);
                    // no break — jsonExit never returns
                case 'momo':
                    $this->requirePost();
                    $res = $flw->chargeMobileMoney([
                        'amount' => (float)($_POST['amount'] ?? 0),
                        'phone' => trim((string)($_POST['phone'] ?? '')),
                        'network' => (string)($_POST['network'] ?? 'MTN'),
                        'email' => trim((string)($_POST['email'] ?? '')),
                        'name' => trim((string)($_POST['name'] ?? '')),
                        'redirect_url' => $this->callbackUrl(),
                        'location_id' => $this->locationId(),
                        'user_id' => $this->userId(),
                    ]);
                    $this->jsonExit(['status' => 'ok'] + $res);
                    // no break
                case 'status':
                    $txRef = trim((string)($_GET['tx_ref'] ?? $_POST['tx_ref'] ?? ''));
                    $tx = $flw->verify($txRef);
                    $this->jsonExit([
                        'status' => 'ok',
                        'tx_ref' => $txRef,
                        'payment_status' => $tx['status'],
                        'amount' => (float)$tx['amount'],
                        'currency' => $tx['currency'],
                        'payment_type' => $tx['payment_type'],
                        'flw_ref' => $tx['flw_ref'],
                    ]);
                    // no break
                case 'test':
                    if (!$this->isManager()) {
                        $this->jsonExit(['status' => 'error', 'msg' => 'Managers only.'], 403);
                    }
                    $this->jsonExit($flw->testConnection());
                    // no break
                default:
                    $this->jsonExit(['status' => 'error', 'msg' => 'Unknown action.'], 404);
            }
        } catch (\Throwable $e) {
            $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()]);
        }
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonExit(['status' => 'error', 'msg' => 'POST required.'], 405);
        }
    }

    private function callbackUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
        return $scheme . '://' . $host . $dir . '/index.php?page=flutterwave&action=callback';
    }

    private function callback(Flutterwave $flw): void
    {
        $txRef = trim((string)($_GET['tx_ref'] ?? $_GET['reference'] ?? ''));
        $chargeId = trim((string)($_GET['charge_id'] ?? $_GET['transaction_id'] ?? ''));
        if ($txRef !== '' && str_starts_with($chargeId, 'chg_')) {
            $flw->rememberCharge($txRef, $chargeId);
        }
        $status = 'failed';
        $message = 'No payment reference was returned.';
        $amount = 0.0;
        if ($txRef !== '') {
            try {
                $tx = $flw->verify($txRef);
                $status = (string)$tx['status'];
                $amount = (float)$tx['amount'];
                $message = match ($status) {
                    'successful' => 'Payment confirmed.',
                    'pending' => 'Payment is still pending with Flutterwave.',
                    default => 'Payment was not completed.',
                };
            } catch (\Throwable $e) {
                $message = $e->getMessage();
            }
        }
        $ok = $status === 'successful';
        $payload = json_encode(['type' => 'flw_payment', 'tx_ref' => $txRef, 'status' => $status, 'amount' => $amount], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Flutterwave payment</title>'
            . '<link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css"></head>'
            . '<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">'
            . '<div class="card shadow border-0 text-center p-4" style="max-width:420px">'
            . '<div class="display-3 ' . ($ok ? 'text-success' : ($status === 'pending' ? 'text-warning' : 'text-danger')) . '"><i class="bi ' . ($ok ? 'bi-check-circle-fill' : ($status === 'pending' ? 'bi-hourglass-split' : 'bi-x-circle-fill')) . '"></i></div>'
            . '<h4 class="fw-bold mt-2">' . htmlspecialchars($message) . '</h4>'
            . '<p class="text-muted mb-1">Reference <code>' . htmlspecialchars($txRef) . '</code></p>'
            . ($amount > 0 ? '<p class="fs-4 fw-bold mb-3">ZMW ' . number_format($amount, 2) . '</p>' : '')
            . '<p class="small text-muted mb-0">You can close this window and return to the POS.</p>'
            . '<button class="btn btn-dark mt-3" onclick="window.close()">Close</button>'
            . '</div>'
            . '<script>try{if(window.opener){window.opener.postMessage(' . $payload . ', "*");}}catch(e){}'
            . ($ok ? 'setTimeout(function(){try{window.close()}catch(e){}},1500);' : '')
            . '</script></body></html>';
    }
}
