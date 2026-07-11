<?php

/**
 * The AJAX endpoint through the real nginx/php-fpm stack: read-only actions,
 * error responses, and connection testing. No backup is started here.
 */
class HttpHandlerE2ETest extends E2ETestCase {

    public function testStatusAndLogActionsReturnJson(): void {
        foreach (['getBackupStatus', 'getPluginLog', 'getRsyncLog'] as $action) {
            $r = self::$web->get(self::$handlerPath, ['action' => $action]);
            $this->assertSame(200, $r['status'], "$action failed");
            $this->assertIsArray($r['json'], "$action must return JSON");
            $this->assertArrayHasKey('running', $r['json']);
        }
    }

    public function testInvalidActionsReturn400(): void {
        $r = self::$web->get(self::$handlerPath, ['action' => 'bogus']);
        $this->assertSame(400, $r['status']);
        $this->assertStringContainsString('Invalid get action', $r['json']['error']);

        $r = self::$web->post(self::$handlerPath, ['action' => 'bogus']);
        $this->assertSame(400, $r['status']);
        $this->assertStringContainsString('Invalid post action', $r['json']['error']);
    }

    public function testMissingActionReturns400(): void {
        $r = self::$web->get(self::$handlerPath);
        $this->assertSame(400, $r['status']);
        $this->assertSame('No action specified', $r['json']['error']);
    }

    public function testConnectionTesterAgainstRealFilesystem(): void {
        self::$ssh->mustRun('mkdir -p ' . escapeshellarg(self::$scratchDir));

        $r = self::$web->post(self::$handlerPath, [
            'action' => 'testConnection',
            'destinations' => implode("\n", [
                self::$scratchDir,                    // exists, writable
                '/nonexistent-parent-xyz/child',      // parent missing
                'rsync://somehost/module',            // daemon: not tested
            ]),
        ]);

        $this->assertSame(200, $r['status']);
        $results = $r['json']['results'];
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['ok']);
        $this->assertFalse($results[1]['ok']);
        $this->assertNull($results[2]['ok']);
    }

    public function testConnectionTesterAgainstRemoteSshDestination(): void {
        $dest = E2EEnv::get('E2E_REMOTE_DEST');
        if ($dest === null) {
            $this->markTestSkipped('set E2E_REMOTE_DEST to test the ssh success path');
        }

        $r = self::$web->post(self::$handlerPath, [
            'action' => 'testConnection',
            'destinations' => $dest,
        ]);

        $this->assertSame(200, $r['status']);
        $result = $r['json']['results'][0];
        $this->assertSame('ssh', $result['type']);
        $this->assertTrue($result['ok'], $result['message']);
    }
}
