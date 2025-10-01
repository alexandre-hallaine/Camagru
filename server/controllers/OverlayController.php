<?php

class OverlayController
{
    public function handle(): void
    {
        $dir = __DIR__ . "/../overlays";
        $images = [];
        foreach (scandir($dir) as $file) {
            if (in_array($file, [".", ".."]))
                continue;

            $path = $dir . "/" . $file;
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
            $base64 = "data:image/" . $type . ";base64," . base64_encode($data);
            $images[] = ["slug" => $file, "content" => $base64];
        }

        sendResponse(200, $images);
    }
}
