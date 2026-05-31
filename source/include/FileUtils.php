<?php

namespace unraid\plugins\EasyRsync;

class FileUtils {
    public static function readJsonFile(string $path): array {
        if (!file_exists($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Unable to read file: $path");
        }

        $data = json_decode($json, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON: " . json_last_error_msg());
        }

        return $data;
    }

    public static function writeJsonFile(string $path, array $data): void {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode JSON.");
        }

        $success = file_put_contents($path, $json);
        if ($success === false) {
            throw new \RuntimeException("Failed to write file: $path");
        }
    }
}