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

    public static function fromArray(mixed $data): RsyncOptions {
        return new self(
            rsyncRecursive: filter_var($data['rsyncRecursive'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncTimes: filter_var($data['rsyncTimes'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncVerbose: filter_var($data['rsyncVerbose'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncHumanReadable: filter_var($data['rsyncHumanReadable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncDelete: $data['rsyncDelete'] ?? "after",
            rsyncRemoteShell: $data['rsyncRemoteShell'] ?? "ssh",
            rsyncCompress: filter_var($data['rsyncCompress'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncCustom: $data['rsyncCustom'] ?? ""
        );
    }
}