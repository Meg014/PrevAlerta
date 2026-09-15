<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class LinkAcknowledgementAudit extends BaseMigration
{
    public function change(): void
    {
        $this->table('checklist_history')
            ->addColumn('checklist_occurrence_id', 'integer', ['null' => true])
            ->addForeignKey('checklist_occurrence_id', 'checklist_occurrences', 'id', ['delete' => 'RESTRICT'])
            ->addIndex('checklist_occurrence_id', ['unique' => true, 'name' => 'unique_occurrence_ack_audit'])
            ->update();
    }
}
