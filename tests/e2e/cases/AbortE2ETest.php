<?php

/**
 * Force-stop of a real in-flight backup through the web UI: a bandwidth-limited
 * transfer is started via manualBackup, then abortNow must kill the running
 * rsync, mark the run aborted, and leave no processes or state files behind.
 */
class AbortE2ETest extends E2ETestCase {
    private static ConfigGuard $guard;
    private static string $src;
    private static string $dst;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$src = self::$scratchDir . '/abort-src';
        self::$dst = self::$scratchDir . '/abort-dst';

        self::$guard = new ConfigGuard(self::$ssh, self::$plugin);
        self::$guard->snapshot();
        self::installTestConfig([], [[
            'sources' => [self::$src . '/'],
            'destinations' => [self::$dst],
            // ~3MB at 100 KB/s: a ~30s transfer window to abort into.
            'rsyncOptions' => ['rsyncCustom' => '--recursive --bwlimit=100'],
        ]]);

        self::$ssh->mustRun(
            'rm -rf ' . escapeshellarg(self::$src) . ' ' . escapeshellarg(self::$dst)
            . ' && mkdir -p ' . escapeshellarg(self::$src)
            . ' && head -c 3145728 /dev/urandom > ' . escapeshellarg(self::$src . '/big.bin')
        );
    }

    public static function tearDownAfterClass(): void {
        // Belt and braces: never leave a test transfer running on the server.
        // ([a]bort: the bracket keeps pkill -f from matching this command's own shell.)
        self::$ssh->run('pkill -f ' . escapeshellarg(self::$scratchDir . '/[a]bort-') . ' 2>/dev/null');
        self::$ssh->run('rm -rf ' . escapeshellarg(self::$src) . ' ' . escapeshellarg(self::$dst));
        self::clearPluginState();
        self::$guard->restore();
        parent::tearDownAfterClass();
    }

    public function testForceStopKillsInFlightBackup(): void {
        self::clearPluginState();

        $r = self::$web->post(self::$handlerPath, ['action' => 'manualBackup']);
        $this->assertSame(200, $r['status']);

        // Wait for the transfer to actually be under way.
        $this->waitUntil(
            fn() => self::$ssh->fileExists(self::$tempDir . '/rsync.pid'),
            20, 'rsync to start'
        );
        $this->assertTrue($this->getBackupStatus()['running'], 'UI must report the backup as running');

        $stop = self::$web->post(self::$handlerPath, ['action' => 'abortNow']);
        $this->assertSame(200, $stop['status']);
        $this->assertStringContainsString('Force stop', $stop['json']['msg']);

        $this->waitUntil(fn() => !$this->getBackupStatus()['running'], 30, 'the backup to stop');

        $log = $this->pluginLog();
        $this->assertStringContainsString('Sync Aborted', $log,
            'no run summary was logged - if the log simply stops after the force stop, the backup '
            . 'script died mid-run (builds up to 2026.06.20.b1 fatal on RsyncFailureException here; '
            . 'deploy a build with the require fix in RsyncSyncer.php)');
        $this->assertFalse(self::$ssh->fileExists(self::$tempDir . '/rsync.pid'));
        $this->assertFalse(self::$ssh->fileExists(self::$tempDir . '/aborted'));
        $noRsync = self::$ssh->run('pgrep -f ' . escapeshellarg(self::$src));
        $this->assertNotSame(0, $noRsync['exit'], 'no rsync process may survive a force stop');
    }
}
