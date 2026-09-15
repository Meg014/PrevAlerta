<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\RecurrenceCalendar;
use App\Service\RecurrenceService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

class SyncOccurrencesCommand extends Command
{
    /** @inheritDoc */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $today = (new RecurrenceCalendar())->today();
        $service = new RecurrenceService();
        $count = 0;
        foreach ($this->fetchTable('Checklists')->find()->select(['id'])->where(['status' => 'ATIVO']) as $item) {
            $service->synchronize($item->id, $today);
            $count++;
        }
        $io->success($count . ' checklists sincronizados até ' . $today->format('d/m/Y') . ' (America/Sao_Paulo).');

        return static::CODE_SUCCESS;
    }
}
