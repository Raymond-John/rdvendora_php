<?php
/**
 * Lightweight .env loader (no Composer dependency).
 */
if (!function_exists('rdv_load_env')) {
    function rdv_load_env($path) {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if (
                (strlen($value) >= 2) &&
                (($value[0] === '"' && substr($value, -1) === '"') ||
                 ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
            }
            if (!isset($_ENV[$name])) {
                $_ENV[$name] = $value;
            }
            if (!isset($_SERVER[$name])) {
                $_SERVER[$name] = $value;
            }
        }
    }
}

if (!function_exists('rdv_env')) {
    function rdv_env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            if (array_key_exists($key, $_ENV)) {
                $value = $_ENV[$key];
            } elseif (array_key_exists($key, $_SERVER)) {
                $value = $_SERVER[$key];
            } else {
                return $default;
            }
        }

        $lower = strtolower((string) $value);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;
        if ($lower === 'empty') return '';

        return $value;
    }
}
