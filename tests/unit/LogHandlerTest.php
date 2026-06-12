<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\LogHandler;

class LogHandlerTest extends TestCase {
    protected function setUp(): void {
        $tempDir = getenv('EASY_RSYNC_TEMP_DIR');
        $this->assertNotFalse($tempDir, 'bootstrap.php must set EASY_RSYNC_TEMP_DIR');
        foreach (glob($tempDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function testWriteToPluginLogHasSpaceAfterTimestamp(): void {
        LogHandler::writeToPluginLog("[Info] hello");

        $contents = file_get_contents(ERSettings::getLogFilePath());
        // e.g. "2026-06-12 00:59:27 [Info] hello" — a space must separate the
        // timestamp from the message (regression guard for the glued format).
        $this->assertMatchesRegularExpression('/\d{2}:\d{2}:\d{2} \[Info\] hello/', $contents);
    }

    public function testRotateLogsMovesCurrentToDotOne(): void {
        $plugin = ERSettings::getLogFilePath();
        $rsync = ERSettings::getRsyncLogFilePath();
        file_put_contents($plugin, "plugin-run-1");
        file_put_contents($rsync, "rsync-run-1");

        LogHandler::rotateLogs();

        $this->assertFileDoesNotExist($plugin, 'current plugin log should be rotated away');
        $this->assertFileDoesNotExist($rsync, 'current rsync log should be rotated away');
        $this->assertSame('plugin-run-1', file_get_contents($plugin . '.1'));
        $this->assertSame('rsync-run-1', file_get_contents($rsync . '.1'));
    }

    public function testRotateLogsNoopWhenNothingToRotate(): void {
        // Should not error when no logs exist yet.
        LogHandler::rotateLogs();
        $this->assertFileDoesNotExist(ERSettings::getLogFilePath() . '.1');
    }
}
