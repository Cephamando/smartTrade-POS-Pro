<?php

namespace App\Core;

use App\Controllers\Controller;

final class Router
{
    public function __construct(private App $app)
    {
    }

    public function dispatch(string $page): void
    {
        $routes = require APP_PATH . '/Config/routes.php';
        if (!isset($routes[$page])) {
            header('Location: index.php');
            exit;
        }

        $config = $routes[$page];
        $controllerClass = $this->controllerClass($page);
        if (!class_exists($controllerClass)) {
            $controllerClass = Controller::class;
        }

        $controller = new $controllerClass($this->app, $page, $config);
        $controller->index();
    }

    private function controllerClass(string $page): string
    {
        $studly = str_replace(' ', '', ucwords(str_replace('_', ' ', $page)));
        return 'App\\Controllers\\' . $studly . 'Controller';
    }
}
