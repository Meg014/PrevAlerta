<?php
declare(strict_types=1);

use App\Service\AcknowledgementService;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
// Processo auxiliar exclusivo do banco de testes, nunca da conexão operacional.
ConnectionManager::alias('test', 'default');
file_put_contents($argv[4], 'ready');
$changed = (new AcknowledgementService())->acknowledge((int)$argv[1], (int)$argv[2], new DateTime($argv[3], 'UTC'));
echo $changed ? 'changed' : 'duplicate';
