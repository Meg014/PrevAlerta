<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Authenticator\LocalAuthenticator;
use App\Service\RecurrenceCalendar;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class LocalAccessTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Checklists', 'app.ChecklistSchedules', 'app.ChecklistOccurrences', 'app.ChecklistHistory'];

    public function setUp(): void
    {
        parent::setUp();
        Configure::write('LocalAccess.enabled', true);
        $this->configRequest(['environment' => ['REMOTE_ADDR' => '127.0.0.1']]);
    }

    public function tearDown(): void
    {
        Configure::write('LocalAccess.enabled', false);
        parent::tearDown();
    }

    public function testDirectAccessAndAuditUsePcmWithoutManualAuthentication(): void
    {
        $this->get('/');
        $this->assertResponseOk();
        $this->assertResponseContains('Painel de alertas');
        $this->assertResponseNotContains('aria-label="Sair"');
        $users = $this->getTableLocator()->get('Users');
        $pcm = $users->find()->where(['email' => LocalAuthenticator::EMAIL])->firstOrFail();
        $this->assertSame('PCM', $pcm->name);
        $this->assertSame('ADMIN', $pcm->role);
        $this->get('/login');
        $this->assertRedirect('/');
        $this->assertSame(1, $users->find()->count());
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $today = (new RecurrenceCalendar())->today();
        $this->post('/checklists/novo', [
            'code' => 'PCM-TEST', 'name' => 'Preventiva PCM', 'area' => 'PCM',
            'start_date' => $today->format('Y-m-d'), 'interval_days' => 15,
        ]);
        $item = $this->getTableLocator()->get('Checklists')->find()->firstOrFail();
        $this->assertRedirect('/checklists/' . $item->id);
        $this->get('/');
        $this->assertResponseOk();
        $occurrences = $this->getTableLocator()->get('ChecklistOccurrences');
        $occurrence = $occurrences->find()->firstOrFail();
        $this->post('/ocorrencias/' . $occurrence->id . '/ciente', ['confirmed' => '1']);
        $this->assertRedirect('/');
        $this->assertSame($pcm->id, $occurrences->get($occurrence->id)->acknowledged_by);
        $schedule = $this->getTableLocator()->get('ChecklistSchedules')->find()->firstOrFail();
        $path = '/checklists/' . $item->id;
        $this->post($path . '/pausar', ['schedule_id' => $schedule->id, 'reason' => 'Teste PCM']);
        $this->assertRedirect($path);
        $this->post($path . '/reativar', ['schedule_id' => $schedule->id, 'start_date' => $today->addDays(1)->format('Y-m-d')]);
        $this->assertRedirect($path);
        $entries = $this->getTableLocator()->get('ChecklistHistory')->find()->orderBy(['id' => 'ASC'])->all();
        $this->assertSame(['CRIACAO', 'CIENTE', 'PAUSA', 'REATIVACAO'], $entries->extract('action')->toList());
        foreach ($entries as $entry) {
            $this->assertSame($pcm->id, $entry->user_id);
            $this->assertNotNull($entry->created);
        }
        $this->get($path . '/historico');
        $this->assertResponseContains('PCM');
        $this->post('/usuarios/' . $pcm->id . '/editar', ['name' => 'Outro', 'email' => 'outro@example.test']);
        $this->assertRedirect('/usuarios');
        $this->assertSame('PCM', $users->get($pcm->id)->name);
    }

    public function testLocalAccessDoesNotTrustRemoteOrForwardedAddresses(): void
    {
        $this->configRequest(['environment' => ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_FOR' => '127.0.0.1']]);
        $this->get('/');
        $this->assertResponseCode(403);
        $this->assertSame(0, $this->getTableLocator()->get('Users')->find()->count());
    }
}
