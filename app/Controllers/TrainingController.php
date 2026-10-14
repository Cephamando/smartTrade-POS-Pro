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
                $training->start($this->userId(), $this->locationId());
                $this->flash('success', 'Training Mode is on. Practice sales stay in the temporary data.');
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
                $this->redirect('dashboard');
            }
            $this->redirect('pos');
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
