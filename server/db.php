<?php
$dsn = "mysql:host=db;dbname=camagru";
$user = "root";
$pass = "root";

try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
    http_response_code(500);
    die("DB connection failed: " . $e->getMessage());
}
