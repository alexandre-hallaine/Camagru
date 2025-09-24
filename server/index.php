<?php

session_start();

require_once __DIR__ . "/utils/helpers.php";
require_once __DIR__ . "/config/env.php";
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/routes/api.php";

$router->dispatch($_SERVER["REQUEST_URI"], $_SERVER["REQUEST_METHOD"]);
