<?php

// php -S router for integration tests: every request is dispatched to the plugin's
// AJAX endpoint, mirroring the /plugins/<app>/include/http_handler.php URL that
// nginx routes to on a real Unraid box.
require dirname(__DIR__, 3) . '/source/include/http_handler.php';
