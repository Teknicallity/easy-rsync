<?php

namespace unraid\plugins\EasyRsync;

use Exception;
use InvalidArgumentException;

require_once dirname(__DIR__) . "/ERSettings.php";
require_once __DIR__ . "/SyncEntry.php";
require_once __DIR__ . "/RsyncOptions.php";
require_once dirname(__DIR__) . "/paths/Destination.php";

class SyncList {
    /**
     * @var SyncEntry[]
     */
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

        return self::fromArray($fileContents);
    }

    /**
     * @throws Exception
     */
    public function saveToFile(): void {
        if (empty($this->entries)) {
            return;
        }

        if (empty($this->entries["sources"] || empty($this->entries["destinations"]))) {
            return;
        }

        $filePath = ERSettings::getPathsJsonFilePath();
        $output = ['syncEntries' => $this->entries];

        $success = file_put_contents(
            $filePath,
            json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if (!$success) {
            throw new Exception("Could not save file");
        }
    }

    public static function fromArray(mixed $json): SyncList {
        $jsonSyncList = isset($json['syncEntries']) ? (array)$json['syncEntries'] : [];

        $syncEntries = [];
        foreach ($jsonSyncList as $jsonEntry) {
            $syncEntries[] = SyncEntry::fromArray($jsonEntry);
        }

        return new SyncList($syncEntries);
    }
}