<?php

namespace App\Controllers;

use App\Models\Product;
use App\Models\Unspsc;

final class UnspscController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        Unspsc::ensureTable();

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
            $this->jsonExit(['results' => Unspsc::search((string)($_GET['q'] ?? ''))]);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['import_unspsc']) || isset($_POST['import_unspsc_bundled'])) {
                    ignore_user_abort(true);
                    @set_time_limit(0);
                    ini_set('max_execution_time', '0');
                    ini_set('memory_limit', '512M');
                }
                if (isset($_POST['import_unspsc_bundled'])) {
                    $bundled = Unspsc::bundledFilePath();
                    if ($bundled === null) {
                        throw new \Exception('Bundled UNSPSC-Classification-Codes.xlsx was not found in docker/storage/other.');
                    }
                    $result = Unspsc::importFile($bundled, Unspsc::BUNDLED_FILENAME);
                    $this->flash('success', "UNSPSC classification imported. {$result['imported']} row(s) loaded ({$result['total']} total).");
                } elseif (isset($_POST['import_unspsc']) && isset($_FILES['unspsc_file'])) {
                    if (($_FILES['unspsc_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        throw new \Exception('Upload failed. Check the file and try again.');
                    }
                    $result = Unspsc::importFile(
                        $_FILES['unspsc_file']['tmp_name'],
                        (string)($_FILES['unspsc_file']['name'] ?? '')
                    );
                    $this->flash('success', "UNSPSC list updated. {$result['imported']} row(s) imported ({$result['total']} total).");
                }
                if (isset($_POST['assign_unspsc'])) {
                    Unspsc::assignToProduct((int)$_POST['product_id'], (string)$_POST['unspsc_code']);
                    if (!empty($_POST['ajax'])) {
                        $this->jsonExit(['status' => 'success']);
                    }
                    $this->flash('success', 'UNSPSC code linked to the product.');
                }
            } catch (\Throwable $e) {
                if (!empty($_POST['ajax'])) {
                    $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()], 400);
                }
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('unspsc');
        }

        $q = trim((string)($_GET['q'] ?? ''));
        $pageNo = max(1, (int)($_GET['p'] ?? 1));
        $list = Unspsc::page($q, $pageNo);

        $this->render([
            'q' => $q,
            'list' => $list,
            'products' => Product::allWithCategory(),
            'classCount' => Unspsc::count(),
            'bundledFile' => Unspsc::bundledFilePath() !== null,
            'bundledName' => Unspsc::BUNDLED_FILENAME,
        ]);
    }
}
