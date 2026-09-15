<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->setDisplayField('name');
    }

    /**
     * @inheritDoc
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('name', 'create')->notEmptyString('name', 'Informe o nome.')->maxLength('name', 120)
            ->requirePresence('email', 'create')->notEmptyString('email')
            ->email('email', false, 'Informe um e-mail válido.')->maxLength('email', 190)
            ->requirePresence('password', 'create')->notEmptyString('password', 'Informe a senha.')
            ->minLength('password', 12, 'Use pelo menos 12 caracteres.')
            ->maxLength('password', 72, 'Use no máximo 72 caracteres.')
            ->inList('role', ['ADMIN', 'USUARIO'], 'Perfil inválido.')->boolean('active');
    }

    /**
     * @inheritDoc
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        return $rules->add($rules->isUnique(['email']), [
            'errorField' => 'email', 'message' => 'Este e-mail já está cadastrado.',
        ]);
    }
}
