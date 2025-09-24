<?php

try {
    $dsn = getenv("DB_DSN");
    $user = getenv("DB_USER");
    $pass = getenv("DB_PASS");

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    sendResponse(500, ["message" => "DB connection failed"]);
}
