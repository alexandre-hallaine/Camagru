<?php

require __DIR__ . "/vendor/autoload.php";
Dotenv\Dotenv::createImmutable(__DIR__)->load();

session_start();

try {
    $dsn = $_ENV["DB_DSN"];
    $user = $_ENV["DB_USER"];
    $pass = $_ENV["DB_PASS"];

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    sendResponse(500, ["message" => "DB connection failed"]);
}

#[NoReturn]
function sendResponse($code, $data): void
{
    header("Content-Type: application/json");
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function validateInput($required): array
{
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) {
        sendResponse(400, ["message" => "Invalid JSON"]);
    }

    foreach ($required as $field) {
        if (!isset($input[$field])) {
            sendResponse(400, ["message" => "Missing field: $field"]);
        }
    }

    return array_map("trim", $input);
}

function sendEmail($to, $subject, $body): bool
{
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV["SMTP_HOST"];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV["SMTP_USER"];
    $mail->Password = $_ENV["SMTP_PASS"];

    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Timeout = 5;

    $mail->setFrom($_ENV["SMTP_FROM"], $_ENV["SMTP_FROM_NAME"]);
    $mail->addAddress($to);

    $mail->Subject = $subject;
    $mail->Body = $body;

    try {
        return $mail->send();
    } catch (Throwable $e) {
        return false;
    }
}

function requireLogin(): void
{
    global $pdo;

    if (!isset($_SESSION["id"])) {
        sendResponse(401, ["message" => "Unauthorized"]);
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["id"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!$user) {
        sendResponse(401, ["message" => "Unauthorized"]);
    }
}

function action($userId, $kind, $payload = null): void
{
    global $pdo;

    try {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare(
            "INSERT INTO actions (user_id, kind, payload, token) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), payload = VALUES(payload)",
        );
        $stmt->execute([
            $userId,
            $kind,
            $payload ? json_encode($payload) : null,
            $token,
        ]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    } catch (\Random\RandomException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    $verifyUrl =
        "http://" . $_SERVER["HTTP_HOST"] . "/auth/?token=" . urlencode($token);

    try {
        $stmt = $pdo->prepare("SELECT email FROM settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (
        !sendEmail(
            $settings["email"],
            "Action required",
            "Hello,\n\n" .
                "Please complete the action by opening the link below:\n" .
                "$verifyUrl\n\n" .
                "If you didn't request this, you can safely ignore this email.",
        )
    ) {
        sendResponse(500, ["message" => "Failed to send verification email"]);
    }
    sendResponse(400, [
        "message" => "Check your email inbox to complete the action.",
    ]);
}

$router = new \Bramus\Router\Router();
$router->setBasePath("/api");

$router->post("/auth/login", function () use ($pdo) {
    $input = validateInput(["username", "password"]);

    try {
        $stmt = $pdo->prepare(
            "SELECT id, password_hash, is_confirmed FROM users WHERE username = ?",
        );
        $stmt->execute([$input["username"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (
        !$user ||
        !password_verify($input["password"], $user["password_hash"])
    ) {
        sendResponse(400, ["message" => "Invalid credentials"]);
    }

    if (!$user["is_confirmed"]) {
        action($user["id"], "VERIFY_ACCOUNT");
    }

    $_SESSION["id"] = $user["id"];
    sendResponse(200, []);
});

$router->post("/auth/register", function () use ($pdo) {
    $input = validateInput(["email", "username", "password"]);

    if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, ["message" => "Invalid email"]);
    }
    if (strlen($input["password"]) < 6) {
        sendResponse(400, ["message" => "Password too short"]);
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, password_hash) VALUES (?, ?)",
        );
        $stmt->execute([
            $input["username"],
            password_hash($input["password"], PASSWORD_DEFAULT),
        ]);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$pdo->lastInsertId()]);
        $user = $stmt->fetch();

        $stmt = $pdo->prepare(
            "INSERT INTO settings (user_id, email) VALUES (?, ?)",
        );
        $stmt->execute([$user["id"], $input["email"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    action($user["id"], "VERIFY_ACCOUNT");
});

$router->post("/auth/reset", function () use ($pdo) {
    $input = validateInput(["username", "password"]);

    if (strlen($input["password"]) < 6) {
        sendResponse(400, ["message" => "Password too short"]);
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$input["username"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!$user) {
        sendResponse(400, ["message" => "Invalid username"]);
    }

    action($user["id"], "RESET_PASSWORD", [
        "password" => password_hash($input["password"], PASSWORD_DEFAULT),
    ]);
});

$router->post("/auth/token", function () use ($pdo) {
    $input = validateInput(["token"]);

    try {
        $stmt = $pdo->prepare(
            "SELECT user_id, kind, payload FROM actions WHERE token = ?",
        );
        $stmt->execute([$input["token"]]);
        $action = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    if (!$action) {
        sendResponse(400, ["message" => "Invalid token"]);
    }

    $payload = $action["payload"]
        ? json_decode($action["payload"], true)
        : null;

    try {
        if ($action["kind"] === "VERIFY_ACCOUNT") {
            $stmt = $pdo->prepare(
                "UPDATE users SET is_confirmed = 1 WHERE id = ?",
            );
            $stmt->execute([$action["user_id"]]);
        } elseif ($action["kind"] === "RESET_PASSWORD") {
            $stmt = $pdo->prepare(
                "UPDATE users SET password_hash = ?, is_confirmed = 1 WHERE id = ?",
            );
            $stmt->execute([$payload["password"], $action["user_id"]]);
        } elseif ($action["kind"] === "CHANGE_EMAIL") {
            $stmt = $pdo->prepare(
                "UPDATE settings SET email = ? WHERE user_id = ?",
            );
            $stmt->execute([$payload["email"], $action["user_id"]]);
        }

        $smtp = $pdo->prepare("DELETE FROM actions WHERE token = ?");
        $smtp->execute([$input["token"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    $_SESSION["id"] = $action["user_id"];
    sendResponse(200, []);
});

$router->post("/auth/logout", function () {
    session_destroy();
    sendResponse(200, []);
});

$router->get("/settings", function () use ($pdo) {
    requireLogin();

    try {
        $stmt = $pdo->prepare(
            "SELECT email, notify_comments FROM settings WHERE user_id = ?",
        );
        $stmt->execute([$_SESSION["id"]]);
        $settings = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["id"]]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    sendResponse(200, [
        "id" => $_SESSION["id"],
        "notify_comments" => (bool) $settings["notify_comments"],
        "email" => $settings["email"],
        "username" => $user["username"],
    ]);
});

$router->post("/settings", function () use ($pdo) {
    requireLogin();
    $input = validateInput([
        "notify_comments",
        "email",
        "username",
        "password",
    ]);

    if (!filter_var($input["email"], FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, ["message" => "Invalid email"]);
    }

    try {
        $smtp = $pdo->prepare(
            "SELECT email, notify_comments FROM settings WHERE user_id = ?",
        );
        $smtp->execute([$_SESSION["id"]]);
        $settings = $smtp->fetch();

        if ($settings["notify_comments"] !== (int) $input["notify_comments"]) {
            $stmt = $pdo->prepare(
                "UPDATE settings SET notify_comments = ? WHERE user_id = ?",
            );
            $stmt->execute([(int) $input["notify_comments"], $_SESSION["id"]]);
        }

        if ($settings["email"] !== $input["email"]) {
            action($_SESSION["id"], "CHANGE_EMAIL", [
                "email" => $input["email"],
            ]);
        }

        $stmt = $pdo->prepare(
            "SELECT username, password_hash FROM users WHERE id = ?",
        );
        $stmt->execute([$_SESSION["id"]]);
        $user = $stmt->fetch();

        if ($user["username"] !== $input["username"]) {
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$input["username"], $_SESSION["id"]]);
        }

        if (strlen($input["password"]) > 0) {
            if (strlen($input["password"]) < 6) {
                sendResponse(400, ["message" => "Password too short"]);
            }

            $stmt = $pdo->prepare(
                "UPDATE users SET password_hash = ? WHERE id = ?",
            );
            $stmt->execute([
                password_hash($input["password"], PASSWORD_DEFAULT),
                $_SESSION["id"],
            ]);
        }
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    sendResponse(200, []);
});

$router->post("/images", function () use ($pdo) {
    requireLogin();
    $input = validateInput(["image"]);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO images (user_id, content) VALUES (?, ?)",
        );
        $stmt->execute([$_SESSION["id"], $input["image"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    sendResponse(200, []);
});

$router->delete("/images/(\d+)", function ($id) use ($pdo) {
    requireLogin();

    try {
        $stmt = $pdo->prepare(
            "DELETE FROM images WHERE id = ? AND user_id = ?",
        );
        $stmt->execute([$id, $_SESSION["id"]]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }

    sendResponse(200, []);
});

$router->get("/images", function () use ($pdo) {
    $page = isset($_GET["page"]) ? (int) $_GET["page"] : 1;

    try {
        $stmt = $pdo->prepare(
            "SELECT id, user_id, content, created_at FROM images ORDER BY created_at DESC LIMIT :limit OFFSET :offset",
        );
        $stmt->bindValue(":limit", 5, PDO::PARAM_INT);
        $stmt->bindValue(":offset", max(0, ($page - 1) * 5), PDO::PARAM_INT);
        $stmt->execute();
        $images = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT id, username FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll();
        $users = array_combine(array_map(fn($u) => $u["id"], $users), $users);

        $stmt = $pdo->prepare(
            "SELECT image_id, user_id, body, created_at FROM comments",
        );
        $stmt->execute();
        $comments = $stmt->fetchAll();

        if (isset($_SESSION["id"])) {
            $stmt = $pdo->prepare(
                "SELECT image_id FROM likes WHERE user_id = ?",
            );
            $stmt->execute([$_SESSION["id"]]);
            $liked = $stmt->fetchAll();
            $liked = array_map(fn($l) => $l["image_id"], $liked);
        }

        foreach ($images as &$image) {
            $image["liked"] = isset($liked) && in_array($image["id"], $liked);
            $image["user"] = $users[$image["user_id"]];
            unset($image["user_id"]);

            $image["comments"] = array_values(
                array_filter(
                    $comments,
                    fn($c) => $c["image_id"] === $image["id"],
                ),
            );
            foreach ($image["comments"] as &$comment) {
                $comment["user"] = $users[$comment["user_id"]];
                unset($comment["user_id"], $comment["image_id"]);
            }
        }

        sendResponse(200, $images);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }
});

$router->post("/images/(\d+)/like", function ($id) use ($pdo) {
    requireLogin();

    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM likes WHERE user_id = ? AND image_id = ?",
        );
        $stmt->execute([$_SESSION["id"], $id]);
        $liked = (bool) $stmt->fetch();

        if ($liked) {
            $stmt = $pdo->prepare(
                "DELETE FROM likes WHERE user_id = ? AND image_id = ?",
            );
            $stmt->execute([$_SESSION["id"], $id]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO likes (user_id, image_id) VALUES (?, ?)",
            );
            $stmt->execute([$_SESSION["id"], $id]);
        }

        sendResponse(200, ["liked" => !$liked]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }
});

$router->post("/images/(\d+)/comment", function ($id) use ($pdo) {
    requireLogin();
    $input = validateInput(["body"]);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO comments (user_id, image_id, body) VALUES (?, ?, ?)",
        );
        $stmt->execute([$_SESSION["id"], $id, $input["body"]]);

        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION["id"]]);
        $user = $stmt->fetch();

        $stmt = $pdo->prepare(
            "SELECT notify_comments, email FROM settings WHERE user_id = (SELECT user_id FROM images WHERE id = ?)",
        );
        $stmt->execute([$id]);
        $settings = $stmt->fetch();

        if ($settings["notify_comments"]) {
            sendEmail(
                $settings["email"],
                "New comment on your image",
                "Hello,\n\n" .
                    "User {$user["username"]} commented on your image:\n\n" .
                    "{$input["body"]}\n\n" .
                    "If you didn't expect this, you can safely ignore this email.",
            );
        }

        sendResponse(200, ["created_at" => date("Y-m-d H:i:s")]);
    } catch (PDOException $e) {
        sendResponse(500, ["message" => $e->getMessage()]);
    }
});

$router->run();
