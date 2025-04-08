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

        $syncEntries = self::getSyncEntriesFromJson($fileContents);
        return new SyncList($syncEntries);
    }

    public static function fromFormData(array $formData): SyncList {
        if (!isset($formData['sourceDirectories'], $formData['destinationHosts'])) {
            throw new InvalidArgumentException("Missing required fields in form data.");
        }

        $sourceDirs = $formData['sourceDirectories'];
        $destHosts = $formData['destinationHosts'];

        if (count($sourceDirs) !== count($destHosts)) {
            throw new InvalidArgumentException("Source directories and destination hosts must have the same length.");
        }

        $syncEntries = [];

        for ($i = 0; $i < count($sourceDirs); $i++) {
            $rawSources = trim($sourceDirs[$i]);
            $rawDestinations = trim($destHosts[$i]);

            if (empty($rawSources) || empty($rawDestinations)) {
                continue;
            }

            $sources = preg_split("/\r\n|\n|\r/", $rawSources);
            $destinations = preg_split("/\r\n|\n|\r/", $rawDestinations);

            $entry = new SyncEntry(
                $sources,
                $destinations,
                null
            );
            $syncEntries[] = $entry;
        }

        return new SyncList($syncEntries);
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
        $output = ['syncList' => $this->entries];

        $success = file_put_contents(
            $filePath,
            json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        if (!$success) {
            throw new Exception("Could not save file");
        }
    }

    private static function getSyncEntriesFromJson(mixed $json): array {
        $jsonSyncList = isset($json['syncList']) ? (array)$json['syncList'] : [];

        $syncEntries = [];
        foreach ($jsonSyncList as $jsonEntry) {
            $syncEntries[] = SyncEntry::fromJson($jsonEntry);
        }

        return $syncEntries;
    }
}