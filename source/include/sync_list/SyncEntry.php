<?php

namespace unraid\plugins\EasyRsync;

use unraid\plugins\EasyRsync\ERSettings;
use unraid\plugins\EasyRsync\RsyncOptions;

class SyncEntry {
    public array $sources = [];
    public array $destinations = [];
    public ?RsyncOptions $rsyncOptions = null;

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

        $sources = self::normalizePathList($sourcesRaw);

        $destinations = self::normalizePathList($destinationsRaw);

        $rsyncOptionsJson = $data['rsyncOptions'] ?? null;
        $rsyncOptions = !is_null($rsyncOptionsJson) ? RsyncOptions::fromArray($rsyncOptionsJson) : null;

        return new SyncEntry(
            sources: $sources,
            destinations: $destinations,
            rsyncOptions: $rsyncOptions
        );
    }

    private static function normalizePathList(string|array $input): array {
        if (is_string($input)) {
            return array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $input)));
        }
        return $input;
    }
}