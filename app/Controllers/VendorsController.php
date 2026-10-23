<?php

namespace App\Controllers;

use App\Models\Vendor;

final class VendorsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['bulk_action'] ?? '') === 'delete') {
                $deleted = 0;
                $blocked = 0;
                foreach ($this->postedIds() as $id) {
                    if (!Vendor::delete($id)) {
                        $blocked++;
                    } else {
                        $deleted++;
                    }
                }
                $msg = $deleted . ' vendor' . ($deleted === 1 ? '' : 's') . ' deleted.';
                if ($blocked) {
                    $msg .= ' ' . $blocked . ' have goods-received records and were kept.';
                }
                $this->flash($blocked && !$deleted ? 'error' : 'success', $msg);
                $this->redirect('vendors');
            }
            if (isset($_POST['add_vendor'])) {
                $name = trim((string)$_POST['name']);
                if ($name !== '') {
                    Vendor::create($name, trim((string)$_POST['contact_person']), trim((string)$_POST['phone']));
                    $this->flash('success', "Vendor '$name' added.");
                }
            } elseif (isset($_POST['save_portal'])) {
                try {
                    $id = (int)($_POST['vendor_id'] ?? 0);
                    $generated = Vendor::setPortal(
                        $id,
                        (string)($_POST['portal_email'] ?? ''),
                        (string)($_POST['portal_password'] ?? ''),
                        !empty($_POST['portal_active'])
                    );
                    $msg = 'Vendor portal login saved.';
                    if ($generated !== null) {
                        $msg .= ' Password, shown once: ' . $generated;
                    }
                    $this->flash('success', $msg);
                } catch (\Throwable $e) {
                    $this->flash('error', $e->getMessage());
                }
            } elseif (isset($_POST['delete_vendor'])) {
                if (!Vendor::delete((int)$_POST['vendor_id'])) {
                    $this->flash('error', 'This vendor has GRV records and cannot be deleted.');
                } else {
                    $this->flash('success', 'Vendor deleted.');
                }
            }
            $this->redirect('vendors');
        }

        $this->render(['vendors' => Vendor::all()]);
    }
}
