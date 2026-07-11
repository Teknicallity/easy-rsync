<?php

use unraid\plugins\EasyRsync\Notification;
use unraid\plugins\EasyRsync\NotificationLevel;

/**
 * Exercises Notification against the recording notify stub (via the
 * EASY_RSYNC_NOTIFY_SCRIPT override): argument construction for both send paths.
 */
class NotificationTest extends IntegrationTestCase {

    public function testSimpleNotifyPassesAllArguments(): void {
        Notification::simpleNotify('Test Subject', 'Test Description', 'Body text', NotificationLevel::WARNING);

        $log = $this->notifyLog();
        $this->assertStringContainsString('-e Easy Rsync', $log);
        $this->assertStringContainsString('-s Test Subject', $log);
        $this->assertStringContainsString('-d Test Description', $log);
        $this->assertStringContainsString('-m Body text', $log);
        $this->assertStringContainsString('-i warning', $log);
        $this->assertStringContainsString('-l /Settings/EasyRsync', $log);
    }

    public function testSendPassesLevelAndLink(): void {
        (new Notification('Subject', 'Desc', 'Message', NotificationLevel::ALERT))->send();

        $log = $this->notifyLog();
        $this->assertStringContainsString('-s Subject', $log);
        $this->assertStringContainsString('-i alert', $log);
        $this->assertStringContainsString('-l /Settings/EasyRsync', $log);
    }

    public function testSendWithEmptySubjectSendsNothing(): void {
        (new Notification('', 'Desc'))->send();

        $this->assertSame('', $this->notifyLog());
    }

    public function testSubjectWithShellMetacharactersIsSafe(): void {
        Notification::simpleNotify('It\'s "quoted" $(danger)', 'desc');

        $log = $this->notifyLog();
        $this->assertStringContainsString('It\'s "quoted" $(danger)', $log,
            'escapeshellarg must deliver the subject verbatim, not execute it');
    }
}
