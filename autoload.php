<?php

/**
 * Simple project autoloader.
 *
 * - Maps simple class names to files under `php/request/{ClassName}.php`.
 * - No external dependencies required. When/if Composer is adopted this
 *   should be replaced with PSR-4 autoloading.
 */
spl_autoload_register(function (string $class) : void {
    // Only support local, simple class names (no namespaces) for now.
    $file = __DIR__ . '/request/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Small .env loader (no dependency) — optional, loads __DIR__/.env if present.
// Rules: lines with `#` at line start are comments, blank lines ignored.
// KEY=VALUE pairs are supported; values may be quoted with single or double quotes.
// Existing environment variables are not overwritten by the .env file.
function load_dotenv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = preg_split("/\r\n|\n|\r/", file_get_contents($path));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // Remove surrounding quotes
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        // Do not overwrite existing env vars
        if (getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) {
            continue;
        }
        putenv("$key=$val");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

/**
 * Detect APP_ENV and load the appropriate dotenv file.
 *
 * Priority:
 * 1. If APP_ENV is already provided via environment (getenv/$_ENV/$_SERVER or defined()), respect it.
 * 2. Otherwise, if a `.env.local` file exists in the project root, set APP_ENV=local and load it.
 * 3. Otherwise set APP_ENV=production and load `.env` (if present).
 */
function detect_and_load_env(): void
{
    // Helper to find an existing APP_ENV from several places
    $env = false;
    if (defined('APP_ENV')) {
        $env = APP_ENV;
    } else {
        $env = getenv('APP_ENV');
        if ($env === false && array_key_exists('APP_ENV', $_ENV)) {
            $env = $_ENV['APP_ENV'];
        }
        if ($env === false && array_key_exists('APP_ENV', $_SERVER)) {
            $env = $_SERVER['APP_ENV'];
        }
    }

    $envLocalPath = __DIR__ . '/.env.local';
    $envPath = __DIR__ . '/.env';

    if ($env !== false && $env !== null && $env !== '') {
        $env = strtolower((string) $env);
        // define APP_ENV constant for scripts that check it
        if (!defined('APP_ENV')) {
            define('APP_ENV', $env);
        }
        // load matching dotenv file if present
        if ($env === 'local' && is_readable($envLocalPath)) {
            load_dotenv($envLocalPath);
            return;
        }
        // default to .env for production/other values
        load_dotenv($envPath);
        return;
    }

    // APP_ENV not provided externally: choose based on presence of .env.local
    if (is_readable($envLocalPath)) {
        // prefer local for development
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
        if (!defined('APP_ENV')) {
            define('APP_ENV', 'local');
        }
        load_dotenv($envLocalPath);
        return;
    }

    // Fallback to production
    putenv('APP_ENV=production');
    $_ENV['APP_ENV'] = 'production';
    $_SERVER['APP_ENV'] = 'production';
    if (!defined('APP_ENV')) {
        define('APP_ENV', 'production');
    }
    load_dotenv($envPath);
}

// Run detection and loading now
detect_and_load_env();

// Optionally expose a small helper for manual loading in scripts/tests.
function project_autoload_register(): void
{
    // noop: autoloader already registered above. Provided for symmetry.
}

return; // make include/require_once a no-op return value
