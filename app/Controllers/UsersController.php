<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\User;

final class UsersController extends Controller
{
    public function index(): void
    {
        if (!in_array($this->role(), ['admin', 'dev'], true)) {
            die('<h1>Access Denied</h1><p>You do not have permission to manage users.</p>');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                    $this->flash('success', 'User created successfully.');
                } catch (\Throwable $e) {
                    $this->flash('error', 'Error: Username might already be taken.');
                }
                $this->redirect('users');
            }

            if (isset($_POST['edit_user'])) {
                $userId = (int)$_POST['user_id'];
                $target = User::findById($userId);
                $targetRole = $target['role'] ?? '';
                if ($targetRole === 'dev' && $this->role() !== 'dev') {
                    $this->flash('error', 'Access Denied: You cannot modify the Developer account.');
                    $this->redirect('users');
                }
                $role = (string)$_POST['role'];
                if ($targetRole === 'dev' && $role !== 'dev') {
                    $role = 'dev';
                }
                try {
                    $hash = !empty($_POST['password']) ? password_hash((string)$_POST['password'], PASSWORD_DEFAULT) : null;
                    User::update($userId, trim((string)$_POST['username']), trim((string)$_POST['full_name']), $role, (int)$_POST['location_id'], $hash);
                    $this->flash('success', 'User updated successfully.');
                } catch (\Throwable $e) {
                    $this->flash('error', 'Error updating user.');
                }
                $this->redirect('users');
            }

            if (isset($_POST['delete_user'])) {
                $userId = (int)$_POST['user_id'];
                $target = User::findById($userId);
                $targetRole = $target['role'] ?? '';
                if ($targetRole === 'dev') {
                    $this->flash('error', 'Critical: The Developer account is protected and cannot be deleted.');
                } elseif ($userId === $this->userId()) {
                    $this->flash('error', 'You cannot delete your active session.');
                } else {
                    try {
                        $result = User::deleteOrDeactivate($userId);
                        if ($result === 'deactivated') {
                            $this->flash('info', 'User has historical data. Account safely deactivated instead.');
                        } else {
                            $this->flash('success', 'User deleted successfully.');
                        }
                    } catch (\Throwable $e) {
                        $this->flash('error', 'Error deleting user: ' . $e->getMessage());
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
