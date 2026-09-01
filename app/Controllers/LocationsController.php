<?php

namespace App\Controllers;

use App\Models\Location;

final class LocationsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'dev']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_location'])) {
                $name = trim((string)$_POST['name']);
                if ($name !== '') {
                    Location::create($name, (string)$_POST['type'], trim((string)$_POST['address']));
                    $this->flash('success', "Location '$name' created successfully.");
                }
            } elseif (isset($_POST['delete_location'])) {
                $result = Location::delete((int)$_POST['location_id']);
                if ($result === 'stock') {
                    $this->flash('error', 'Cannot delete: This location still has stock.');
                } elseif ($result === 'sales') {
                    $this->flash('error', 'Cannot delete: Historical sales data exists for this location.');
                } else {
                    $this->flash('success', 'Location deleted.');
                }
            }
            $this->redirect('locations');
        }

        $this->render(['locations' => Location::all()]);
    }
}
