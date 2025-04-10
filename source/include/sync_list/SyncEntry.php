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

    public static function fromArray(mixed $data): SyncEntry {
        $sourcesRaw = $data['sources'] ?? [];
        $destinationsRaw = $data['destinations'] ?? [];

        $sources = is_string($sourcesRaw)
            ? array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $sourcesRaw)))
            : (array)$sourcesRaw;

        $destinations = is_string($destinationsRaw)
            ? array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $destinationsRaw)))
            : (array)$destinationsRaw;

        $rsyncOptionsJson = $data['rsyncOptions'] ?? null;
        $rsyncOptions = !is_null($rsyncOptionsJson) ? RsyncOptions::fromArray($rsyncOptionsJson) : null;

        return new SyncEntry(
            sources: $sources,
            destinations: $destinations,
            rsyncOptions: $rsyncOptions
        );
    }
}