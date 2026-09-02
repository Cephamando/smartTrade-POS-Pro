<?php

namespace App\Controllers;

use App\Models\User;

final class LoginController extends Controller
{
    public function index(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
                $error = "Your session expired. Refresh the page and try again.";
            } else {
                $username = trim((string)($_POST['username'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                if ($username === '' || $password === '') {
                    $error = "Enter your username and password.";
                } else {
                    $user = User::findActiveByUsername($username);
                    if ($user && password_verify($password, $user['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['location_id'] = $user['location_id'];
                        $_SESSION['location_name'] = $user['location_name'];
                        $_SESSION['force_change'] = $user['force_password_change'];

                        if ((int)$user['force_password_change'] === 1) {
                            $this->redirect('change_password');
                        }
                        if (in_array($user['role'], ['admin', 'manager', 'dev'], true)) {
                            $this->redirect('dashboard');
                        }
                        if (in_array($user['role'], ['chef', 'head_chef'], true)) {
                            $this->redirect('kitchen');
                        }
                        $this->redirect('pos');
                    }
                    $error = "Username or password is incorrect.";
                }
            }
        }

        $this->render(['error' => $error]);
    }
}
