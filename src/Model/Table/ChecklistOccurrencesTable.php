<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class ChecklistOccurrencesTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->belongsTo('ChecklistSchedules');
        $this->belongsTo('Users', ['foreignKey' => 'acknowledged_by']);
        $this->hasOne('AcknowledgementAudit', [
            'className' => 'ChecklistHistory', 'foreignKey' => 'checklist_occurrence_id',
        ]);
    }
}
