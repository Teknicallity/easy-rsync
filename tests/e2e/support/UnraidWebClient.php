<?php

/**
 * HTTP client for the Unraid webGui: sends the session cookie (named
 * unraid_<md5(host)> by webGui's local_prepend.php) and, on POST, the CSRF
 * token that same prepend enforces. TLS verification is disabled because the
 * server's cert is issued for its myunraid.net name, not the LAN IP.
 */
class UnraidWebClient {
    public function __construct(
        private string $baseUrl,     // e.g. https://10.1.1.231
        private string $cookieName,  // unraid_<md5(host)>
        private string $sessionId,
        private string $csrfToken,
    ) {}

    /**
     * @return array{status:int, body:string, json:?array}
     */
    public function get(string $path, array $query = []): array {
        $url = $this->baseUrl . $path . ($query ? '?' . http_build_query($query) : '');
        return $this->request('GET', $url);
    }

    /**
     * @return array{status:int, body:string, json:?array}
     */
    public function post(string $path, array $fields = [], bool $withCsrf = true): array {
        if ($withCsrf) {
            $fields['csrf_token'] = $this->csrfToken;
        }
        return $this->request('POST', $this->baseUrl . $path, http_build_query($fields));
    }

    /** A request without the session cookie (for auth-rejection tests). */
    public function getUnauthenticated(string $path): array {
        return $this->request('GET', $this->baseUrl . $path, null, false);
    }

    private function request(string $method, string $url, ?string $body = null, bool $withCookie = true): array {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        if ($withCookie) {
            $options[CURLOPT_COOKIE] = $this->cookieName . '=' . $this->sessionId;
        }
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $options);
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("HTTP $method $url failed: $err");
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [
            'status' => $status,
            'body' => (string) $responseBody,
            'json' => json_decode((string) $responseBody, true),
        ];
    }
}
