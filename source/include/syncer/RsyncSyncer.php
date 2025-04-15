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
        $command = "rsync $rsyncOptions '$source' '$destination' --log-file='" . ERSettings::getRsyncLogFilePath() . "'";
//        self::$logger->info("Current command: $command");
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            throw new RsyncFailureException(code: $return_var);
        }
    }
}