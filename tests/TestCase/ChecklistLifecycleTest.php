<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Service\AcknowledgementService;
use App\Service\ChecklistLifecycleService;
use App\Service\ChecklistService;
use App\Service\RecurrenceService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ChecklistLifecycleTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Checklists', 'app.ChecklistSchedules', 'app.ChecklistOccurrences', 'app.ChecklistHistory'];
    private int $adminId;
    private int $userId;
    private ChecklistLifecycleService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new ChecklistLifecycleService();
        $users = $this->getTableLocator()->get('Users');
        foreach (['ADMIN', 'USUARIO'] as $role) {
            $user = $users->newEntity(['name' => 'João ' . $role, 'email' => $role . '@lifecycle.test', 'password' => 'Lifecycle-password-123']);
            $user->patch(['role' => $role, 'active' => true], ['guard' => false]);
            $users->saveOrFail($user);
            $role === 'ADMIN' ? $this->adminId = $user->id : $this->userId = $user->id;
        }
    }

    private function item(string $start = '2026-09-01', int $interval = 5): object
    {
        $table = $this->getTableLocator()->get('Checklists');
        $item = $table->newEmptyEntity();
        $schedule = $this->getTableLocator()->get('ChecklistSchedules')->newEmptyEntity();
        $this->assertTrue((new ChecklistService())->save($item, $schedule, [
            'code' => 'PAUSA-' . bin2hex(random_bytes(4)), 'name' => 'Preventiva', 'area' => 'Oficina',
            'start_date' => $start, 'interval_days' => $interval,
        ], $this->adminId));

        return $table->get($item->id, contain: ['CurrentSchedule']);
    }

    private function pause(object $item, string $utc = '2026-09-11 12:00:00'): void
    {
        $this->service->pause($item->id, $item->current_schedule->id, $this->adminId, 'Equipamento parado', new DateTime($utc, 'UTC'));
    }

    public function testPausePreservesDueCyclesAndSuppressesAllOperationalCards(): void
    {
        $item = $this->item();
        $todayItem = $this->item('2026-09-11');
        $before = (new RecurrenceService())->dashboard(new Date('2026-09-11'));
        $this->assertSame(1, $before['counts']['ATRASADO']);
        $this->assertSame(1, $before['counts']['HOJE']);
        $this->pause($item);
        $this->pause($todayItem);
        $saved = $this->getTableLocator()->get('Checklists')->get($item->id, contain: ['CurrentSchedule']);
        $this->assertSame('PAUSADO', $saved->status);
        $this->assertNull($saved->current_schedule);
        $this->assertSame($this->adminId, $saved->paused_by);
        $this->assertSame('Equipamento parado', $saved->pause_reason);
        $this->assertSame('2026-09-11 09:00:00', $saved->paused_at->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s'));
        $table = $this->getTableLocator()->get('ChecklistOccurrences');
        $beforeRows = $table->find()->orderBy(['id' => 'ASC'])->all()->toArray();
        $this->assertCount(4, $beforeRows);
        $view = (new RecurrenceService())->dashboard(new Date('2027-02-01'));
        $this->assertSame(['ATRASADO' => 0, 'HOJE' => 0, 'PROXIMOS' => 0, 'EM_DIA' => 0, 'PAUSADO' => 2], $view['counts']);
        $this->assertEquals($beforeRows, $table->find()->orderBy(['id' => 'ASC'])->all()->toArray());
        $audit = $this->getTableLocator()->get('ChecklistHistory')->find()->where(['action' => 'PAUSA', 'checklist_id' => $item->id])->firstOrFail();
        $this->assertSame($this->adminId, $audit->user_id);
        $this->assertSame('Equipamento parado', json_decode($audit->new_value, true)['reason']);
    }

    public function testPauseMaterializesMissingCyclesAtLocalMidnight(): void
    {
        $item = $this->item('2026-12-30', 1);
        $this->pause($item, '2027-01-01 02:59:59');
        $dates = $this->getTableLocator()->get('ChecklistOccurrences')->find()->orderBy(['scheduled_date' => 'ASC'])
            ->all()->map(fn($row) => $row->scheduled_date->format('Y-m-d'))->toList();
        $this->assertSame(['2026-12-30', '2026-12-31'], $dates);
        $closed = $this->getTableLocator()->get('ChecklistSchedules')->get($item->current_schedule->id);
        $this->assertSame(1, $closed->materialized_cycle);
        $this->assertSame('2027-01-01 02:59:59', $closed->ended_at->format('Y-m-d H:i:s'));
    }

    public static function restarts(): array
    {
        return [
            'exemplo solicitado' => ['2026-09-20', '2026-11-04', ['2026-09-20', '2026-10-05', '2026-10-20', '2026-11-04']],
            'virada de mês' => ['2026-09-30', '2026-10-15', ['2026-09-30', '2026-10-15']],
            'virada de ano' => ['2026-12-31', '2027-01-30', ['2026-12-31', '2027-01-15', '2027-01-30']],
            'data passada informada' => ['2026-09-01', '2026-09-16', ['2026-09-01', '2026-09-16']],
        ];
    }

    #[DataProvider('restarts')]
    public function testNewScheduleResetsProgressAndKeepsOldHistory(string $start, string $through, array $expected): void
    {
        $item = $this->item('2026-08-01', 15);
        $engine = new RecurrenceService();
        $engine->synchronize($item->id, new Date('2026-09-11'));
        $occurrences = $this->getTableLocator()->get('ChecklistOccurrences');
        $first = $occurrences->find()->firstOrFail();
        (new AcknowledgementService())->acknowledge($first->id, $this->userId, new DateTime('2026-09-11 10:00:00', 'UTC'));
        $this->pause($item);
        $oldOccurrences = $occurrences->find()->orderBy(['id' => 'ASC'])->all()->toArray();
        $history = $this->getTableLocator()->get('ChecklistHistory');
        $oldHistory = $history->find()->orderBy(['id' => 'ASC'])->all()->toArray();
        $schedules = $this->getTableLocator()->get('ChecklistSchedules');
        $oldSchedule = $schedules->get($item->current_schedule->id)->toArray();
        $this->service->reactivate($item->id, $item->current_schedule->id, $this->adminId, $start, new DateTime('2026-09-12 12:00:00', 'UTC'));
        $fresh = $this->getTableLocator()->get('Checklists')->get($item->id, contain: ['CurrentSchedule']);
        $this->assertSame('ATIVO', $fresh->status);
        $this->assertNull($fresh->paused_at);
        $this->assertNotSame($item->current_schedule->id, $fresh->current_schedule->id);
        $this->assertSame(-1, $fresh->current_schedule->materialized_cycle);
        $this->assertSame($start, $fresh->current_schedule->start_date->format('Y-m-d'));
        $view = $engine->dashboard(new Date($through));
        $this->assertSame($start, $view['rows'][0]['date']->format('Y-m-d'));
        $dates = $occurrences->find()->where(['checklist_schedule_id' => $fresh->current_schedule->id])
            ->orderBy(['scheduled_date' => 'ASC'])->all()->map(fn($row) => $row->scheduled_date->format('Y-m-d'))->toList();
        $this->assertSame($expected, $dates);
        $this->assertEquals($oldSchedule, $schedules->get($item->current_schedule->id)->toArray());
        $this->assertEquals($oldOccurrences, $occurrences->find()->where(['checklist_schedule_id' => $item->current_schedule->id])->orderBy(['id' => 'ASC'])->all()->toArray());
        $this->assertEquals($oldHistory, $history->find()->where(['action !=' => 'REATIVACAO'])->orderBy(['id' => 'ASC'])->all()->toArray());
        $audit = $history->find()->where(['action' => 'REATIVACAO'])->firstOrFail();
        $this->assertSame($this->adminId, $audit->user_id);
        $this->assertSame($start, json_decode($audit->new_value, true)['start_date']);
        $this->expectException(BadRequestException::class);
        $pendingOld = $occurrences->find()->where(['checklist_schedule_id' => $item->current_schedule->id, 'status' => 'PENDENTE'])->firstOrFail();
        (new AcknowledgementService())->acknowledge($pendingOld->id, $this->userId, new DateTime('2027-02-01', 'UTC'));
    }

    public static function invalidValues(): array
    {
        return [['PAUSA', ''], ['PAUSA', '   '], ['REATIVACAO', ''], ['REATIVACAO', '2026-02-30'], ['REATIVACAO', '15/09/2026']];
    }

    #[DataProvider('invalidValues')]
    public function testRequiredReasonAndStrictNewDate(string $action, string $value): void
    {
        $item = $this->item();
        if ($action === 'REATIVACAO') {
            $this->pause($item);
        }
        $before = $this->getTableLocator()->get('ChecklistHistory')->find()->count();
        try {
            $method = $action === 'PAUSA' ? 'pause' : 'reactivate';
            $this->service->$method($item->id, $item->current_schedule->id, $this->adminId, $value);
            $this->fail('Valor inválido aceito.');
        } catch (BadRequestException $e) {
            $this->assertSame($before, $this->getTableLocator()->get('ChecklistHistory')->find()->count());
        }
    }

    public function testDuplicatesAndStaleFormsCannotChangeNewSchedule(): void
    {
        $item = $this->item();
        $this->pause($item);
        try {
            $this->pause($item);
            $this->fail('Pausa duplicada aceita.');
        } catch (BadRequestException $e) {
            $this->assertSame(1, $this->getTableLocator()->get('ChecklistHistory')->find()->where(['action' => 'PAUSA'])->count());
        }
        $this->service->reactivate($item->id, $item->current_schedule->id, $this->adminId, '2026-09-20');
        foreach (['pause', 'reactivate'] as $method) {
            try {
                $this->service->$method($item->id, $item->current_schedule->id, $this->adminId, $method === 'pause' ? 'Duplicada' : '2026-10-01');
                $this->fail('Formulário antigo aceito.');
            } catch (BadRequestException $e) {
                $this->assertSame(2, $this->getTableLocator()->get('ChecklistSchedules')->find()->count());
            }
        }
        $fresh = $this->getTableLocator()->get('Checklists')->get($item->id, contain: ['CurrentSchedule']);
        $this->pause($fresh);
        $this->expectException(BadRequestException::class);
        $this->service->reactivate($item->id, $item->current_schedule->id, $this->adminId, '2026-10-01');
    }

    public static function actions(): array
    {
        return [['pause'], ['reactivate']];
    }

    #[DataProvider('actions')]
    public function testAuditFailureRollsBackEntireTransition(string $method): void
    {
        $item = $this->item();
        if ($method === 'reactivate') {
            $this->pause($item);
        }
        $tables = ['Checklists', 'ChecklistSchedules', 'ChecklistOccurrences', 'ChecklistHistory'];
        $before = [];
        foreach ($tables as $name) {
            $before[$name] = $this->getTableLocator()->get($name)->find()->all()->toArray();
        }
        $this->getTableLocator()->get('ChecklistHistory')->getEventManager()->on('Model.beforeSave', static function ($event): void {
            $event->stopPropagation();
        });
        try {
            $this->service->$method($item->id, $item->current_schedule->id, $this->adminId, $method === 'pause' ? 'Parado' : '2026-09-20');
            $this->fail('Falha de auditoria ignorada.');
        } catch (PersistenceFailedException $e) {
            foreach ($tables as $name) {
                $this->assertEquals($before[$name], $this->getTableLocator()->get($name)->find()->all()->toArray(), $name);
            }
        }
    }

    #[DataProvider('actions')]
    public function testUserCannotTransitionThroughHttpOrService(string $method): void
    {
        $item = $this->item();
        if ($method === 'reactivate') {
            $this->pause($item);
        }
        $path = '/checklists/' . $item->id . ($method === 'pause' ? '/pausar' : '/reativar');
        $this->session(['Auth' => $this->userId]);
        $this->get($path);
        $this->assertResponseCode(403);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post($path, ['schedule_id' => $item->current_schedule->id, 'reason' => 'Parado', 'start_date' => '2026-09-20']);
        $this->assertResponseCode(403);
        $this->expectException(ForbiddenException::class);
        $this->service->$method($item->id, $item->current_schedule->id, $this->userId, $method === 'pause' ? 'Parado' : '2026-09-20');
    }

    #[DataProvider('actions')]
    public function testConcurrentTransitionsProduceOneAuditAndOneSchedule(string $method): void
    {
        $item = $this->item();
        if ($method === 'reactivate') {
            $this->pause($item);
        }
        $connection = $this->getTableLocator()->get('Checklists')->getConnection();
        $processes = [];
        $readyFiles = [];
        $connection->begin();
        $connection->execute('SELECT id FROM checklists WHERE id = ? FOR UPDATE', [$item->id]);
        try {
            for ($i = 0; $i < 2; $i++) {
                $ready = TMP . 'lifecycle-ready-' . bin2hex(random_bytes(8));
                $readyFiles[] = $ready;
                $process = proc_open(
                    [PHP_BINARY, ROOT . '/tests/lifecycle_worker.php', (string)$item->id,
                    (string)$item->current_schedule->id, (string)$this->adminId, $method, $ready],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    ROOT,
                );
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }
            $deadline = microtime(true) + 10;
            while ((!file_exists($readyFiles[0]) || !file_exists($readyFiles[1])) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($readyFiles[0]);
            $this->assertFileExists($readyFiles[1]);
        } finally {
            $connection->commit();
        }
        $results = [];
        try {
            foreach ($processes as [$process, $pipes]) {
                $results[] = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $error);
            }
        } finally {
            foreach ($readyFiles as $ready) {
                if (file_exists($ready)) {
                    unlink($ready);
                }
            }
        }
        sort($results);
        $this->assertSame(['changed', 'duplicate'], $results);
        $this->assertSame(1, $this->getTableLocator()->get('ChecklistHistory')->find()
            ->where(['action' => $method === 'pause' ? 'PAUSA' : 'REATIVACAO'])->count());
        $this->assertSame($method === 'pause' ? 1 : 2, $this->getTableLocator()->get('ChecklistSchedules')->find()->count());
    }

    public function testStaleEditCannotRewriteClosedSchedule(): void
    {
        $item = $this->item('2027-01-01');
        $this->pause($item);
        $this->assertFalse((new ChecklistService())->save($item, $item->current_schedule, ['notes' => 'Formulário antigo'], $this->adminId));
        $this->service->reactivate($item->id, $item->current_schedule->id, $this->adminId, '2027-02-01');
        $this->assertFalse((new ChecklistService())->save($item, $item->current_schedule, ['start_date' => '2027-03-01'], $this->adminId));
        $this->assertSame('2027-01-01', $this->getTableLocator()->get('ChecklistSchedules')->get($item->current_schedule->id)->start_date->format('Y-m-d'));
    }

    public function testAdminHttpFlowAndReadonlyPausedEditing(): void
    {
        $item = $this->item();
        $this->session(['Auth' => $this->adminId]);
        $path = '/checklists/' . $item->id;
        $this->get($path . '/pausar');
        $this->assertResponseOk();
        $this->post($path . '/pausar', ['reason' => 'Parado', 'schedule_id' => $item->current_schedule->id]);
        $this->assertResponseCode(403);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post($path . '/pausar', ['reason' => '  ', 'schedule_id' => $item->current_schedule->id]);
        $this->assertResponseCode(422);
        $this->post($path . '/pausar', ['reason' => 'Equipamento parado', 'schedule_id' => $item->current_schedule->id]);
        $this->assertRedirect($path);
        $this->get('/?status=PAUSADO');
        $this->assertResponseOk();
        $this->assertResponseContains('Equipamento parado');
        $this->assertResponseContains('João ADMIN');
        $this->assertResponseContains('REATIVAR');
        $this->get($path . '/editar');
        $this->assertResponseOk();
        $paused = $this->getTableLocator()->get('Checklists')->get($item->id);
        $schedule = $this->getTableLocator()->get('ChecklistSchedules')->get($item->current_schedule->id);
        $this->assertTrue((new ChecklistService())->save($paused, $schedule, ['notes' => 'Revisado durante a pausa'], $this->adminId));
        $this->assertFalse((new ChecklistService())->save($paused, $schedule, ['start_date' => '2027-01-01'], $this->adminId));
        $this->post($path . '/reativar', ['start_date' => '2026-09-20', 'schedule_id' => $item->current_schedule->id]);
        $this->assertRedirect($path);
        $this->get($path . '/historico');
        $this->assertResponseOk();
        $this->assertResponseContains('Equipamento parado');
        $this->assertResponseContains('Nova data inicial: 20/09/2026');
        $this->get('/historico?action=REATIVACAO');
        $this->assertResponseOk();
        $this->assertResponseContains('20/09/2026');
    }
}
