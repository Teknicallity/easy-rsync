<?php

// Stub of Unraid's /usr/local/emhttp/webGui/include/Helpers.php for tests.
// Only includes helpers actually referenced by code under test.

if (!function_exists('mk_option')) {
    function mk_option($selected, string $value, string $text, string $extra = ''): string {
        $sel = ((string)$selected === (string)$value) ? ' selected="selected"' : '';
        $attr = $extra !== '' ? ' ' . $extra : '';
        return '<option value="' . htmlspecialchars($value) . '"' . $sel . $attr . '>' . htmlspecialchars($text) . '</option>';
    }
}

if (!function_exists('autov')) {
    // Unraid appends a cache-busting query param to the asset path; for tests the
    // identity path is enough. Echoes by default, returns when $ret is true.
    function autov(string $file, bool $ret = false) {
        if ($ret) {
            return $file;
        }
        echo $file;
    }
}
