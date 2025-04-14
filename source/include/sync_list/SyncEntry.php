<?php

namespace unraid\plugins\EasyRsync;

require_once dirname(__DIR__) . "/ERSettings.php";
require_once dirname(__DIR__) . "/ERHelper.php";
require_once __DIR__ . "/RsyncOptions.php";
require_once __DIR__ . "/SyncResult.php";
require_once __DIR__ . "/SyncStatus.php";

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
        if ($rsyncOptionsJson !== null) {
            $rsyncOptions = RsyncOptions::fromArray($rsyncOptionsJson);
        } else {
            $userConfig = ERSettings::getUserConfig();
            $rsyncOptions = RsyncOptions::fromArray($userConfig);
        }

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

    public function sync(callable $isAborted, bool $dryRunMode): SyncStatus {
        $this->results = [];
        $pairs = $this->generatePairs();
        $rsyncOptionsStr = $this->rsyncOptions->buildRsyncArgumentsString(doDryRun: $dryRunMode);
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
                self::$logger->info("Abort request received");
                return SyncStatus::Skipped;
            }

            $error = null;

            try {
                self::$logger->info("Syncing '" . $pair['source'] . "' to '" . $pair['destination'] . "'");
                $this->performSync($pair['source'], $pair['destination'], $rsyncOptionsStr);
                $status = SyncStatus::Success;
            } catch (RsyncFailureException|\Exception $e) {
                $status = SyncStatus::Failed;
                $error = $e->getMessage();
                self::$logger->error("Failed to sync '" . $pair['source'] . " with " . $pair['destination'] . "' Check Rsync Log.");
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

    /**
     * @throws RsyncFailureException
     */
    private function performSync(string $source, string $destination, string $rsyncOptions): void {
        $command = "rsync $rsyncOptions '$source' '$destination' --log-file='" . ERSettings::getRsyncLogFilePath() . "'";
//        self::$logger->info("Current command: $command");
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new RsyncFailureException(code: $return_var);
        }
    }
}