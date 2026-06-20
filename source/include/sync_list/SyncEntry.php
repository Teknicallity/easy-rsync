<?php

namespace unraid\plugins\EasyRsync;

require_once dirname(__DIR__) . "/ERSettings.php";
require_once dirname(__DIR__) . "/ERHelper.php";
require_once __DIR__ . "/RsyncOptions.php";
require_once __DIR__ . "/SyncResult.php";
require_once __DIR__ . "/SyncStatus.php";

use Exception;
use unraid\plugins\EasyRsync\Exceptions\RsyncFailureException;

//$logger = Logger::getLogger();

class SyncEntry {
    private static Logger $logger;
    public array $sources = [];
    public array $destinations = [];
    public ?RsyncOptions $rsyncOptions = null;
    /**
     * @var SyncResult[]
     */
    public array $results = [];
    public ?SyncStatus $finalStatus = null;
    public ?Syncer $syncer = null;

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
        self::$logger = Logger::getLogger();
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
        $rsyncOptions = $rsyncOptionsJson !== null ? RsyncOptions::fromArray($rsyncOptionsJson) : null;

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

    /**
     * @throws Exception
     */
    public function sync(callable $isAborted, bool $dryRunMode): SyncStatus {
        if ($this->syncer === null) {
            throw new Exception("Syncer not set");
        }

        $this->results = [];
        $pairs = $this->generatePairs();

        // An entry with no source/destination pairs (e.g. a source set but the
        // destination left blank, or vice-versa) has nothing to sync. Record it and
        // return a real status here instead of falling through to the final
        // `return $this->finalStatus` while it is still null -- returning null would
        // violate the declared SyncStatus return type and abort the whole run with a
        // fatal TypeError.
        if (empty($pairs)) {
            self::$logger->warning(
                "Sync entry has no source/destination pairs to sync; skipping. "
                . "Ensure both sources and destinations are set."
            );
            $this->results[] = new SyncResult(
                implode(', ', $this->sources),
                implode(', ', $this->destinations),
                SyncStatus::Skipped,
                'No source/destination pairs to sync'
            );
            $this->finalStatus = SyncStatus::Skipped;
            return $this->finalStatus;
        }

        if ($this->rsyncOptions === null) {
            $userConfig = ERSettings::getUserConfig();
            $rsyncOptions = RsyncOptions::fromArray($userConfig);
            $rsyncOptionsStr = $rsyncOptions->buildRsyncArgumentsString(doDryRun: $dryRunMode);
        } else {
            $rsyncOptionsStr = $this->rsyncOptions->buildRsyncArgumentsString(doDryRun: $dryRunMode);
        }
        self::$logger->debug("Built rsync options: $rsyncOptionsStr");

        foreach ($pairs as $index => $pair) {
            if ($isAborted()) {
                // If an abort has been requested, mark remaining syncs as skipped
                for ($i = $index; $i < count($pairs); $i++) {
                    $this->results[] = new SyncResult(
                        $pairs[$i]['source'],
                        $pairs[$i]['destination'],
                        SyncStatus::Skipped,
                        'Abort requested'
                    );
                }
                return SyncStatus::Skipped;
            }

            $error = null;

            try {
                self::$logger->info("Syncing '" . $pair['source'] . "' to '" . $pair['destination'] . "'");
                $this->syncer->performSync($pair['source'], $pair['destination'], $rsyncOptionsStr);
                $status = SyncStatus::Success;
            } catch (RsyncFailureException|Exception $e) {
                if ($isAborted()) {
                    // rsync was killed by a Force Stop, not a genuine failure.
                    $status = SyncStatus::Skipped;
                    $error = 'Force stopped';
                    self::$logger->info("Sync force-stopped for '" . $pair['source'] . "'");
                } else {
                    $status = SyncStatus::Failed;
                    $error = $e->getMessage();
                    self::$logger->error("Failed to sync '" . $pair['source'] . " with " . $pair['destination'] . "' Check Rsync Log.");
                }
            }

            // Store the result of the current sync
            $this->results[] = new SyncResult(
                $pair['source'],
                $pair['destination'],
                $status,
                $error
            );

            if ($status->isWorseThan($this->finalStatus)) {
                $this->finalStatus = $status;
            }
        }

        return $this->finalStatus;
    }

    private function generatePairs(): array {
        $pairs = [];
        foreach ($this->sources as $source) {
            foreach ($this->destinations as $destination) {
                $pairs[] = ['source' => $source, 'destination' => $destination];
            }
        }
        return $pairs;
    }
}