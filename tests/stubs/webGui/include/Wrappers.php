<?php

// Stub of Unraid's /usr/local/emhttp/webGui/include/Wrappers.php for tests.
// Provides a minimal parse_plugin_cfg() that respects the EASY_RSYNC_CONFIG_DIR
// override and falls back to the shipped source/default.cfg.

if (!function_exists('parse_plugin_cfg')) {
    function parse_plugin_cfg(string $appName): array {
        $configDir = getenv('EASY_RSYNC_CONFIG_DIR');
        if ($configDir) {
            $cfgPath = rtrim($configDir, '/') . '/' . $appName . '.cfg';
            if (is_file($cfgPath)) {
                $parsed = parse_ini_file($cfgPath);
                if (is_array($parsed)) {
                    return $parsed;
                }
            }
        }

        $defaultCfg = dirname(__DIR__, 3) . '/../source/default.cfg';
        if (is_file($defaultCfg)) {
            $parsed = parse_ini_file($defaultCfg);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return [];
    }
}
