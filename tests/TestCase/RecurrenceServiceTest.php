<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Service\ChecklistService;
use App\Service\RecurrenceCalendar;
use App\Service\RecurrenceService;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class RecurrenceServiceTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Checklists', 'app.ChecklistSchedules', 'app.ChecklistOccurrences', 'app.ChecklistHistory'];
    private int $userId;

    public function setUp(): void
    {
        parent::setUp();
        $users = $this->getTableLocator()->get('Users');
        $user = $users->newEntity(['name' => 'Maria', 'email' => 'recurrence@example.test', 'password' => 'Recurrence-password-123']);
        $user->patch(['role' => 'ADMIN', 'active' => true], ['guard' => false]);
        $users->saveOrFail($user);
        $this->userId = $user->id;
    }

    private function checklist(string $start, int $interval = 15, string $status = 'ATIVO'): object
    {
        $checklists = $this->getTableLocator()->get('Checklists');
        $schedules = $this->getTableLocator()->get('ChecklistSchedules');
        $item = $checklists->newEmptyEntity();
        $schedule = $schedules->newEmptyEntity();
        $data = ['code' => 'TEST-' . bin2hex(random_bytes(4)), 'name' => 'Preventiva elétrica',
            'area' => 'Recepção', 'route' => 'Rota A', 'start_date' => $start, 'interval_days' => $interval];
        $this->assertTrue((new ChecklistService())->save($item, $schedule, $data, $this->userId));
        if ($status !== 'ATIVO') {
            $checklists->updateAll(['status' => $status], ['id' => $item->id]);
        }

        return $checklists->get($item->id, contain: ['CurrentSchedule']);
    }

    public function testAllDueCyclesPersistAndOldestRemainsOverdue(): void
    {
        $item = $this->checklist('2026-09-10');
        $service = new RecurrenceService();
        $view = $service->dashboard(new Date('2026-10-25'));
        $rows = $this->getTableLocator()->get('ChecklistOccurrences')->find()->orderBy(['scheduled_date' => 'ASC'])->all();
        $this->assertSame(
            ['2026-09-10', '2026-09-25', '2026-10-10', '2026-10-25'],
            $rows->map(fn($row) => $row->scheduled_date->format('Y-m-d'))->toList(),
        );
        $this->assertSame('2026-09-10', $view['rows'][0]['date']->format('Y-m-d'));
        $this->assertSame(-45, $view['rows'][0]['days']);
        $this->assertSame(4, $view['rows'][0]['pending']);
        $this->assertSame(1, $view['counts']['ATRASADO']);
        $this->assertSame(0, $view['counts']['HOJE']);
        $service->synchronize($item->id, new Date('2026-10-25'));
        $this->assertSame(4, $this->getTableLocator()->get('ChecklistOccurrences')->find()->count());
        $this->assertSame(1, $this->getTableLocator()->get('ChecklistHistory')->find()->count());
    }

    public function testTodayTomorrowSevenAndEightDaysAndPaused(): void
    {
        foreach (['2026-09-09', '2026-09-10', '2026-09-11', '2026-09-17', '2026-09-18'] as $date) {
            $this->checklist($date, 5);
        }
        $paused = $this->checklist('2020-01-01', 1, 'PAUSADO');
        $view = (new RecurrenceService())->dashboard(new Date('2026-09-10'));
        $this->assertSame(['ATRASADO' => 1, 'HOJE' => 1, 'PROXIMOS' => 2, 'EM_DIA' => 1, 'PAUSADO' => 1], $view['counts']);
        $this->assertSame([ -1, 0, 1, 7, 8, null ], array_column($view['rows'], 'days'));
        $this->assertSame(2, $this->getTableLocator()->get('ChecklistOccurrences')->find()->count());
        $this->assertSame(-1, $this->getTableLocator()->get('ChecklistSchedules')->get($paused->current_schedule->id)->materialized_cycle);
    }

    public function testAcknowledgedCycleDoesNotShiftOriginalSequence(): void
    {
        $item = $this->checklist('2026-09-10');
        $service = new RecurrenceService();
        $service->synchronize($item->id, new Date('2026-09-12'));
        $occurrences = $this->getTableLocator()->get('ChecklistOccurrences');
        // Preparação de cenário da Fase 3; não existe ação CIENTE na aplicação.
        $occurrences->updateAll(['status' => 'RECONHECIDA', 'acknowledged_by' => $this->userId,
            'acknowledged_at' => '2026-09-12 12:00:00'], ['checklist_schedule_id' => $item->current_schedule->id]);
        $view = $service->dashboard(new Date('2026-09-12'));
        $this->assertSame('2026-09-25', $view['rows'][0]['date']->format('Y-m-d'));
        $this->assertSame(0, $view['rows'][0]['pending']);
        $this->assertSame(1, $occurrences->find()->count());
        $view = $service->dashboard(new Date('2026-10-11'));
        $this->assertSame('2026-09-25', $view['rows'][0]['date']->format('Y-m-d'));
        $this->assertSame(2, $view['rows'][0]['pending']);
        $this->assertSame(1, $occurrences->find()->where(['status' => 'RECONHECIDA'])->count());
    }

    public function testMoreThanOneBatchAndResume(): void
    {
        $item = $this->checklist('2020-01-01', 1);
        $service = new RecurrenceService();
        $today = new Date('2022-12-31');
        $service->synchronize($item->id, $today);
        $count = (new RecurrenceCalendar())->dueCycle(new Date('2020-01-01'), 1, $today) + 1;
        $table = $this->getTableLocator()->get('ChecklistOccurrences');
        $this->assertSame($count, $table->find()->count());
        $service->synchronize($item->id, new Date('2023-01-01'));
        $this->assertSame($count + 1, $table->find()->count());
        $this->assertSame($count, $this->getTableLocator()->get('ChecklistSchedules')->get($item->current_schedule->id)->materialized_cycle);
    }

    public function testExistingRecognizedRecordIsPreservedWhileBackfilling(): void
    {
        $item = $this->checklist('2026-12-31');
        $table = $this->getTableLocator()->get('ChecklistOccurrences');
        $row = $table->newEmptyEntity();
        $row->patch(['checklist_schedule_id' => $item->current_schedule->id, 'scheduled_date' => new Date('2026-12-31'),
            'status' => 'RECONHECIDA', 'acknowledged_by' => $this->userId, 'acknowledged_at' => DateTime::now()], ['guard' => false]);
        $table->saveOrFail($row);
        $view = (new RecurrenceService())->dashboard(new Date('2027-01-30'));
        $this->assertSame(3, $table->find()->count());
        $this->assertSame('RECONHECIDA', $table->get($row->id)->status);
        $this->assertSame('2027-01-15', $view['rows'][0]['date']->format('Y-m-d'));
    }

    public function testStartedScheduleCannotBeRewrittenBeforeDashboardVisit(): void
    {
        $today = (new RecurrenceCalendar())->today();
        $item = $this->checklist($today->subDays(20)->format('Y-m-d'));
        $service = new ChecklistService();
        $this->assertFalse($service->save(
            $item,
            $item->current_schedule,
            ['start_date' => $today->addDays(15)->format('Y-m-d')],
            $this->userId,
        ));
        $fresh = $this->getTableLocator()->get('Checklists')->get($item->id, contain: ['CurrentSchedule']);
        $this->assertFalse($service->save($fresh, $fresh->current_schedule, ['interval_days' => 30], $this->userId));
        $fresh = $this->getTableLocator()->get('Checklists')->get($item->id, contain: ['CurrentSchedule']);
        $this->assertTrue($service->save($fresh, $fresh->current_schedule, ['notes' => 'Revisado'], $this->userId));
    }

    public function testDashboardUsesLocalClockAndPausedFilter(): void
    {
        $this->checklist('2026-12-31');
        $paused = $this->checklist('2026-01-01', 5, 'PAUSADO');
        $previous = DateTime::getTestNow();
        DateTime::setTestNow(new DateTime('2027-01-01 02:30:00', 'UTC'));
        try {
            $this->session(['Auth' => $this->userId]);
            $this->get('/');
            $this->assertResponseOk();
            $this->assertResponseContains('31/12/2026');
            $this->assertResponseContains('VENCE HOJE');
            $this->assertResponseNotContains($paused->code);
            $this->get('/?status=PAUSADO');
            $this->assertResponseOk();
            $this->assertResponseContains($paused->code);
            $this->assertResponseContains('Fora dos alertas');
        } finally {
            DateTime::setTestNow($previous);
        }
    }
}
