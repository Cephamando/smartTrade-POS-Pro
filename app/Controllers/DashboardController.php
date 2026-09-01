<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Sale;
use App\Models\Shift;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $dashLocId = $this->locationId();
        $kpis = Sale::dashboardKpis($dashLocId);
        $dashLocName = 'All Locations';
        if ($dashLocId > 0) {
            $dashLocName = Location::nameById($dashLocId);
        }
        $this->render(array_merge($kpis, [
            'dashLocId' => $dashLocId,
            'dashLocName' => $dashLocName,
            'activeShifts' => Shift::openWithStats(),
            'tier' => defined('LICENSE_TIER') ? LICENSE_TIER : 'lite',
        ]));
    }
}
