<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ChecklistSchedulesTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->belongsTo('Checklists');
        $this->belongsTo('Users', ['foreignKey' => 'created_by']);
        $this->hasMany('ChecklistOccurrences');
    }

    /**
     * @inheritDoc
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator->requirePresence('start_date', 'create')
            ->notEmptyDate('start_date', 'Informe a data inicial.')
            ->date('start_date', ['ymd'], 'Informe uma data válida.')
            ->requirePresence('interval_days', 'create')->notEmptyString('interval_days', 'Informe a periodicidade.')
            ->integer('interval_days', 'Informe um número inteiro de dias.')
            ->greaterThan('interval_days', 0, 'A periodicidade deve ser maior que zero.')
            ->lessThanOrEqual('interval_days', 2147483647, 'Quantidade acima do limite do banco.');
    }
}
