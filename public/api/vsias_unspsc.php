<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Helpers\VsiasApi;
use App\Models\Unspsc;

VsiasApi::requireToken();

$q = trim((string)($_GET['q'] ?? ''));
try {
    Unspsc::ensureTable();
    VsiasApi::json([
        'status' => 'success',
        'results' => Unspsc::search($q),
    ]);
} catch (Throwable $e) {
    VsiasApi::json(['status' => 'error', 'message' => $e->getMessage()], 500);
}
