<?php
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

function requireEnv(array $keys): void
{
    foreach ($keys as $key) {
        if (empty($_ENV[$key])) {
            throw new Exception("Missing env variable: {$key}");
        }
    }
}

loadEnv(__DIR__ . '/../.env');

requireEnv([
    'FTP_HOST',
    'FTP_USER',
    'FTP_PASS',
    'FTP_PATH',
    'GITHUB_TOKEN',
    'GITHUB_REPO',
]);

return [

    'app' => [
        'env'   => $_ENV['APP_ENV'] ?? 'production',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    ],

    'ftp' => [
        'host'    => $_ENV['FTP_HOST'],
        'port'    => isset($_ENV['FTP_PORT']) ? (int)$_ENV['FTP_PORT'] : 21,
        'user'    => $_ENV['FTP_USER'],
        'pass'    => $_ENV['FTP_PASS'],
        'path'    => rtrim($_ENV['FTP_PATH'], '/') . '/',
        'timeout' => isset($_ENV['FTP_TIMEOUT']) ? (int)$_ENV['FTP_TIMEOUT'] : 30,
        'ssl'     => true,
    ],

    'github' => [
        'token'  => $_ENV['GITHUB_TOKEN'],
        'repo'   => $_ENV['GITHUB_REPO'],
        'branch' => $_ENV['GITHUB_BRANCH'] ?? 'main',
    ],

];