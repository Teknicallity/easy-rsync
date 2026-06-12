<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\ERHelper;
use unraid\plugins\EasyRsync\ERSettings;

class ERHelperTest extends TestCase {
    protected function setUp(): void {
        $tempDir = getenv('EASY_RSYNC_TEMP_DIR');
        $this->assertNotFalse($tempDir, 'bootstrap.php must set EASY_RSYNC_TEMP_DIR');
        foreach (glob($tempDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function testIsAbortRequestedReflectsFlagFile(): void {
        $this->assertFalse(ERHelper::isAbortRequested(), 'No abort flag should exist initially');

        file_put_contents(ERSettings::getStateRsyncAbortedFilePath(), '1');
        $this->assertTrue(ERHelper::isAbortRequested(), 'Abort flag should be detected once written');
    }

    public function testIsBackupRunningTrueForLivePid(): void {
        file_put_contents(ERSettings::getStateRsyncRunningFilePath(), (string) getmypid());
        $this->assertTrue(ERHelper::isBackupRunning(), 'A live PID should report as running');
    }

    public function testIsBackupRunningFalseForDeadPidAndRemovesFile(): void {
        $runningPath = ERSettings::getStateRsyncRunningFilePath();
        // A PID above the kernel max can never exist under /proc.
        file_put_contents($runningPath, '2147483647');

        $this->assertFalse(ERHelper::isBackupRunning(), 'A dead PID should report as not running');
        $this->assertFileDoesNotExist($runningPath, 'A stale running file should be removed');
    }

    public function testIsBackupRunningFalseWhenNoFile(): void {
        $this->assertFalse(ERHelper::isBackupRunning(), 'No running file means not running');
    }
}
