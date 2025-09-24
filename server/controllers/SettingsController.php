<?php

require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../models/Setting.php";
require_once __DIR__ . "/../models/Action.php";

class SettingsController
{
    private PDO $pdo;
    private User $userModel;
    private Setting $settingModel;
    private Action $actionModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
        $this->settingModel = new Setting($pdo);
        $this->actionModel = new Action($pdo);
    }

    public function handle(Request $request): void
    {
        $requestMethod = $request->getMethod();
        if ($requestMethod === "GET") {
            requireLogin($this->pdo);

            $settings = $this->settingModel->findByUserId($_SESSION["id"]);
            $user = $this->userModel->findById($_SESSION["id"]);

            sendResponse(200, [
                "id" => $_SESSION["id"],
                "notify_comments" => (bool) $settings["notify_comments"],
                "email" => $settings["email"],
                "username" => $user["username"],
                "csrf_token" => $_SESSION["csrf_token"],
            ]);
        } elseif ($requestMethod === "POST") {
            requireLogin($this->pdo);
            $input = validateInput([
                "notify_comments",
                "email",
                "username",
                "password",
            ]);

            if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL)) {
                sendResponse(400, ["message" => "Invalid email"]);
            }

            $settings = $this->settingModel->findByUserId($_SESSION["id"]);

            if (
                $settings["notify_comments"] !== (int) $input["notify_comments"]
            ) {
                $this->settingModel->updateNotifyComments(
                    $_SESSION["id"],
                    (bool) $input["notify_comments"],
                );
            }

            if ($settings["email"] !== $input["email"]) {
                action($this->pdo, $_SESSION["id"], "CHANGE_EMAIL", [
                    "email" => $input["email"],
                ]);
            }

            $user = $this->userModel->findById($_SESSION["id"]);

            if ($user["username"] !== $input["username"]) {
                $this->userModel->updateUsername(
                    $_SESSION["id"],
                    $input["username"],
                );
            }

            if (strlen($input["password"]) > 0) {
                if (strlen($input["password"]) < 6) {
                    sendResponse(400, ["message" => "Password too short"]);
                }

                $this->userModel->updatePassword(
                    $_SESSION["id"],
                    password_hash($input["password"], PASSWORD_DEFAULT),
                );
            }
            sendResponse(200, []);
        } else {
            sendResponse(405, ["message" => "Method Not Allowed"]);
        }
    }
}
