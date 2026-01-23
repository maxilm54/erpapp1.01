<?php
function env(string $key, $default = null)
{
    static $env = null;

    if ($env === null) {
        $path = BASE_PATH . '/.env';

        if (!file_exists($path)) {
            return $default;
        }

        $env = parse_ini_file($path, false, INI_SCANNER_TYPED);
    }

    return $env[$key] ?? $default;
}