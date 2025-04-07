<?php

namespace unraid\plugins\EasyRsync;

class RsyncOptions {
    public bool $rsyncRecursive;
    public bool $rsyncTimes;
    public bool $rsyncVerbose;
    public bool $rsyncHumanReadable;
    public string $rsyncDelete;
    public string $rsyncRemoteShell;
    public bool $rsyncCompress;
    public string $rsyncCustom;

    private function __construct(
        bool $rsyncRecursive = true,
        bool $rsyncTimes = true,
        bool $rsyncVerbose = true,
        bool $rsyncHumanReadable = true,
        string $rsyncDelete = "after",
        string $rsyncRemoteShell = "ssh",
        bool $rsyncCompress = true,
        string $rsyncCustom = ""
    ) {
        $this->rsyncRecursive = $rsyncRecursive;
        $this->rsyncTimes = $rsyncTimes;
        $this->rsyncVerbose = $rsyncVerbose;
        $this->rsyncHumanReadable = $rsyncHumanReadable;
        $this->rsyncDelete = $rsyncDelete;
        $this->rsyncRemoteShell = $rsyncRemoteShell;
        $this->rsyncCompress = $rsyncCompress;
        $this->rsyncCustom = $rsyncCustom;
    }

    public static function fromJson(mixed $json): RsyncOptions {
        return new self(
            rsyncRecursive: (bool)($json['rsyncRecursive'] ?? true),
            rsyncTimes: (bool)($json['rsyncTimes'] ?? true),
            rsyncVerbose: (bool)($json['rsyncVerbose'] ?? true),
            rsyncHumanReadable: (bool)($json['rsyncHumanReadable'] ?? true),
            rsyncDelete: $json['rsyncDelete'] ?: "after",
            rsyncRemoteShell: $json['rsyncRemoteShell'] ?: "ssh",
            rsyncCompress: (bool)($json['rsyncCompress'] ?? true),
            rsyncCustom: $json['rsyncCustom'] ?: ""
        );
    }
}