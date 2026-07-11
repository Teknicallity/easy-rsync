<?php

/**
 * Sanity checks on the target server - run first for clear diagnostics before
 * the behavioural suites (any failure here would fail those too, cryptically).
 */
class PreflightTest extends E2ETestCase {

    public function testServerMeetsPluginRequirements(): void {
        $phpVersion = trim(self::$ssh->mustRun('php -r "echo PHP_VERSION;"'));
        $this->assertTrue(version_compare($phpVersion, '8.1.0', '>='),
            "plugin code uses PHP 8.1 features but server has $phpVersion");

        $rsyncVersion = self::$ssh->mustRun('rsync --version | head -1');
        preg_match('/version\s+(\d+\.\d+\.\d+)/', $rsyncVersion, $m);
        $this->assertTrue(version_compare($m[1] ?? '0', '3.2.3', '>='),
            "--mkpath needs rsync >= 3.2.3, server has: $rsyncVersion");
    }

    public function testPluginFilesAreInstalled(): void {
        $emhttpDir = '/usr/local/emhttp/plugins/' . self::$plugin;
        foreach (['include/http_handler.php', 'scripts/rsync_backup.php', 'default.cfg'] as $file) {
            $this->assertTrue(self::$ssh->fileExists("$emhttpDir/$file"), "$file missing from install");
        }
    }

    public function testAuthenticatedWebAccessWorks(): void {
        $r = self::$web->get(self::$handlerPath, ['action' => 'getBackupStatus']);
        $this->assertSame(200, $r['status']);
        $this->assertArrayHasKey('running', $r['json']);
    }
}
