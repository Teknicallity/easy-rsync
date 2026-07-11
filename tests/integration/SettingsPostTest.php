<?php

use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\Logger;

/**
 * Exercises the settings-form POST handler (source/pages/settings.php) end to end:
 * config file writes, sync-list persistence, cron file generation/removal, and the
 * update_cron call - the save semantics the unit suite only covers at render level.
 */
class SettingsPostTest extends IntegrationTestCase {

    protected function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    /** Include settings.php with $_POST set, swallowing its HTML output. */
    private function postToSettingsPage(array $post): string {
        $_POST = $post;
        try {
            ob_start();
            include dirname(__DIR__, 2) . '/source/pages/settings.php';
            return (string) ob_get_clean();
        } finally {
            $_POST = [];
        }
    }

    public function testSaveWritesConfigSyncListAndCron(): void {
        $this->writeUserConfig();

        $this->postToSettingsPage([
            'logLevel' => 'DEBUG',
            'notificationMode' => 'none',
            'backupFrequency' => 'daily',
            'frequencyMinute' => '30',
            'frequencyHour' => '3',
            'syncEntries' => [
                [
                    'sources' => "/mnt/user/a\n/mnt/user/b",
                    'destinations' => '/mnt/backup',
                    'rsyncOptions' => ['rsyncCustom' => '--archive'],
                ],
            ],
        ]);

        // Config file reflects the posted values.
        $cfg = parse_ini_file(ERSettings::getUserConfigFilePath());
        $this->assertSame('DEBUG', $cfg['logLevel']);
        $this->assertSame('none', $cfg['notificationMode']);
        $this->assertSame('daily', $cfg['backupFrequency']);

        // Sync list persisted with textarea semantics (newline-separated sources).
        $paths = json_decode((string) file_get_contents(ERSettings::getPathsJsonFilePath()), true);
        $this->assertSame(['/mnt/user/a', '/mnt/user/b'], $paths['syncEntries'][0]['sources']);
        $this->assertSame(['/mnt/backup'], $paths['syncEntries'][0]['destinations']);
        $this->assertSame('--archive', $paths['syncEntries'][0]['rsyncOptions']['rsyncCustom']);

        // Cron file written with the validated schedule, pointing at the backup script.
        $cron = (string) file_get_contents($this->configDir . '/easy.rsync.cron');
        $this->assertStringContainsString('30 3 * * *', $cron);
        $this->assertStringContainsString('scripts/rsync_backup.php', $cron);

        // Unraid's update_cron was invoked to activate the change.
        $this->assertStringContainsString('update_cron', $this->shimLog('update_cron'));
    }

    public function testFieldsAbsentFromPostKeepTheirOldValues(): void {
        // The UI disables (and so does not submit) inputs that don't apply; the
        // server must keep the previous values for any key missing from $_POST.
        $this->writeUserConfig(['frequencyHour' => '5', 'logLevel' => 'WARNING']);

        $this->postToSettingsPage(['logLevel' => 'ERROR']);

        $cfg = parse_ini_file(ERSettings::getUserConfigFilePath());
        $this->assertSame('ERROR', $cfg['logLevel']);
        $this->assertSame('5', $cfg['frequencyHour'], 'unsubmitted keys keep their old values');
    }

    public function testDisablingScheduleRemovesCronFile(): void {
        $this->writeUserConfig(['backupFrequency' => 'daily', 'frequencyMinute' => '0', 'frequencyHour' => '2']);
        file_put_contents($this->configDir . '/easy.rsync.cron', "# Easy Rsync cron settings\n0 2 * * * php x\n");

        $this->postToSettingsPage(['backupFrequency' => 'disabled']);

        $this->assertFileDoesNotExist($this->configDir . '/easy.rsync.cron');
        $this->assertStringContainsString('update_cron', $this->shimLog('update_cron'));
    }

    public function testInvalidScheduleWritesNoCronAndWarns(): void {
        $this->writeUserConfig();

        $html = $this->postToSettingsPage([
            'backupFrequency' => 'daily',
            'frequencyMinute' => '75', // out of range
            'frequencyHour' => '3',
        ]);

        $this->assertFileDoesNotExist($this->configDir . '/easy.rsync.cron',
            'an invalid schedule must not produce a cron line');
        $this->assertStringContainsString('Backup schedule was NOT applied', $html);
    }
}
