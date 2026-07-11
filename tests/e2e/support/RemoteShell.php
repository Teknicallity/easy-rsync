<?php

/**
 * Runs commands on the Unraid server over ssh (key-based, non-interactive).
 * All remote arguments must be escaped with escapeshellarg() by the caller when
 * interpolated into a command string.
 */
class RemoteShell {
    public function __construct(
        private string $host,
        private string $user = 'root',
    ) {}

    /**
     * @return array{exit:int, stdout:string, stderr:string}
     */
    public function run(string $command, ?string $stdin = null): array {
        $cmd = [
            'ssh', '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10', '-o', 'LogLevel=ERROR',
            $this->user . '@' . $this->host,
            $command,
        ];
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('failed to start ssh');
        }
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /** Run a command that must succeed; returns its stdout. */
    public function mustRun(string $command, ?string $stdin = null): string {
        $result = $this->run($command, $stdin);
        if ($result['exit'] !== 0) {
            throw new RuntimeException(
                "remote command failed (exit {$result['exit']}): $command\n{$result['stderr']}"
            );
        }
        return $result['stdout'];
    }

    public function fileExists(string $path): bool {
        return $this->run('test -e ' . escapeshellarg($path))['exit'] === 0;
    }

    public function readFile(string $path): string {
        return $this->mustRun('cat ' . escapeshellarg($path));
    }

    public function readFileIfExists(string $path): ?string {
        $result = $this->run('cat ' . escapeshellarg($path) . ' 2>/dev/null');
        return $result['exit'] === 0 ? $result['stdout'] : null;
    }

    public function writeFile(string $path, string $content): void {
        $this->mustRun('cat > ' . escapeshellarg($path), $content);
    }
}
