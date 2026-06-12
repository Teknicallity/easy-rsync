<?php

namespace unraid\plugins\EasyRsync;

require_once __DIR__ ."/Syncer.php";
require_once dirname(__DIR__) . "/ERSettings.php";

use unraid\plugins\EasyRsync\Exceptions\RsyncFailureException;
use unraid\plugins\EasyRsync\Syncer;

class RsyncSyncer implements Syncer {

    /**
     * @throws RsyncFailureException
     */
    public function performSync(string $source, string $destination, string $rsyncOptions): void {
        $rsyncLogFilePath = ERSettings::getRsyncLogFilePath();

        // 2>&1: rsync's fatal errors go to stderr, which --log-file does NOT record.
        $command = "rsync $rsyncOptions '$source' '$destination'"
            . " --log-file='" . $rsyncLogFilePath . "' 2>&1";

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            $capturedText = trim(implode("\n", $output));
            $meaning = RsyncFailureException::describeExitCode($return_var);
            $message = "Rsync failed to sync '$source' -> '$destination' (exit code $return_var: $meaning)";
            if ($capturedText !== '') {
                $message .= "\n" . $capturedText;
            }
            // Make the failure visible in the Rsync Log tab.
            @file_put_contents($rsyncLogFilePath,
                date("Y-m-d H:i:s") . " [ERROR] " . $message . PHP_EOL, FILE_APPEND);

            throw new RsyncFailureException(message: $message, code: $return_var);
        }
    }
}