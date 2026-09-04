<?php
declare(strict_types=1);

function loadEnvFile(string $path): array
{
    if (!is_readable($path)) return [];

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') continue;

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }

    return $values;
}

function configValue(string|array $keys, array $localEnv): string
{
    foreach ((array) $keys as $key) {
        $serverValue = getenv($key);
        if (is_string($serverValue) && $serverValue !== '') return $serverValue;
        if (isset($localEnv[$key]) && $localEnv[$key] !== '') return (string) $localEnv[$key];
    }

    return '';
}
