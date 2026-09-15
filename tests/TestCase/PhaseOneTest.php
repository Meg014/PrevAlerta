<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Service\ChecklistService;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class PhaseOneTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Checklists', 'app.ChecklistSchedules', 'app.ChecklistOccurrences', 'app.ChecklistHistory'];
    private int $adminId;
    private int $userId;

    public function setUp(): void
    {
        parent::setUp();
        $users = $this->getTableLocator()->get('Users');
        foreach (['ADMIN', 'USUARIO'] as $role) {
            $user = $users->newEntity(['name' => $role, 'email' => strtolower($role) . '@example.test', 'password' => 'A-test-password-123']);
            $user->patch(['role' => $role, 'active' => true], ['guard' => false]);
            $users->saveOrFail($user);
            if ($role === 'ADMIN') {
                $this->adminId = $user->id;
            } else {
                $this->userId = $user->id;
            }
        }
    }

    private function data(array $overrides = []): array
    {
        return $overrides + ['code' => 'ELE-001', 'name' => 'Preventiva elétrica', 'area' => 'Recepção', 'interval_days' => '15', 'start_date' => '2026-09-10'];
    }

    private function save(array $data): array
    {
        $checklist = $this->getTableLocator()->get('Checklists')->newEmptyEntity();
        $schedule = $this->getTableLocator()->get('ChecklistSchedules')->newEmptyEntity();
        $saved = (new ChecklistService())->save($checklist, $schedule, $data, $this->adminId);

        return [$saved, $checklist, $schedule];
    }

    public function testAnonymousMustLogin(): void
    {
        $this->get('/checklists');
        $this->assertRedirectContains('/login');
        $this->get('/login');
        $this->assertResponseOk();
        $this->assertResponseContains('Bem-vindo de volta');
    }

    public function testLoginAndLogout(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'A-test-password-123']);
        $this->assertRedirect('/');
        $this->assertSession($this->adminId, 'Auth');
        $this->session(['Auth' => $this->adminId]);
        $this->get('/');
        $this->assertResponseOk();
        $this->assertResponseContains('Painel de alertas');
        $this->post('/logout');
        $this->assertRedirect('/login');
        $this->assertSession(null, 'Auth');
    }

    public function testWrongPasswordAndInactiveUserCannotLogin(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'incorrect']);
        $this->assertResponseOk();
        $this->assertSession(null, 'Auth');
        $this->getTableLocator()->get('Users')->updateAll(['active' => false], ['id' => $this->userId]);
        $this->post('/login', ['email' => 'usuario@example.test', 'password' => 'A-test-password-123']);
        $this->assertSession(null, 'Auth');
    }

    public function testUserCanReadButCannotWriteOrManageUsers(): void
    {
        [, $item] = $this->save($this->data());
        $this->session(['Auth' => $this->userId]);
        $this->get('/checklists');
        $this->assertResponseOk();
        $this->assertResponseNotContains('Novo checklist');
        $this->get('/checklists/' . $item->id);
        $this->assertResponseOk();
        $this->get('/checklists/novo');
        $this->assertResponseCode(403);
        $this->get('/usuarios');
        $this->assertResponseCode(403);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/checklists/' . $item->id . '/editar', $this->data(['name' => 'Invadido']));
        $this->assertResponseCode(403);
        $this->assertSame('Preventiva elétrica', $this->getTableLocator()->get('Checklists')->get($item->id)->name);
    }

    public function testSessionRefreshesPermissions(): void
    {
        $this->session(['Auth' => $this->adminId]);
        $this->getTableLocator()->get('Users')->updateAll(['role' => 'USUARIO'], ['id' => $this->adminId]);
        $this->get('/usuarios');
        $this->assertResponseCode(403);
    }

    public function testCsrfIsRequired(): void
    {
        $this->session(['Auth' => $this->adminId]);
        $this->post('/checklists/novo', $this->data(['start_date' => '2099-09-10']));
        $this->assertResponseCode(403);
    }

    public function testCreateAndEditThroughHttp(): void
    {
        $this->session(['Auth' => $this->adminId]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->get('/checklists/novo');
        $this->assertResponseOk();
        $this->post('/checklists/novo', $this->data(['start_date' => '2099-09-10']));
        $this->assertResponseCode(302);
        $item = $this->getTableLocator()->get('Checklists')->find()->firstOrFail();
        $this->get('/checklists/' . $item->id . '/editar');
        $this->assertResponseOk();
        $this->post('/checklists/' . $item->id . '/editar', $this->data(['start_date' => '2099-09-20', 'name' => 'Novo nome']));
        $this->assertRedirect('/checklists/' . $item->id);
        $this->assertSame(3, $this->getTableLocator()->get('ChecklistHistory')->find()->count());
        $this->get('/checklists/' . $item->id);
        $this->assertResponseOk();
        $this->assertResponseContains('20/09/2099');
    }

    public function testInvalidIntervalsAndDatesNeverPersist(): void
    {
        foreach (['0', '-5', '1.5', 'abc', '', '2147483648'] as $interval) {
            [$saved, , $schedule] = $this->save($this->data(['interval_days' => $interval]));
            $this->assertFalse($saved, 'Interval: ' . $interval);
            $this->assertNotEmpty($schedule->getError('interval_days'));
        }
        [$saved] = $this->save($this->data(['start_date' => '2026-02-30']));
        $this->assertFalse($saved);
        [$saved] = $this->save($this->data(['name' => '  ']));
        $this->assertFalse($saved);
        $this->assertSame(0, $this->getTableLocator()->get('Checklists')->find()->count());
        $this->assertSame(0, $this->getTableLocator()->get('ChecklistHistory')->find()->count());
    }

    public function testDuplicateCodeAndForgedStatus(): void
    {
        [$saved, $item] = $this->save($this->data(['status' => 'PAUSADO', 'id' => 999]));
        $this->assertTrue($saved);
        $this->assertSame('ATIVO', $item->status);
        $this->assertNotSame(999, $item->id);
        [$saved, $duplicate] = $this->save($this->data());
        $this->assertFalse($saved);
        $this->assertNotEmpty($duplicate->getError('code'));
        $this->assertSame(1, $this->getTableLocator()->get('ChecklistSchedules')->find()->count());
        $this->assertSame(1, $this->getTableLocator()->get('ChecklistHistory')->find()->count());
    }

    public function testAuditFailureRollsBackChecklistAndSchedule(): void
    {
        $history = $this->getTableLocator()->get('ChecklistHistory');
        $history->getEventManager()->on('Model.beforeSave', function ($event): void {
            $event->stopPropagation();
        });
        try {
            $this->save($this->data());
            $this->fail('Expected failed audit.');
        } catch (PersistenceFailedException $e) {
            $this->assertSame(0, $this->getTableLocator()->get('Checklists')->find()->count());
            $this->assertSame(0, $this->getTableLocator()->get('ChecklistSchedules')->find()->count());
        }
    }

    public function testPasswordsAreHashedAndHidden(): void
    {
        $user = $this->getTableLocator()->get('Users')->get($this->adminId);
        $this->assertTrue((new DefaultPasswordHasher())->check('A-test-password-123', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }
}
