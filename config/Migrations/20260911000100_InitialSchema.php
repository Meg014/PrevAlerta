<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class InitialSchema extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('password', 'string', ['limit' => 255])
            ->addColumn('role', 'string', ['limit' => 10, 'default' => 'USUARIO'])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created', 'datetime')->addColumn('modified', 'datetime')
            ->addIndex('email', ['unique' => true])->create();
        $this->table('checklists')
            ->addColumn('code', 'string', ['limit' => 80])
            ->addColumn('name', 'string', ['limit' => 180])
            ->addColumn('area', 'string', ['limit' => 120])
            ->addColumn('route', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'ATIVO'])
            ->addColumn('created', 'datetime')->addColumn('modified', 'datetime')
            ->addIndex('code', ['unique' => true])->addIndex(['status', 'area'])->create();
        $this->table('checklist_schedules')
            ->addColumn('checklist_id', 'integer')
            ->addColumn('start_date', 'date')->addColumn('interval_days', 'integer')
            ->addColumn('created_by', 'integer')->addColumn('ended_at', 'datetime', ['null' => true])
            ->addColumn('created', 'datetime')->addColumn('modified', 'datetime')
            ->addForeignKey('checklist_id', 'checklists', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['checklist_id', 'ended_at'])->create();
        $this->table('checklist_occurrences')
            ->addColumn('checklist_schedule_id', 'integer')->addColumn('scheduled_date', 'date')
            ->addColumn('acknowledged_at', 'datetime', ['null' => true])
            ->addColumn('acknowledged_by', 'integer', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'PENDENTE'])
            ->addColumn('created', 'datetime')->addColumn('modified', 'datetime')
            ->addForeignKey('checklist_schedule_id', 'checklist_schedules', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('acknowledged_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['checklist_schedule_id', 'scheduled_date'], ['unique' => true])
            ->addIndex(['status', 'scheduled_date'])->create();
        $this->table('checklist_history')
            ->addColumn('checklist_id', 'integer')->addColumn('user_id', 'integer')
            ->addColumn('action', 'string', ['limit' => 40])->addColumn('description', 'text')
            ->addColumn('old_value', 'text', ['null' => true])->addColumn('new_value', 'text', ['null' => true])
            ->addColumn('created', 'datetime')
            ->addForeignKey('checklist_id', 'checklists', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['checklist_id', 'created'])->create();
    }
}
