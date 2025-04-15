<?php

namespace unraid\plugins\EasyRsync;

use Exception;
use InvalidArgumentException;

require_once dirname(__DIR__) . "/ERSettings.php";
require_once dirname(__DIR__) . "/FileUtils.php";
require_once dirname(__DIR__) . "/ERHelper.php";
require_once __DIR__ . "/RsyncOptions.php";
require_once __DIR__ . "/SyncEntry.php";
require_once __DIR__ . "/SyncResult.php";
require_once __DIR__ . "/SyncStatus.php";
require_once dirname(__DIR__) . "/paths/Destination.php";

$logger = Logger::getLogger();

class SyncList {
    private static Logger $logger;
    /**
     * @var SyncEntry[]
     */
    public array $entries = [];
    private bool $abortRequested = false;
    public ?SyncStatus $finalStatus = null;
    public ?Syncer $syncer = null;

    private function __construct(array $entries) {
        self::$logger = Logger::getLogger();
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

    /**
     * @throws Exception
     */
    public function syncAll(bool $doDryRun): void {
        if ($this->syncer === null) {
            throw new Exception("Syncer not set");
        }

        $this->abortRequested = false;
        $this->finalStatus = null;

        foreach ($this->entries as $entry) {
//            if ($this->abortRequested) break;
            $entry->syncer = $this->syncer;
            $outcome = $entry->sync(fn() => $this->checkAbortStatus(), $doDryRun);

            if ($outcome->isWorseThan($this->finalStatus)) {
                $this->finalStatus = $outcome;
            }
        }
    }

    /**
     * Checks if abort has been requested and updates the internal state accordingly.
     */
    private function checkAbortStatus(): bool {
        if ($this->abortRequested) return true;

        $isAbortRequested = ERHelper::isAbortRequested();
        if ($isAbortRequested) {
            $this->abortRequested = true;
        }

        return $isAbortRequested;
    }

    public function generateSummaryMessage(bool $useEmojis): string {
        $summary = "";

        foreach ($this->entries as $index => $entry) {
            $summary .= "*Sync Job #" . ($index + 1) . "*\\n";
            $results = $entry->results;
            foreach ($results as $result) {
                $icon = $useEmojis ? $result->status->getStatusIcon() : $result->status->getStatusText();
//                $source = $this->truncateText($result->source);
//                $destination = $this->truncateText($result->destination);
                $summary .= "$icon $result->source ->\\n⠀⠀$result->destination\\n";  // uses '⠀⠀', not spaces
            }
            $summary .= "\\n";
        }

        return trim($summary);
    }
}