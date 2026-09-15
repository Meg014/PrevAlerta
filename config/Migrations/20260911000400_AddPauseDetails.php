<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddPauseDetails extends BaseMigration
{
    public function change(): void
    {
        $this->table('checklists')
            ->addColumn('paused_at', 'datetime', ['null' => true])
            ->addColumn('paused_by', 'integer', ['null' => true])
            ->addColumn('pause_reason', 'text', ['null' => true])
            ->addForeignKey('paused_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->update();
    }
}
