<?php

namespace unraid\plugins\EasyRsync;

class PathHelper {

    public static function deconstructPath(string $path): array {
        $trimmedPath = rtrim($path, DIRECTORY_SEPARATOR);
        $pathComponents = explode(DIRECTORY_SEPARATOR, $trimmedPath);

        if ($pathComponents[0] === '') {
            array_shift($pathComponents);
        }

        return $pathComponents;
    }
}