<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\ERSettings;

class ERSettingsTest extends TestCase {
    protected function setUp(): void {
        $configDir = getenv('EASY_RSYNC_CONFIG_DIR');
        $this->assertNotFalse($configDir, 'bootstrap.php must set EASY_RSYNC_CONFIG_DIR');
        foreach (glob($configDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function testGetConfigDirRespectsEnv(): void {
        $this->assertSame(getenv('EASY_RSYNC_CONFIG_DIR'), ERSettings::getConfigDir());
    }

    public function testGetTempDirRespectsEnv(): void {
        $this->assertSame(getenv('EASY_RSYNC_TEMP_DIR'), ERSettings::getTempDir());
    }

    public function testSaveAndReadUserConfigRoundTrip(): void {
        $cfg = [
            'rsyncRecursive' => 'true',
            'rsyncDelete' => 'before',
            'logLevel' => 'DEBUG',
            'notificationMode' => 'summary',
        ];
        ERSettings::saveUserConfig($cfg);

        $loaded = ERSettings::getUserConfig();
        $this->assertSame('true', $loaded['rsyncRecursive']);
        $this->assertSame('before', $loaded['rsyncDelete']);
        $this->assertSame('DEBUG', $loaded['logLevel']);
        $this->assertSame('summary', $loaded['notificationMode']);
    }

    public function testGetPathsReturnsEmptyArraysWhenFileMissing(): void {
        $paths = ERSettings::getPaths();
        $this->assertSame(['sources' => [], 'destinations' => []], $paths);
    }

    public function testSaveAndGetPathsRoundTrip(): void {
        ERSettings::saveSourcesAndDestinations(
            ['/mnt/user/share1', '/mnt/user/share2'],
            ['user@host:/backups']
        );
        $paths = ERSettings::getPaths();
        $this->assertSame(['/mnt/user/share1', '/mnt/user/share2'], array_values($paths['sources']));
        $this->assertSame(['user@host:/backups'], array_values($paths['destinations']));
    }

    public function testSaveSourcesAndDestinationsTrimsAndDropsEmpty(): void {
        ERSettings::saveSourcesAndDestinations(
            ['  /a  ', '', '/b'],
            ['user@host:/c', '  ']
        );
        $paths = ERSettings::getPaths();
        $this->assertSame(['/a', '/b'], array_values($paths['sources']));
        $this->assertSame(['user@host:/c'], array_values($paths['destinations']));
    }

    public function testBuildCronStringDaily(): void {
        $this->assertSame('30 3 * * *', ERSettings::buildCronString([
            'backupFrequency' => 'daily',
            'frequencyMinute' => '30',
            'frequencyHour' => '3',
        ]));
    }

    public function testBuildCronStringWeekly(): void {
        $this->assertSame('30 3 * * 1', ERSettings::buildCronString([
            'backupFrequency' => 'weekly',
            'frequencyMinute' => '30',
            'frequencyHour' => '3',
            'frequencyWeekday' => '1',
        ]));
    }

    public function testBuildCronStringMonthly(): void {
        $this->assertSame('30 3 15 * *', ERSettings::buildCronString([
            'backupFrequency' => 'monthly',
            'frequencyMinute' => '30',
            'frequencyHour' => '3',
            'frequencyDayOfMonth' => '15',
        ]));
    }

    public function testBuildCronStringCustom(): void {
        $this->assertSame('0 */4 * * *', ERSettings::buildCronString([
            'backupFrequency' => 'custom',
            'frequencyCustom' => '0 */4 * * *',
        ]));
    }

    public function testBuildCronStringCustomEmptyReturnsNull(): void {
        $this->assertNull(ERSettings::buildCronString([
            'backupFrequency' => 'custom',
            'frequencyCustom' => '',
        ]));
    }

    public function testBuildCronStringDisabledReturnsNull(): void {
        $this->assertNull(ERSettings::buildCronString(['backupFrequency' => 'disabled']));
    }

    public function testBuildCronStringMissingFrequencyReturnsNull(): void {
        $this->assertNull(ERSettings::buildCronString([]));
    }

    public function testUpdateCronWritesFileForDailyConfig(): void {
        ERSettings::saveUserConfig([
            'backupFrequency' => 'daily',
            'frequencyMinute' => '30',
            'frequencyHour' => '3',
        ]);

        ERSettings::updateCron();

        $cronFile = ERSettings::getConfigDir() . '/easy.rsync.cron';
        $this->assertFileExists($cronFile);
        $contents = file_get_contents($cronFile);
        $this->assertStringContainsString('30 3 * * *', $contents);
        $this->assertStringContainsString('scripts/rsync_backup.php', $contents);
    }

    public function testUpdateCronDeletesFileWhenDisabled(): void {
        $cronFile = ERSettings::getConfigDir() . '/easy.rsync.cron';
        file_put_contents($cronFile, "stale\n");
        $this->assertFileExists($cronFile);

        ERSettings::saveUserConfig(['backupFrequency' => 'disabled']);
        ERSettings::updateCron();

        $this->assertFileDoesNotExist($cronFile);
    }
}
