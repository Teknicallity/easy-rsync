<?php

namespace unraid\plugins\EasyRsync;

class RsyncOptions {
    public bool $rsyncRecursive;
    public bool $rsyncTimes;
    public bool $rsyncLinks;
    public bool $rsyncVerbose;
    public bool $rsyncHumanReadable;
    public string $rsyncDelete;
    public string $rsyncRemoteShell;
    public bool $rsyncCompress;
    public string $rsyncCustom;

    private function __construct(
        bool $rsyncRecursive = true,
        bool $rsyncTimes = true,
        bool $rsyncLinks = true,
        bool $rsyncVerbose = true,
        bool $rsyncHumanReadable = true,
        string $rsyncDelete = "after",
        string $rsyncRemoteShell = "ssh",
        bool $rsyncCompress = true,
        string $rsyncCustom = ""
    ) {
        $this->rsyncRecursive = $rsyncRecursive;
        $this->rsyncTimes = $rsyncTimes;
        $this->rsyncLinks = $rsyncLinks;
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
            rsyncLinks: filter_var($data['rsyncLinks'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncVerbose: filter_var($data['rsyncVerbose'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncHumanReadable: filter_var($data['rsyncHumanReadable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncDelete: $data['rsyncDelete'] ?? "after",
            rsyncRemoteShell: $data['rsyncRemoteShell'] ?? "ssh",
            rsyncCompress: filter_var($data['rsyncCompress'] ?? true, FILTER_VALIDATE_BOOLEAN),
            rsyncCustom: $data['rsyncCustom'] ?? ""
        );
    }

    public function buildRsyncArgumentsString(bool $doDryRun = false): string {
        if (!empty($this->rsyncCustom)) {
            $options = $this->rsyncCustom;

            if ($doDryRun && !str_contains($options, '--dry-run')) {
                $options .= ' --dry-run';
            }

            return trim($options);
        }

        $options = "";

        if ($this->rsyncRecursive) {
            $options .= ' --recursive';
        }
        // Preserve symlinks as symlinks. Without --links rsync SILENTLY skips them
        // ("skipping non-regular file"), dropping them from the backup entirely.
        if ($this->rsyncLinks) {
            $options .= ' --links';
        }
        if ($this->rsyncTimes) {
            $options .= ' --times';
        }
        if ($this->rsyncVerbose) {
            $options .= ' --verbose';
        }
        if ($this->rsyncHumanReadable) {
            $options .= ' --human-readable';
        }

        switch ($this->rsyncDelete) {
            case 'after':
                $options .= ' --delete-after';
                break;
            case 'before':
                $options .= ' --delete-before';
                break;
            case 'during':
                $options .= ' --delete-during';
                break;
            case 'delay':
                $options .= ' --delete-delay';
                break;
        }

        if ($this->rsyncCompress) {
            $options .= ' --compress';
        }

        // rsync only creates the final dest component; a missing intermediate
        // parent makes a REAL run fail (a --dry-run silently "succeeds"),
        // leaving an empty/non-created destination. --mkpath (rsync >= 3.2.3;
        // Unraid ships 3.2.7+/3.3.x) creates parents for local AND remote dests.
        $options .= ' --mkpath';

        if ($doDryRun) {
            $options .= ' --dry-run';
        }

        return trim($options);
    }
}