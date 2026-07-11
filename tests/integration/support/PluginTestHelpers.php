<?php

/**
 * Shared helpers for the integration suite: writing the plugin's config and
 * sync list, reading its plugin log, and polling. Consumers supply where the
 * config/temp dirs live, since IntegrationTestCase owns a fresh pair per test
 * while HttpHandlerTest reads them off its long-lived HttpServerHarness.
 */
trait PluginTestHelpers {
    abstract protected function pluginConfigDir(): string;
    abstract protected function pluginTempDir(): string;

    /** Write the user config file: shipped defaults merged with $overrides. */
    protected function writeUserConfig(array $overrides = []): void {
        $defaults = parse_ini_file(dirname(__DIR__, 3) . '/source/default.cfg');
        $config = array_merge($defaults, $overrides);
        $lines = '';
        foreach ($config as $key => $value) {
            $lines .= $key . '="' . $value . '"' . "\n";
        }
        file_put_contents($this->pluginConfigDir() . '/easy.rsync.cfg', $lines);
    }

    /** Write the sync-job list (backup_paths.json). */
    protected function writeSyncList(array $entries): void {
        file_put_contents(
            $this->pluginConfigDir() . '/backup_paths.json',
            json_encode(['syncEntries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function pluginLog(): string {
        $path = $this->pluginTempDir() . '/easy-rsync.log';
        return file_exists($path) ? (string) file_get_contents($path) : '';
    }

    /** Poll until $condition() is true or fail after $timeoutSeconds. */
    protected function waitUntil(callable $condition, float $timeoutSeconds, string $what): void {
        $deadline = microtime(true) + $timeoutSeconds;
        while (!$condition()) {
            if (microtime(true) > $deadline) {
                $this->fail("Timed out after {$timeoutSeconds}s waiting for: $what");
            }
            usleep(50_000);
        }
    }
}
