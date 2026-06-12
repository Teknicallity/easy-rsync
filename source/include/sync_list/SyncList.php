<?php

namespace unraid\plugins\EasyRsync;

use DateTime;
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

        $userConfig = ERSettings::getUserConfig();

        $this->abortRequested = false;
        $this->finalStatus = null;

        foreach ($this->entries as $index => $entry) {
//            if ($this->abortRequested) break;
            $backupEntryStartedTime = new DateTime();
            $entry->syncer = $this->syncer;
            $outcomeStatus = $entry->sync(fn() => $this->checkAbortStatus(), $doDryRun);

            if ($outcomeStatus->isWorseThan($this->finalStatus)) {
                $this->finalStatus = $outcomeStatus;
            }

            $this->handleEntrySyncNotifications($entry, $userConfig, $backupEntryStartedTime, $outcomeStatus, $index);
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
            self::$logger->info("Abort detected: the current sync has finished; skipping the remaining syncs.");
        }

        return $isAbortRequested;
    }

    public function generateSummaryMessage(bool $useEmojis): string {
        $summary = "";

        foreach ($this->entries as $index => $entry) {
            $summary .= "**Sync Job #" . ($index + 1) . "**\\n";
            $summary .= $this->generateEntryMessage($entry, $useEmojis);
            $summary .= "\\n";
        }

        return trim($summary);
    }

    private function generateEntryMessage(SyncEntry $entry, bool $useEmojis): string {
        $message = "";
        $results = $entry->results;
        foreach ($results as $result) {
            $icon = $useEmojis ? $result->status->getStatusIcon() : $result->status->getStatusText();
//                $source = $this->truncateText($result->source);
//                $destination = $this->truncateText($result->destination);
            $message .= "$icon $result->source ->\\n⠀⠀$result->destination\\n";  // uses '⠀⠀', not spaces
        }

        return trim($message);
    }

    private function handleEntrySyncNotifications(
        SyncEntry $entry, array $userConfig, DateTime $startedTime, SyncStatus $outcomeStatus, int $index
    ): void {
        $backupFinishedTime = new DateTime();
        $backupTime = $startedTime->diff($backupFinishedTime);
        $duration = $backupTime->format('%H:%I:%S');

        $subject = match ($outcomeStatus) {
            SyncStatus::Success => "Sync Entry #" . $index+1 . " Completed",
            SyncStatus::Failed => "Sync Entry #" . $index+1 . " Completed with Errors",
            SyncStatus::Skipped => "Sync Entry #" . $index+1 . " Aborted",
        };

        $doForEachNotification = $userConfig["notificationMode"] === "both" || $userConfig["notificationMode"] === "foreach";
        if ($doForEachNotification) {
            $notificationLevel = match ($outcomeStatus) {
                SyncStatus::Success => NotificationLevel::NORMAL,
                SyncStatus::Failed => NotificationLevel::ALERT,
                SyncStatus::Skipped => NotificationLevel::WARNING,
            };

            $notification = new Notification(
                $subject,
                "Completed in $duration",
                message: $this->generateEntryMessage($entry, true),
                level: $notificationLevel
            );

            $notification->send();
        }

        $logMessage = $subject . ". Took " . $duration;
        switch ($outcomeStatus) {
            case SyncStatus::Failed:
                self::$logger->error($logMessage);
                break;
            case SyncStatus::Skipped:
                self::$logger->warning($logMessage);
                break;
            default:
                self::$logger->info($logMessage);
        }
    }
}