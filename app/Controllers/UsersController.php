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
            if (isset($_POST['add_user'])) {
                $role = (string)$_POST['role'];
                if ($role === 'dev' && $this->role() !== 'dev') {
                    $role = 'admin';
                }
                try {
                    User::create(
                        trim((string)$_POST['username']),
                        trim((string)$_POST['full_name']),
                        password_hash((string)$_POST['password'], PASSWORD_DEFAULT),
                        $role,
                        (int)$_POST['location_id']
                    );
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
                try {
                    $hash = !empty($_POST['password']) ? password_hash((string)$_POST['password'], PASSWORD_DEFAULT) : null;
                    User::update($userId, trim((string)$_POST['username']), trim((string)$_POST['full_name']), $role, (int)$_POST['location_id'], $hash);
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
