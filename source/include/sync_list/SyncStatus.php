<?php

namespace unraid\plugins\EasyRsync;

enum SyncStatus: int {
    case Success = 0;
    case Skipped = 1;
    case Failed = 2;

    public static function getWorseStatus(SyncStatus $status1, SyncStatus $status2): SyncStatus {
        if ($status1->value >= $status2->value) {
            return $status1;
        }
        return $status2;
    }

    public function isWorseThan(?SyncStatus $status): bool {
        if ($status === null) {
            return true;
        }
        return $this->value > $status->value;
    }

    public function getStatusIcon(): string {
        return match ($this) {
            SyncStatus::Success => '✅',
            SyncStatus::Failed => '❌',
            SyncStatus::Skipped => '⏭️',
            default => '❔',
        };
    }

    public function getStatusText(): string {
        return match ($this) {
            SyncStatus::Success => 'Success:',
            SyncStatus::Failed => 'Failure:',
            SyncStatus::Skipped => 'Skipped:',
            default => '❔',
        };
    }
}