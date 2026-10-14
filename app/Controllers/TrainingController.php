<?php

namespace App\Controllers;

use App\Services\Training;

final class TrainingController extends Controller
{
    public function index(): void
    {
        if (!$this->userId()) {
            $this->redirect('login');
        }
        $training = new Training();
        if (isset($_POST['start_training'])) {
            try {
                $dest = $training->start(
                    $this->userId(),
                    $this->locationId(),
                    (string)($_POST['course'] ?? ''),
                    $this->role()
                );
                $this->flash('success', $dest['title'] . ' is on. Practice records stay in the temporary data.');
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
                $this->redirect('dashboard');
            }
            $this->redirect($dest['page'], $dest['query'] ?? []);
        }
        if (isset($_POST['end_training'])) {
            $training->end();
            $this->flash('success', 'Training ended. The practice data has been removed.');
            $this->redirect('dashboard');
        }
        if ($this->wantsAjax() || isset($_GET['pulse'])) {
            $this->jsonExit($training->pulse());
        }
        $this->redirect('pos');
    }
}
