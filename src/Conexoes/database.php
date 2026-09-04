<?php
declare(strict_types=1);

/** auuuuu Abre a conexão MySQL usada pelas consultas de unidades de saúde. */
function conectarBanco(): PDO
{
    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException(
            'O driver PDO MySQL não está instalado. Instale a extensão php-mysql e reinicie o servidor PHP.'
        );
    }

    $config = carregarConfiguracaoBanco(dirname(__DIR__, 2) . '/.env');
    $host = $config['DB_HOST'] ?? '';
    $port = $config['DB_PORT'] ?? '3306';
    $database = $config['DB_DATABASE'] ?? '';
    $username = $config['DB_USERNAME'] ?? '';
    $password = $config['DB_PASSWORD'] ?? '';

    if ($host === '' || $database === '' || $username === '' || $password === '') {
        throw new RuntimeException('Configure DB_HOST, DB_DATABASE, DB_USERNAME e DB_PASSWORD no arquivo .env.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function carregarConfiguracaoBanco(string $path): array
{
    $config = [];
    $keys = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    foreach ($keys as $key) {
        $value = getenv($key);
        if (is_string($value) && $value !== '') {
            $config[$key] = $value;
        }
    }

    if (!is_readable($path)) return $config;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if (!in_array($key, $keys, true) || isset($config[$key])) continue;
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        $config[$key] = $value;
    }
    return $config;
}
