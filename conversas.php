<?php
declare(strict_types=1);

const STATE_FILE = __DIR__ . '/conversas.json';

function loadStates(): array
{
    if (!is_file(STATE_FILE)) return [];
    $states = json_decode((string) file_get_contents(STATE_FILE), true);
    return is_array($states) ? $states : [];
}

function saveStates(array $states): void
{
    $limit = time() - 86400 * 7;
    $states = array_filter($states, static fn(array $state): bool => ($state['updated_at'] ?? 0) >= $limit);
    file_put_contents(STATE_FILE, json_encode($states, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
