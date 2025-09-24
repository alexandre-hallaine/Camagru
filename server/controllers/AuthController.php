<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Action.php';
require_once __DIR__ . '/../models/Setting.php';

class AuthController
{
    private PDO $pdo;
    private User $userModel;
    private Action $actionModel;
    private Setting $settingModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
        $this->actionModel = new Action($pdo);
        $this->settingModel = new Setting($pdo);
    }

    public function login(Request $request): void
    {
        $requestMethod = $request->getMethod();
        if ($requestMethod !== "POST") {
            sendResponse(405, ["message" => "Method Not Allowed"]);
        }

        $input = validateInput(["username", "password"]);

        $user = $this->userModel->findByUsername($input["username"]);

        if (
            !$user ||
            !password_verify($input["password"], $user["password_hash"])
        ) {
            sendResponse(400, ["message" => "Invalid credentials"]);
        }

        if (!$user["is_confirmed"]) {
            action($this->pdo, $user["id"], "VERIFY_ACCOUNT");
        }

        $_SESSION["id"] = $user["id"];
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        sendResponse(200, []);
    }

    public function register(Request $request): void
    {
        $requestMethod = $request->getMethod();
        if ($requestMethod !== "POST") {
            sendResponse(405, ["message" => "Method Not Allowed"]);
        }

        $input = validateInput(["email", "username", "password"]);

        if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL)) {
            sendResponse(400, ["message" => "Invalid email"]);
        }
        if (strlen($input["password"]) < 6) {
            sendResponse(400, ["message" => "Password too short"]);
        }

        $userId = $this->userModel->create(
            $input["username"],
            password_hash($input["password"], PASSWORD_DEFAULT),
        );
        $this->settingModel->create($userId, $input["email"]);

        action($this->pdo, $userId, "VERIFY_ACCOUNT");
    }

    public function reset(Request $request): void
    {
        $requestMethod = $request->getMethod();
        if ($requestMethod !== "POST") {
            sendResponse(405, ["message" => "Method Not Allowed"]);
        }

        $input = validateInput(["username", "password"]);

        if (strlen($input["password"]) < 6) {
            sendResponse(400, ["message" => "Password too short"]);
        }

        $user = $this->userModel->findByUsername($input["username"]);

        if (!$user) {
            sendResponse(400, ["message" => "Invalid username"]);
        }

        action($this->pdo, $user["id"], "RESET_PASSWORD", [
            "password" => password_hash($input["password"], PASSWORD_DEFAULT),
        ]);
    }

    public function token(Request $request): void
    {
        $requestMethod = $request->getMethod();
        if ($requestMethod !== "POST") {
            sendResponse(405, ["message" => "Method Not Allowed"]);
        }

        $input = validateInput(["token"]);

        $action = $this->actionModel->findByToken($input["token"]);

        if (!$action) {
            sendResponse(400, ["message" => "Invalid token"]);
        }

        $payload = $action["payload"]
            ? json_decode($action["payload"], true)
            : null;

        if ($action["kind"] === "VERIFY_ACCOUNT") {
            $this->userModel->confirmAccount($action["user_id"]);
        } elseif ($action["kind"] === "RESET_PASSWORD") {
            $this->userModel->updatePassword(
                $action["user_id"],
                $payload["password"],
            );
        } elseif ($action["kind"] === "CHANGE_EMAIL") {
            $this->settingModel->updateEmail(
                $action["user_id"],
                $payload["email"],
            );
        }

        $this->actionModel->delete($input["token"]);

        $_SESSION["id"] = $action["user_id"];
        sendResponse(200, []);
    }

    public function logout(Request $request): void
    {
        $requestMethod = $request->getMethod();
        if ($requestMethod !== "POST") {
            sendResponse(405, ["message" => "Method Not Allowed"]);
        }

        session_destroy();
        sendResponse(200, []);
    }
}
