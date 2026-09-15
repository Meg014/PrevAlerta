<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class ChecklistSchedule extends Entity
{
    protected array $_accessible = ['start_date' => true, 'interval_days' => true];
}
