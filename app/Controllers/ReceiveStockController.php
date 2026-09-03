<?php

namespace App\Controllers;

final class ReceiveStockController extends Controller
{
    public function index(): void
    {
        $this->redirect('purchasing', ['tab' => 'received']);
    }
}
