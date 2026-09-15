<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;

class User extends Entity
{
    protected array $_accessible = ['name' => true, 'email' => true, 'password' => true];
    protected array $_hidden = ['password'];
    /**
     * Gera o hash antes de persistir a senha.
     *
     * @return string
     */
    protected function _setPassword(string $password): string
    {
        return (new DefaultPasswordHasher())->hash($password);
    }
}
