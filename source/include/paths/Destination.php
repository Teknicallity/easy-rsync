<?php

namespace unraid\plugins\EasyRsync;

class Destination {
    public string $username;
    public string $host;
    public array $pathParts;
    public string $fullPath;

    public function __construct(string $destination) {
        $parts = explode('@', $destination, 2);

        if (count($parts) == 1) {
            $username = '';
            $remainingPart = $parts[0];
        } else {
            $username = $parts[0];
            $remainingPart = $parts[1];
        }

        $hostAndPathParts = explode(':', $remainingPart, 2);
        $host = $hostAndPathParts[0];
        $fullPath = $hostAndPathParts[1] ?? '';

        $pathComponents = PathHelper::deconstructPath($fullPath);

        $this->username = $username;
        $this->host = $host;
        $this->pathParts = $pathComponents;
        $this->fullPath = $fullPath;

        return $this;
    }
}