<?php

require_once __DIR__ ."/source/include/ERSettings.php";

use unraid\plugins\EasyRsync\ERSettings;


$paths = ERSettings::getPaths();

$paths = [
    "sources" => ["/mnt/user/project_demos"],
    "destinations"=> ["sheputa@10.1.1.225:/home/sheputa/rsyncTesting
", "
"]
];

ERSettings::saveSourcesAndDestinations($paths['sources'], $paths['destinations']);