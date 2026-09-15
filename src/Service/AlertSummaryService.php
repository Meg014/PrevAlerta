<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;

/** Adapta o dashboard para notificações, sem persistir sua sincronização. */
class AlertSummaryService
{
    use LocatorAwareTrait;

    /** Retorna os mesmos totais dos cards; descarta todas as gravações da consulta. */
    public function read(): array
    {
        $connection = $this->fetchTable('Checklists')->getConnection();
        if ($connection->inTransaction()) {
            throw new RuntimeException('O resumo exige uma conexão sem transação em andamento.');
        }
        $connection->begin();
        try {
            $data = (new RecurrenceService())->dashboard();

            return ['overdue' => $data['counts']['ATRASADO'], 'today' => $data['counts']['HOJE']];
        } finally {
            $connection->rollback();
        }
    }
}
