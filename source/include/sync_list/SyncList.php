<?php

namespace unraid\plugins\EasyRsync;

use Exception;
use InvalidArgumentException;

require_once dirname(__DIR__) . "/ERSettings.php";
require_once dirname(__DIR__) . "/FileUtils.php";
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

        $fileContents = FileUtils::readJsonFile($filePath);

        return self::fromArray($fileContents);
    }

    /**
     * @throws Exception
     */
    public function saveToFile(): void {
        if (empty($this->entries)) {
            return;
        }

        $filePath = ERSettings::getPathsJsonFilePath();
        $output = ['syncEntries' => $this->entries];

        FileUtils::writeJsonFile($filePath, $output);
    }

    public static function fromArray(mixed $json): SyncList {
        $jsonSyncList = isset($json['syncEntries']) ? (array)$json['syncEntries'] : [];

        $syncEntries = [];
        foreach ($jsonSyncList as $index => $jsonEntry) {
            if (!is_array($jsonEntry)) {
                // You can log or throw depending on how strict you want to be
                throw new \UnexpectedValueException("Entry at index $index is not a valid array");
            }

            try {
                $syncEntries[] = SyncEntry::fromArray($jsonEntry);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Invalid sync entry at index $index: " . $e->getMessage(), 0, $e);
            }
        }

        return new SyncList($syncEntries);
    }
}