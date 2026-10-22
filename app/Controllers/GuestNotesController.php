<?php

namespace App\Controllers;

use App\Models\GuestNote;

final class GuestNotesController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handle_note'])) {
            GuestNote::markHandled((int)$_POST['handle_note']);
            $this->flash('success', 'Marked as handled.');
            $this->redirect('guest_notes');
        }
        $this->render([
            'notes' => GuestNote::list(),
            'openCount' => GuestNote::openCount(),
        ]);
    }
}
