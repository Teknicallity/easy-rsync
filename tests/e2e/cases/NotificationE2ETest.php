<?php

/**
 * Real Unraid notifications from a real backup run. Opt-in via
 * E2E_ALLOW_NOTIFICATIONS=1: it creates genuine notifications, so configured
 * notification agents (email/Discord/...) WILL fire. The notification files
 * this test causes are removed afterwards.
 */
class NotificationE2ETest extends E2ETestCase {
    private static ConfigGuard $guard;
    private static bool $enabled = false;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$enabled = E2EEnv::get('E2E_ALLOW_NOTIFICATIONS') === '1';
        if (!self::$enabled) {
            return;
        }
        self::$guard = new ConfigGuard(self::$ssh, self::$plugin);
        self::$guard->snapshot();
        $src = self::$scratchDir . '/notify-src';
        self::installTestConfig(
            ['notificationMode' => 'summary'],
            [['sources' => [$src . '/'], 'destinations' => [self::$scratchDir . '/notify-dst']]]
        );
        self::$ssh->mustRun('mkdir -p ' . escapeshellarg($src));
        self::$ssh->writeFile($src . '/file.txt', "notify test\n");
    }

    public static function tearDownAfterClass(): void {
        if (self::$enabled) {
            self::$ssh->run('rm -rf '
                . escapeshellarg(self::$scratchDir . '/notify-src') . ' '
                . escapeshellarg(self::$scratchDir . '/notify-dst'));
            self::clearPluginState();
            self::$guard->restore();
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void {
        if (!self::$enabled) {
            $this->markTestSkipped('set E2E_ALLOW_NOTIFICATIONS=1 to test real notifications (agents will fire)');
        }
    }

    public function testBackupRunCreatesUnraidNotifications(): void {
        self::clearPluginState();
        $markerFile = self::$scratchDir . '/notify-marker';
        self::$ssh->mustRun('touch ' . escapeshellarg($markerFile));

        self::$web->post(self::$handlerPath, ['action' => 'manualBackup']);
        $this->waitForBackupToFinish();

        // Notifications created since the marker, belonging to this plugin's event.
        $found = self::$ssh->run(
            'find /tmp/notifications -name "*.notify" -newer ' . escapeshellarg($markerFile)
            . ' -exec grep -l "Easy Rsync" {} + 2>/dev/null'
        );
        $files = array_filter(explode("\n", trim($found['stdout'])));
        $this->assertNotEmpty($files, 'the backup run must create Easy Rsync notifications');

        // find's output is not chronological; look across everything the run created.
        $contents = '';
        foreach ($files as $file) {
            $contents .= self::$ssh->readFile($file) . "\n";
        }
        $this->assertStringContainsString('subject="Sync started"', $contents);
        $this->assertStringContainsString('subject="Sync Completed"', $contents,
            'the summary notification (notificationMode=summary) must be sent');

        // Clean up the notifications this test created (UI + archive noise).
        foreach ($files as $file) {
            self::$ssh->run('rm -f ' . escapeshellarg($file));
        }
        self::$ssh->run('rm -f ' . escapeshellarg($markerFile));
    }
}
