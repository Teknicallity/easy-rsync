<?php

namespace unraid\plugins\EasyRsync;

class SyncResult {
    public string $source;
    public string $destination;
    public SyncStatus $status;
    public ?string $error;

    public function __construct(string $source, string $destination, SyncStatus $status, ?string $error = null) {
        $this->source = $source;
        $this->destination = $destination;
        $this->status = $status;
        $this->error = $error;
    }
}