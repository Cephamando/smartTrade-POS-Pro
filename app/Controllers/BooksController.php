<?php

namespace App\Controllers;

use App\Models\Books;
use App\Models\Location;

final class BooksController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        $catalog = Books::catalog();
        $book = (string)($_GET['book'] ?? '');
        if (!isset($catalog[$book])) {
            $book = (string)array_key_first($catalog);
        }
        $start = $this->day((string)($_GET['start'] ?? date('Y-m-01')));
        $end = $this->day((string)($_GET['end'] ?? date('Y-m-d')));
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        $locationId = (int)($_GET['location_id'] ?? 0);
        $opened = Books::open($book, $start, $end, $locationId);

        if (isset($_GET['fragment'])) {
            $this->jsonExit([
                'status' => 'ok',
                'html' => $this->renderPartial('book_sheet', [
                    'opened' => $opened,
                    'start' => $start,
                    'end' => $end,
                ]),
            ]);
        }

        $this->render([
            'catalog' => $catalog,
            'book' => $book,
            'start' => $start,
            'end' => $end,
            'locationId' => $locationId,
            'locations' => Location::names(),
            'opened' => $opened,
        ]);
    }

    private function day(string $value): string
    {
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
