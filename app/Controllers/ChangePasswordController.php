<?php

namespace App\Controllers;

use App\Models\User;

final class ChangePasswordController extends Controller
{
    public function index(): void
    {
        $userId = $this->userId();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPass = (string)$_POST['current_password'];
            $newPass = (string)$_POST['new_password'];
            $confirmPass = (string)$_POST['confirm_password'];
            if ($currentPass === '' || $newPass === '' || $confirmPass === '') {
                $this->flash('error', 'Complete all password fields.');
            } elseif ($newPass !== $confirmPass) {
                $this->flash('error', 'New password and confirmation do not match.');
            } else {
                $hash = User::passwordHash($userId);
                if ($hash && password_verify($currentPass, $hash)) {
                    User::changePassword($userId, password_hash($newPass, PASSWORD_DEFAULT));
                    $_SESSION['force_change'] = 0;
                    $this->flash('success', 'Password updated.');
                    $this->redirect('dashboard');
                } else {
                    $this->flash('error', 'Current password is incorrect.');
                }
            }
        }
        $this->render();
    }
}
