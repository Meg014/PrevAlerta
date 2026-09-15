<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class UserManagementTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Checklists', 'app.ChecklistSchedules', 'app.ChecklistOccurrences', 'app.ChecklistHistory'];

    public function testAdminCreatesUserAndUpdatesWithoutReplacingPassword(): void
    {
        $users = $this->getTableLocator()->get('Users');
        $admin = $users->newEntity(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'Admin-password-123']);
        $admin->patch(['role' => 'ADMIN', 'active' => true], ['guard' => false]);
        $users->saveOrFail($admin);
        $this->session(['Auth' => $admin->id]);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->get('/usuarios/novo');
        $this->assertResponseOk();
        $this->post('/usuarios/novo', ['name' => 'Maria', 'email' => 'MARIA@example.test', 'password' => 'Maria-password-123', 'role' => 'USUARIO', 'active' => '1']);
        $this->assertRedirect('/usuarios');
        $maria = $users->find()->where(['email' => 'maria@example.test'])->firstOrFail();
        $this->assertSame('USUARIO', $maria->role);
        $hash = $maria->password;
        $this->get('/usuarios/' . $maria->id . '/editar');
        $this->assertResponseOk();
        $this->post('/usuarios/' . $maria->id . '/editar', ['name' => 'Maria Silva', 'email' => 'maria@example.test', 'password' => '', 'role' => 'USUARIO', 'active' => '0']);
        $this->assertRedirect('/usuarios');
        $updated = $users->get($maria->id);
        $this->assertSame($hash, $updated->password);
        $this->assertFalse($updated->active);
        $this->assertTrue((new DefaultPasswordHasher())->check('Maria-password-123', $updated->password));
        $this->post('/usuarios/' . $admin->id . '/editar', ['name' => 'Admin', 'email' => 'admin@example.test', 'password' => '', 'role' => 'USUARIO', 'active' => '0']);
        $this->assertRedirect('/usuarios');
        $this->assertSame('ADMIN', $users->get($admin->id)->role);
        $this->assertTrue($users->get($admin->id)->active);
    }

    public function testWeakPasswordAndDuplicateEmailAreRejected(): void
    {
        $users = $this->getTableLocator()->get('Users');
        $user = $users->newEntity(['name' => 'Maria', 'email' => 'maria@example.test', 'password' => 'short']);
        $this->assertNotEmpty($user->getError('password'));
        $this->assertFalse($users->save($user));
        $user = $users->newEntity(['name' => 'Maria', 'email' => 'maria@example.test', 'password' => 'Maria-password-123']);
        $user->patch(['role' => 'USUARIO', 'active' => true], ['guard' => false]);
        $users->saveOrFail($user);
        $duplicate = $users->newEntity(['name' => 'Outra', 'email' => 'maria@example.test', 'password' => 'Maria-password-123']);
        $this->assertFalse($users->save($duplicate));
        $this->assertNotEmpty($duplicate->getError('email'));
    }
}
