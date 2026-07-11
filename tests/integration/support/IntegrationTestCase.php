<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\Logger;

/**
 * Base class for integration tests. Each test gets an isolated config/temp dir
 * pair, a Started-array var.ini fixture, a recording notify stub, and PATH shims
 * for the Unraid commands the code exec()s (update_cron, logger). All overrides
 * are environment variables, so they are inherited by subprocesses too (the
 * backup script, php -S, and its exec() children). The environment is restored
 * after each test so the unit suite is unaffected in a combined run.
 */
abstract class IntegrationTestCase extends TestCase {
    use PluginTestHelpers;

    protected string $baseDir;
    protected string $configDir;
    protected string $tempDir;
    /** Scratch dir for source/destination trees used by rsync. */
    protected string $dataDir;
    protected string $shimLogDir;
    private array $savedEnv = [];

    protected function pluginConfigDir(): string {
        return $this->configDir;
    }

    protected function pluginTempDir(): string {
        return $this->tempDir;
    }

    protected function setUp(): void {
        $this->baseDir = sys_get_temp_dir() . '/easy-rsync-integration/' . bin2hex(random_bytes(4));
        $this->configDir = $this->baseDir . '/config';
        $this->tempDir = $this->baseDir . '/tmp';
        $this->dataDir = $this->baseDir . '/data';
        $this->shimLogDir = $this->baseDir . '/shim-logs';
        foreach ([$this->configDir, $this->tempDir, $this->dataDir, $this->shimLogDir] as $dir) {
            mkdir($dir, 0755, true);
        }

        $varIni = $this->baseDir . '/var.ini';
        file_put_contents($varIni, "fsState=\"Started\"\n");

        $this->setEnv('EASY_RSYNC_CONFIG_DIR', $this->configDir);
        $this->setEnv('EASY_RSYNC_TEMP_DIR', $this->tempDir);
        $this->setEnv('EASY_RSYNC_EMHTTP_VARS', $varIni);
        $this->setEnv('EASY_RSYNC_DOCROOT', self::stubsDir());
        $this->setEnv('EASY_RSYNC_NOTIFY_SCRIPT', self::shimBinDir() . '/notify');
        $this->setEnv('EASY_RSYNC_SHIM_LOG_DIR', $this->shimLogDir);
        $this->setEnv('PATH', self::shimBinDir() . ':' . getenv('PATH'));

        Logger::resetInstance();
    }

    protected function tearDown(): void {
        foreach ($this->savedEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        $this->savedEnv = [];
        Logger::resetInstance();
        self::removeDir($this->baseDir);
    }

    private function setEnv(string $key, string $value): void {
        if (!array_key_exists($key, $this->savedEnv)) {
            $this->savedEnv[$key] = getenv($key);
        }
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }

    protected static function repoRoot(): string {
        return dirname(__DIR__, 3);
    }

    protected static function stubsDir(): string {
        return dirname(__DIR__, 2) . '/stubs';
    }

    protected static function shimBinDir(): string {
        return dirname(__DIR__) . '/bin';
    }

    /** Mark the array as started/stopped in the var.ini fixture. */
    protected function setArrayState(string $fsState): void {
        file_put_contents($this->baseDir . '/var.ini', "fsState=\"$fsState\"\n");
    }

    /** Create a file tree: ['relative/path' => 'content', ...]. Returns the tree root. */
    protected function makeTree(string $name, array $files): string {
        $root = $this->dataDir . '/' . $name;
        foreach ($files as $path => $content) {
            $full = $root . '/' . $path;
            @mkdir(dirname($full), 0755, true);
            file_put_contents($full, $content);
        }
        @mkdir($root, 0755, true); // ensure root exists even with no files
        return $root;
    }

    /**
     * Run source/scripts/rsync_backup.php as a subprocess (as cron/http_handler do)
     * with the test environment. Returns ['exit' => int, 'stdout' => string, 'stderr' => string].
     */
    protected function runBackupScript(array $args = [], float $timeoutSeconds = 60.0): array {
        [$proc, $pipes] = $this->startBackupScript($args);
        return $this->waitForProcess($proc, $pipes, $timeoutSeconds);
    }

    /** Start the backup script without waiting. Returns [resource $proc, array $pipes]. */
    protected function startBackupScript(array $args = []): array {
        $cmd = array_merge(
            [PHP_BINARY, self::repoRoot() . '/source/scripts/rsync_backup.php'],
            $args
        );
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($proc, 'failed to start rsync_backup.php');
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        return [$proc, $pipes];
    }

    /** Wait for a proc started by startBackupScript, enforcing a timeout. */
    protected function waitForProcess($proc, array $pipes, float $timeoutSeconds): array {
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                $exit = $status['exitcode'];
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($proc, 9);
                proc_close($proc);
                $this->fail("rsync_backup.php did not finish within {$timeoutSeconds}s.\nstderr: $stderr");
            }
            usleep(50_000);
        }
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    protected function rsyncLog(): string {
        $path = $this->tempDir . '/rsync.log';
        return file_exists($path) ? (string) file_get_contents($path) : '';
    }

    protected function notifyLog(): string {
        $path = $this->shimLogDir . '/notify.log';
        return file_exists($path) ? (string) file_get_contents($path) : '';
    }

    protected function shimLog(string $name): string {
        $path = $this->shimLogDir . '/' . $name . '.log';
        return file_exists($path) ? (string) file_get_contents($path) : '';
    }

    public static function removeDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($dir);
    }
}
