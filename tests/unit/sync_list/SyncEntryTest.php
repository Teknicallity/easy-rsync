<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\Logger;
use unraid\plugins\EasyRsync\RsyncOptions;
use unraid\plugins\EasyRsync\SyncEntry;
use unraid\plugins\EasyRsync\Syncer;
use unraid\plugins\EasyRsync\SyncStatus;
use unraid\plugins\EasyRsync\Exceptions\RsyncFailureException;

class SyncEntryTest extends TestCase {
    protected function tearDown(): void {
        Logger::resetInstance();
    }

    public function testFromArrayWithStringPathsSplitsByNewlines(): void {
        $entry = SyncEntry::fromArray([
            'sources' => "/mnt/user/a\n/mnt/user/b\n",
            'destinations' => "host:/dst1\nhost:/dst2",
        ]);
        $this->assertSame(['/mnt/user/a', '/mnt/user/b'], array_values($entry->sources));
        $this->assertSame(['host:/dst1', 'host:/dst2'], array_values($entry->destinations));
        $this->assertNull($entry->rsyncOptions);
    }

    public function testFromArrayWithArrayPathsPassesThrough(): void {
        $entry = SyncEntry::fromArray([
            'sources' => ['/a', '/b'],
            'destinations' => ['host:/c'],
            'rsyncOptions' => ['rsyncRecursive' => true, 'rsyncCustom' => '--test'],
        ]);
        $this->assertSame(['/a', '/b'], $entry->sources);
        $this->assertSame(['host:/c'], $entry->destinations);
        $this->assertInstanceOf(RsyncOptions::class, $entry->rsyncOptions);
        $this->assertSame('--test', $entry->rsyncOptions->rsyncCustom);
    }

    public function testSyncWithoutSyncerThrows(): void {
        $entry = new SyncEntry(['/a'], ['host:/b'], RsyncOptions::fromArray([]));
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Syncer not set');
        $entry->sync(fn() => false, false);
    }

    public function testSyncSuccessRecordsResults(): void {
        $syncer = new class implements Syncer {
            public array $calls = [];
            public function performSync(string $source, string $destination, string $rsyncOptions): void {
                $this->calls[] = [$source, $destination, $rsyncOptions];
            }
        };

        $entry = new SyncEntry(
            ['/src1', '/src2'],
            ['host:/dst'],
            RsyncOptions::fromArray(['rsyncRecursive' => true, 'rsyncDelete' => 'after'])
        );
        $entry->syncer = $syncer;

        $status = $entry->sync(fn() => false, false);

        $this->assertSame(SyncStatus::Success, $status);
        $this->assertCount(2, $entry->results);
        $this->assertCount(2, $syncer->calls);
        $this->assertSame('/src1', $syncer->calls[0][0]);
        $this->assertSame('host:/dst', $syncer->calls[0][1]);
        $this->assertStringContainsString('--recursive', $syncer->calls[0][2]);
        $this->assertStringContainsString('--delete-after', $syncer->calls[0][2]);
    }

    public function testSyncFailurePromotesFinalStatus(): void {
        $syncer = new class implements Syncer {
            public function performSync(string $source, string $destination, string $rsyncOptions): void {
                throw new RsyncFailureException('nope', 23);
            }
        };

        $entry = new SyncEntry(['/src'], ['host:/dst'], RsyncOptions::fromArray([]));
        $entry->syncer = $syncer;

        $status = $entry->sync(fn() => false, false);
        $this->assertSame(SyncStatus::Failed, $status);
        $this->assertSame(SyncStatus::Failed, $entry->results[0]->status);
        $this->assertNotEmpty($entry->results[0]->error);
    }

    public function testAbortMarksRemainingSkipped(): void {
        $syncer = new class implements Syncer {
            public int $calls = 0;
            public function performSync(string $source, string $destination, string $rsyncOptions): void {
                $this->calls++;
            }
        };

        $entry = new SyncEntry(['/a', '/b', '/c'], ['host:/d'], RsyncOptions::fromArray([]));
        $entry->syncer = $syncer;

        $abortAfter = 1;
        $seen = 0;
        $status = $entry->sync(function () use (&$seen, $abortAfter) {
            $abort = $seen >= $abortAfter;
            $seen++;
            return $abort;
        }, false);

        $this->assertSame(SyncStatus::Skipped, $status);
        $this->assertSame(1, $syncer->calls, 'Only the first pair should be synced before abort');
        $this->assertCount(3, $entry->results);
        $this->assertSame(SyncStatus::Success, $entry->results[0]->status);
        $this->assertSame(SyncStatus::Skipped, $entry->results[1]->status);
        $this->assertSame(SyncStatus::Skipped, $entry->results[2]->status);
    }

    public function testForceStopWhileSyncingMarksJobSkipped(): void {
        $syncer = new class implements Syncer {
            public function performSync(string $source, string $destination, string $rsyncOptions): void {
                throw new RsyncFailureException('killed by signal', 20);
            }
        };

        $entry = new SyncEntry(['/a'], ['host:/b'], RsyncOptions::fromArray([]));
        $entry->syncer = $syncer;

        // Abort is not yet set at the loop check, but set by the time we're in the
        // catch — i.e. the running rsync was force-stopped mid-transfer.
        $checks = 0;
        $entry->sync(function () use (&$checks) {
            return $checks++ > 0;
        }, false);

        $this->assertSame(SyncStatus::Skipped, $entry->results[0]->status);
        $this->assertSame('Force stopped', $entry->results[0]->error);
    }

    public function testDryRunPassesDryRunFlag(): void {
        $syncer = new class implements Syncer {
            public string $opts = '';
            public function performSync(string $source, string $destination, string $rsyncOptions): void {
                $this->opts = $rsyncOptions;
            }
        };

        $entry = new SyncEntry(['/a'], ['host:/b'], RsyncOptions::fromArray([]));
        $entry->syncer = $syncer;

        $entry->sync(fn() => false, true);
        $this->assertStringContainsString('--dry-run', $syncer->opts);
    }
}
