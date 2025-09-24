<?php

class OverlayController
{
    public function handleOverlays(Request $request): void
    {
        $requestMethod = $request->getMethod();
        if ($requestMethod !== "GET") {
            sendResponse(405, ["message" => "Method Not Allowed"]);
        }

        $files = scandir(__DIR__ . "/../overlays");
        $images = [];
        foreach ($files as $file) {
            if (in_array($file, [".", ".."])) {
                continue;
            }
            $path = __DIR__ . "/../overlays/" . $file;
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            $base64 = "data:image/" . $type . ";base64," . base64_encode($data);
            $images[] = ["slug" => $file, "content" => $base64];
        }
        sendResponse(200, $images);
    }
}
