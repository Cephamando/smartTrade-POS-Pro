<?php

namespace App\Controllers;

use App\Models\User;

final class ChangePasswordController extends Controller
{
    public function index(): void
    {
        $userId = $this->userId();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_approval'])) {
            $currentPass = (string)($_POST['current_password'] ?? '');
            $code = trim((string)($_POST['approval_code'] ?? ''));
            $hash = User::passwordHash($userId);
            $problem = $hash ? User::approvalCodeError($code, $hash) : 'Current password is incorrect.';
            if (!$hash || !password_verify($currentPass, $hash)) {
                $this->flash('error', 'Current password is incorrect.');
            } elseif ($problem !== null) {
                $this->flash('error', $problem);
            } else {
                User::setApprovalCode($userId, $code);
                $this->flash('success', 'Approval code saved. Use it at the till, not the login password.');
            }
            $this->redirect('change_password');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPass = (string)($_POST['current_password'] ?? '');
            $newPass = (string)($_POST['new_password'] ?? '');
            $confirmPass = (string)($_POST['confirm_password'] ?? '');
            if ($currentPass === '' || $newPass === '' || $confirmPass === '') {
                $this->flash('error', 'Complete all password fields.');
            } elseif (!User::passwordOk($newPass)) {
                $this->flash('error', 'New password must be at least 6 characters.');
            } elseif ($newPass !== $confirmPass) {
                $this->flash('error', 'New password and confirmation do not match.');
            } else {
                $hash = User::passwordHash($userId);
                $approvalHash = (string)(User::findById($userId)['approval_code_hash'] ?? '');
                if ($hash && password_verify($currentPass, $hash)) {
                    if ($approvalHash !== '' && password_verify($newPass, $approvalHash)) {
                        $this->flash('error', 'The new password must not be the till approval code.');
                        $this->redirect('change_password');
                    }
                    $_SESSION['session_rev'] = User::changePassword($userId, password_hash($newPass, PASSWORD_DEFAULT));
                    $_SESSION['force_change'] = 0;
                    $_SESSION['last_seen'] = time();
                    $this->flash('success', 'Password updated. Other signed-in browsers were signed out.');
                    $this->redirect('dashboard');
                } else {
                    $this->flash('error', 'Current password is incorrect.');
                }
            }
        }
        $me = User::findById($userId);
        $this->render([
            'canApprove' => User::approverRole($this->role()),
            'hasApproval' => trim((string)($me['approval_code_hash'] ?? '')) !== '',
        ]);
    }
}
