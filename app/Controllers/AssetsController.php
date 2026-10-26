<?php

namespace App\Controllers;

use App\Models\Asset;
use App\Models\Location;

final class AssetsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev', 'owner']);
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            try {
                if (isset($_POST['dispose_asset'])) {
                    Asset::dispose((int)($_POST['id'] ?? 0), (string)($_POST['disposed_on'] ?? ''), (float)($_POST['disposal_proceeds'] ?? 0));
                    $this->flash('success', 'The asset has been taken off the register.');
                } else {
                    Asset::save($_POST);
                    $this->flash('success', 'The asset has been saved.');
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'The asset could not be saved.');
            }
            $this->redirect('assets');
        }
        $rows = [];
        foreach (Asset::all() as $asset) {
            $asset['position'] = Asset::position($asset);
            $rows[] = $asset;
        }
        $this->render([
            'assets' => $rows,
            'categories' => Asset::categories(),
            'locations' => Location::all(),
            'snapshot' => Asset::snapshot(0),
        ]);
    }
}
