<?php
// Fournit une fonction db() qui retourne une instance PDO réutilisable.
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $baseConfig = __DIR__ . '/../config/config.php';
        $localConfig = __DIR__ . '/../config/config.local.php';
        $config = file_exists($localConfig) ? require $localConfig : require $baseConfig;

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['db']['host'],
            $config['db']['port'],
            $config['db']['name'],
            $config['db']['charset']
        );

        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}
