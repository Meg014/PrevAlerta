<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\ChecklistHistory;
use Cake\ORM\Table;

class ChecklistHistoryTable extends Table
{
    /**
     * @inheritDoc
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('checklist_history');
        $this->setEntityClass(ChecklistHistory::class);
        $this->belongsTo('Checklists');
        $this->belongsTo('Users');
        $this->belongsTo('ChecklistOccurrences');
    }
}
