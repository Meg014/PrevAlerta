<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Service\AcknowledgementService;
use App\Service\ChecklistService;
use App\Service\OccurrencePresentation;
use App\Service\RecurrenceCalendar;
use App\Service\RecurrenceService;
use Cake\Http\Exception\BadRequestException;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AcknowledgementTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Checklists', 'app.ChecklistSchedules', 'app.ChecklistOccurrences', 'app.ChecklistHistory'];
    private int $adminId;
    private int $userId;

    public function setUp(): void
    {
        parent::setUp();
        $users = $this->getTableLocator()->get('Users');
        foreach (['ADMIN' => 'João', 'USUARIO' => 'Maria'] as $role => $name) {
            $user = $users->newEntity(['name' => $name, 'email' => strtolower($role) . '@ack.test', 'password' => 'Ack-password-123']);
            $user->patch(['role' => $role, 'active' => true], ['guard' => false]);
            $users->saveOrFail($user);
            if ($role === 'ADMIN') {
                $this->adminId = $user->id;
            } else {
                $this->userId = $user->id;
            }
        }
    }

    private function checklist(string $start, int $interval, string $today): object
    {
        $checklists = $this->getTableLocator()->get('Checklists');
        $item = $checklists->newEmptyEntity();
        $schedule = $this->getTableLocator()->get('ChecklistSchedules')->newEmptyEntity();
        $this->assertTrue((new ChecklistService())->save($item, $schedule, [
            'code' => 'ACK-' . bin2hex(random_bytes(3)), 'name' => 'Preventiva elétrica', 'area' => 'Recepção',
            'start_date' => $start, 'interval_days' => $interval,
        ], $this->adminId));
        (new RecurrenceService())->synchronize($item->id, new Date($today));

        return $checklists->get($item->id, contain: ['CurrentSchedule']);
    }

    private function firstOccurrence(): object
    {
        return $this->getTableLocator()->get('ChecklistOccurrences')->find()->orderBy(['scheduled_date' => 'ASC'])->firstOrFail();
    }

    public static function acknowledgementDates(): array
    {
        return [
            'mesmo dia tarde' => ['2026-09-10', '2026-09-11 02:59:59', 0],
            'atraso um dia' => ['2026-09-10', '2026-09-11 03:00:00', 1],
            'atraso vários dias' => ['2026-09-10', '2026-09-14 12:00:00', 4],
            'virada de mês' => ['2026-09-30', '2026-10-01 12:00:00', 1],
            'virada de ano' => ['2026-12-31', '2027-01-02 12:00:00', 2],
            'ano novo UTC ainda dia anterior em SP' => ['2026-12-31', '2027-01-01 02:30:00', 0],
        ];
    }

    #[DataProvider('acknowledgementDates')]
    public function testAcknowledgementTimingAndActor(string $start, string $utc, int $delay): void
    {
        $instant = new DateTime($utc, 'UTC');
        $today = (new RecurrenceCalendar())->today($instant);
        $item = $this->checklist($start, 15, $today->format('Y-m-d'));
        $occurrence = $this->firstOccurrence();
        $this->assertTrue((new AcknowledgementService())->acknowledge($occurrence->id, $this->userId, $instant));
        $saved = $this->getTableLocator()->get('ChecklistOccurrences')->get($occurrence->id);
        $this->assertSame('RECONHECIDA', $saved->status);
        $this->assertSame($this->userId, $saved->acknowledged_by);
        $this->assertSame($utc, $saved->acknowledged_at->setTimezone('UTC')->format('Y-m-d H:i:s'));
        $this->assertSame($start, $saved->scheduled_date->format('Y-m-d'));
        $result = (new OccurrencePresentation())->describe($saved, new Date('2030-01-01'));
        $this->assertSame($delay, $result['days']);
        $this->assertSame($delay === 0 ? 'NO PRAZO' : 'ATRASADO', $result['label']);
        $audit = $this->getTableLocator()->get('ChecklistHistory')->find()->where(['action' => 'CIENTE'])->firstOrFail();
        $this->assertSame($saved->id, $audit->checklist_occurrence_id);
        $this->assertSame($this->userId, $audit->user_id);
        $this->assertSame($item->id, $audit->checklist_id);
        $this->assertSame($start, json_decode($audit->new_value, true)['scheduled_date']);
    }

    public function testLateAcknowledgementDoesNotMoveSchedule(): void
    {
        $item = $this->checklist('2026-09-10', 15, '2026-09-12');
        $before = $item->current_schedule->toArray();
        (new AcknowledgementService())->acknowledge(
            $this->firstOccurrence()->id,
            $this->userId,
            new DateTime('2026-09-12 12:00:00', 'UTC'),
        );
        $view = (new RecurrenceService())->dashboard(new Date('2026-09-12'));
        $this->assertSame('2026-09-25', $view['rows'][0]['date']->format('Y-m-d'));
        $this->assertSame(0, $view['counts']['ATRASADO']);
        $this->assertSame(1, $view['counts']['EM_DIA']);
        $this->assertEquals($before, $this->getTableLocator()->get('ChecklistSchedules')->get($item->current_schedule->id)->toArray());
    }

    public function testDuplicatePreservesFirstActorTimeAndOneAudit(): void
    {
        $this->checklist('2026-09-10', 15, '2026-09-12');
        $id = $this->firstOccurrence()->id;
        $service = new AcknowledgementService();
        $this->assertTrue($service->acknowledge($id, $this->userId, new DateTime('2026-09-12 12:00:00', 'UTC')));
        $this->assertFalse($service->acknowledge($id, $this->adminId, new DateTime('2026-09-13 12:00:00', 'UTC')));
        $saved = $this->getTableLocator()->get('ChecklistOccurrences')->get($id);
        $this->assertSame($this->userId, $saved->acknowledged_by);
        $this->assertSame('2026-09-12 12:00:00', $saved->acknowledged_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $this->getTableLocator()->get('ChecklistHistory')->find()->where(['action' => 'CIENTE'])->count());
    }

    public function testAccumulatedCyclesStaySeparateAndCanBeAcknowledgedIndividually(): void
    {
        $this->checklist('2026-09-01', 5, '2026-09-12');
        $table = $this->getTableLocator()->get('ChecklistOccurrences');
        $rows = $table->find()->orderBy(['scheduled_date' => 'ASC'])->all()->toList();
        $this->assertCount(3, $rows);
        $service = new AcknowledgementService();
        $instant = new DateTime('2026-09-12 12:00:00', 'UTC');
        $service->acknowledge($rows[0]->id, $this->userId, $instant);
        $this->assertSame(2, $table->find()->where(['status' => 'PENDENTE'])->count());
        $view = (new RecurrenceService())->dashboard(new Date('2026-09-12'));
        $this->assertSame('2026-09-06', $view['rows'][0]['date']->format('Y-m-d'));
        // Confirmar o ciclo mais recente também não quita a pendência intermediária.
        $service->acknowledge($rows[2]->id, $this->userId, $instant);
        $this->assertSame('PENDENTE', $table->get($rows[1]->id)->status);
        $service->acknowledge($rows[1]->id, $this->userId, $instant);
        $view = (new RecurrenceService())->dashboard(new Date('2026-09-12'));
        $this->assertSame('2026-09-16', $view['rows'][0]['date']->format('Y-m-d'));
        $this->assertSame(0, $view['rows'][0]['pending']);
    }

    public function testAuditFailureRollsBackAcknowledgement(): void
    {
        $this->checklist('2026-09-10', 15, '2026-09-12');
        $id = $this->firstOccurrence()->id;
        $history = $this->getTableLocator()->get('ChecklistHistory');
        $history->getEventManager()->on('Model.beforeSave', static function ($event): void {
            $event->stopPropagation();
        });
        try {
            (new AcknowledgementService())->acknowledge($id, $this->userId, new DateTime('2026-09-12 12:00:00', 'UTC'));
            $this->fail('A falha de auditoria precisa desfazer o CIENTE.');
        } catch (PersistenceFailedException $e) {
            $saved = $this->getTableLocator()->get('ChecklistOccurrences')->get($id);
            $this->assertSame('PENDENTE', $saved->status);
            $this->assertNull($saved->acknowledged_at);
            $this->assertNull($saved->acknowledged_by);
        }
    }

    public function testFutureOccurrenceCannotBeAcknowledged(): void
    {
        $this->checklist('2026-09-10', 15, '2026-09-10');
        $this->expectException(BadRequestException::class);
        (new AcknowledgementService())->acknowledge(
            $this->firstOccurrence()->id,
            $this->userId,
            new DateTime('2026-09-10 02:59:59', 'UTC'),
        );
    }

    public function testPausedChecklistCannotBeAcknowledged(): void
    {
        $item = $this->checklist('2026-09-10', 15, '2026-09-10');
        $this->getTableLocator()->get('Checklists')->updateAll(['status' => 'PAUSADO'], ['id' => $item->id]);
        $this->expectException(BadRequestException::class);
        (new AcknowledgementService())->acknowledge(
            $this->firstOccurrence()->id,
            $this->userId,
            new DateTime('2026-09-10 12:00:00', 'UTC'),
        );
    }

    public function testHttpAcknowledgementRefreshesDashboardAndHistory(): void
    {
        $item = $this->checklist('2026-09-10', 15, '2026-09-10');
        $id = $this->firstOccurrence()->id;
        $previous = DateTime::getTestNow();
        DateTime::setTestNow(new DateTime('2026-09-10 20:00:00', 'UTC'));
        try {
            $this->session(['Auth' => $this->userId]);
            $this->get('/');
            $this->assertResponseOk();
            $this->assertResponseContains('data-occurrence="' . $id . '"');
            $this->get('/ocorrencias/' . $id . '/confirmar');
            $this->assertResponseOk();
            $this->assertResponseContains('Confirma que o aviso deste checklist foi tratado?');
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->post('/ocorrencias/' . $id . '/ciente', ['confirmed' => '1', 'acknowledged_by' => $this->adminId,
                'acknowledged_at' => '2000-01-01', 'return_to' => 'https://example.org']);
            $this->assertRedirect('/');
            $saved = $this->getTableLocator()->get('ChecklistOccurrences')->get($id);
            $this->assertSame($this->userId, $saved->acknowledged_by);
            $this->assertSame('2026-09-10 20:00:00', $saved->acknowledged_at->format('Y-m-d H:i:s'));
            $this->get('/');
            $this->assertResponseOk();
            $this->assertResponseNotContains('data-occurrence="' . $id . '"');
            $this->assertResponseContains('25/09/2026');
            $this->get('/checklists/' . $item->id . '/historico');
            $this->assertResponseOk();
            $this->assertResponseContains('NO PRAZO');
            $this->assertResponseContains('10/09/2026 17:00:00');
            $this->assertResponseContains('Maria');
            $this->get('/historico?action=CIENTE');
            $this->assertResponseOk();
            $this->assertResponseContains('marcada como ciente por Maria');
        } finally {
            DateTime::setTestNow($previous);
        }
    }

    public function testRequiresAuthenticationCsrfConfirmationAndPost(): void
    {
        $this->checklist('2026-09-10', 15, '2026-09-10');
        $id = $this->firstOccurrence()->id;
        $this->get('/ocorrencias/' . $id . '/confirmar');
        $this->assertRedirectContains('/login');
        $this->session(['Auth' => $this->userId]);
        $this->post('/ocorrencias/' . $id . '/ciente', ['confirmed' => '1']);
        $this->assertResponseCode(403);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/ocorrencias/' . $id . '/ciente', []);
        $this->assertResponseCode(400);
        $this->get('/ocorrencias/' . $id . '/ciente');
        $this->assertResponseCode(404);
        $this->assertSame('PENDENTE', $this->getTableLocator()->get('ChecklistOccurrences')->get($id)->status);
    }

    public function testNoInterfaceCanDeleteRecognizedOccurrence(): void
    {
        $this->checklist('2026-09-10', 15, '2026-09-10');
        $id = $this->firstOccurrence()->id;
        (new AcknowledgementService())->acknowledge($id, $this->userId, new DateTime('2026-09-10 12:00:00', 'UTC'));
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        foreach ([$this->userId, $this->adminId] as $actor) {
            $this->session(['Auth' => $actor]);
            $this->delete('/ocorrencias/' . $id);
            $this->assertResponseCode(404);
            $this->delete('/historico/' . $id);
            $this->assertResponseCode(404);
        }
        $this->assertSame('RECONHECIDA', $this->getTableLocator()->get('ChecklistOccurrences')->get($id)->status);
    }

    public function testConcurrentRequestsProduceOnlyOneAcknowledgement(): void
    {
        $item = $this->checklist('2026-09-10', 15, '2026-09-12');
        $id = $this->firstOccurrence()->id;
        $connection = $this->getTableLocator()->get('Checklists')->getConnection();
        $processes = [];
        $readyFiles = [];
        $connection->begin();
        $connection->execute('SELECT id FROM checklists WHERE id = ? FOR UPDATE', [$item->id]);
        try {
            foreach ([$this->userId, $this->adminId] as $actor) {
                $ready = TMP . 'ack-ready-' . bin2hex(random_bytes(8));
                $readyFiles[] = $ready;
                $process = proc_open(
                    [PHP_BINARY, ROOT . '/tests/acknowledgement_worker.php', (string)$id,
                    (string)$actor, '2026-09-12 12:00:00', $ready],
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
        $audits = $this->getTableLocator()->get('ChecklistHistory')->find()->where(['action' => 'CIENTE']);
        $this->assertSame(1, $audits->count());
        $this->assertSame(
            $this->getTableLocator()->get('ChecklistOccurrences')->get($id)->acknowledged_by,
            $audits->firstOrFail()->user_id,
        );
    }
}
