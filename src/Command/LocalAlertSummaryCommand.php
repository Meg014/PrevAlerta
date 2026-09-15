<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\AlertSummaryService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

/** Resumo local para o Windows, sem persistir a sincronização do dashboard. */
class LocalAlertSummaryCommand extends Command
{
    /** @inheritDoc */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $summary = (new AlertSummaryService())->read();
        $io->out(json_encode($summary, JSON_THROW_ON_ERROR));

        return static::CODE_SUCCESS;
    }
}
