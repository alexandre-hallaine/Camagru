<?php

$env_file = __DIR__ . "/../.env";
if (!file_exists($env_file)) {
    sendResponse(500, ["message" => ".env file not found"]);
}

$lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), "#") === 0) {
        continue;
    }

    [$name, $value] = explode("=", $line, 2);
    $name = trim($name);
    $value = trim($value);

    putenv(sprintf("%s=%s", $name, $value));
}
