<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Service\AcknowledgementService;
use App\Service\ChecklistLifecycleService;
use App\Service\ChecklistService;
use App\Service\RecurrenceCalendar;
use App\Service\RecurrenceService;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class LocalAlertSummaryCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Checklists', 'app.ChecklistSchedules', 'app.ChecklistOccurrences', 'app.ChecklistHistory'];

    public function testEmptySummaryDoesNotChangeData(): void
    {
        $before = $this->snapshot();
        $this->exec('local_alert_summary');
        $this->assertExitSuccess();
        $this->assertOutputContains('{"overdue":0,"today":0}');
        $this->assertSame($before, $this->snapshot());
    }

    public function testMatchesDashboardWithoutPersistingOrAcknowledgingOccurrences(): void
    {
        $users = $this->getTableLocator()->get('Users');
        $user = $users->newEntity(['name' => 'Operador', 'email' => 'local@example.test', 'password' => 'Local-password-123']);
        $user->patch(['role' => 'ADMIN', 'active' => true], ['guard' => false]);
        $users->saveOrFail($user);
        $checklists = $this->getTableLocator()->get('Checklists');
        $schedules = $this->getTableLocator()->get('ChecklistSchedules');
        $occurrences = $this->getTableLocator()->get('ChecklistOccurrences');
        $today = (new RecurrenceCalendar())->today();
        foreach (['LATE' => -10, 'TODAY' => 0, 'PAUSED' => -20, 'REACTIVATED' => -20, 'ACK' => 0] as $code => $days) {
            $item = $checklists->newEmptyEntity();
            $schedule = $schedules->newEmptyEntity();
            $this->assertTrue((new ChecklistService())->save($item, $schedule, [
                'code' => $code, 'name' => $code, 'area' => 'Teste',
                'start_date' => $today->addDays($days)->format('Y-m-d'), 'interval_days' => 5,
            ], $user->id));
            if (in_array($code, ['PAUSED', 'REACTIVATED'], true)) {
                $lifecycle = new ChecklistLifecycleService();
                $lifecycle->pause($item->id, $schedule->id, $user->id, 'Teste local');
                if ($code === 'REACTIVATED') {
                    $lifecycle->reactivate($item->id, $schedule->id, $user->id, $today->format('Y-m-d'));
                }
            }
            if ($code === 'ACK') {
                (new RecurrenceService())->synchronize($item->id, $today);
                $occurrence = $occurrences->find()->where(['checklist_schedule_id' => $schedule->id])->firstOrFail();
                (new AcknowledgementService())->acknowledge($occurrence->id, $user->id);
            }
        }
        $before = $this->snapshot();
        $this->exec('local_alert_summary');
        $this->assertExitSuccess();
        $this->assertOutputContains('{"overdue":1,"today":2}');
        $this->assertSame($before, $this->snapshot(), 'O aviso deve preservar todos os registros, inclusive cursores e auditoria.');
        $this->exec('local_alert_summary');
        $this->assertExitSuccess();
        $this->assertSame($before, $this->snapshot(), 'Repetir a consulta também não persiste a sincronização.');
        $dashboard = (new RecurrenceService())->dashboard();
        $this->assertSame(1, $dashboard['counts']['ATRASADO']);
        $this->assertSame(2, $dashboard['counts']['HOJE']);
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['Users', 'Checklists', 'ChecklistSchedules', 'ChecklistOccurrences', 'ChecklistHistory'] as $table) {
            $instance = $this->getTableLocator()->get($table);
            $result[$table] = $instance->getConnection()->execute(
                'SELECT * FROM ' . $instance->getTable() . ' ORDER BY id',
            )->fetchAll('assoc');
        }

        return $result;
    }
}
