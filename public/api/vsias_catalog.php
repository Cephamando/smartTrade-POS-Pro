<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Helpers\VsiasApi;
use App\Services\VsiasBridge;

VsiasApi::requireToken();

try {
    $bridge = new VsiasBridge($pdo);
    VsiasApi::json(['status' => 'success'] + $bridge->catalog());
} catch (Throwable $e) {
    VsiasApi::json(['status' => 'error', 'message' => $e->getMessage()], 500);
}
