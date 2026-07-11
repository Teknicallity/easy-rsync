<?php

/**
 * E2E configuration from environment variables, optionally seeded from
 * tests/e2e/.env (untracked; see .env.example). E2E_HOST is required - the
 * suite refuses to run without an explicit target server.
 */
class E2EEnv {
    private static bool $loaded = false;

    public static function load(): void {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        $envFile = dirname(__DIR__) . '/.env';
        if (!is_file($envFile)) {
            return;
        }
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            // Real environment variables win over .env entries.
            if (getenv($key) === false) {
                putenv("$key=" . trim($value));
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string {
        self::load();
        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    public static function require(string $key): string {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new RuntimeException(
                "$key is not set. Copy tests/e2e/.env.example to tests/e2e/.env and fill it in, "
                . "or export $key in the environment."
            );
        }
        return $value;
    }

    public static function host(): string {
        return self::require('E2E_HOST');
    }

    public static function sshUser(): string {
        return self::get('E2E_SSH_USER', 'root');
    }

    public static function plugin(): string {
        return self::get('E2E_PLUGIN', 'easy.rsync.beta');
    }

    public static function scheme(): string {
        return self::get('E2E_SCHEME', 'https');
    }
}
