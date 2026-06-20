<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\Logger;

/**
 * Renders source/pages/settings.php and asserts that user-controlled config and
 * path values are HTML-escaped, so a value containing a quote or angle bracket
 * cannot break out of its field (the finding-D display/corruption + XSS bug).
 */
class SettingsRenderTest extends TestCase {
    private string $cfgPath;
    private string $pathsPath;

    protected function setUp(): void {
        $this->cfgPath = ERSettings::getUserConfigFilePath();
        $this->pathsPath = ERSettings::getPathsJsonFilePath();
    }

    protected function tearDown(): void {
        @unlink($this->cfgPath);
        @unlink($this->pathsPath);
        @unlink(ERSettings::getConfigDir() . '/easy.rsync.cron');
        Logger::resetInstance();
    }

    private function render(array $post = []): string {
        $_POST = $post;
        try {
            ob_start();
            include dirname(__DIR__, 2) . '/source/pages/settings.php';
            return (string) ob_get_clean();
        } finally {
            $_POST = [];
        }
    }

    public function testSettingsPageEscapesUserControlledValues(): void {
        // Config values with angle brackets (a "-laden value can't survive parse_ini_file,
        // so quotes are exercised via the JSON path fixture below instead).
        file_put_contents($this->cfgPath, implode("\n", [
            'rsyncRecursive="true"',
            'rsyncTimes="true"',
            'rsyncLinks="true"',
            'rsyncVerbose="true"',
            'rsyncHumanReadable="true"',
            'rsyncDelete="after"',
            'rsyncRemoteShell="ssh"',
            'rsyncCompress="false"',
            'rsyncCustom="<x>"',
            'logLevel="INFO"',
            'notificationMode="summary"',
            'backupFrequency="custom"',
            'frequencyWeekday="0"',
            'frequencyDayOfMonth="1"',
            'frequencyHour="0"',
            'frequencyMinute="0"',
            'frequencyCustom="<c>"',
        ]) . "\n");

        // A sync entry whose paths contain HTML-breaking characters.
        file_put_contents($this->pathsPath, json_encode([
            'syncEntries' => [[
                'sources'      => ['/mnt/a</textarea>'],
                'destinations' => ['host:"q"'],
                'rsyncOptions' => ['rsyncCustom' => '--z="q"<b>', 'rsyncDelete' => 'after'],
            ]],
        ]));

        $html = $this->render();

        // Global custom rsync flags (rendered twice: the form field + the JS template).
        $this->assertStringNotContainsString('value="<x>"', $html, 'raw <x> must not appear in an attribute');
        $this->assertStringContainsString('value="&lt;x&gt;"', $html, '<x> should be HTML-escaped');

        // Custom cron expression.
        $this->assertStringNotContainsString('value="<c>"', $html);
        $this->assertStringContainsString('&lt;c&gt;', $html);

        // Source path in a <textarea> body — must not be able to close the textarea.
        $this->assertStringNotContainsString('/mnt/a</textarea>', $html, 'a path must not break out of <textarea>');
        $this->assertStringContainsString('/mnt/a&lt;/textarea&gt;', $html);

        // Destination path containing double quotes.
        $this->assertStringNotContainsString('host:"q"', $html);
        $this->assertStringContainsString('host:&quot;q&quot;', $html);

        // Per-entry custom rsync flags (quotes + angle brackets).
        $this->assertStringContainsString('--z=&quot;q&quot;&lt;b&gt;', $html);
    }

    public function testInvalidCustomScheduleShowsWarning(): void {
        // A malformed custom cron (4 fields, not 5) is rejected server-side; the user
        // must be told the schedule was not applied rather than left silently disabled.
        $html = $this->render([
            'backupFrequency' => 'custom',
            'frequencyCustom' => '0 3 * *',
        ]);
        $this->assertStringContainsString('Backup schedule was NOT applied', $html);
    }

    public function testValidScheduleShowsNoWarning(): void {
        $html = $this->render([
            'backupFrequency' => 'daily',
            'frequencyMinute' => '30',
            'frequencyHour'   => '3',
        ]);
        $this->assertStringNotContainsString('Backup schedule was NOT applied', $html);
    }
}
