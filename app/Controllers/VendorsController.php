<?php

namespace App\Controllers;

use App\Models\Vendor;

final class VendorsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_vendor'])) {
                $name = trim((string)$_POST['name']);
                if ($name !== '') {
                    Vendor::create($name, trim((string)$_POST['contact_person']), trim((string)$_POST['phone']));
                    $this->flash('success', "Vendor '$name' added.");
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
