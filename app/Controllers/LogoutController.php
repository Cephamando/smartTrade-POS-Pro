<?php

namespace App\Controllers;

final class LogoutController extends Controller
{
    public function index(): void
    {
        (new \App\Services\Training())->end();
        if ((int)($_SESSION['user_id'] ?? 0) > 0) {
            \App\Services\Activity::log($this->pdo(), 'logout', 'ok', [
                'user_id' => (int)$_SESSION['user_id'],
                'username' => (string)($_SESSION['username'] ?? ''),
                'location_id' => (int)($_SESSION['location_id'] ?? 0),
                'location_name' => (string)($_SESSION['location_name'] ?? ''),
                'subject' => 'Signed out',
            ]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: index.php?page=login');
        exit;
    }
}
