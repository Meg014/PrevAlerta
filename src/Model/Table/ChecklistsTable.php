<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ChecklistsTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->setDisplayField('name');
        $this->hasMany('ChecklistSchedules');
        $this->hasOne('CurrentSchedule', [
            'className' => 'ChecklistSchedules', 'foreignKey' => 'checklist_id',
            'conditions' => ['CurrentSchedule.ended_at IS' => null],
        ]);
        $this->hasMany('ChecklistHistory');
    }

    /**
     * @inheritDoc
     */
    public function validationDefault(Validator $validator): Validator
    {
        foreach (['code' => 80, 'name' => 180, 'area' => 120] as $field => $length) {
            $validator->requirePresence($field, 'create')
                ->notEmptyString($field, 'Campo obrigatório.')->maxLength($field, $length);
        }

        return $validator->allowEmptyString('route')->maxLength('route', 120)
            ->allowEmptyString('description')->maxLength('description', 10000)
            ->allowEmptyString('notes')->maxLength('notes', 10000)
            ->inList('status', ['ATIVO', 'PAUSADO']);
    }

    /**
     * @inheritDoc
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        return $rules->add($rules->isUnique(['code']), [
            'errorField' => 'code', 'message' => 'Este código já está cadastrado.',
        ]);
    }
}
