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

    public function handle(): void
    {
        requireLogin($this->pdo);

        $token = bin2hex(random_bytes(32));
        $settings = $this->settingModel->findByUserId($_SESSION["id"]);
        $user = $this->userModel->findById($_SESSION["id"]);

        $_SESSION["csrf_token"] = $token;

        sendResponse(200, [
            "csrf_token" => $token
            "id" => $user["id"],
            "username" => $user["username"],
            "email" => $settings["email"],
            "notify_comments" => (bool) $settings["notify_comments"],
        ]);
    }

    public function update(): void
    {
        requireLogin($this->pdo);
        $input = validateInput([
            "username",
            "password",
            "email",
            "notify_comments",
        ]);

        if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL))
            sendResponse(400, ["message" => "Invalid email"]);

        $user = $this->userModel->findById($_SESSION["id"]);
        $settings = $this->settingModel->findByUserId($_SESSION["id"]);

        if ($user["username"] !== $input["username"])
            $this->userModel->updateUsername($_SESSION["id"], $input["username"]);

        if (strlen($input["password"]) > 0) {
            if (strlen($input["password"]) < 6)
                sendResponse(400, ["message" => "Password too short"]);

            $this->userModel->updatePassword($_SESSION["id"], password_hash($input["password"], PASSWORD_DEFAULT));
        }

        if ($settings["email"] !== $input["email"])
            action($this->pdo, $_SESSION["id"], "CHANGE_EMAIL", ["email" => $input["email"]]);

        if ($settings["notify_comments"] !== (int) $input["notify_comments"])
            $this->settingModel->updateNotifyComments($_SESSION["id"], (bool) $input["notify_comments"]);

        sendResponse(200, []);
    }
}
