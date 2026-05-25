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
}
