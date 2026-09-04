<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use PDO;

class Controller
{
    public function __construct(
        protected App $app,
        protected string $page,
        protected array $route = []
    ) {
    }

    public function index(): void
    {
        $this->run();
    }

    protected function pdo(): PDO
    {
        return Database::connection();
    }

    protected function userId(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    protected function locationId(): int
    {
        return (int)($_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0);
    }

    protected function role(): string
    {
        return strtolower((string)($_SESSION['role'] ?? ''));
    }

    protected function isManager(): bool
    {
        return in_array($this->role(), ['admin', 'manager', 'dev'], true);
    }

    protected function requireRole(array $roles, string $fallback = 'dashboard'): void
    {
        $roles = array_map('strtolower', $roles);
        if (!in_array($this->role(), $roles, true)) {
            $this->flash('error', 'You do not have permission to open this page.');
            $this->redirect($fallback);
        }
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['swal_type'] = $type;
        $_SESSION['swal_msg'] = $message;
    }

    protected function jsonExit(array $data, int $code = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        echo $json !== false ? $json : '{"status":"error","msg":"Unable to encode response."}';
        exit;
    }

    protected function run(): void
    {
        $pdo = Database::connection();
        $page = $this->page;
        $actionOnly = !empty($this->route['action_only']);

        $actionFile = APP_PATH . '/Actions/' . $page . '.php';
        if (is_file($actionFile)) {
            require $actionFile;
        }

        if ($actionOnly) {
            return;
        }

        $vars = get_defined_vars();
        unset($vars['this']);
        $this->render($vars);
    }

    protected function render(array $vars = []): void
    {
        unset($vars['this']);
        extract($vars, EXTR_SKIP);
        $pdo = $pdo ?? Database::connection();
        $page = $this->page;
        $standalone = !empty($this->route['standalone']);

        $viewFile = APP_PATH . '/Views/' . $page . '.php';
        if (!is_file($viewFile)) {
            return;
        }

        $header = APP_PATH . '/Views/layouts/header.php';
        $footer = APP_PATH . '/Views/layouts/footer.php';

        if (!$standalone && is_file($header)) {
            require $header;
        }
        require $viewFile;
        if (!$standalone && is_file($footer)) {
            require $footer;
        }
    }

    protected function redirect(string $page, array $query = []): never
    {
        $url = 'index.php?page=' . urlencode($page);
        foreach ($query as $key => $value) {
            $url .= '&' . urlencode((string)$key) . '=' . urlencode((string)$value);
        }
        header('Location: ' . $url);
        exit;
    }
}
