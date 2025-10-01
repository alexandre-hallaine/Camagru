<?php

require_once __DIR__ . "/../models/Image.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../models/Comment.php";
require_once __DIR__ . "/../models/Like.php";

class ImageController
{
    private PDO $pdo;
    private Image $imageModel;
    private User $userModel;
    private Comment $commentModel;
    private Like $likeModel;
    private Setting $settingModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->imageModel = new Image($pdo);
        $this->userModel = new User($pdo);
        $this->commentModel = new Comment($pdo);
        $this->likeModel = new Like($pdo);
        $this->settingModel = new Setting($pdo);
    }

    public function handle(): void
    {
        $page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
        $limit = 5;
        $offset = max(0, ($page - 1) * $limit);

        $images = $this->imageModel->getPaginated($limit, $offset);
        $allUsers = $this->userModel->getAll();
        $allComments = $this->commentModel->getAll();
        $likedImages = isset($_SESSION["id"]) ? $this->likeModel->getLikedImagesByUser($_SESSION["id"]) : [];

        $allUsers = array_combine(array_map(fn($u) => $u["id"], $allUsers), $allUsers);

        foreach ($images as &$image) {
            $image["liked"] = in_array($image["id"], $likedImages);
            $image["user"] = $allUsers[$image["user_id"]];
            unset($image["user_id"]);

            $image["comments"] = array_values(array_filter($allComments, fn($c) => $c["image_id"] === $image["id"]));
            foreach ($image["comments"] as &$comment) {
                $comment["user"] = $allUsers[$comment["user_id"]];
                unset($comment["user_id"], $comment["image_id"]);
            }
        }

        sendResponse(200, $images);
    }

    public function create(): void
    {
        requireLogin($this->pdo);
        $input = validateInput(["image", "overlay"]);

        ($img = imagecreatefromstring(base64_decode(preg_replace(
            "/^data:image\/\w+;base64,/",
            "",
            $input["image"],
        )))) or sendResponse(400, ["message" => "Invalid image data"]);

        ($overlay = imagecreatefrompng(
            __DIR__ . "/../overlays/" . $input["overlay"],
        )) or sendResponse(400, ["message" => "Invalid overlay image"]);

        $width = imagesx($img);
        $height = imagesy($img);

        $resized_overlay = imagecreatetruecolor($width, $height);
        imagecopyresized($resized_overlay, $overlay, 0, 0, 0, 0, $width, $height, imagesx($overlay), imagesy($overlay));
        imagecopy($img, $resized_overlay, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagepng($img);
        $content = "data:image/png;base64," . base64_encode(ob_get_clean());

        imagedestroy($img);
        imagedestroy($overlay);
        imagedestroy($resized_overlay);

        $this->imageModel->create($_SESSION["id"], $content);
        sendResponse(200, []);
    }

    public function delete(int $id): void
    {
        requireLogin($this->pdo);
        $this->imageModel->delete($id, $_SESSION["id"]);
        sendResponse(200, []);
    }

    public function like(int $id): void
    {
        requireLogin($this->pdo);
        $liked = $this->likeModel->isLikedByUser($_SESSION["id"], $id);

        if ($liked) {
            $this->likeModel->removeLike($_SESSION["id"], $id);
        } else {
            $this->likeModel->addLike($_SESSION["id"], $id);
        }

        sendResponse(200, ["liked" => !$liked]);
    }

    public function comment(int $id): void
    {
        requireLogin($this->pdo);
        $input = validateInput(["body"]);

        $this->commentModel->create($_SESSION["id"], $id, $input["body"]);

        $user = $this->userModel->findById($_SESSION["id"]);
        $settings = $this->settingModel->findByUserId($this->imageModel->getUserIdByImageId($id));

        if ($settings["notify_comments"] &&
            !sendEmail(
                $settings["email"],
                "New comment on your image",
                "Hello,\n\n" .
                    "User {$user["username"]} commented on your image:\n\n" .
                    "{$input["body"]}\n\n" .
                    "If you didn't expect this, you can safely ignore this email."))
            sendResponse(500, ["message" => "Failed to send notification email"]);

        sendResponse(200, ["created_at" => date("Y-m-d H:i:s")]);
    }
}
