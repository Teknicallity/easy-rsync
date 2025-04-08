<?php

namespace unraid\plugins\EasyRsync;

use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\RsyncOptions;

class SyncEntry {
    public array $sources;
    public array $destinations;
    public ?RsyncOptions $rsyncOptions;

    public function __construct(
        array $sources = [],
        array $destinations = [],
        RsyncOptions $rsyncOptions = null
    ) {
        if (!is_array($sources)) {
            throw new \InvalidArgumentException('Sources must be an array');
        }
        if (!is_array($destinations)) {
            throw new \InvalidArgumentException('Destinations must be an array');
        }
        $this->sources = $sources;
        $this->destinations = $destinations;
        $this->rsyncOptions = $rsyncOptions;
    }

    public static function fromJson(mixed $json): SyncEntry {
        $sources = (array)$json['sources'] ?? [];
        $destinations = (array)$json['destinations'] ?? [];
        $rsyncOptionsJson = $json['rsyncOptions'] ?? null;

        $rsyncOptions = !is_null($rsyncOptionsJson) ? RsyncOptions::fromJson($rsyncOptionsJson) : null;

        return new SyncEntry(
            sources: $sources,
            destinations: $destinations,
            rsyncOptions: $rsyncOptions
        );
    }
}