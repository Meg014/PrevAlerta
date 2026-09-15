<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\ChecklistOccurrence;
use Cake\I18n\Date;

class OccurrencePresentation
{
    /** O atraso de um CIENTE é definitivo e independe do dia da consulta. */
    public function describe(ChecklistOccurrence $occurrence, ?Date $today = null): array
    {
        $calendar = new RecurrenceCalendar();
        $recognized = $occurrence->status === 'RECONHECIDA' && $occurrence->acknowledged_at !== null;
        $reference = $recognized ? $calendar->today($occurrence->acknowledged_at) : ($today ?? $calendar->today());
        $days = max(0, $calendar->daysUntil($occurrence->scheduled_date, $reference));
        $label = $recognized ? ($days === 0 ? 'NO PRAZO' : 'ATRASADO') : 'PENDENTE';

        return compact('recognized', 'days', 'label');
    }
}
