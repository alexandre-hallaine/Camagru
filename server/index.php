<?php

session_start();

require_once __DIR__ . "/utils/helpers.php";
require_once __DIR__ . "/utils/database.php";
require_once __DIR__ . "/utils/api.php";

$router->dispatch($_SERVER["REQUEST_URI"], $_SERVER["REQUEST_METHOD"]);
