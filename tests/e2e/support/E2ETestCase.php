<?php

use PHPUnit\Framework\TestCase;

/**
 * Base class for e2e tests against a real Unraid server. Per test class it:
 *  - verifies the target is reachable, the plugin is installed, the array is
 *    started, and no real backup is currently running (aborting otherwise);
 *  - authenticates by injecting a webGui session over ssh (no password needed:
 *    a session file in /var/lib/php plus the unraid_<md5(host)> cookie), and
 *    reads the CSRF token from /var/local/emhttp/var.ini;
 *  - removes the injected session again afterwards.
 *
 * Tests only write to the scratch dir (/tmp/easy-rsync-e2e) and - in classes
 * that use ConfigGuard - to the plugin's own config, which is restored.
 */
abstract class E2ETestCase extends TestCase {
    protected static RemoteShell $ssh;
    protected static UnraidWebClient $web;
    protected static string $plugin;
    protected static string $configDir;
    protected static string $tempDir;
    protected static string $handlerPath;
    protected static string $pageUrl;
    protected static string $scratchDir = '/tmp/easy-rsync-e2e';
    private static ?string $sessionFile = null;

    public static function setUpBeforeClass(): void {
        $host = E2EEnv::host();
        self::$plugin = E2EEnv::plugin();
        self::$configDir = '/boot/config/plugins/' . self::$plugin;
        self::$tempDir = '/tmp/' . self::$plugin;
        self::$handlerPath = '/plugins/' . self::$plugin . '/include/http_handler.php';
        self::$pageUrl = '/Settings/EasyRsync' . (str_ends_with(self::$plugin, '.beta') ? '.Beta' : '');

        self::$ssh = new RemoteShell($host, E2EEnv::sshUser());
        self::preflight();

        // Authenticate: inject a session file and read the CSRF token. If any
        // later setup step fails, remove the session again - a leaked file in
        // /var/lib/php is a passwordless root webGui login.
        $sessionId = bin2hex(random_bytes(16));
        self::$sessionFile = "/var/lib/php/sess_$sessionId";
        self::$ssh->writeFile(self::$sessionFile, 'unraid_login|i:' . time() . ';unraid_user|s:4:"root";');
        try {
            self::$ssh->mustRun('chmod 600 ' . escapeshellarg(self::$sessionFile));

            $varIni = self::$ssh->readFile('/var/local/emhttp/var.ini');
            if (!preg_match('/^csrf_token="([^"]+)"/m', $varIni, $m)) {
                throw new RuntimeException('could not read csrf_token from /var/local/emhttp/var.ini');
            }

            self::$web = new UnraidWebClient(
                E2EEnv::scheme() . '://' . $host,
                'unraid_' . md5($host),
                $sessionId,
                $m[1]
            );

            self::$ssh->mustRun('mkdir -p ' . escapeshellarg(self::$scratchDir));
        } catch (Throwable $e) {
            self::$ssh->run('rm -f ' . escapeshellarg(self::$sessionFile));
            self::$sessionFile = null;
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void {
        if (self::$sessionFile !== null) {
            self::$ssh->run('rm -f ' . escapeshellarg(self::$sessionFile));
            self::$sessionFile = null;
        }
    }

    private static function preflight(): void {
        $echo = self::$ssh->run('echo e2e-ok');
        if (trim($echo['stdout']) !== 'e2e-ok') {
            throw new RuntimeException(
                'cannot ssh to ' . E2EEnv::sshUser() . '@' . E2EEnv::host() . ": {$echo['stderr']}"
            );
        }
        if (!self::$ssh->fileExists('/usr/local/emhttp/plugins/' . self::$plugin)) {
            throw new RuntimeException('plugin ' . self::$plugin . ' is not installed on ' . E2EEnv::host());
        }
        $fsState = self::$ssh->run('grep -o \'fsState="[^"]*"\' /var/local/emhttp/var.ini')['stdout'];
        if (!str_contains($fsState, 'Started')) {
            throw new RuntimeException('the Unraid array is not started; backups cannot run');
        }
        // Never interfere with a genuinely running backup.
        $running = self::$ssh->run(
            'test -e ' . escapeshellarg(self::$tempDir . '/running')
            . ' && kill -0 "$(tr -cd 0-9 < ' . escapeshellarg(self::$tempDir . '/running') . ')" 2>/dev/null'
        );
        if ($running['exit'] === 0) {
            throw new RuntimeException('a backup is currently running for ' . self::$plugin . '; aborting e2e tests');
        }
    }

    /** Poll until $condition() is true or fail after $timeoutSeconds. */
    protected function waitUntil(callable $condition, float $timeoutSeconds, string $what): void {
        $deadline = microtime(true) + $timeoutSeconds;
        while (!$condition()) {
            if (microtime(true) > $deadline) {
                $this->fail("Timed out after {$timeoutSeconds}s waiting for: $what");
            }
            usleep(250_000);
        }
    }

    /** Build a full plugin .cfg from the shipped defaults plus overrides. */
    protected static function renderConfig(array $overrides = []): string {
        $defaults = parse_ini_file(dirname(__DIR__, 3) . '/source/default.cfg');
        $config = array_merge($defaults, $overrides);
        $lines = '';
        foreach ($config as $key => $value) {
            $lines .= $key . '="' . $value . '"' . "\n";
        }
        return $lines;
    }

    /** Install a scratch-dir test config on the server (call after ConfigGuard::snapshot()). */
    protected static function installTestConfig(array $cfgOverrides, array $syncEntries): void {
        $overrides = array_merge(['notificationMode' => 'none', 'backupFrequency' => 'disabled'], $cfgOverrides);
        self::$ssh->writeFile(self::$configDir . '/' . self::$plugin . '.cfg', self::renderConfig($overrides));
        self::$ssh->writeFile(
            self::$configDir . '/backup_paths.json',
            json_encode(['syncEntries' => $syncEntries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        // No scheduled runs while the test config is in place.
        self::$ssh->mustRun('rm -f ' . escapeshellarg(self::$configDir . '/' . self::$plugin . '.cron') . ' && update_cron');
    }

    /** Clear the plugin's state/log files so log assertions only see the current run. */
    protected static function clearPluginState(): void {
        // Never pull the run lock out from under a live backup (e.g. the user's
        // cron fired between test classes) - that would let the tests kill it.
        $running = escapeshellarg(self::$tempDir . '/running');
        $live = self::$ssh->run("test -e $running && kill -0 \"\$(tr -cd 0-9 < $running)\" 2>/dev/null");
        if ($live['exit'] === 0) {
            throw new RuntimeException('a real backup is running on the server; refusing to clear its state');
        }
        foreach (['easy-rsync.log', 'easy-rsync.log.1', 'rsync.log', 'rsync.log.1', 'running', 'aborted', 'rsync.pid'] as $file) {
            self::$ssh->run('rm -f ' . escapeshellarg(self::$tempDir . '/' . $file));
        }
    }

    protected function getBackupStatus(): array {
        $r = self::$web->get(self::$handlerPath, ['action' => 'getBackupStatus']);
        $this->assertSame(200, $r['status'], 'getBackupStatus failed: ' . $r['body']);
        return $r['json'];
    }

    protected function pluginLog(): string {
        return (string) self::$ssh->readFileIfExists(self::$tempDir . '/easy-rsync.log');
    }

    /** Wait for a backup started via the web UI to finish (completion marker in the fresh log). */
    protected function waitForBackupToFinish(float $timeoutSeconds = 60.0): void {
        $this->waitUntil(function (): bool {
            if ($this->getBackupStatus()['running']) {
                return false;
            }
            $log = $this->pluginLog();
            return str_contains($log, 'Sync Completed') || str_contains($log, 'Sync Aborted')
                || str_contains($log, 'No sync entries');
        }, $timeoutSeconds, 'the backup run to finish');
    }
}
