<?php

use PHPUnit\Framework\TestCase;
use unraid\plugins\EasyRsync\SyncStatus;

class SyncStatusTest extends TestCase {
    public function testIsWorseThanNullIsAlwaysTrue(): void {
        $this->assertTrue(SyncStatus::Success->isWorseThan(null));
        $this->assertTrue(SyncStatus::Skipped->isWorseThan(null));
        $this->assertTrue(SyncStatus::Failed->isWorseThan(null));
    }

    public function testIsWorseThanOrdering(): void {
        $this->assertTrue(SyncStatus::Failed->isWorseThan(SyncStatus::Success));
        $this->assertTrue(SyncStatus::Failed->isWorseThan(SyncStatus::Skipped));
        $this->assertTrue(SyncStatus::Skipped->isWorseThan(SyncStatus::Success));

        $this->assertFalse(SyncStatus::Success->isWorseThan(SyncStatus::Failed));
        $this->assertFalse(SyncStatus::Success->isWorseThan(SyncStatus::Success));
        $this->assertFalse(SyncStatus::Failed->isWorseThan(SyncStatus::Failed));
    }

    public function testGetWorseStatusPicksGreater(): void {
        $this->assertSame(SyncStatus::Failed, SyncStatus::getWorseStatus(SyncStatus::Failed, SyncStatus::Success));
        $this->assertSame(SyncStatus::Failed, SyncStatus::getWorseStatus(SyncStatus::Success, SyncStatus::Failed));
        $this->assertSame(SyncStatus::Skipped, SyncStatus::getWorseStatus(SyncStatus::Success, SyncStatus::Skipped));
    }

    public function testIconsAndTextAreNonEmpty(): void {
        foreach (SyncStatus::cases() as $case) {
            $this->assertNotSame('', $case->getStatusIcon());
            $this->assertNotSame('', $case->getStatusText());
        }
    }
}
