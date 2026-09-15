<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;

/** Persiste somente ciclos com data prevista até hoje, em lotes transacionais. */
class RecurrenceService
{
    use LocatorAwareTrait;

    public const BATCH_SIZE = 500;

    /** Recupera inclusive os dias em que ninguém acessou a aplicação. */
    public function synchronize(int $checklistId, ?Date $today = null): void
    {
        $today ??= (new RecurrenceCalendar())->today();
        do {
            $more = $this->synchronizeBatch($checklistId, $today);
        } while ($more);
    }

    /** O bloqueio do checklist serializa acessos simultâneos e edições. */
    private function synchronizeBatch(int $checklistId, Date $today): bool
    {
        $checklists = $this->fetchTable('Checklists');
        $schedules = $this->fetchTable('ChecklistSchedules');
        $occurrences = $this->fetchTable('ChecklistOccurrences');
        $calendar = new RecurrenceCalendar();

        return $checklists->getConnection()->transactional(function () use (
            $checklistId,
            $today,
            $checklists,
            $schedules,
            $occurrences,
            $calendar,
        ): array {
            $checklist = $checklists->find()->where(['id' => $checklistId])->epilog('FOR UPDATE')->firstOrFail();
            if ($checklist->status !== 'ATIVO') {
                return ['more' => false];
            }
            $schedule = $schedules->find()->where(['checklist_id' => $checklistId, 'ended_at IS' => null])
                ->firstOrFail();
            if ($schedule->materialized_cycle === null) {
                throw new RuntimeException('Atualize as migrations e limpe o cache de schema antes de sincronizar.');
            }
            $last = $calendar->dueCycle($schedule->start_date, $schedule->interval_days, $today);
            $first = $schedule->materialized_cycle + 1;
            if ($first > $last) {
                return ['more' => false];
            }
            $end = min($last, $first + self::BATCH_SIZE - 1);
            $from = $calendar->occurrence($schedule->start_date, $schedule->interval_days, $first);
            $through = $calendar->occurrence($schedule->start_date, $schedule->interval_days, $end);
            $existing = [];
            foreach (
                $occurrences->find()->select(['scheduled_date'])->where([
                'checklist_schedule_id' => $schedule->id,
                'scheduled_date >=' => $from, 'scheduled_date <=' => $through,
                ]) as $row
            ) {
                $existing[$row->scheduled_date->format('Y-m-d')] = true;
            }
            $columns = ['checklist_schedule_id', 'scheduled_date', 'status', 'created', 'modified'];
            $insert = $occurrences->getConnection()->insertQuery()->insert($columns)->into('checklist_occurrences');
            $now = DateTime::now('UTC')->format('Y-m-d H:i:s');
            $count = 0;
            for ($cycle = $first; $cycle <= $end; $cycle++) {
                $date = $calendar->occurrence($schedule->start_date, $schedule->interval_days, $cycle)->format('Y-m-d');
                if (!isset($existing[$date])) {
                    $insert->values(array_combine($columns, [$schedule->id, $date, 'PENDENTE', $now, $now]));
                    $count++;
                }
            }
            if ($count > 0) {
                $insert->execute();
            }
            // Atualização técnica não altera a data de edição do cadastro.
            $schedules->updateAll(['materialized_cycle' => $end], ['id' => $schedule->id]);

            // Não retornar false: no CakePHP isso desfaz a transação.
            return ['more' => $end < $last];
        })['more'];
    }

    /** Uma linha por checklist; uma pendência antiga nunca é pulada pelo calendário. */
    public function dashboard(?Date $today = null): array
    {
        $calendar = new RecurrenceCalendar();
        $today ??= $calendar->today();
        $counts = array_fill_keys(['ATRASADO', 'HOJE', 'PROXIMOS', 'EM_DIA', 'PAUSADO'], 0);
        $rows = [];
        $checklists = $this->fetchTable('Checklists');
        foreach ($checklists->find()->select(['id'])->where(['status' => 'ATIVO']) as $checklist) {
            $this->synchronize($checklist->id, $today);
        }
        $query = $checklists->find()->contain(['CurrentSchedule'])->orderBy(['Checklists.name' => 'ASC']);
        foreach ($query as $checklist) {
            $schedule = $checklist->current_schedule;
            $row = ['checklist' => $checklist, 'schedule' => $schedule, 'date' => null, 'days' => null,
                'pending' => 0, 'situation' => 'PAUSADO'];
            if ($checklist->status === 'ATIVO') {
                if (!$schedule) {
                    throw new RuntimeException('Checklist ativo sem programação: ' . $checklist->code);
                }
                $pending = $this->fetchTable('ChecklistOccurrences')->find()->where([
                    'checklist_schedule_id' => $schedule->id, 'status' => 'PENDENTE',
                    'scheduled_date <=' => $today,
                ]);
                $row['pending'] = $pending->count();
                $oldest = $pending->orderBy(['scheduled_date' => 'ASC'])->first();
                $row['date'] = $oldest?->scheduled_date ?? $calendar->occurrence(
                    $schedule->start_date,
                    $schedule->interval_days,
                    max(0, $calendar->dueCycle($schedule->start_date, $schedule->interval_days, $today) + 1),
                );
                $row['days'] = $calendar->daysUntil($today, $row['date']);
                $row['situation'] = $calendar->situation($row['days']);
            }
            $counts[$row['situation']]++;
            $rows[] = $row;
        }
        $priority = array_flip(array_keys($counts));
        usort($rows, static function (array $a, array $b) use ($priority): int {
            return [$priority[$a['situation']], $a['days'], $a['checklist']->name]
                <=> [$priority[$b['situation']], $b['days'], $b['checklist']->name];
        });

        return compact('today', 'counts', 'rows');
    }
}
