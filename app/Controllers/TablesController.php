<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\RestaurantTable;
use App\Models\Setting;

final class TablesController extends Controller
{
    private const DEFAULT_MENU_URL = 'https://queenbeesrestaurants.com/index.php?page=shop&view=menu';

    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);

        if (($_POST['bulk_action'] ?? '') === 'delete') {
            foreach ($this->postedIds() as $id) {
                RestaurantTable::delete($id);
            }
            $this->flash('success', 'Selected tables deleted.');
            $this->redirect('tables');
        }
        if (isset($_POST['add_table'])) {
            RestaurantTable::create(
                (int)$_POST['location_id'],
                trim((string)$_POST['zone_name']),
                trim((string)$_POST['table_name']),
                (int)$_POST['capacity']
            );
            $this->flash('success', 'Table added.');
            $this->redirect('tables');
        }
        if (isset($_POST['delete_table'])) {
            RestaurantTable::delete((int)$_POST['table_id']);
            $this->flash('success', 'Table deleted.');
            $this->redirect('tables');
        }
        if (isset($_POST['save_reserve'])) {
            $this->saveReserve();
        }

        $settings = Setting::all();
        $this->render([
            'locations' => Location::names(),
            'tables' => RestaurantTable::allWithLocation(),
            'reserveLabel' => self::reserveLabel($settings),
            'reserveAmount' => round(max(0, (float)($settings['shop_reserve_amount'] ?? 0)), 2),
            'menuUrl' => self::menuUrl($settings),
        ]);
    }

    private function saveReserve(): never
    {
        $label = trim((string)($_POST['shop_reserve_label'] ?? ''));
        if ($label === '') {
            $label = 'Table reservation';
        }
        if (strlen($label) > 80) {
            $this->flash('error', 'The reservation name must be 80 characters or fewer.');
            $this->redirect('tables');
        }
        $amount = round((float)($_POST['shop_reserve_amount'] ?? 0), 2);
        if ($amount < 0 || $amount > 100000) {
            $this->flash('error', 'Enter a reservation surcharge from 0 to 100000.');
            $this->redirect('tables');
        }
        $url = trim((string)($_POST['shop_menu_url'] ?? ''));
        if ($url === '') {
            $url = self::DEFAULT_MENU_URL;
        }
        if (!self::validMenuUrl($url)) {
            $this->flash('error', 'The menu address must be an https link to the shop menu.');
            $this->redirect('tables');
        }
        Setting::save([
            'shop_reserve_label' => $label,
            'shop_reserve_amount' => number_format($amount, 2, '.', ''),
            'shop_menu_url' => $url,
        ]);
        $this->flash('success', 'Reservation surcharge saved.');
        $this->redirect('tables');
    }

    /** @param array<string, mixed> $settings */
    public static function reserveLabel(array $settings): string
    {
        $label = trim((string)($settings['shop_reserve_label'] ?? ''));
        return $label !== '' ? $label : 'Table reservation';
    }

    /** @param array<string, mixed> $settings */
    public static function menuUrl(array $settings): string
    {
        $url = trim((string)($settings['shop_menu_url'] ?? ''));
        return self::validMenuUrl($url) ? $url : self::DEFAULT_MENU_URL;
    }

    public static function validMenuUrl(string $url): bool
    {
        if (strlen($url) > 300 || preg_match('/\s/', $url)) {
            return false;
        }
        if (!str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        parse_str((string)($parts['query'] ?? ''), $query);
        return ($query['page'] ?? '') === 'shop' && ($query['view'] ?? '') === 'menu';
    }
}
