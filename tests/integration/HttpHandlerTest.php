<?php

use PHPUnit\Framework\TestCase;

/**
 * Exercises the AJAX endpoint (source/include/http_handler.php) over real HTTP via
 * php -S: request dispatch, JSON contracts, error codes, and the fire-and-forget
 * backup launches. One server (and one env) per class; state is reset per test.
 */
class HttpHandlerTest extends TestCase {
    use PluginTestHelpers;

    private static HttpServerHarness $server;

    public static function setUpBeforeClass(): void {
        self::$server = new HttpServerHarness();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void {
        self::$server->stop();
    }

    protected function setUp(): void {
        self::$server->resetState();
    }

    protected function pluginConfigDir(): string {
        return self::$server->configDir;
    }

    protected function pluginTempDir(): string {
        return self::$server->tempDir;
    }

    public function testGetWithoutActionIsBadRequest(): void {
        $r = self::$server->get();
        $this->assertSame(400, $r['status']);
        $this->assertSame('No action specified', $r['json']['error']);
    }

    public function testPostWithoutActionIsBadRequest(): void {
        $r = self::$server->post();
        $this->assertSame(400, $r['status']);
        $this->assertSame('No action specified', $r['json']['error']);
    }

    public function testUnknownActionsAreBadRequests(): void {
        $this->assertSame(400, self::$server->get(['action' => 'bogus'])['status']);
        $this->assertSame(400, self::$server->post(['action' => 'bogus'])['status']);
    }

    public function testUnsupportedMethodIsRejected(): void {
        $r = self::$server->request('PUT', ['action' => 'getBackupStatus']);
        $this->assertSame(405, $r['status']);
        $this->assertSame('Invalid request method', $r['json']['error']);
    }

    public function testGetBackupStatusReflectsRunLock(): void {
        $this->writeUserConfig();

        $r = self::$server->get(['action' => 'getBackupStatus']);
        $this->assertSame(200, $r['status']);
        $this->assertFalse($r['json']['running']);

        // A run lock holding a live PID (this phpunit process) means "running".
        file_put_contents(self::$server->tempDir . '/running', (string) getmypid());
        $r = self::$server->get(['action' => 'getBackupStatus']);
        $this->assertTrue($r['json']['running']);
    }

    public function testGetPluginLogEscapesHtml(): void {
        $this->writeUserConfig();

        $r = self::$server->get(['action' => 'getPluginLog']);
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Log file does not exist', $r['json']['log']);

        file_put_contents(self::$server->tempDir . '/easy-rsync.log', "line1 <img src=x>\nline2\n");
        $r = self::$server->get(['action' => 'getPluginLog']);
        $this->assertStringContainsString('&lt;img src=x&gt;', $r['json']['log'], 'log HTML must be escaped');
        $this->assertStringNotContainsString('<img', $r['json']['log']);
        $this->assertStringContainsString('<br />', $r['json']['log'], 'newlines become <br/> for the UI');
    }

    public function testGetRsyncLogReturnsContents(): void {
        $this->writeUserConfig();
        file_put_contents(self::$server->tempDir . '/rsync.log', "sent 42 bytes\n");

        $r = self::$server->get(['action' => 'getRsyncLog']);
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('sent 42 bytes', $r['json']['log']);
        $this->assertFalse($r['json']['running']);
    }

    public function testAbortWithoutRunningBackupIsANoOp(): void {
        $this->writeUserConfig();

        $r = self::$server->post(['action' => 'abort']);
        $this->assertSame(200, $r['status']);
        $this->assertSame('No backup is running.', $r['json']['msg']);
        $this->assertFileDoesNotExist(self::$server->tempDir . '/aborted');
    }

    public function testAbortSetsFlagAndNotifies(): void {
        $this->writeUserConfig();
        file_put_contents(self::$server->tempDir . '/running', (string) getmypid());

        $r = self::$server->post(['action' => 'abort']);

        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Graceful stop requested', $r['json']['msg']);
        $this->assertFileExists(self::$server->tempDir . '/aborted');
        $notifyLog = (string) @file_get_contents(self::$server->shimLogDir . '/notify.log');
        $this->assertStringContainsString('Backup stop requested', $notifyLog);
        $this->assertStringContainsString('-i warning', $notifyLog);
    }

    public function testAbortNowWithStalePidReportsFlagOnly(): void {
        $this->writeUserConfig();
        file_put_contents(self::$server->tempDir . '/running', (string) getmypid());
        file_put_contents(self::$server->tempDir . '/rsync.pid', '99999999'); // no such process

        $r = self::$server->post(['action' => 'abortNow']);

        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('remaining jobs will be skipped', $r['json']['msg']);
        $this->assertFileExists(self::$server->tempDir . '/aborted');
    }

    public function testTestConnectionClassifiesDestinations(): void {
        $this->writeUserConfig();
        $writable = self::$server->dataDir . '/writable';
        mkdir($writable, 0755, true);

        $r = self::$server->post([
            'action' => 'testConnection',
            'destinations' => implode("\n", [
                $writable,
                '/nonexistent-parent-xyz/child',
                'rsync://host/module',
            ]),
        ]);

        $this->assertSame(200, $r['status']);
        $results = $r['json']['results'];
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['ok']);
        $this->assertFalse($results[1]['ok']);
        $this->assertNull($results[2]['ok'], 'daemon destinations are not tested');
    }

    public function testManualDryBackupRunsToCompletionWithoutCopying(): void {
        $this->writeUserConfig(['notificationMode' => 'none']);
        $src = self::$server->dataDir . '/src';
        mkdir($src, 0755, true);
        file_put_contents($src . '/a.txt', 'alpha');
        $dst = self::$server->dataDir . '/dst';
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => [$dst]]]);

        $r = self::$server->post(['action' => 'manualDryBackup']);

        $this->assertSame(200, $r['status']);
        $this->assertSame('Starting sync', $r['json']['msg']);
        // 'Sync Completed. Took' - a failed run logs 'Sync Completed with Errors',
        // which would make the no-file-copied assertion below pass vacuously.
        $this->waitUntil(fn() => str_contains($this->pluginLog(), 'Sync Completed. Took'), 20, 'dry backup to finish');
        $this->assertFileDoesNotExist("$dst/a.txt");
    }

    public function testManualBackupSyncsForReal(): void {
        $this->writeUserConfig(['notificationMode' => 'none']);
        $src = self::$server->dataDir . '/src';
        mkdir($src, 0755, true);
        file_put_contents($src . '/a.txt', 'alpha');
        $dst = self::$server->dataDir . '/dst';
        $this->writeSyncList([['sources' => [$src . '/'], 'destinations' => [$dst]]]);

        $r = self::$server->post(['action' => 'manualBackup']);

        $this->assertSame(200, $r['status']);
        $this->assertSame('Starting sync', $r['json']['msg']);
        $this->waitUntil(fn() => str_contains($this->pluginLog(), 'Sync Completed. Took'), 20, 'backup to finish');
        $this->assertSame('alpha', file_get_contents("$dst/a.txt"));
        $this->waitUntil(fn() => !file_exists(self::$server->tempDir . '/running'), 5, 'run lock release');
    }
}
