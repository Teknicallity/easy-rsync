<?php

namespace unraid\plugins\EasyRsync;

require_once dirname(__DIR__) . "/ERSettings.php";
require_once __DIR__ . "/SyncEntry.php";
require_once __DIR__ . "/RsyncOptions.php";
require_once dirname(__DIR__) . "/paths/Destination.php";

class SyncList {
    public array $entries = [];

    private function __construct(array $entries) {
        $this->entries = $entries;
    }

    public static function fromFile(): SyncList {
        $filePath = ERSettings::getPathsJsonFilePath();
//        $filePath = ERSettings::$configDir . '/' . $filename;

        $fileContents = null;
        if (file_exists($filePath)) {
            $fileContents = json_decode(file_get_contents($filePath), true);
        }

        $syncEntries = self::getSyncEntries($fileContents);
        return new SyncList($syncEntries);
    }

    public function saveToFile(): void {
        if (empty($this->entries)) {
            return;
        }

        if (empty($this->entries["sources"] || empty($this->entries["destinations"]))) {
            return;
        }

        $filePath = ERSettings::getPathsJsonFilePath();
        $output = ['syncList' => $this->entries];

        file_put_contents(
            $filePath,
            json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private static function getSyncEntries(mixed $json): array {
        $jsonSyncList = isset($json['syncList']) ? (array)$json['syncList'] : [];

        $syncEntries = [];
        foreach ($jsonSyncList as $jsonEntry) {
            $syncEntries[] = SyncEntry::fromJson($jsonEntry);
        }

        return $syncEntries;
    }
}