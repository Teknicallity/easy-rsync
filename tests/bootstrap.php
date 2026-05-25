<?php

// Per-run isolated config/temp dirs under the system tmp.
$runId = bin2hex(random_bytes(4));
$baseDir = sys_get_temp_dir() . '/easy-rsync-tests/' . $runId;
$configDir = $baseDir . '/config';
$tempDir = $baseDir . '/tmp';

@mkdir($configDir, 0755, true);
@mkdir($tempDir, 0755, true);

putenv('EASY_RSYNC_CONFIG_DIR=' . $configDir);
putenv('EASY_RSYNC_TEMP_DIR=' . $tempDir);
$_ENV['EASY_RSYNC_CONFIG_DIR'] = $configDir;
$_ENV['EASY_RSYNC_TEMP_DIR'] = $tempDir;

// Route Unraid helper include paths to our stubs.
$_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/stubs';

// Best-effort cleanup at shutdown.
register_shutdown_function(static function () use ($baseDir): void {
    if (!is_dir($baseDir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
    @rmdir($baseDir);
});

require __DIR__ . '/../vendor/autoload.php';
