<?php

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/SettingsController.php';
require_once __DIR__ . '/../controllers/ImageController.php';
require_once __DIR__ . '/../controllers/OverlayController.php';
require_once __DIR__ . '/../utils/Request.php';

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

        if (preg_match("/^\/api\/(.*)$/", $requestUri, $matches)) {
            $requestUri = "/" . $matches[1];
        }

        $request = new Request(); // Create the Request object here

        foreach ($this->routes as $route) {
            $pattern = "#^" . $route["path"] . "$#";
            if (
                preg_match($pattern, $requestUri, $matches) &&
                ($route["method"] === "ANY" ||
                    $route["method"] === $requestMethod)
            ) {
                $params = array_slice($matches, 1);
                array_unshift($params, $request); // Prepend the Request object
                call_user_func_array($route["handler"], $params);
                exit();
            }
        }

        sendResponse(404, ["message" => "Not Found"]);
    }
}

$router = new Router($pdo);

$authController = new AuthController($pdo);
$settingsController = new SettingsController($pdo);
$imageController = new ImageController($pdo);
$overlayController = new OverlayController($pdo);

$router->addRoute("POST", "/auth/login", [$authController, "login"]);
$router->addRoute("POST", "/auth/register", [$authController, "register"]);
$router->addRoute("POST", "/auth/reset", [$authController, "reset"]);
$router->addRoute("POST", "/auth/token", [$authController, "token"]);
$router->addRoute("POST", "/auth/logout", [$authController, "logout"]);

$router->addRoute("ANY", "/settings", [$settingsController, "handle"]);

$router->addRoute("ANY", "/images", [$imageController, "handleImages"]);
$router->addRoute("DELETE", "/images/(\d+)", [$imageController, "deleteImage"]);
$router->addRoute("POST", "/images/(\d+)/like", [$imageController, "likeImage"]);
$router->addRoute("POST", "/images/(\d+)/comment", [$imageController, "commentImage"]);

$router->addRoute("GET", "/overlays", [$overlayController, "handleOverlays"]);
