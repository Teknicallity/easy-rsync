<?php

/**
 * The full manual-backup flow on the real server: web-triggered dry run and
 * real run of the installed plugin against scratch data under /tmp, with the
 * plugin's own config swapped in for the duration and restored afterwards.
 */
class BackupFlowE2ETest extends E2ETestCase {
    private static ConfigGuard $guard;
    private static string $src;
    private static string $dst;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$src = self::$scratchDir . '/src';
        self::$dst = self::$scratchDir . '/dst';

        self::$guard = new ConfigGuard(self::$ssh, self::$plugin);
        self::$guard->snapshot();
        self::installTestConfig([], [
            ['sources' => [self::$src . '/'], 'destinations' => [self::$dst]],
        ]);

        self::$ssh->mustRun(
            'rm -rf ' . escapeshellarg(self::$src) . ' ' . escapeshellarg(self::$dst)
            . ' && mkdir -p ' . escapeshellarg(self::$src . '/nested')
        );
        self::$ssh->writeFile(self::$src . '/hello.txt', "e2e test data\n");
        self::$ssh->writeFile(self::$src . '/nested/deep.txt', "nested file\n");
    }

    public static function tearDownAfterClass(): void {
        self::$ssh->run('rm -rf ' . escapeshellarg(self::$src) . ' ' . escapeshellarg(self::$dst));
        self::clearPluginState();
        self::$guard->restore();
        parent::tearDownAfterClass();
    }

    public function testDryRunReportsButCopiesNothing(): void {
        self::clearPluginState();

        $r = self::$web->post(self::$handlerPath, ['action' => 'manualDryBackup']);
        $this->assertSame(200, $r['status']);
        $this->assertSame('Starting sync', $r['json']['msg']);

        $this->waitForBackupToFinish();
        // 'Sync Completed. Took' - not the 'Sync Completed with Errors' variant.
        $this->assertStringContainsString('Sync Completed. Took', $this->pluginLog());
        $this->assertFalse(self::$ssh->fileExists(self::$dst . '/hello.txt'),
            'a dry run must not copy anything');
    }

    public function testManualBackupSyncsScratchData(): void {
        self::clearPluginState();

        $r = self::$web->post(self::$handlerPath, ['action' => 'manualBackup']);
        $this->assertSame(200, $r['status']);
        $this->assertSame('Starting sync', $r['json']['msg']);

        $this->waitForBackupToFinish();
        $this->assertStringContainsString('Sync Completed. Took', $this->pluginLog());

        // Destination matches the source tree.
        $diff = self::$ssh->run(
            'diff -r ' . escapeshellarg(self::$src) . ' ' . escapeshellarg(self::$dst)
        );
        $this->assertSame(0, $diff['exit'], 'src/dst differ: ' . $diff['stdout'] . $diff['stderr']);

        // The web UI's Rsync Log tab shows the transfer.
        $rsyncLog = self::$web->get(self::$handlerPath, ['action' => 'getRsyncLog']);
        $this->assertStringContainsString('hello.txt', $rsyncLog['json']['log']);

        // State files are cleaned up.
        $this->assertFalse(self::$ssh->fileExists(self::$tempDir . '/running'));
        $this->assertFalse(self::$ssh->fileExists(self::$tempDir . '/rsync.pid'));
    }

    public function testDeletedSourceFileIsRemovedFromDestination(): void {
        // Relies on the destination from the previous test; default --delete-after.
        $this->assertTrue(self::$ssh->fileExists(self::$dst . '/hello.txt'), 'precondition');
        self::$ssh->mustRun('rm ' . escapeshellarg(self::$src . '/hello.txt'));
        self::clearPluginState();

        self::$web->post(self::$handlerPath, ['action' => 'manualBackup']);
        $this->waitForBackupToFinish();

        $this->assertFalse(self::$ssh->fileExists(self::$dst . '/hello.txt'),
            '--delete-after must remove files gone from the source');
        $this->assertTrue(self::$ssh->fileExists(self::$dst . '/nested/deep.txt'));
    }

    public function testBackupToRemoteSshDestination(): void {
        $dest = E2EEnv::get('E2E_REMOTE_DEST');
        if ($dest === null) {
            $this->markTestSkipped('set E2E_REMOTE_DEST to test rsync-over-ssh');
        }
        // The default options include --delete-after: never sync into the
        // user-supplied path itself, only into a run-specific subdirectory.
        $dest = rtrim($dest, '/') . '/e2e-run-' . bin2hex(random_bytes(4));

        self::installTestConfig([], [
            ['sources' => [self::$src . '/'], 'destinations' => [$dest]],
        ]);
        self::clearPluginState();

        self::$web->post(self::$handlerPath, ['action' => 'manualBackup']);
        $this->waitForBackupToFinish();

        $this->assertStringContainsString('Sync Completed. Took', $this->pluginLog());
        $this->assertStringNotContainsString('with Errors', $this->pluginLog());
    }
}
