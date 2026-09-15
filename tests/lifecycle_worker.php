<?php
declare(strict_types=1);

use App\Service\ChecklistLifecycleService;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Exception\BadRequestException;
use Cake\I18n\DateTime;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
ConnectionManager::alias('test', 'default');
file_put_contents($argv[5], 'ready');
try {
    $method = $argv[4];
    (new ChecklistLifecycleService())->$method(
        (int)$argv[1],
        (int)$argv[2],
        (int)$argv[3],
        $method === 'pause' ? 'Equipamento parado' : '2026-09-20',
        new DateTime('2026-09-11 12:00:00', 'UTC'),
    );
    echo 'changed';
} catch (BadRequestException $e) {
    echo 'duplicate';
}
