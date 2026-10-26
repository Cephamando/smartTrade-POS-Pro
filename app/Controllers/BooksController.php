<?php

namespace App\Controllers;

use App\Models\Books;
use App\Models\Location;

final class BooksController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev', 'owner']);
        $catalog = Books::catalog();
        $book = (string)($_GET['book'] ?? '');
        $start = $this->day((string)($_GET['start'] ?? date('Y-m-01')));
        $end = $this->day((string)($_GET['end'] ?? date('Y-m-d')));
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        $locationId = (int)($_GET['location_id'] ?? 0);
        $filled = Books::filled($start, $end, $locationId);
        if (!isset($catalog[$book])) {
            $book = $filled[0] ?? (string)array_key_first($catalog);
        }
        $ordered = [];
        foreach ($filled as $key) {
            $ordered[$key] = $catalog[$key];
        }
        foreach ($catalog as $key => $meta) {
            if (!isset($ordered[$key])) {
                $ordered[$key] = $meta;
            }
        }
        $opened = Books::open($book, $start, $end, $locationId);

        if (isset($_GET['fragment'])) {
            $this->jsonExit([
                'status' => 'ok',
                'book' => $book,
                'filled' => $filled,
                'html' => $this->renderPartial('book_sheet', [
                    'opened' => $opened,
                    'start' => $start,
                    'end' => $end,
                ]),
            ]);
        }

        $this->render([
            'catalog' => $ordered,
            'book' => $book,
            'start' => $start,
            'end' => $end,
            'locationId' => $locationId,
            'locations' => Location::names(),
            'opened' => $opened,
            'filled' => $filled,
        ]);
    }

    private function day(string $value): string
    {
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
