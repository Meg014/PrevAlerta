<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Checklists', 'app.ChecklistSchedules', 'app.ChecklistOccurrences', 'app.ChecklistHistory'];

    public function testCreatesFirstAdminAndRefusesAnother(): void
    {
        putenv('PREV_ADMIN_PASSWORD=Command-password-123');
        try {
            $this->exec('create_admin admin@example.test Administrador');
            $this->assertExitSuccess();
            $user = $this->getTableLocator()->get('Users')->find()->firstOrFail();
            $this->assertSame('ADMIN', $user->role);
            $this->assertTrue($user->active);
            $this->exec('create_admin another@example.test Outro');
            $this->assertExitError();
            $this->assertSame(1, $this->getTableLocator()->get('Users')->find()->count());
        } finally {
            putenv('PREV_ADMIN_PASSWORD');
        }
    }

    public function testRequiresPasswordWithoutCreatingAccount(): void
    {
        putenv('PREV_ADMIN_PASSWORD');
        $this->exec('create_admin admin@example.test Administrador');
        $this->assertExitError();
        $this->assertSame(0, $this->getTableLocator()->get('Users')->find()->count());
    }
}
