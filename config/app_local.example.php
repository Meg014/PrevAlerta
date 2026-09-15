<?php
use function Cake\Core\env;

// Copie para app_local.php e configure credenciais exclusivas do ambiente.
return [
    'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'Security' => ['salt' => env('SECURITY_SALT', '__SALT__')],
    'App' => ['fullBaseUrl' => env('APP_FULL_BASE_URL', 'http://127.0.0.1:8765')],
    'Datasources' => [
        'default' => [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int)env('DB_PORT', 3306),
            'username' => env('DB_USER', 'prev_agenda'),
            'password' => env('DB_PASSWORD', ''),
            'database' => env('DB_NAME', 'prev_agenda'),
            'url' => env('DATABASE_URL', null),
        ],
        'test' => [
            'host' => env('DB_TEST_HOST', '127.0.0.1'),
            'port' => (int)env('DB_TEST_PORT', 3306),
            'username' => env('DB_TEST_USER', 'prev_agenda_test'),
            'password' => env('DB_TEST_PASSWORD', ''),
            'database' => env('DB_TEST_NAME', 'test_prev_agenda'),
            'url' => env('DATABASE_TEST_URL', null),
        ],
    ],
];
