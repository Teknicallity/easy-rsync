<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\Logger;
use unraid\plugins\EasyRsync\SyncList;
use unraid\plugins\EasyRsync\SyncEntry;
use unraid\plugins\EasyRsync\Syncer;

class SyncListTest extends TestCase {
    protected function tearDown(): void {
        Logger::resetInstance();
        @unlink(ERSettings::getStateRsyncAbortedFilePath());
    }

    public function testFromArrayWithEmptyJsonYieldsEmptyList(): void {
        $list = SyncList::fromArray([]);
        $this->assertSame([], $list->entries);
    }

    public function testFromArrayBuildsEntries(): void {
        $list = SyncList::fromArray([
            'syncEntries' => [
                ['sources' => ['/a'], 'destinations' => ['host:/b']],
                ['sources' => ['/c'], 'destinations' => ['host:/d']],
            ],
        ]);
        $this->assertCount(2, $list->entries);
        $this->assertInstanceOf(SyncEntry::class, $list->entries[0]);
        $this->assertSame(['/a'], $list->entries[0]->sources);
        $this->assertSame(['host:/d'], $list->entries[1]->destinations);
    }

    public function testFromArrayRejectsNonArrayEntry(): void {
        $this->expectException(Throwable::class);
        SyncList::fromArray(['syncEntries' => ['not-an-array']]);
    }

    public function testSyncAllWithoutSyncerThrows(): void {
        $list = SyncList::fromArray(['syncEntries' => [['sources' => ['/a'], 'destinations' => ['host:/b']]]]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Syncer not set');
        $list->syncAll(false);
    }

    public function testAbortLogsDetectionMessageOnce(): void {
        // notificationMode != foreach/both so no notify shell-out; INFO so info() writes.
        ERSettings::saveUserConfig(['notificationMode' => 'none', 'logLevel' => 'INFO']);
        Logger::resetInstance();

        // Simulate a pending abort request (what the abort handler writes).
        file_put_contents(ERSettings::getStateRsyncAbortedFilePath(), '1');

        $syncer = new class implements Syncer {
            public int $calls = 0;
            public function performSync(string $source, string $destination, string $rsyncOptions): void {
                $this->calls++;
            }
        };

        $list = SyncList::fromArray([
            'syncEntries' => [
                ['sources' => ['/a'], 'destinations' => ['host:/b']],
                ['sources' => ['/c'], 'destinations' => ['host:/d']],
            ],
        ]);
        $list->syncer = $syncer;
        $list->syncAll(false);

        $this->assertSame(0, $syncer->calls, 'No syncs should run once an abort is pending');

        $log = file_get_contents(ERSettings::getLogFilePath());
        $this->assertSame(
            1,
            substr_count($log, 'Abort detected'),
            'Abort detection should be logged exactly once, not once per remaining entry'
        );
    }
}
