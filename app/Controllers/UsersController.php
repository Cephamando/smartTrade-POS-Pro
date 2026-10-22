<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\User;

final class UsersController extends Controller
{
    public function index(): void
    {
        if (!in_array($this->role(), ['admin', 'dev'], true)) {
            $this->flash('error', 'You do not have permission to manage users.');
            $this->redirect('dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['bulk_action'] ?? '') === 'delete') {
                $deleted = 0;
                $deactivated = 0;
                $skipped = 0;
                foreach ($this->postedIds() as $userId) {
                    $target = User::findById($userId);
                    $targetRole = $target['role'] ?? '';
                    if ($targetRole === 'dev' || $userId === $this->userId()) {
                        $skipped++;
                        continue;
                    }
                    $result = User::deleteOrDeactivate($userId);
                    if ($result === 'deactivated') {
                        $deactivated++;
                    } else {
                        $deleted++;
                    }
                }
                $parts = [];
                if ($deleted) {
                    $parts[] = $deleted . ' deleted';
                }
                if ($deactivated) {
                    $parts[] = $deactivated . ' deactivated (they have history)';
                }
                if ($skipped) {
                    $parts[] = $skipped . ' left unchanged';
                }
                $this->flash('success', $parts ? implode(', ', $parts) . '.' : 'Nothing was selected.');
                $this->redirect('users');
            }
            if (isset($_POST['reset_sign_in'])) {
                $userId = (int)$_POST['user_id'];
                $target = User::findById($userId);
                $targetRole = (string)($target['role'] ?? '');
                if (!$target || !User::officeRole($targetRole)) {
                    $this->flash('error', 'That account does not use a sign-in code.');
                } elseif ($targetRole === 'dev' && $this->role() !== 'dev') {
                    $this->flash('error', 'The developer account cannot be modified.');
                } else {
                    User::clearSignInCode($userId);
                    $this->flash('success', 'Sign-in code reset. The next sign-in will set a new one.');
                }
                $this->redirect('users');
            }

            if (isset($_POST['add_user'])) {
                $role = (string)$_POST['role'];
                if ($role === 'dev' && $this->role() !== 'dev') {
                    $role = 'admin';
                }
                $password = (string)$_POST['password'];
                $code = trim((string)($_POST['approval_code'] ?? ''));
                if (!User::passwordOk($password)) {
                    $this->flash('error', 'The password must be at least 6 characters.');
                    $this->redirect('users');
                }
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                if (User::approverRole($role)) {
                    $problem = $code === '' ? 'This role needs an approval code for the till.' : User::approvalCodeError($code, $passwordHash);
                    if ($problem !== null) {
                        $this->flash('error', $problem);
                        $this->redirect('users');
                    }
                }
                try {
                    $newId = User::create(
                        trim((string)$_POST['username']),
                        trim((string)$_POST['full_name']),
                        $passwordHash,
                        $role,
                        (int)$_POST['location_id']
                    );
                    if ($code !== '' && User::approverRole($role)) {
                        User::setApprovalCode($newId, $code);
                    }
                    $this->flash('success', 'User created.');
                } catch (\Throwable $e) {
                    $this->flash('error', 'That username is already in use.');
                }
                $this->redirect('users');
            }

            if (isset($_POST['edit_user'])) {
                $userId = (int)$_POST['user_id'];
                $target = User::findById($userId);
                $targetRole = $target['role'] ?? '';
                if ($targetRole === 'dev' && $this->role() !== 'dev') {
                    $this->flash('error', 'The developer account cannot be modified.');
                    $this->redirect('users');
                }
                $role = (string)$_POST['role'];
                if ($targetRole === 'dev' && $role !== 'dev') {
                    $role = 'dev';
                }
                $password = (string)($_POST['password'] ?? '');
                $code = trim((string)($_POST['approval_code'] ?? ''));
                if ($password !== '' && !User::passwordOk($password)) {
                    $this->flash('error', 'The password must be at least 6 characters.');
                    $this->redirect('users');
                }
                $newHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                $checkHash = $newHash ?? (string)($target['password_hash'] ?? '');
                $hasApproval = trim((string)($target['approval_code_hash'] ?? '')) !== '';
                if (User::approverRole($role) && $code === '' && !$hasApproval) {
                    $this->flash('error', 'This role needs an approval code for the till.');
                    $this->redirect('users');
                }
                if ($code !== '') {
                    $problem = User::approvalCodeError($code, $checkHash);
                    if ($problem !== null) {
                        $this->flash('error', $problem);
                        $this->redirect('users');
                    }
                }
                try {
                    User::update($userId, trim((string)$_POST['username']), trim((string)$_POST['full_name']), $role, (int)$_POST['location_id'], $newHash);
                    if ($code !== '') {
                        User::setApprovalCode($userId, $code);
                    }
                    if ($newHash && $userId === $this->userId()) {
                        $_SESSION['session_rev'] = User::sessionRev($userId);
                        $_SESSION['last_seen'] = time();
                    }
                    $this->flash('success', 'User updated.');
                } catch (\Throwable $e) {
                    $this->flash('error', 'Unable to update this user.');
                }
                $this->redirect('users');
            }

            if (isset($_POST['delete_user'])) {
                $userId = (int)$_POST['user_id'];
                $target = User::findById($userId);
                $targetRole = $target['role'] ?? '';
                if ($targetRole === 'dev') {
                    $this->flash('error', 'The developer account cannot be deleted.');
                } elseif ($userId === $this->userId()) {
                    $this->flash('error', 'You cannot delete the signed-in account.');
                } else {
                    try {
                        $result = User::deleteOrDeactivate($userId);
                        if ($result === 'deactivated') {
                            $this->flash('info', 'This user has history, so the account was deactivated.');
                        } else {
                            $this->flash('success', 'User deleted.');
                        }
                    } catch (\Throwable $e) {
                        $this->flash('error', 'Unable to delete this user.');
                    }
                }
                $this->redirect('users');
            }
        }

        $this->render([
            'users' => User::activeStaff(),
            'locations' => Location::names(),
        ]);
    }
}
