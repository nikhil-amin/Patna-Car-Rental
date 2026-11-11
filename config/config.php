<?php
declare(strict_types=1);

/** Load KEY=VALUE pairs from .env (no external dependency). */
function env_load(string $file): array
{
    $vars = [];
    if (!is_readable($file)) {
        return $vars;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $v = trim(preg_replace('/\s+#.*$/', '', $v), " \t\"'");
        $vars[trim($k)] = $v;
    }
    return $vars;
}

/** config('DB_HOST', 'default'). Real environment variables override .env. */
function config(string $key, mixed $default = null): mixed
{
    static $cache = null;
    $cache ??= env_load(dirname(__DIR__) . '/.env');
    $env = getenv($key);
    return $env !== false ? $env : ($cache[$key] ?? $default);
}
