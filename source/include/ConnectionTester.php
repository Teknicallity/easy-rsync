<?php

namespace unraid\plugins\EasyRsync;

/**
 * Best-effort reachability check for a backup destination, used by the "Test
 * Connection" button and the pre-flight check. Classifies a destination and probes
 * it; never blocks a backup (callers treat the result as informational).
 */
class ConnectionTester {
    private const SSH_TIMEOUT = 8;

    /**
     * @return string 'daemon' (rsync://... or host::module), 'ssh' ([user@]host:/path),
     *                or 'local' (a plain path).
     */
    public static function classify(string $destination): string {
        $d = trim($destination);
        if (str_starts_with($d, 'rsync://') || str_contains($d, '::')) {
            return 'daemon';
        }
        // [user@]host:path  - host segment has no slash (else it's a local path).
        if (preg_match('#^([^@]+@)?[^/:]+:#', $d)) {
            return 'ssh';
        }
        return 'local';
    }

    /**
     * @return array{destination:string,type:string,ok:?bool,message:string}
     *         ok===true pass, ok===false fail, ok===null not applicable/not tested.
     */
    public static function test(string $destination): array {
        $destination = trim($destination);
        return match (self::classify($destination)) {
            'ssh'   => self::testSsh($destination),
            'local' => self::testLocal($destination),
            default => self::result($destination, 'daemon', null,
                'Not tested (rsync daemon/URL) - backups will still run.'),
        };
    }

    private static function testSsh(string $destination): array {
        $target = explode(':', $destination, 2)[0]; // [user@]host
        // BatchMode = no password prompt - matches the backup's non-interactive key auth.
        $remoteCheck = 'command -v rsync >/dev/null 2>&1 && echo RSYNC_OK || echo RSYNC_MISSING';
        $command = 'ssh -o BatchMode=yes -o ConnectTimeout=' . self::SSH_TIMEOUT . ' '
            . escapeshellarg($target) . ' ' . escapeshellarg($remoteCheck) . ' 2>&1';

        exec($command, $out, $rc);
        $output = trim(implode("\n", $out));

        if ($rc === 0 && str_contains($output, 'RSYNC_OK')) {
            return self::result($destination, 'ssh', true, 'Connected; rsync is installed on the remote host.');
        }
        if ($rc === 0 && str_contains($output, 'RSYNC_MISSING')) {
            return self::result($destination, 'ssh', false, 'Connected, but rsync is NOT installed on the remote host.');
        }
        $reason = $output !== '' ? $output : "ssh exited with code $rc";
        return self::result($destination, 'ssh', false, "SSH connection failed: $reason");
    }

    private static function testLocal(string $destination): array {
        if (is_dir($destination)) {
            return is_writable($destination)
                ? self::result($destination, 'local', true, 'Local path exists and is writable.')
                : self::result($destination, 'local', false, 'Local path exists but is NOT writable.');
        }
        $parent = dirname($destination);
        return (is_dir($parent) && is_writable($parent))
            ? self::result($destination, 'local', true, "Local path will be created (parent '$parent' is writable).")
            : self::result($destination, 'local', false, "Local path does not exist and its parent '$parent' is not writable.");
    }

    private static function result(string $destination, string $type, ?bool $ok, string $message): array {
        return ['destination' => $destination, 'type' => $type, 'ok' => $ok, 'message' => $message];
    }
}
