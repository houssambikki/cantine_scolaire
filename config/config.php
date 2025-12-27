<?php
// Configuration publique (sans secrets). Utilisez config.local.php pour vos valeurs locales.
return [
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'cantine_scolaire',
        'user' => 'root',
        'pass' => '', // rester vide dans le repo public
        'charset' => 'utf8mb4',
    ],
];
