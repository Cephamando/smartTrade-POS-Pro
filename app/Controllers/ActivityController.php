<?php

namespace App\Controllers;

use App\Services\Activity;

final class ActivityController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['dev']);
        $start = (string)($_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')));
        $end = (string)($_GET['end_date'] ?? date('Y-m-d'));
        if (strtotime($start) === false) {
            $start = date('Y-m-d', strtotime('-7 days'));
        }
        if (strtotime($end) === false) {
            $end = date('Y-m-d');
        }
        $userId = (int)($_GET['user_id'] ?? 0);
        $action = trim((string)($_GET['action_name'] ?? ''));
        $q = trim((string)($_GET['q'] ?? ''));
        $logs = [];
        $actions = [];
        try {
            $logs = Activity::search($this->pdo(), $start, $end, $userId, $action, $q);
            $actions = Activity::actions($this->pdo());
        } catch (\Throwable $e) {
            $this->flash('error', 'The activity log is not ready yet. Refresh the page.');
        }
        $users = $this->pdo()->query('SELECT id, username, full_name FROM users ORDER BY full_name, username')->fetchAll() ?: [];
        $this->render([
            'startDate' => $start,
            'endDate' => $end,
            'userId' => $userId,
            'action' => $action,
            'q' => $q,
            'logs' => $logs,
            'actions' => $actions,
            'users' => $users,
        ]);
    }
}
