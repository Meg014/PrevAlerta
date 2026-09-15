<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

use App\Service\ChecklistService;
use App\Service\RecurrenceCalendar;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;

// Sempre usa a conexão de testes, nunca o banco operacional.
ConnectionManager::alias('test', 'default');
$users = TableRegistry::getTableLocator()->get('Users');
foreach (['ADMIN', 'USUARIO'] as $role) {
    $email = strtolower($role) . '@browser.example.test';
    if ($users->exists(['email' => $email])) {
        continue;
    }
    $user = $users->newEntity(['name' => 'Teste ' . $role, 'email' => $email, 'password' => 'Browser-test-password-123']);
    $user->patch(['role' => $role, 'active' => true], ['guard' => false]);
    $users->saveOrFail($user);
}
echo "Usuários de teste preparados.\n";

// Cenários operacionais exclusivos do banco de testes.
$today = (new RecurrenceCalendar())->today();
$checklists = TableRegistry::getTableLocator()->get('Checklists');
$schedules = TableRegistry::getTableLocator()->get('ChecklistSchedules');
$admin = $users->find()->where(['email' => 'admin@browser.example.test'])->firstOrFail();
foreach (['ATRASADO' => -31, 'HOJE' => 0, 'PROXIMOS' => 7, 'EM_DIA' => 8, 'PAUSADO' => -60] as $label => $offset) {
    $code = 'FASE2-' . $label;
    if ($checklists->exists(['code' => $code])) {
        continue;
    }
    $item = $checklists->newEmptyEntity();
    $schedule = $schedules->newEmptyEntity();
    $saved = (new ChecklistService())->save($item, $schedule, [
        'code' => $code, 'name' => 'Preventiva ' . $label, 'area' => 'Recepção', 'route' => 'Rota 02',
        'start_date' => $today->addDays($offset)->format('Y-m-d'), 'interval_days' => 15,
    ], $admin->id);
    if (!$saved) {
        throw new RuntimeException('Falha no cenário de navegador: ' . $label);
    }
    if ($label === 'PAUSADO') {
        $checklists->updateAll(['status' => 'PAUSADO'], ['id' => $item->id]);
    }
}
