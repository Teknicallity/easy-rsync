<?php

/**
 * The settings-form POST through the real emhttp page pipeline: config file
 * writes on /boot, cron file generation, and the update_cron merge into the
 * live crontab (/etc/cron.d/root) - then verifies the restore removes it all.
 */
class SettingsSaveE2ETest extends E2ETestCase {
    private static ConfigGuard $guard;

    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        self::$guard = new ConfigGuard(self::$ssh, self::$plugin);
        self::$guard->snapshot();
    }

    public static function tearDownAfterClass(): void {
        self::$guard->restore();
        parent::tearDownAfterClass();
    }

    public function testSavingScheduleWritesConfigCronAndCrontab(): void {
        $r = self::$web->post(self::$pageUrl, [
            'logLevel' => 'DEBUG',
            'notificationMode' => 'none',
            'backupFrequency' => 'daily',
            'frequencyMinute' => '42',
            'frequencyHour' => '4',
            'syncEntries' => [
                [
                    'sources' => self::$scratchDir . '/src',
                    'destinations' => self::$scratchDir . '/dst',
                ],
            ],
        ]);
        $this->assertSame(200, $r['status']);
        $this->assertStringNotContainsString('Backup schedule was NOT applied', $r['body']);

        // Config persisted on /boot.
        $cfg = self::$ssh->readFile(self::$configDir . '/' . self::$plugin . '.cfg');
        $this->assertStringContainsString('logLevel="DEBUG"', $cfg);
        $this->assertStringContainsString('backupFrequency="daily"', $cfg);

        // Sync list persisted.
        $paths = json_decode(self::$ssh->readFile(self::$configDir . '/backup_paths.json'), true);
        $this->assertSame([self::$scratchDir . '/src'], $paths['syncEntries'][0]['sources']);

        // Cron file written and merged into the live crontab by update_cron.
        $cron = self::$ssh->readFile(self::$configDir . '/' . self::$plugin . '.cron');
        $this->assertStringContainsString('42 4 * * *', $cron);
        $this->assertStringContainsString(self::$plugin . '/scripts/rsync_backup.php', $cron);
        $crontab = self::$ssh->readFile('/etc/cron.d/root');
        $this->assertStringContainsString(self::$plugin . '/scripts/rsync_backup.php', $crontab);
    }

    public function testInvalidCustomScheduleIsRejectedWithWarning(): void {
        $r = self::$web->post(self::$pageUrl, [
            'backupFrequency' => 'custom',
            'frequencyCustom' => '0 3 * *', // four fields: invalid
        ]);
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Backup schedule was NOT applied', $r['body']);
        $this->assertFalse(self::$ssh->fileExists(self::$configDir . '/' . self::$plugin . '.cron'),
            'an invalid schedule must not leave a cron file behind');
    }

    public function testDisablingScheduleClearsCrontab(): void {
        // Re-enable a schedule first so there is something to clear.
        self::$web->post(self::$pageUrl, [
            'backupFrequency' => 'daily', 'frequencyMinute' => '42', 'frequencyHour' => '4',
        ]);
        $this->assertTrue(self::$ssh->fileExists(self::$configDir . '/' . self::$plugin . '.cron'), 'precondition');

        $r = self::$web->post(self::$pageUrl, ['backupFrequency' => 'disabled']);
        $this->assertSame(200, $r['status']);

        $this->assertFalse(self::$ssh->fileExists(self::$configDir . '/' . self::$plugin . '.cron'));
        $crontab = self::$ssh->readFile('/etc/cron.d/root');
        $this->assertStringNotContainsString(self::$plugin . '/scripts/rsync_backup.php', $crontab);
    }
}
