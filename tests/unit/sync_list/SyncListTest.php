<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\Logger;
use unraid\plugins\EasyRsync\SyncList;
use unraid\plugins\EasyRsync\SyncEntry;
use unraid\plugins\EasyRsync\Syncer;
use unraid\plugins\EasyRsync\RsyncOptions;

class SyncListTest extends TestCase {
    protected function tearDown(): void {
        Logger::resetInstance();
        @unlink(ERSettings::getStateRsyncAbortedFilePath());
        @unlink(ERSettings::getPathsJsonFilePath());
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

    /**
     * End-to-end persistence chain: the settings form POST shape -> SyncList::fromArray
     * -> saveToFile() (what settings.php does) -> the {syncEntries} file on disk ->
     * SyncList::fromFile() (what rsync_backup.php does at backup time). Guards that the
     * live path still works after the old {sources,destinations} helpers were removed.
     */
    public function testSaveToFileFromFileRoundTripMirrorsUiPost(): void {
        $post = [
            'syncEntries' => [
                [ // entry 0: uses GLOBAL rsync settings (form omits rsyncOptions when disabled)
                    'sources'      => "/mnt/user/docs\r\n/mnt/user/pics\r\n",
                    'destinations' => "backup@nas:/srv/docs\r\n",
                ],
                [ // entry 1: OVERRIDES rsync settings
                    'sources'      => "/mnt/user/vault",
                    'destinations' => "/mnt/disk2/vault",
                    'rsyncOptions' => [
                        'rsyncTimes'  => 'false',
                        'rsyncDelete' => 'before',
                        'rsyncCustom' => '-avh --exclude=node_modules',
                    ],
                ],
            ],
        ];

        // SAVE (settings.php POST handler)
        SyncList::fromArray($post)->saveToFile();

        // On-disk format is the live {syncEntries} shape.
        $raw = file_get_contents(ERSettings::getPathsJsonFilePath());
        $this->assertStringContainsString('"syncEntries"', $raw);
        $this->assertStringNotContainsString('"sources":', substr($raw, 0, strpos($raw, '[')), 'no top-level sources/destinations format');

        // READ (rsync_backup.php at backup time)
        $list = SyncList::fromFile();
        $this->assertCount(2, $list->entries);

        // entry 0: textarea strings split into arrays; rsyncOptions null -> global settings
        $this->assertSame(['/mnt/user/docs', '/mnt/user/pics'], array_values($list->entries[0]->sources));
        $this->assertSame(['backup@nas:/srv/docs'], array_values($list->entries[0]->destinations));
        $this->assertNull($list->entries[0]->rsyncOptions, 'no rsyncOptions key -> uses global settings');

        // entry 1: per-entry overrides survive the disk round-trip
        $this->assertSame(['/mnt/user/vault'], array_values($list->entries[1]->sources));
        $this->assertSame(['/mnt/disk2/vault'], array_values($list->entries[1]->destinations));
        $opts = $list->entries[1]->rsyncOptions;
        $this->assertInstanceOf(RsyncOptions::class, $opts);
        $this->assertFalse($opts->rsyncTimes);
        $this->assertSame('before', $opts->rsyncDelete);
        $this->assertSame('-avh --exclude=node_modules', $opts->rsyncCustom);
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
