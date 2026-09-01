<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\RestaurantTable;

final class TablesController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);

        if (isset($_POST['add_table'])) {
            RestaurantTable::create(
                (int)$_POST['location_id'],
                trim((string)$_POST['zone_name']),
                trim((string)$_POST['table_name']),
                (int)$_POST['capacity']
            );
            $this->flash('success', 'Table added successfully!');
            $this->redirect('tables');
        }
        if (isset($_POST['delete_table'])) {
            RestaurantTable::delete((int)$_POST['table_id']);
            $this->flash('success', 'Table deleted.');
            $this->redirect('tables');
        }

        $this->render([
            'locations' => Location::names(),
            'tables' => RestaurantTable::allWithLocation(),
        ]);
    }
}
