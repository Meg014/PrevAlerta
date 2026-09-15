<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddRecurrenceProgress extends BaseMigration
{
    public function change(): void
    {
        $this->table('checklist_schedules')
            ->addColumn('materialized_cycle', 'integer', ['default' => -1])
            ->update();
        $this->table('checklist_occurrences')
            ->addIndex(['checklist_schedule_id', 'status', 'scheduled_date'], ['name' => 'occurrence_pending_lookup'])
            ->update();
    }
}
