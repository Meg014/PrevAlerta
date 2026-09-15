<?php
declare(strict_types=1);

namespace App\Authenticator;

use Authentication\Authenticator\AbstractAuthenticator;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/** Identidade interna do PC; mantém as associações de auditoria existentes. */
class LocalAuthenticator extends AbstractAuthenticator
{
    use LocatorAwareTrait;

    public const EMAIL = 'pcm@prevagenda.invalid';

    /** @inheritDoc */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $address = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        if (!in_array($address, ['127.0.0.1', '::1'], true) && !Configure::read('LocalAccess.allowRemote')) {
            throw new ForbiddenException('O acesso automático está disponível somente neste computador.');
        }
        $users = $this->fetchTable('Users');
        $user = $users->find()->where(['email' => self::EMAIL])->first();
        if (!$user) {
            $user = $users->newEntity([
                'name' => 'PCM', 'email' => self::EMAIL, 'password' => bin2hex(random_bytes(32)),
            ]);
            $user->patch(['role' => 'ADMIN', 'active' => true], ['guard' => false]);
            if (!$users->save($user)) {
                // Outra requisição pode ter criado a conta no primeiro acesso.
                $user = $users->find()->where(['email' => self::EMAIL])->firstOrFail();
            }
        }
        if ($user->name !== 'PCM' || $user->role !== 'ADMIN' || !$user->active) {
            throw new RuntimeException('A conta interna PCM deve estar ativa com perfil ADMIN e nome PCM.');
        }

        return new Result($user, Result::SUCCESS);
    }
}
