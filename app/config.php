<?php
// Lê e carrega o arquivo .env
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, '"\'');
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Constantes da aplicação
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_DATABASE', getenv('DB_DATABASE') ?: 'transcritor');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('STORAGE_PATH', __DIR__ . '/../' . (getenv('STORAGE_PATH') ?: 'storage'));

// Garante a existência dos diretórios de storage
$directories = [
    STORAGE_PATH,
    STORAGE_PATH . '/uploads',
    STORAGE_PATH . '/audio',
    STORAGE_PATH . '/transcriptions',
    STORAGE_PATH . '/logs',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
