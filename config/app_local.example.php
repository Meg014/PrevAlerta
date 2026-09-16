<?php
use Cake\Database\Driver\Postgres;
use function Cake\Core\env;

// Copie para app_local.php e configure credenciais exclusivas do ambiente.
return [
    'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'Security' => ['salt' => env('SECURITY_SALT', '__SALT__')],
    'App' => ['fullBaseUrl' => env('APP_FULL_BASE_URL', 'http://127.0.0.1:8765')],
    'Datasources' => [
        'default' => [
            'driver' => Postgres::class,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int)env('DB_PORT', 5432),
            'username' => env('DB_USER', 'pcm'),
            'password' => env('DB_PASSWORD', 'DEFINIR_NO_SERVIDOR'),
            'database' => env('DB_NAME', 'prev_alerta'),
            'encoding' => 'utf8',
            'schema' => env('DB_SCHEMA', 'public'),
            'url' => env('DATABASE_URL', null),
        ],
        'test' => [
            'driver' => Postgres::class,
            'host' => env('DB_TEST_HOST', '127.0.0.1'),
            'port' => (int)env('DB_TEST_PORT', 5432),
            'username' => env('DB_TEST_USER', 'pcm_test'),
            'password' => env('DB_TEST_PASSWORD', 'DEFINIR_APENAS_PARA_TESTES'),
            'database' => env('DB_TEST_NAME', 'test_prev_alerta'),
            'encoding' => 'utf8',
            'schema' => env('DB_TEST_SCHEMA', 'public'),
            'url' => env('DATABASE_TEST_URL', null),
        ],
    ],
];
