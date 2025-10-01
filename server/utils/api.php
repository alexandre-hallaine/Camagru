<?php

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/SettingsController.php';
require_once __DIR__ . '/../controllers/ImageController.php';
require_once __DIR__ . '/../controllers/OverlayController.php';

class Router
{
    private PDO $pdo;
    private array $routes = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            "method" => $method,
            "path" => $path,
            "handler" => $handler,
        ];
    }

    public function dispatch(string $requestUri, string $requestMethod): void
    {
        $requestUri = strtok($requestUri, "?");
        if (preg_match("/^\/api\/(.*)$/", $requestUri, $matches))
            $requestUri = "/" . $matches[1];

        foreach ($this->routes as $route) {
            if (preg_match("#^" . $route["path"] . "$#", $requestUri, $matches) &&
                ($route["method"] === "ANY" || $route["method"] === $requestMethod)) {
                $params = array_slice($matches, 1);

                try {
                    call_user_func_array($route["handler"], $params);
                } catch (Exception $e) {
                    sendResponse(500, ["message" => $e->getMessage()]);
                }

                exit();
            }
        }

        sendResponse(404, ["message" => "Not Found"]);
    }
}

$router = new Router($pdo);

$authController = new AuthController($pdo);
$router->addRoute("POST", "/auth/login", [$authController, "login"]);
$router->addRoute("POST", "/auth/register", [$authController, "register"]);
$router->addRoute("POST", "/auth/reset", [$authController, "reset"]);
$router->addRoute("POST", "/auth/token", [$authController, "token"]);
$router->addRoute("POST", "/auth/logout", [$authController, "logout"]);

$settingsController = new SettingsController($pdo);
$router->addRoute("GET", "/settings", [$settingsController, "handle"]);
$router->addRoute("POST", "/settings", [$settingsController, "update"]);

$imageController = new ImageController($pdo);
$router->addRoute("GET", "/images", [$imageController, "handle"]);
$router->addRoute("POST", "/images", [$imageController, "create"]);
$router->addRoute("DELETE", "/images/(\d+)", [$imageController, "delete"]);
$router->addRoute("POST", "/images/(\d+)/like", [$imageController, "like"]);
$router->addRoute("POST", "/images/(\d+)/comment", [$imageController, "comment"]);

$overlayController = new OverlayController($pdo);
$router->addRoute("GET", "/overlays", [$overlayController, "handle"]);
