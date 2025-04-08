<?php

namespace unraid\plugins\EasyRsync;

class PathHelper {

    /**
     * Extracts components from a file system path.
     *
     * Takes a string representing a file system path and splits it into its component parts,
     * excluding any trailing directory separators. If the given path starts with a directory separator,
     * the leading empty string is removed.
     *
     * @param string $path The full path to be extracted into components
     * @return array An array of strings where each element represents one part of the original path
     */
    public static function extractPathComponents(string $path): array {
        $trimmedPath = rtrim($path, DIRECTORY_SEPARATOR);
        $pathComponents = explode(DIRECTORY_SEPARATOR, $trimmedPath);

        if ($pathComponents[0] === '') {
            array_shift($pathComponents);
        }

        return $pathComponents;
    }
}