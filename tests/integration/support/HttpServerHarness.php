<?php

/**
 * Runs source/include/http_handler.php behind PHP's built-in web server with the
 * same env-var isolation IntegrationTestCase uses, so tests can exercise the AJAX
 * endpoint over real HTTP - including the fire-and-forget `php rsync_backup.php &`
 * children it exec()s, which inherit the server's environment.
 */
class HttpServerHarness {
    public string $baseDir;
    public string $configDir;
    public string $tempDir;
    public string $dataDir;
    public string $shimLogDir;
    public int $port;

    /** @var resource|null */
    private $proc = null;

    public function start(): void {
        $this->baseDir = sys_get_temp_dir() . '/easy-rsync-integration/http-' . bin2hex(random_bytes(4));
        $this->configDir = $this->baseDir . '/config';
        $this->tempDir = $this->baseDir . '/tmp';
        $this->dataDir = $this->baseDir . '/data';
        $this->shimLogDir = $this->baseDir . '/shim-logs';
        foreach ([$this->configDir, $this->tempDir, $this->dataDir, $this->shimLogDir] as $dir) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->baseDir . '/var.ini', "fsState=\"Started\"\n");

        $repoRoot = dirname(__DIR__, 3);
        $stubsDir = $repoRoot . '/tests/stubs';
        $shimBinDir = $repoRoot . '/tests/integration/bin';
        $router = $repoRoot . '/tests/integration/fixtures/router.php';

        $env = array_merge(self::currentEnv(), [
            'EASY_RSYNC_CONFIG_DIR' => $this->configDir,
            'EASY_RSYNC_TEMP_DIR' => $this->tempDir,
            'EASY_RSYNC_EMHTTP_VARS' => $this->baseDir . '/var.ini',
            'EASY_RSYNC_DOCROOT' => $stubsDir,
            'EASY_RSYNC_NOTIFY_SCRIPT' => $shimBinDir . '/notify',
            'EASY_RSYNC_SHIM_LOG_DIR' => $this->shimLogDir,
            'PATH' => $shimBinDir . ':' . getenv('PATH'),
        ]);

        $this->port = self::findFreePort();
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $this->port, '-t', $stubsDir, $router];
        $this->proc = proc_open($cmd, [
            1 => ['file', $this->baseDir . '/server.log', 'a'],
            2 => ['file', $this->baseDir . '/server.log', 'a'],
        ], $pipes, $repoRoot, $env);
        if (!is_resource($this->proc)) {
            throw new RuntimeException('failed to start php -S');
        }

        // Wait until the server accepts connections.
        $deadline = microtime(true) + 10;
        while (true) {
            $sock = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);
            if ($sock !== false) {
                fclose($sock);
                return;
            }
            if (microtime(true) > $deadline) {
                throw new RuntimeException('php -S did not start listening: ' . (string) @file_get_contents($this->baseDir . '/server.log'));
            }
            usleep(50_000);
        }
    }

    public function stop(): void {
        if (is_resource($this->proc)) {
            proc_terminate($this->proc, 9);
            proc_close($this->proc);
            $this->proc = null;
        }
        IntegrationTestCase::removeDir($this->baseDir);
    }

    /**
     * @return array{status:int, json:?array, body:string}
     */
    public function request(string $method, array $params = []): array {
        $url = 'http://127.0.0.1:' . $this->port . '/plugins/easy.rsync/include/http_handler.php';
        $ch = curl_init();
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("HTTP request failed: $err");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'json' => json_decode($body, true), 'body' => $body];
    }

    public function get(array $params = []): array {
        return $this->request('GET', $params);
    }

    public function post(array $params = []): array {
        return $this->request('POST', $params);
    }

    /** Remove all plugin state/log files between tests (config + temp + shim logs). */
    public function resetState(): void {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->shimLogDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($this->configDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        IntegrationTestCase::removeDir($this->dataDir);
        mkdir($this->dataDir, 0755, true);
    }

    private static function currentEnv(): array {
        $env = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && getenv($key) !== false) {
                $env[$key] = getenv($key);
            }
        }
        // getenv() covers vars set via putenv() too.
        foreach (getenv() as $key => $value) {
            $env[$key] = $value;
        }
        return $env;
    }

    private static function findFreePort(): int {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            throw new RuntimeException("could not bind a test port: $errstr");
        }
        $name = stream_socket_get_name($server, false);
        fclose($server);
        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
