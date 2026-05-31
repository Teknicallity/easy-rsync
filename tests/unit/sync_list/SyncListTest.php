<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\Logger;
use unraid\plugins\EasyRsync\SyncList;
use unraid\plugins\EasyRsync\SyncEntry;
use unraid\plugins\EasyRsync\Syncer;

class SyncListTest extends TestCase {
    protected function tearDown(): void {
        Logger::resetInstance();
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
}
