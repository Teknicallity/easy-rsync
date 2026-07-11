<?php

use unraid\plugins\EasyRsync\ERHelper;

/**
 * Runs source/scripts/rsync_backup.php as a real subprocess (exactly how cron and
 * http_handler invoke it) with real rsync against temp trees: run-lock, log
 * rotation, array-online gate, exit codes, notifications, abort and force-stop.
 */
class RsyncBackupScriptTest extends IntegrationTestCase {

    public function testSuccessfulBackupSyncsAndExitsZero(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha', 'sub/b.txt' => 'beta']);
        $dst = $this->dataDir . '/dst';
        $this->writeUserConfig();
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => [$dst]]]);

        $result = $this->runBackupScript();

        $this->assertSame(0, $result['exit'], "stderr: {$result['stderr']}");
        $this->assertSame('alpha', file_get_contents("$dst/a.txt"));
        $this->assertSame('beta', file_get_contents("$dst/sub/b.txt"));
        $this->assertFileDoesNotExist($this->tempDir . '/running', 'run lock must be released');
        // default notificationMode=summary: a start and a summary notification are sent
        $notify = $this->notifyLog();
        $this->assertStringContainsString('Sync started', $notify);
        $this->assertStringContainsString('Sync Completed', $notify);
        $this->assertStringContainsString('-i normal', $notify);
    }

    public function testDryRunCopiesNothing(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);
        $dst = $this->dataDir . '/dst';
        $this->writeUserConfig();
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => [$dst]]]);

        $result = $this->runBackupScript(['--dry-run']);

        $this->assertSame(0, $result['exit'], "stderr: {$result['stderr']}");
        $this->assertFileDoesNotExist("$dst/a.txt");
    }

    public function testFailedSyncExitsOneAndAlerts(): void {
        $dst = $this->dataDir . '/dst';
        $this->writeUserConfig();
        $this->writeSyncList([['sources' => [$this->dataDir . '/missing/'], 'destinations' => [$dst]]]);

        $result = $this->runBackupScript();

        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('-i alert', $this->notifyLog());
        $this->assertFileDoesNotExist($this->tempDir . '/running');
    }

    public function testMultipleEntriesWorstStatusWins(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);
        $dstGood = $this->dataDir . '/dst-good';
        $dstAny = $this->dataDir . '/dst-any';
        $this->writeUserConfig();
        $this->writeSyncList([
            ['sources' => [$src . '/'], 'destinations' => [$dstGood]],
            ['sources' => [$this->dataDir . '/missing/'], 'destinations' => [$dstAny]],
        ]);

        $result = $this->runBackupScript();

        $this->assertSame(1, $result['exit'], 'one failed entry fails the whole run');
        $this->assertFileExists("$dstGood/a.txt", 'the healthy entry still syncs');
    }

    public function testEntryWithoutDestinationIsSkipped(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);
        $this->writeUserConfig();
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => []]]);

        $result = $this->runBackupScript();

        $this->assertSame(1, $result['exit'], 'a skipped-only run is not a success');
        // A skipped-only run produces no file or notification, so the log is the
        // only signal that the entry was skipped (rather than attempted).
        $this->assertStringContainsString('no source/destination pairs', $this->pluginLog());
    }

    public function testEmptySyncListFinishesCleanly(): void {
        $this->writeUserConfig();
        $this->writeSyncList([]);

        $result = $this->runBackupScript();

        $this->assertSame(0, $result['exit']);
        $this->assertFileDoesNotExist($this->tempDir . '/running');
    }

    public function testMissingPathsFileFinishesCleanly(): void {
        $this->writeUserConfig();
        // no backup_paths.json written

        $result = $this->runBackupScript();

        $this->assertSame(0, $result['exit']);
        $this->assertFileDoesNotExist($this->tempDir . '/running', 'cleanup must release the run lock');
    }

    public function testArrayOfflineAbortsBeforeSyncing(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);
        $dst = $this->dataDir . '/dst';
        $this->writeUserConfig();
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => [$dst]]]);
        $this->setArrayState('Stopped');

        $result = $this->runBackupScript();

        $this->assertSame(0, $result['exit']);
        $this->assertFileDoesNotExist("$dst/a.txt", 'nothing may be synced with the array offline');
        $this->assertStringContainsString('Array is not online', $this->notifyLog());
    }

    public function testSecondRunIsBlockedWhileFirstIsRunning(): void {
        $this->writeUserConfig();
        // Simulate a running backup: the run lock holds a live PID (this phpunit process).
        file_put_contents($this->tempDir . '/running', (string) getmypid());

        $result = $this->runBackupScript();

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('Sync Already Running', $this->notifyLog());
        $this->assertSame((string) getmypid(), file_get_contents($this->tempDir . '/running'),
            'the original run lock must be left untouched');
    }

    public function testStaleRunLockIsIgnored(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);
        $dst = $this->dataDir . '/dst';
        $this->writeUserConfig();
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => [$dst]]]);
        // A PID that cannot exist (beyond pid_max) => the lock is stale.
        file_put_contents($this->tempDir . '/running', '99999999');

        $result = $this->runBackupScript();

        $this->assertSame(0, $result['exit'], "stderr: {$result['stderr']}");
        $this->assertFileExists("$dst/a.txt", 'a stale lock must not block the backup');
    }

    public function testLogsRotateOneGeneration(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);
        $this->writeUserConfig();
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => [$this->dataDir . '/dst']]]);
        file_put_contents($this->tempDir . '/easy-rsync.log', "PREVIOUS RUN MARKER\n");

        $result = $this->runBackupScript();

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('PREVIOUS RUN MARKER',
            (string) file_get_contents($this->tempDir . '/easy-rsync.log.1'));
        $this->assertStringNotContainsString('PREVIOUS RUN MARKER', $this->pluginLog(),
            'the current log must start fresh');
    }

    public function testNotificationModeNoneSendsNoSummary(): void {
        $src = $this->makeTree('src', ['a.txt' => 'alpha']);
        $this->writeUserConfig(['notificationMode' => 'none']);
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => [$this->dataDir . '/dst']]]);

        $result = $this->runBackupScript();

        $this->assertSame(0, $result['exit']);
        $this->assertStringNotContainsString('Sync Completed', $this->notifyLog());
    }

    public function testGracefulAbortSkipsRemainingEntries(): void {
        // Entry 1 transfers ~500KB at 100 KB/s (~5s) so we can abort mid-run.
        $slowSrc = $this->makeTree('slow-src', ['big.bin' => random_bytes(512 * 1024)]);
        $fastSrc = $this->makeTree('fast-src', ['small.txt' => 'x']);
        $dstSlow = $this->dataDir . '/dst-slow';
        $dstFast = $this->dataDir . '/dst-fast';
        $this->writeUserConfig();
        $this->writeSyncList([
            [
                'sources' => [$slowSrc . '/'],
                'destinations' => [$dstSlow],
                'rsyncOptions' => ['rsyncCustom' => '--recursive --bwlimit=100'],
            ],
            ['sources' => [$fastSrc . '/'], 'destinations' => [$dstFast]],
        ]);

        [$proc, $pipes] = $this->startBackupScript();
        // The script clears any dangling abort flag at startup, so only set it once
        // the first transfer is underway (the rsync pid file exists).
        $this->waitUntil(fn() => file_exists($this->tempDir . '/rsync.pid'), 15, 'first rsync to start');
        file_put_contents($this->tempDir . '/aborted', '1');
        $result = $this->waitForProcess($proc, $pipes, 30);

        $this->assertSame(1, $result['exit'], 'an aborted run is not a success');
        $this->assertFileExists("$dstSlow/big.bin", 'the in-flight sync finishes gracefully');
        $this->assertFileDoesNotExist("$dstFast/small.txt", 'remaining entries are skipped');
        $this->assertFileDoesNotExist($this->tempDir . '/aborted', 'cleanup removes the abort flag');
        $this->assertFileDoesNotExist($this->tempDir . '/running');
    }

    public function testForceStopKillsRunningRsync(): void {
        // A transfer that would take ~40s at 50 KB/s; force-stop must end it quickly.
        $slowSrc = $this->makeTree('slow-src', ['big.bin' => random_bytes(2 * 1024 * 1024)]);
        $dst = $this->dataDir . '/dst';
        $this->writeUserConfig();
        $this->writeSyncList([[
            'sources' => [$slowSrc . '/'],
            'destinations' => [$dst],
            'rsyncOptions' => ['rsyncCustom' => '--recursive --bwlimit=50'],
        ]]);

        $startedAt = microtime(true);
        [$proc, $pipes] = $this->startBackupScript();
        $this->waitUntil(fn() => file_exists($this->tempDir . '/rsync.pid'), 15, 'rsync to start');
        // Mimic http_handler's abortNow: set the abort flag first, then kill.
        file_put_contents($this->tempDir . '/aborted', '1');
        $this->assertTrue(ERHelper::killRunningRsync(), 'a live rsync should be signalled');
        $result = $this->waitForProcess($proc, $pipes, 30);
        $elapsed = microtime(true) - $startedAt;

        $this->assertSame(1, $result['exit']);
        $this->assertLessThan(30, $elapsed, 'force stop must not wait for the full transfer');
        $this->assertFileDoesNotExist($this->tempDir . '/running');
        $this->assertFileDoesNotExist($this->tempDir . '/rsync.pid');
        $this->assertFileDoesNotExist($this->tempDir . '/aborted');
    }
}
