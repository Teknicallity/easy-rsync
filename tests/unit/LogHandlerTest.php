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

    public function testGetRsyncLogEscapesHtml(): void {
        // rsync --verbose logs transferred filenames; an attacker-controlled name must
        // not be rendered as live HTML when the log is injected via innerHTML.
        file_put_contents(
            ERSettings::getRsyncLogFilePath(),
            "sending <img src=x onerror=\"alert('xss')\">\nnext line"
        );

        $out = LogHandler::getRsyncLog();

        $this->assertStringNotContainsString('<img', $out, 'raw HTML tag must be escaped');
        $this->assertStringContainsString('&lt;img', $out, 'tag should be HTML-escaped');
        $this->assertStringContainsString('&quot;', $out, 'double quotes should be escaped (ENT_QUOTES)');
        $this->assertStringContainsString('&#039;', $out, 'single quotes should be escaped (ENT_QUOTES)');
        // Newlines are still turned into <br/> after escaping.
        $this->assertMatchesRegularExpression('/<br\s*\/?>/', $out, 'newlines should become <br/>');
    }

    public function testGetPluginLogEscapesHtml(): void {
        file_put_contents(ERSettings::getLogFilePath(), "<script>alert(1)</script>");

        $out = LogHandler::getPluginLog();

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }
}
