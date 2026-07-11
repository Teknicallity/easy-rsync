<?php

// The e2e suite runs on the dev machine and drives a real Unraid server over
// ssh + https. It needs no local plugin environment - just the autoloader (the
// support classes are in composer's autoload-dev classmap) and the target
// configuration from the environment / tests/e2e/.env.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

E2EEnv::load();
