<?php

namespace App\Controllers;

use App\Services\Currency;

/**
 * index.php?page=currency&action=rates   → live ZMW rates (JSON)
 * index.php?page=currency&action=refresh → force refresh (managers)
 * index.php?page=currency&action=convert&amount=…&from=USD&to=ZMW
 */
final class CurrencyController extends Controller
{
    public function index(): void
    {
        $fx = new Currency($this->pdo());
        $action = (string)($_GET['action'] ?? 'rates');
        try {
            if ($action === 'refresh') {
                if (!$this->isManager()) {
                    $this->jsonExit(['status' => 'error', 'msg' => 'Managers only.'], 403);
                }
                $this->jsonExit(['status' => 'ok'] + $fx->rates(true));
            }
            if ($action === 'convert') {
                $amount = (float)($_GET['amount'] ?? 0);
                $from = strtoupper((string)($_GET['from'] ?? 'USD'));
                $to = strtoupper((string)($_GET['to'] ?? Currency::BASE));
                $result = $fx->convert($amount, $from, $to);
                $this->jsonExit(['status' => $result === null ? 'error' : 'ok', 'amount' => $amount, 'from' => $from, 'to' => $to, 'result' => $result, 'msg' => $result === null ? 'No rate for ' . $from . '/' . $to : null]);
            }
            $this->jsonExit(['status' => 'ok', 'enabled' => $fx->enabled()] + $fx->rates());
        } catch (\Throwable $e) {
            $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()]);
        }
    }
}
